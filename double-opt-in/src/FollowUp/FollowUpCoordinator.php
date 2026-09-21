<?php
/**
 * Plans, claims, executes and records follow-up actions.
 *
 * @package Forge12\DoubleOptIn\FollowUp
 * @since   5.6.0
 */

declare( strict_types=1 );

namespace Forge12\DoubleOptIn\FollowUp;

use Forge12\DoubleOptIn\Audit\AuditLogger;
use Forge12\DoubleOptIn\Repository\FollowUpRepositoryInterface;
use Forge12\Shared\LoggerInterface;
use forge12\contactform7\CF7DoubleOptIn\OptIn;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The single path every follow-up execution goes through — the first
 * run after confirmation, the cron retry and the admin's manual retry.
 *
 * Guarantees:
 *  - An action is planned once per opt-in (unique key) and claimed by
 *    exactly one request per attempt (conditional UPDATE).
 *  - A successful action is never executed again; `unknown` is never
 *    re-executed without an explicit administrator decision.
 *  - Nothing runs for an unconfirmed or opted-out opt-in.
 *  - Results carry codes, never form data.
 */
final class FollowUpCoordinator {

	public const CRON_RETRY_HOOK = 'f12_doi_follow_up_retry';
	public const CRON_SWEEP_HOOK = 'f12_doi_follow_up_sweep';

	/**
	 * How long a claimed action may stay `running` before the sweep
	 * declares its outcome unknown. Must exceed the longest adapter
	 * timeout (Elementor replay: 30 s).
	 */
	public const LEASE_SECONDS = 300;

	/**
	 * Replay tickets are consumed within the same PHP request that
	 * issued them; two minutes is generous.
	 */
	public const TICKET_TTL_SECONDS = 120;

	/**
	 * A `pending` row this old on a confirmed opt-in means the confirming
	 * request died before executing it.
	 */
	public const STALE_PENDING_SECONDS = 600;

	/** @var self|null */
	private static $instance;

	/** @var FollowUpRepositoryInterface */
	private $repository;

	/** @var FollowUpAdapterRegistry */
	private $registry;

	/** @var LoggerInterface */
	private $logger;

	/** @var callable():int */
	private $clock;

	/** @var callable(string, string, string, array<string, mixed>):mixed */
	private $audit;

	/** @var callable(int, string, array<int, mixed>):mixed */
	private $scheduler;

	/**
	 * @param callable|null $clock     Returns the current Unix time.
	 * @param callable|null $audit     `(type, severity, message, details)`.
	 * @param callable|null $scheduler `(timestamp, hook, args)`.
	 */
	public function __construct(
		FollowUpRepositoryInterface $repository,
		FollowUpAdapterRegistry $registry,
		LoggerInterface $logger,
		?callable $clock = null,
		?callable $audit = null,
		?callable $scheduler = null
	) {
		$this->repository = $repository;
		$this->registry   = $registry;
		$this->logger     = $logger;
		$this->clock      = $clock ?? static function (): int {
			return time();
		};
		$this->audit      = $audit ?? static function ( string $type, string $severity, string $message, array $details ) {
			return AuditLogger::log( $type, $severity, $message, $details );
		};
		$this->scheduler  = $scheduler ?? static function ( int $timestamp, string $hook, array $args ) {
			if ( function_exists( 'wp_schedule_single_event' ) ) {
				return wp_schedule_single_event( $timestamp, $hook, $args );
			}
			return false;
		};
	}

	/**
	 * Accessor for the legacy layer (compatibility/), which cannot take
	 * constructor injection. Set by FollowUpServiceProvider.
	 */
	public static function instance(): ?self {
		return self::$instance;
	}

	public static function setInstance( ?self $instance ): void {
		self::$instance = $instance;
	}

	public function getRegistry(): FollowUpAdapterRegistry {
		return $this->registry;
	}

	public function adapterFor( OptIn $optIn ): ?FollowUpAdapterInterface {
		return $this->registry->forOptIn( $optIn );
	}

