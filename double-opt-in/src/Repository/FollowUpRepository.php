<?php
/**
 * Follow-up repository (wpdb).
 *
 * @package Forge12\DoubleOptIn\Repository
 * @since   5.6.0
 */

declare( strict_types=1 );

namespace Forge12\DoubleOptIn\Repository;

use Forge12\DoubleOptIn\FollowUp\FollowUpAction;
use Forge12\DoubleOptIn\FollowUp\FollowUpAttempt;
use Forge12\DoubleOptIn\FollowUp\FollowUpRecord;
use Forge12\DoubleOptIn\FollowUp\FollowUpResult;
use Forge12\DoubleOptIn\FollowUp\FollowUpStatus;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FollowUpRepository
 */
class FollowUpRepository implements FollowUpRepositoryInterface {

	/** @var \wpdb */
	private $wpdb;

	/** @var string */
	private $table;

	/** @var string */
	private $optInTable;

	public function __construct( \wpdb $wpdb ) {
		$this->wpdb       = $wpdb;
		$this->table      = $wpdb->prefix . FollowUpSchema::TABLE_NAME;
		$this->optInTable = $wpdb->prefix . 'f12_cf7_doubleoptin';
	}

	/**
	 * {@inheritdoc}
	 */
	public function getTableName(): string {
		return $this->table;
	}

	/**
	 * {@inheritdoc}
	 */
	public function plan( int $optInId, string $integration, array $actions, array $skipReasons, string $fingerprint, string $now ): int {
		$inserted = 0;

		foreach ( $actions as $action ) {
			if ( ! $action instanceof FollowUpAction ) {
				continue;
			}

			$skip   = $skipReasons[ $action->getId() ] ?? '';
			$status = $skip !== '' ? FollowUpStatus::SKIPPED : FollowUpStatus::PENDING;

			// wpdb::prepare() renders null as '' — invalid for a datetime
			// column in strict mode — so NULL is written as a literal.
			$finished = $skip !== '' ? $this->wpdb->prepare( '%s', $now ) : 'NULL';

			// INSERT IGNORE: the unique key (optin_id, action_id) turns a
			// second plan for the same opt-in — a parallel click, a retry
			// of the confirming request — into a no-op.
			$result = $this->wpdb->query(
				$this->wpdb->prepare(
					"INSERT IGNORE INTO {$this->table}
						(optin_id, integration, action_id, action_kind, action_label, config_fingerprint,
						 status, attempts, error_code, finished_at, created_at, updated_at)
					 VALUES (%d, %s, %s, %s, %s, %s, %s, 0, %s, {$finished}, %s, %s)",
					$optInId,
					$integration,
					$action->getId(),
					$action->getKind(),
					$action->getLabel(),
					$fingerprint,
					$status,
					$skip,
					$now,
					$now
				)
			);

			if ( is_int( $result ) && $result > 0 ) {
				$inserted += $result;
			}
		}

		return $inserted;
	}

	/**
	 * {@inheritdoc}
	 */
	public function findByOptIn( int $optInId ): array {
		$rows = $this->wpdb->get_results(
			$this->wpdb->prepare( "SELECT * FROM {$this->table} WHERE optin_id = %d ORDER BY id ASC", $optInId ),
			ARRAY_A
		);

		$records = array();
		foreach ( (array) $rows as $row ) {
			if ( is_array( $row ) ) {
				$records[] = FollowUpRecord::fromRow( $row );
			}
		}
		return $records;
	}

	/**
	 * {@inheritdoc}
	 */
	public function claim( int $rowId, array $fromStatuses, string $attemptId, string $trigger, string $now, string $leaseUntil ): bool {
		$fromStatuses = array_values( array_intersect( $fromStatuses, FollowUpStatus::all() ) );
		if ( empty( $fromStatuses ) ) {
			return false;
		}

		$placeholders = implode( ', ', array_fill( 0, count( $fromStatuses ), '%s' ) );
		// A manual retry starts a fresh automatic-retry budget: the admin
		// fixed the cause, so the backoff schedule applies again from the
		// start. `attempts` keeps counting every attempt for the record.
		$budget = $trigger === FollowUpAttempt::TRIGGER_MANUAL ? '1' : 'budget_attempts + 1';

		$params = array_merge(
			array( FollowUpStatus::RUNNING, $attemptId, $trigger, $now, $leaseUntil, $now, $rowId ),
			$fromStatuses
		);

		$affected = $this->wpdb->query(
			$this->wpdb->prepare(
				"UPDATE {$this->table}
				 SET status = %s, attempt_id = %s, attempt_trigger = %s, attempts = attempts + 1,
				     budget_attempts = {$budget},
				     started_at = %s, finished_at = NULL, lease_until = %s, next_attempt_at = NULL,
				     error_code = '', retryable = 0, http_status = 0, response_kind = '', evidence = '',
				     updated_at = %s
				 WHERE id = %d AND status IN ({$placeholders})",
				$params
			)
		);

		return $affected === 1;
	}

	/**
	 * {@inheritdoc}
	 */
	public function complete( int $rowId, string $attemptId, FollowUpResult $result, string $now, string $nextAttemptAt ): bool {
		$next = $nextAttemptAt !== '' ? $this->wpdb->prepare( '%s', $nextAttemptAt ) : 'NULL';

		$affected = $this->wpdb->query(
			$this->wpdb->prepare(
				"UPDATE {$this->table}
				 SET status = %s, error_code = %s, retryable = %d, http_status = %d, response_kind = %s,
				     entry_ref = CASE WHEN %s = '' THEN entry_ref ELSE %s END,
				     evidence = %s, finished_at = %s, next_attempt_at = {$next}, lease_until = NULL,
				     ticket_hash = NULL, ticket_expires = NULL, updated_at = %s
				 WHERE id = %d AND attempt_id = %s AND status = %s",
				$result->getStatus(),
				$result->getErrorCode(),
				$result->isRetryable() ? 1 : 0,
				$result->getHttpStatus(),
				$result->getResponseKind(),
				$result->getEntryRef(),
				$result->getEntryRef(),
				$result->getEvidence(),
				$now,
				$now,
				$rowId,
				$attemptId,
				FollowUpStatus::RUNNING
			)
		);

		return $affected === 1;
	}

