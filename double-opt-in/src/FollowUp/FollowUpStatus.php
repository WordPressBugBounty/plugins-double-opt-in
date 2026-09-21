<?php
/**
 * Follow-up action states.
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
 * The lifecycle of one follow-up action (one row in the follow-up table).
 *
 *   pending ──claim──> running ──> succeeded | skipped
 *                         │   ──> failed_retryable ──claim──> running …
 *                         │   ──> failed_permanent
 *                         └──> unknown  (timeout after send, crash, lease expiry)
 *
 * `unknown` is deliberately not retried on its own: the action may have
 * run. Only an administrator who accepts the risk of a duplicate can
 * push it back into `running`.
 */
final class FollowUpStatus {

	public const PENDING          = 'pending';
	public const RUNNING          = 'running';
	public const SUCCEEDED        = 'succeeded';
	public const FAILED_RETRYABLE = 'failed_retryable';
	public const FAILED_PERMANENT = 'failed_permanent';
	public const UNKNOWN          = 'unknown';
	public const SKIPPED          = 'skipped';

	/**
	 * Aggregate states for one opt-in (derived, never stored).
	 */
	public const AGGREGATE_NONE           = 'none';
	public const AGGREGATE_LEGACY_UNKNOWN = 'legacy_unknown';
	public const AGGREGATE_PENDING        = 'pending';
	public const AGGREGATE_COMPLETED      = 'completed';
	public const AGGREGATE_PARTIAL        = 'partial';
	public const AGGREGATE_FAILED         = 'failed';
	public const AGGREGATE_UNKNOWN        = 'unknown';

	/**
	 * @return string[]
	 */
	public static function all(): array {
		return array(
			self::PENDING,
			self::RUNNING,
			self::SUCCEEDED,
			self::FAILED_RETRYABLE,
			self::FAILED_PERMANENT,
			self::UNKNOWN,
			self::SKIPPED,
		);
	}

	/**
	 * States from which a result can no longer change without an
	 * explicit administrator decision.
	 */
	public static function isSettled( string $status ): bool {
		return in_array( $status, array( self::SUCCEEDED, self::SKIPPED, self::FAILED_PERMANENT, self::UNKNOWN ), true );
	}

	/**
	 * States counted as "nothing left to do".
	 */
	public static function isDone( string $status ): bool {
		return $status === self::SUCCEEDED || $status === self::SKIPPED;
	}

	/**
	 * States that need attention in the admin list.
	 *
	 * @return string[]
	 */
	public static function problematic(): array {
		return array( self::FAILED_RETRYABLE, self::FAILED_PERMANENT, self::UNKNOWN );
	}

	/**
	 * Derive the aggregate state of one opt-in from its action states.
	 *
	 * @param string[] $statuses One status per action row.
	 */
	public static function aggregate( array $statuses ): string {
		if ( empty( $statuses ) ) {
			return self::AGGREGATE_NONE;
		}

		$has = array_fill_keys( self::all(), 0 );
		foreach ( $statuses as $status ) {
			if ( isset( $has[ $status ] ) ) {
				$has[ $status ]++;
			}
		}

		if ( $has[ self::PENDING ] > 0 || $has[ self::RUNNING ] > 0 ) {
			return self::AGGREGATE_PENDING;
		}

		$problems = $has[ self::FAILED_RETRYABLE ] + $has[ self::FAILED_PERMANENT ] + $has[ self::UNKNOWN ];
		if ( $problems === 0 ) {
			return self::AGGREGATE_COMPLETED;
		}

		if ( $has[ self::SUCCEEDED ] > 0 ) {
			return self::AGGREGATE_PARTIAL;
		}

		if ( $has[ self::FAILED_RETRYABLE ] + $has[ self::FAILED_PERMANENT ] === 0 ) {
			return self::AGGREGATE_UNKNOWN;
		}

		return self::AGGREGATE_FAILED;
	}
}