	/**
	 * Bind the follow-up plan of an opt-in. Called during confirmation,
	 * BEFORE the confirmation is saved: if the request dies in between,
	 * the rows exist and the sweep finishes the work; if the confirmation
	 * save fails, nothing runs because execution requires a confirmed
	 * opt-in. Idempotent.
	 *
	 * @param bool $defaultMailEnabled Result of `f12_cf7_doubleoptin_send_default_mail`.
	 *
	 * @return bool False when no adapter handles this opt-in (the caller
	 *              then keeps its previous behaviour).
	 */
	public function plan( OptIn $optIn, bool $defaultMailEnabled ): bool {
		$adapter = $this->adapterFor( $optIn );
		if ( $adapter === null ) {
			return false;
		}

		if ( ! empty( $this->repository->findByOptIn( $optIn->get_id() ) ) ) {
			return true;
		}

		try {
			$actions = $adapter->planActions( $optIn );
		} catch ( \Throwable $e ) {
			// A form that cannot be read (deleted, plugin half-updated) is
			// itself a result worth recording.
			$this->logger->error(
				'Follow-up planning failed',
				array(
					'plugin'      => 'double-opt-in',
					'optin_id'    => $optIn->get_id(),
					'integration' => $adapter->getIntegration(),
					'exception'   => get_class( $e ),
				)
			);
			$actions = array( new FollowUpAction( 'plan', FollowUpAction::KIND_MARKER, 'Plan', false ) );
			$this->repository->plan( $optIn->get_id(), $adapter->getIntegration(), $actions, array(), FollowUpAction::fingerprint( $actions ), $this->now() );
			$this->completeImmediately( $optIn, 'plan', FollowUpResult::failedPermanent( 'plan_failed' ) );
			return true;
		}

		$skipReasons = array();
		foreach ( $actions as $action ) {
			if ( $action->getSkipReason() !== '' ) {
				$skipReasons[ $action->getId() ] = $action->getSkipReason();
			} elseif ( ! $defaultMailEnabled && $action->isGatedByDefaultMail() ) {
				$skipReasons[ $action->getId() ] = 'send_default_mail_disabled';
			}
		}

		if ( empty( $actions ) ) {
			$actions     = array( new FollowUpAction( FollowUpAction::ID_NONE, FollowUpAction::KIND_MARKER, 'No follow-up actions', false ) );
			$skipReasons = array( FollowUpAction::ID_NONE => 'no_actions_configured' );
		}

		$inserted = $this->repository->plan(
			$optIn->get_id(),
			$adapter->getIntegration(),
			$actions,
			$skipReasons,
			FollowUpAction::fingerprint( $actions ),
			$this->now()
		);

		$this->logger->info(
			'Follow-up actions planned',
			array(
				'plugin'      => 'double-opt-in',
				'optin_id'    => $optIn->get_id(),
				'integration' => $adapter->getIntegration(),
				'planned'     => $inserted,
				'skipped'     => count( $skipReasons ),
			)
		);

		return true;
	}

