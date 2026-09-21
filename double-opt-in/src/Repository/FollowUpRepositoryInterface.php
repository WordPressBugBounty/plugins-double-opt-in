<?php
/**
 * Follow-up repository contract.
 *
 * @package Forge12\DoubleOptIn\Repository
 * @since   5.6.0
 */

declare( strict_types=1 );

namespace Forge12\DoubleOptIn\Repository;

use Forge12\DoubleOptIn\FollowUp\FollowUpAction;
use Forge12\DoubleOptIn\FollowUp\FollowUpRecord;
use Forge12\DoubleOptIn\FollowUp\FollowUpResult;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Persistence for follow-up action state.
 *
 * Every state change that decides "who executes" (`claim`,
 * `consumeTicket`) is a single conditional UPDATE — the database, not a
 * PHP flag or a transient, arbitrates between concurrent requests.
 */
interface FollowUpRepositoryInterface {

	/**
	 * Insert one row per action unless it already exists (the unique key
	 * (optin_id, action_id) makes this idempotent).
	 *
	 * @param FollowUpAction[]      $actions
	 * @param array<string, string> $skipReasons Action id => reason; those rows
	 *                                           are inserted as `skipped`.
	 *
	 * @return int Number of rows actually inserted.
	 */
	public function plan( int $optInId, string $integration, array $actions, array $skipReasons, string $fingerprint, string $now ): int;

	/**
	 * @return FollowUpRecord[]
	 */
	public function findByOptIn( int $optInId ): array;

	/**
	 * Atomically move one row from any of $fromStatuses to `running`.
	 *
	 * @param string[] $fromStatuses
	 *
	 * @return bool True only for the request that won the claim.
	 */
	public function claim( int $rowId, array $fromStatuses, string $attemptId, string $trigger, string $now, string $leaseUntil ): bool;

	/**
	 * Store the result of a claimed row. Only succeeds while the row is
	 * still `running` under the same attempt id.
	 */
	public function complete( int $rowId, string $attemptId, FollowUpResult $result, string $now, string $nextAttemptAt ): bool;

	/**
	 * Attach a hashed single-use ticket to every row of an attempt.
	 */
	public function setTicket( int $optInId, string $attemptId, string $ticketHash, string $expiresAt ): void;

	/**
	 * Atomically consume a ticket. Returns the action ids bound to it, or
	 * null when the ticket is unknown, expired or already used.
	 *
	 * @return string[]|null
	 */
	public function consumeTicket( int $optInId, string $ticketHash, string $now ): ?array;

	/**
	 * Rows stuck in `running` past their lease become `unknown`: the
	 * process may have died after the side effect. Returns affected rows.
	 */
	public function expireLeases( string $now ): int;

	/**
	 * Opt-in ids with work the sweep may pick up: `failed_retryable`
	 * whose next attempt is due, or `pending` on a confirmed opt-in older
	 * than $pendingBefore (the confirming request died before running it).
	 *
	 * @return int[]
	 */
	public function findDueOptInIds( string $now, string $pendingBefore, int $limit ): array;

	/**
	 * Mark every open row (pending / failed_retryable) of an opt-in as
	 * skipped. Returns affected rows.
	 */
	public function skipOpen( int $optInId, string $reason, string $now ): int;

	public function deleteByOptIn( int $optInId ): int;

	/**
	 * Delete rows whose opt-in no longer exists. Backstop for deletion
	 * paths that do not fire `f12_doi_optin_pre_delete` (repository
	 * deletes, direct SQL by third parties). Returns affected rows.
	 */
	public function deleteOrphans( int $limit ): int;

	/**
	 * Fully qualified table name — for list filters that need an EXISTS
	 * sub-query against the opt-in table.
	 */
	public function getTableName(): string;
}