	/**
	 * {@inheritdoc}
	 */
	public function setTicket( int $optInId, string $attemptId, string $ticketHash, string $expiresAt ): void {
		$this->wpdb->query(
			$this->wpdb->prepare(
				"UPDATE {$this->table} SET ticket_hash = %s, ticket_expires = %s
				 WHERE optin_id = %d AND attempt_id = %s AND status = %s",
				$ticketHash,
				$expiresAt,
				$optInId,
				$attemptId,
				FollowUpStatus::RUNNING
			)
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function consumeTicket( int $optInId, string $ticketHash, string $now ): ?array {
		if ( $ticketHash === '' ) {
			return null;
		}

		$actionIds = $this->wpdb->get_col(
			$this->wpdb->prepare(
				"SELECT action_id FROM {$this->table}
				 WHERE optin_id = %d AND ticket_hash = %s AND ticket_expires >= %s AND status = %s",
				$optInId,
				$ticketHash,
				$now,
				FollowUpStatus::RUNNING
			)
		);

		if ( empty( $actionIds ) ) {
			return null;
		}

		// The conditional UPDATE is the arbiter: of two requests presenting
		// the same ticket, only one sees affected rows > 0.
		$affected = $this->wpdb->query(
			$this->wpdb->prepare(
				"UPDATE {$this->table} SET ticket_hash = NULL, ticket_expires = NULL
				 WHERE optin_id = %d AND ticket_hash = %s AND ticket_expires >= %s AND status = %s",
				$optInId,
				$ticketHash,
				$now,
				FollowUpStatus::RUNNING
			)
		);

		if ( ! is_int( $affected ) || $affected < 1 ) {
			return null;
		}

		return array_map( 'strval', $actionIds );
	}

	/**
	 * {@inheritdoc}
	 */
	public function expireLeases( string $now ): int {
		$affected = $this->wpdb->query(
			$this->wpdb->prepare(
				"UPDATE {$this->table}
				 SET status = %s, error_code = %s, retryable = 0, finished_at = %s, lease_until = NULL,
				     ticket_hash = NULL, ticket_expires = NULL, updated_at = %s
				 WHERE status = %s AND lease_until IS NOT NULL AND lease_until < %s",
				FollowUpStatus::UNKNOWN,
				'lease_expired',
				$now,
				$now,
				FollowUpStatus::RUNNING,
				$now
			)
		);

		return is_int( $affected ) ? $affected : 0;
	}

	/**
	 * {@inheritdoc}
	 */
	public function findDueOptInIds( string $now, string $pendingBefore, int $limit ): array {
		$ids = $this->wpdb->get_col(
			$this->wpdb->prepare(
				"SELECT DISTINCT f.optin_id FROM {$this->table} f
				 INNER JOIN {$this->optInTable} o ON o.id = f.optin_id
				 WHERE o.doubleoptin = 1
				   AND ( ( f.status = %s AND f.next_attempt_at IS NOT NULL AND f.next_attempt_at <= %s )
				      OR ( f.status = %s AND f.created_at < %s ) )
				 ORDER BY f.optin_id ASC
				 LIMIT %d",
				FollowUpStatus::FAILED_RETRYABLE,
				$now,
				FollowUpStatus::PENDING,
				$pendingBefore,
				max( 1, $limit )
			)
		);

		return array_map( 'intval', (array) $ids );
	}

	/**
	 * {@inheritdoc}
	 */
	public function skipOpen( int $optInId, string $reason, string $now ): int {
		$affected = $this->wpdb->query(
			$this->wpdb->prepare(
				"UPDATE {$this->table}
				 SET status = %s, error_code = %s, retryable = 0, next_attempt_at = NULL, finished_at = %s, updated_at = %s
				 WHERE optin_id = %d AND status IN (%s, %s)",
				FollowUpStatus::SKIPPED,
				$reason,
				$now,
				$now,
				$optInId,
				FollowUpStatus::PENDING,
				FollowUpStatus::FAILED_RETRYABLE
			)
		);

		return is_int( $affected ) ? $affected : 0;
	}

	/**
	 * {@inheritdoc}
	 */
	public function deleteOrphans( int $limit ): int {
		$affected = $this->wpdb->query(
			$this->wpdb->prepare(
				// The derived table lets MySQL delete from a table it also
				// reads in the sub-query, and carries the LIMIT that a
				// multi-table DELETE does not accept.
				"DELETE FROM {$this->table} WHERE id IN (
				     SELECT id FROM ( SELECT f.id FROM {$this->table} f
				         LEFT JOIN {$this->optInTable} o ON o.id = f.optin_id
				         WHERE o.id IS NULL LIMIT %d ) AS orphan_ids )",
				max( 1, $limit )
			)
		);

		return is_int( $affected ) ? $affected : 0;
	}

	/**
	 * {@inheritdoc}
	 */
	public function deleteByOptIn( int $optInId ): int {
		$affected = $this->wpdb->delete( $this->table, array( 'optin_id' => $optInId ), array( '%d' ) );
		return is_int( $affected ) ? $affected : 0;
	}
}