	/**
	 * Execute the eligible actions of an opt-in.
	 *
	 * @param string $trigger One of FollowUpAttempt::TRIGGER_*.
	 * @param array{include_unknown?: bool, action_ids?: string[]} $options
	 *
	 * @return FollowUpRecord[] The opt-in's rows after the run.
	 */
	public function run( OptIn $optIn, string $trigger, array $options = array() ): array {
		$adapter = $this->adapterFor( $optIn );
		$optInId = $optIn->get_id();

		if ( $adapter === null || $optInId <= 0 || ! $optIn->is_confirmed() ) {
			return $this->repository->findByOptIn( $optInId );
		}

		// Consent withdrawn: nothing further may happen for this address.
		if ( $optIn->is_optout() ) {
			$this->repository->skipOpen( $optInId, 'opted_out', $this->now() );
			return $this->repository->findByOptIn( $optInId );
		}

		$records  = $this->repository->findByOptIn( $optInId );
		$eligible = $this->selectEligible( $records, $trigger, $options );

		if ( $trigger === FollowUpAttempt::TRIGGER_MANUAL ) {
			// Who retried what is part of the record — above all when an
			// administrator accepted the risk of a duplicate.
			$includeUnknown = ! empty( $options['include_unknown'] );
			( $this->audit )(
				AuditLogger::TYPE_FOLLOW_UP,
				$includeUnknown ? AuditLogger::SEVERITY_WARNING : AuditLogger::SEVERITY_INFO,
				$includeUnknown
					? 'Manual follow-up retry including actions with unknown outcome'
					: 'Manual follow-up retry',
				array(
					'event'           => 'follow_up.manual_retry',
					'optin_id'        => $optInId,
					'include_unknown' => $includeUnknown,
					'action_ids'      => array_values( array_map( 'strval', (array) ( $options['action_ids'] ?? array() ) ) ),
					'eligible'        => count( $eligible ),
				)
			);
		}

		if ( empty( $eligible ) ) {
			return $records;
		}

		$attempt = new FollowUpAttempt( self::generateId(), $trigger, $optInId );
		$now     = $this->now();
		$lease   = $this->format( ( $this->clock )() + self::LEASE_SECONDS );

		/** @var FollowUpRecord[] $claimed */
		$claimed = array();
		foreach ( $eligible as $record ) {
			if ( $this->repository->claim( $record->id, array( $record->status ), $attempt->getId(), $trigger, $now, $lease ) ) {
				$claimed[ $record->actionId ] = $record;
			}
		}

		if ( empty( $claimed ) ) {
			// Another request won every claim — it owns this attempt.
			return $this->repository->findByOptIn( $optInId );
		}

		$actions = array();
		foreach ( $claimed as $record ) {
			$actions[] = $record->toAction();
		}

		$this->logger->info(
			'Follow-up attempt started',
			array(
				'plugin'      => 'double-opt-in',
				'optin_id'    => $optInId,
				'integration' => $adapter->getIntegration(),
				'attempt_id'  => $attempt->getId(),
				'trigger'     => $trigger,
				'actions'     => array_keys( $claimed ),
			)
		);

		$startedAt = microtime( true );
		$results   = array();
		$fallback  = 'action_outcome_unknown';
		try {
			$results = $adapter->execute( $optIn, $actions, $attempt );
		} catch ( \Throwable $e ) {
			$fallback = 'adapter_exception';
			$this->logger->error(
				'Follow-up adapter threw',
				array(
					'plugin'     => 'double-opt-in',
					'optin_id'   => $optInId,
					'attempt_id' => $attempt->getId(),
					'exception'  => get_class( $e ),
				)
			);
		}
		$durationMs = (int) round( ( microtime( true ) - $startedAt ) * 1000 );

		$finishedAt = $this->now();
		$report     = array();
		$nextRetry  = 0;
		foreach ( $claimed as $actionId => $record ) {
			$result = $results[ $actionId ] ?? null;
			if ( ! $result instanceof FollowUpResult ) {
				$result = FollowUpResult::unknown( $fallback );
			}

			$attemptNumber = $record->attempts + 1;
			// Position in the automatic-retry budget. A manual retry starts
			// a fresh budget (the admin fixed the cause); mirrors the
			// repository's claim().
			$budgetNumber = $trigger === FollowUpAttempt::TRIGGER_MANUAL ? 1 : $record->budgetAttempts + 1;
			$next         = '';
			if ( $result->getStatus() === FollowUpStatus::FAILED_RETRYABLE ) {
				$delay = $this->backoffDelay( $budgetNumber );
				if ( $delay === null ) {
					$result = $result->withStatus( FollowUpStatus::FAILED_PERMANENT );
				} else {
					$at        = ( $this->clock )() + $delay;
					$next      = $this->format( $at );
					$nextRetry = $nextRetry === 0 ? $at : min( $nextRetry, $at );
				}
			}

			$this->repository->complete( $record->id, $attempt->getId(), $result, $finishedAt, $next );

			$report[] = array_merge(
				array(
					'action_id'      => $actionId,
					'attempt_number' => $attemptNumber,
					'next_attempt'   => $next,
				),
				$result->toArray()
			);
		}

		if ( $nextRetry > 0 ) {
			( $this->scheduler )( $nextRetry, self::CRON_RETRY_HOOK, array( $optInId ) );
		}

		$records  = $this->repository->findByOptIn( $optInId );
		$statuses = array();
		foreach ( $records as $record ) {
			$statuses[ $record->actionId ] = $record->status;
		}

		try {
			$adapter->onSettled( $optIn, $statuses );
		} catch ( \Throwable $e ) {
			$this->logger->error(
				'Follow-up onSettled threw',
				array(
					'plugin'    => 'double-opt-in',
					'optin_id'  => $optInId,
					'exception' => get_class( $e ),
				)
			);
		}

		$this->auditAttempt( $optIn, $adapter, $attempt, $statuses, $report, $durationMs );

		return $records;
	}

	/**
	 * Cron: expire dead leases, then run everything that is due.
	 *
	 * @param callable(int):?OptIn $loader Loads an opt-in by id.
	 *
	 * @return int Number of opt-ins processed.
	 */
	public function sweep( callable $loader, int $limit = 20 ): int {
		$this->repository->deleteOrphans( 500 );

		$expired = $this->repository->expireLeases( $this->now() );
		if ( $expired > 0 ) {
			( $this->audit )(
				AuditLogger::TYPE_FOLLOW_UP,
				AuditLogger::SEVERITY_WARNING,
				'Follow-up actions with unknown outcome after an interrupted attempt',
				array(
					'event'    => 'follow_up.lease_expired',
					'affected' => $expired,
				)
			);
		}

		$ids = $this->repository->findDueOptInIds(
			$this->now(),
			$this->format( ( $this->clock )() - self::STALE_PENDING_SECONDS ),
			$limit
		);

		$processed = 0;
		foreach ( $ids as $id ) {
			$optIn = $loader( $id );
			if ( $optIn instanceof OptIn ) {
				$this->run( $optIn, FollowUpAttempt::TRIGGER_CRON );
				$processed++;
			}
		}

		return $processed;
	}

