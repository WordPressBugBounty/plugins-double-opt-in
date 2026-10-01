<?php
/**
 * Aggregate numbers about opt-ins, without personal data.
 *
 * @package Forge12\DoubleOptIn\Repository
 * @since   5.8.0
 */

declare( strict_types=1 );

namespace Forge12\DoubleOptIn\Repository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OptInStatsRepository {

	/** @var \wpdb|object */
	private $wpdb;

	/** @var string */
	private $table;

	/**
	 * @param \wpdb|object $wpdb WordPress database.
	 */
	public function __construct( $wpdb ) {
		$this->wpdb  = $wpdb;
		$this->table = $wpdb->prefix . 'f12_cf7_doubleoptin';
	}

	/**
	 * Opt-ins created in [$from, $to) and how many of them are unconfirmed.
	 *
	 * @return array{total: int, unconfirmed: int}
	 */
	public function confirmationWindow( int $from, int $to ): array {
		$since = gmdate( 'Y-m-d H:i:s', $from );
		$until = gmdate( 'Y-m-d H:i:s', $to );

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- WPCS does not recognise $this->wpdb->prepare(); the table name comes from $wpdb->prefix.
		$row = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT COUNT(*) AS total, SUM(CASE WHEN doubleoptin = 1 THEN 0 ELSE 1 END) AS unconfirmed FROM {$this->table} WHERE createtime >= %s AND createtime < %s",
				$since,
				$until
			),
			ARRAY_A
		);
		// phpcs:enable
		$row = is_array( $row ) ? $row : array();

		return array(
			'total'       => (int) ( $row['total'] ?? 0 ),
			'unconfirmed' => (int) ( $row['unconfirmed'] ?? 0 ),
		);
	}

	/**
	 * What happened in [$from, $to): opt-ins created, how many of those
	 * were confirmed (also when withdrawn later — a confirmation leaves its
	 * IP, an opt-out only resets the flag), and consents withdrawn in that
	 * time.
	 *
	 * @since 5.8.0
	 *
	 * @return array{created: int, confirmed: int, opted_out: int}
	 */
	public function activity( int $from, int $to ): array {
		$since = gmdate( 'Y-m-d H:i:s', $from );
		$until = gmdate( 'Y-m-d H:i:s', $to );

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- WPCS does not recognise $this->wpdb->prepare(); the table name comes from $wpdb->prefix.
		$row = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT
					SUM(CASE WHEN createtime >= %s AND createtime < %s THEN 1 ELSE 0 END) AS created,
					SUM(CASE WHEN createtime >= %s AND createtime < %s AND (doubleoptin = 1 OR ipaddr_confirmation <> '') THEN 1 ELSE 0 END) AS confirmed,
					SUM(CASE WHEN optouttime >= %s AND optouttime < %s THEN 1 ELSE 0 END) AS opted_out
				FROM {$this->table}
				WHERE (createtime >= %s AND createtime < %s) OR (optouttime >= %s AND optouttime < %s)",
				$since,
				$until,
				$since,
				$until,
				$since,
				$until,
				$since,
				$until,
				$since,
				$until
			),
			ARRAY_A
		);
		// phpcs:enable
		$row = is_array( $row ) ? $row : array();

		return array(
			'created'   => (int) ( $row['created'] ?? 0 ),
			'confirmed' => (int) ( $row['confirmed'] ?? 0 ),
			'opted_out' => (int) ( $row['opted_out'] ?? 0 ),
		);
	}
}
