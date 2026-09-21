<?php
/**
 * One persisted follow-up action row.
 *
 * @package Forge12\DoubleOptIn\FollowUp
 * @since   5.6.0
 */

declare( strict_types=1 );

namespace Forge12\DoubleOptIn\FollowUp;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plain data holder for a row of `{prefix}f12_cf7_doubleoptin_followup`.
 * Times are UTC `Y-m-d H:i:s` strings or '' when unset.
 */
final class FollowUpRecord {

	/** @var int */
	public $id = 0;

	/** @var int */
	public $optInId = 0;

	/** @var string */
	public $integration = '';

	/** @var string */
	public $actionId = '';

	/** @var string */
	public $actionKind = '';

	/** @var string */
	public $actionLabel = '';

	/** @var string */
	public $fingerprint = '';

	/** @var string */
	public $status = FollowUpStatus::PENDING;

	/** @var int */
	public $attempts = 0;

	/**
	 * Attempts since the last manual retry — what the automatic backoff
	 * counts. `attempts` counts every attempt ever made.
	 *
	 * @var int
	 */
	public $budgetAttempts = 0;

	/** @var string */
	public $attemptId = '';

	/** @var string */
	public $trigger = '';

	/** @var string */
	public $startedAt = '';

	/** @var string */
	public $finishedAt = '';

	/** @var string */
	public $nextAttemptAt = '';

	/** @var string */
	public $leaseUntil = '';

	/** @var string */
	public $errorCode = '';

	/** @var bool */
	public $retryable = false;

	/** @var int */
	public $httpStatus = 0;

	/** @var string */
	public $responseKind = '';

	/** @var string */
	public $entryRef = '';

	/** @var string */
	public $evidence = '';

	/** @var string */
	public $createdAt = '';

	/**
	 * Hydrate from a database row (ARRAY_A).
	 *
	 * @param array<string, mixed> $row
	 */
	public static function fromRow( array $row ): self {
		$r                = new self();
		$r->id            = (int) ( $row['id'] ?? 0 );
		$r->optInId       = (int) ( $row['optin_id'] ?? 0 );
		$r->integration   = (string) ( $row['integration'] ?? '' );
		$r->actionId      = (string) ( $row['action_id'] ?? '' );
		$r->actionKind    = (string) ( $row['action_kind'] ?? '' );
		$r->actionLabel   = (string) ( $row['action_label'] ?? '' );
		$r->fingerprint   = (string) ( $row['config_fingerprint'] ?? '' );
		$r->status        = (string) ( $row['status'] ?? FollowUpStatus::PENDING );
		$r->attempts      = (int) ( $row['attempts'] ?? 0 );
		$r->budgetAttempts = (int) ( $row['budget_attempts'] ?? 0 );
		$r->attemptId     = (string) ( $row['attempt_id'] ?? '' );
		$r->trigger       = (string) ( $row['attempt_trigger'] ?? '' );
		$r->startedAt     = (string) ( $row['started_at'] ?? '' );
		$r->finishedAt    = (string) ( $row['finished_at'] ?? '' );
		$r->nextAttemptAt = (string) ( $row['next_attempt_at'] ?? '' );
		$r->leaseUntil    = (string) ( $row['lease_until'] ?? '' );
		$r->errorCode     = (string) ( $row['error_code'] ?? '' );
		$r->retryable     = ! empty( $row['retryable'] );
		$r->httpStatus    = (int) ( $row['http_status'] ?? 0 );
		$r->responseKind  = (string) ( $row['response_kind'] ?? '' );
		$r->entryRef      = (string) ( $row['entry_ref'] ?? '' );
		$r->evidence      = (string) ( $row['evidence'] ?? '' );
		$r->createdAt     = (string) ( $row['created_at'] ?? '' );
		return $r;
	}

	/**
	 * Admin/REST representation. Contains no personal data by
	 * construction — every field is structural.
	 *
	 * @return array<string, mixed>
	 */
	public function toArray(): array {
		return array(
			'id'              => $this->id,
			'optin_id'        => $this->optInId,
			'integration'     => $this->integration,
			'action_id'       => $this->actionId,
			'action_kind'     => $this->actionKind,
			'action_label'    => $this->actionLabel,
			'status'          => $this->status,
			'attempts'        => $this->attempts,
			'budget_attempts' => $this->budgetAttempts,
			'attempt_id'      => $this->attemptId,
			'trigger'         => $this->trigger,
			'started_at'      => $this->startedAt,
			'finished_at'     => $this->finishedAt,
			'next_attempt_at' => $this->nextAttemptAt,
			'error_code'      => $this->errorCode,
			'retryable'       => $this->retryable,
			'http_status'     => $this->httpStatus,
			'response_kind'   => $this->responseKind,
			'entry_ref'       => $this->entryRef,
			'evidence'        => $this->evidence,
			'created_at'      => $this->createdAt,
		);
	}

	/**
	 * Rebuild the action descriptor this row was planned from.
	 */
	public function toAction(): FollowUpAction {
		return new FollowUpAction( $this->actionId, $this->actionKind, $this->actionLabel, true, '', $this->entryRef );
	}
}