	/**
	 * Status overview for the admin / REST.
	 *
	 * @return array{aggregate: string, actions: array<int, array<string, mixed>>}
	 */
	public function statusFor( int $optInId, bool $confirmed ): array {
		$records  = $this->repository->findByOptIn( $optInId );
		$statuses = array();
		$actions  = array();
		foreach ( $records as $record ) {
			$statuses[] = $record->status;
			$actions[]  = $record->toArray();
		}

		$aggregate = FollowUpStatus::aggregate( $statuses );
		if ( $aggregate === FollowUpStatus::AGGREGATE_NONE && $confirmed ) {
			// Confirmed before follow-up tracking existed (or by an
			// integration without an adapter). Deliberately not replayed.
			$aggregate = FollowUpStatus::AGGREGATE_LEGACY_UNKNOWN;
		}

		return array(
			'aggregate' => $aggregate,
			'actions'   => $actions,
		);
	}

	/**
	 * Issue a single-use replay ticket bound to one opt-in and the rows
	 * of one attempt. Only the SHA-256 is stored.
	 */
	public function issueTicket( int $optInId, string $attemptId ): string {
		$ticket = self::generateId() . self::generateId();
		$this->repository->setTicket(
			$optInId,
			$attemptId,
			hash( 'sha256', $ticket ),
			$this->format( ( $this->clock )() + self::TICKET_TTL_SECONDS )
		);
		return $ticket;
	}

	/**
	 * Consume a replay ticket. Returns the action ids it authorises, or
	 * null for an unknown, expired or already used ticket.
	 *
	 * @return string[]|null
	 */
	public function consumeTicket( int $optInId, string $ticket ): ?array {
		if ( $optInId <= 0 || strlen( $ticket ) < 32 || strlen( $ticket ) > 128 ) {
			return null;
		}
		return $this->repository->consumeTicket( $optInId, hash( 'sha256', $ticket ), $this->now() );
	}

	/**
	 * Cascade on opt-in deletion (retention, manual delete, eraser).
	 */
	public function forget( int $optInId ): void {
		if ( $optInId > 0 ) {
			$this->repository->deleteByOptIn( $optInId );
		}
	}

	/**
	 * Backoff in seconds before automatic retry N (1-based position of
	 * the attempt that just failed within the current budget — reset by
	 * every manual retry), or null when automatic retries are exhausted.
	 */
	private function backoffDelay( int $attemptNumber ): ?int {
		$schedule = array( 60, 300, 1800 );
		if ( function_exists( 'apply_filters' ) ) {
			/**
			 * Delays (seconds) between automatic retries of follow-up
			 * actions that demonstrably did not run. The number of
			 * entries is the maximum number of automatic retries.
			 *
			 * @param int[] $schedule
			 *
			 * @since 5.6.0
			 */
			$filtered = apply_filters( 'f12_doi_follow_up_backoff', $schedule );
			if ( is_array( $filtered ) ) {
				$schedule = array_values( array_map( 'intval', $filtered ) );
			}
		}

		$index = $attemptNumber - 1;
		if ( ! isset( $schedule[ $index ] ) || $schedule[ $index ] < 0 ) {
			return null;
		}
		return $schedule[ $index ];
	}

