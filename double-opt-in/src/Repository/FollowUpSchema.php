<?php
/**
 * Schema of the follow-up table.
 *
 * @package Forge12\DoubleOptIn\Repository
 * @since   5.6.0
 */

declare( strict_types=1 );

namespace Forge12\DoubleOptIn\Repository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates / updates `{prefix}f12_cf7_doubleoptin_followup`.
 *
 * Called from the activation hook, the `OnUpdate` ladder and its
 * "table missing" safety net — the same three places as the other core
 * tables, so a restored backup or a manual file upload heals itself.
 *
 * No form data is stored here. The row references the opt-in by id;
 * the payload stays in the opt-in row where retention and deletion
 * already apply to it.
 */
final class FollowUpSchema {

	public const TABLE_NAME = 'f12_cf7_doubleoptin_followup';

	/**
	 * Run dbDelta for the current blog.
	 */
	public static function install(): void {
		global $wpdb;

		if ( ! function_exists( 'dbDelta' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}

		$table   = $wpdb->prefix . self::TABLE_NAME;
		$charset = $wpdb->get_charset_collate();

		// dbDelta formatting rules: two spaces after PRIMARY KEY, one
		// column per line, KEY instead of INDEX.
		$sql = "CREATE TABLE {$table} (
        id bigint(20) unsigned NOT NULL auto_increment,
        optin_id bigint(20) unsigned NOT NULL,
        integration varchar(50) NOT NULL DEFAULT '',
        action_id varchar(100) NOT NULL,
        action_kind varchar(20) NOT NULL DEFAULT '',
        action_label varchar(190) NOT NULL DEFAULT '',
        config_fingerprint char(64) NOT NULL DEFAULT '',
        status varchar(24) NOT NULL DEFAULT 'pending',
        attempts int(11) unsigned NOT NULL DEFAULT 0,
        budget_attempts int(11) unsigned NOT NULL DEFAULT 0,
        attempt_id varchar(64) NOT NULL DEFAULT '',
        attempt_trigger varchar(20) NOT NULL DEFAULT '',
        started_at datetime DEFAULT NULL,
        finished_at datetime DEFAULT NULL,
        next_attempt_at datetime DEFAULT NULL,
        lease_until datetime DEFAULT NULL,
        error_code varchar(64) NOT NULL DEFAULT '',
        retryable tinyint(1) NOT NULL DEFAULT 0,
        http_status smallint(5) unsigned NOT NULL DEFAULT 0,
        response_kind varchar(24) NOT NULL DEFAULT '',
        entry_ref varchar(100) NOT NULL DEFAULT '',
        evidence varchar(64) NOT NULL DEFAULT '',
        ticket_hash char(64) DEFAULT NULL,
        ticket_expires datetime DEFAULT NULL,
        created_at datetime NOT NULL,
        updated_at datetime NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY optin_action (optin_id,action_id),
        KEY status_next (status,next_attempt_at),
        KEY status_lease (status,lease_until)
    ) {$charset};";

		dbDelta( $sql );
	}

	/**
	 * Whether the table exists and has the newest column. Cheap enough
	 * for the OnUpdate safety net, which runs on every load: one
	 * SHOW COLUMNS … LIKE against a small table.
	 */
	public static function isCurrent(): bool {
		global $wpdb;
		if ( ! self::exists() ) {
			return false;
		}
		$table = $wpdb->prefix . self::TABLE_NAME;
		return (bool) $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM {$table} LIKE %s", 'budget_attempts' ) );
	}

	/**
	 * Whether the table exists on the current blog.
	 */
	public static function exists(): bool {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE_NAME;
		return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
	}
}
