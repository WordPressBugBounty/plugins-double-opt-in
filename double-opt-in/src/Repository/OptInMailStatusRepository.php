<?php
/**
 * Delivery status of the confirmation mail, per opt-in.
 *
 * Its own class rather than a method on OptInRepositoryInterface: that
 * interface is public Core API for add-ons, and the three columns
 * (`mail_status`, `mail_error`, `mail_status_at`, since 5.8.0) are written
 * independently of the entity. OptInRepository::update() writes only the
 * entity's fields, so a later save leaves them untouched.
 *
 * "sent" means handed to the mail server (wp_mail returned true). Whether a
 * mail arrives in an inbox is nothing WordPress can know.
 *
 * @package Forge12\DoubleOptIn\Repository
 * @since   5.8.0
 */

declare( strict_types=1 );

namespace Forge12\DoubleOptIn\Repository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OptInMailStatusRepository {

	public const SENT   = 'sent';
	public const FAILED = 'failed';

	private const ERROR_MAX = 255;

	/** @var \wpdb|object */
	private $wpdb;

	/** @var string */
	private $table;

	/** @var callable():int */
	private $clock;

	/**
	 * @param \wpdb|object  $wpdb  The database handle.
	 * @param callable|null $clock Returns the current Unix time (test seam).
	 */
	public function __construct( $wpdb, ?callable $clock = null ) {
		$this->wpdb  = $wpdb;
		$this->table = $wpdb->prefix . 'f12_cf7_doubleoptin';
		$this->clock = $clock ?? 'time';
	}

	/**
	 * Record the outcome of a send attempt.
	 */
	public function mark( int $optInId, string $status, string $error = '' ): bool {
		if ( $optInId <= 0 || ! in_array( $status, array( self::SENT, self::FAILED ), true ) ) {
			return false;
		}
		$error = $status === self::FAILED ? self::truncate( $error ) : '';

		$result = $this->wpdb->update(
			$this->table,
			array(
				'mail_status'    => $status,
				'mail_error'     => $error,
				'mail_status_at' => gmdate( 'Y-m-d H:i:s', (int) call_user_func( $this->clock ) ),
			),
			array( 'id' => $optInId ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);

		return $result !== false;
	}

	/**
	 * Stored status of one opt-in.
	 *
	 * @return array{status: string, error: string, at: string}
	 */
	public function find( int $optInId ): array {
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- WPCS does not recognise $this->wpdb->prepare(); the table name comes from $wpdb->prefix.
		$row = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT mail_status, mail_error, mail_status_at FROM {$this->table} WHERE id = %d",
				$optInId
			),
			ARRAY_A
		);
		// phpcs:enable
		$row = is_array( $row ) ? $row : array();

		return array(
			'status' => (string) ( $row['mail_status'] ?? '' ),
			'error'  => (string) ( $row['mail_error'] ?? '' ),
			'at'     => (string) ( $row['mail_status_at'] ?? '' ),
		);
	}

	/**
	 * Drop the stored error text. It can quote the recipient address, so the
	 * privacy eraser clears it along with the other personal data.
	 */
	public function clearError( int $optInId ): bool {
		return $this->wpdb->update(
			$this->table,
			array( 'mail_error' => '' ),
			array( 'id' => $optInId ),
			array( '%s' ),
			array( '%d' )
		) !== false;
	}

	/**
	 * Failed sends recorded within the last $seconds.
	 */
	public function countFailedSince( int $seconds ): int {
		$since = gmdate( 'Y-m-d H:i:s', (int) call_user_func( $this->clock ) - max( 0, $seconds ) );

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- WPCS does not recognise $this->wpdb->prepare(); the table name comes from $wpdb->prefix.
		$count = $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table} WHERE mail_status = %s AND mail_status_at >= %s",
				self::FAILED,
				$since
			)
		);
		// phpcs:enable

		return (int) $count;
	}

	/**
	 * Failed sends recorded in [$from, $to).
	 *
	 * @since 5.8.0
	 */
	public function countFailedBetween( int $from, int $to ): int {
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- WPCS does not recognise $this->wpdb->prepare(); the table name comes from $wpdb->prefix.
		$count = $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table} WHERE mail_status = %s AND mail_status_at >= %s AND mail_status_at < %s",
				self::FAILED,
				gmdate( 'Y-m-d H:i:s', $from ),
				gmdate( 'Y-m-d H:i:s', $to )
			)
		);
		// phpcs:enable

		return (int) $count;
	}

	/**
	 * Keep an error message short enough for the column, on a character
	 * boundary.
	 */
	public static function truncate( string $error ): string {
		$error = trim( preg_replace( '/\s+/', ' ', $error ) ?? '' );
		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $error, 0, self::ERROR_MAX );
		}
		return substr( $error, 0, self::ERROR_MAX );
	}
}