	/**
	 * @param FollowUpRecord[] $records
	 * @param array{include_unknown?: bool, action_ids?: string[]} $options
	 *
	 * @return FollowUpRecord[]
	 */
	private function selectEligible( array $records, string $trigger, array $options ): array {
		$nowTs = ( $this->clock )();

		switch ( $trigger ) {
			case FollowUpAttempt::TRIGGER_MANUAL:
				$allowed = array( FollowUpStatus::PENDING, FollowUpStatus::FAILED_RETRYABLE, FollowUpStatus::FAILED_PERMANENT );
				if ( ! empty( $options['include_unknown'] ) ) {
					$allowed[] = FollowUpStatus::UNKNOWN;
				}
				break;
			case FollowUpAttempt::TRIGGER_CRON:
				$allowed = array( FollowUpStatus::PENDING, FollowUpStatus::FAILED_RETRYABLE );
				break;
			default:
				$allowed = array( FollowUpStatus::PENDING );
		}

		$only = isset( $options['action_ids'] ) && is_array( $options['action_ids'] )
			? array_map( 'strval', $options['action_ids'] )
			: null;

		$eligible = array();
		foreach ( $records as $record ) {
			if ( $record->actionKind === FollowUpAction::KIND_MARKER ) {
				continue;
			}
			if ( ! in_array( $record->status, $allowed, true ) ) {
				continue;
			}
			if ( $only !== null && ! in_array( $record->actionId, $only, true ) ) {
				continue;
			}
			if ( $trigger === FollowUpAttempt::TRIGGER_CRON
				&& $record->status === FollowUpStatus::FAILED_RETRYABLE
				&& ( $record->nextAttemptAt === '' || strtotime( $record->nextAttemptAt . ' UTC' ) > $nowTs )
			) {
				continue;
			}
			$eligible[] = $record;
		}

		return $eligible;
	}

	/**
	 * Record a result for a row that never needs execution (planning
	 * failure). Claims first so the unique claim path stays the only
	 * writer of results.
	 */
	private function completeImmediately( OptIn $optIn, string $actionId, FollowUpResult $result ): void {
		$attemptId = self::generateId();
		$now       = $this->now();
		foreach ( $this->repository->findByOptIn( $optIn->get_id() ) as $record ) {
			if ( $record->actionId === $actionId
				&& $this->repository->claim( $record->id, array( FollowUpStatus::PENDING ), $attemptId, FollowUpAttempt::TRIGGER_CONFIRM, $now, $now )
			) {
				$this->repository->complete( $record->id, $attemptId, $result, $now, '' );
			}
		}
	}

	/**
	 * One audit event per attempt, with the per-action outcome in the
	 * details. Severity follows the aggregate so failures are findable
	 * in the audit log without debug logging enabled.
	 *
	 * @param array<string, string>             $statuses
	 * @param array<int, array<string, mixed>>  $report
	 */
	private function auditAttempt( OptIn $optIn, FollowUpAdapterInterface $adapter, FollowUpAttempt $attempt, array $statuses, array $report, int $durationMs ): void {
		$aggregate = FollowUpStatus::aggregate( array_values( $statuses ) );

		switch ( $aggregate ) {
			case FollowUpStatus::AGGREGATE_COMPLETED:
				$severity = AuditLogger::SEVERITY_INFO;
				$message  = 'Follow-up actions completed';
				break;
			case FollowUpStatus::AGGREGATE_PENDING:
				$severity = AuditLogger::SEVERITY_WARNING;
				$message  = 'Follow-up actions failed, retry scheduled';
				break;
			case FollowUpStatus::AGGREGATE_UNKNOWN:
				$severity = AuditLogger::SEVERITY_WARNING;
				$message  = 'Follow-up actions with unknown outcome';
				break;
			case FollowUpStatus::AGGREGATE_PARTIAL:
				$severity = AuditLogger::SEVERITY_ERROR;
				$message  = 'Follow-up actions partially failed';
				break;
			default:
				$severity = AuditLogger::SEVERITY_ERROR;
				$message  = 'Follow-up actions failed';
		}

		$details = array(
			'event'       => 'follow_up.attempt',
			'optin_id'    => $optIn->get_id(),
			'integration' => $adapter->getIntegration(),
			'form_id'     => $optIn->get_cf_form_id(),
			'attempt_id'  => $attempt->getId(),
			'trigger'     => $attempt->getTrigger(),
			'aggregate'   => $aggregate,
			'duration_ms' => $durationMs,
			'actions'     => $report,
		);

		( $this->audit )( AuditLogger::TYPE_FOLLOW_UP, $severity, $message, $details );

		$this->logger->info(
			$message,
			array(
				'plugin'     => 'double-opt-in',
				'optin_id'   => $optIn->get_id(),
				'attempt_id' => $attempt->getId(),
				'aggregate'  => $aggregate,
			)
		);
	}

	private function now(): string {
		return $this->format( ( $this->clock )() );
	}

	private function format( int $timestamp ): string {
		return gmdate( 'Y-m-d H:i:s', $timestamp );
	}

	/**
	 * 32 hex characters from a CSPRNG.
	 */
	public static function generateId(): string {
		return bin2hex( random_bytes( 16 ) );
	}
}
