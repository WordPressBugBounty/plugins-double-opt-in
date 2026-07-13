<?php
/**
 * Migration Interface
 *
 * @package Forge12\DoubleOptIn\Migration
 * @since   4.3.0
 */

namespace Forge12\DoubleOptIn\Migration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface MigrationInterface
 *
 * @api
 *
 * A migration is a one-shot, immutable schema change. Once a migration
 * has shipped in a released version it MUST NEVER be edited; new changes
 * are new migrations. The registry records applied migration IDs in the
 * `f12_doi_applied_migrations` option so each migration runs exactly once
 * per site.
 *
 * Rollback is intentionally not supported: if a migration needs to be
 * reversed, ship a new forward-only migration that undoes it. This is
 * operationally safer than a rollback-capable system because there is
 * only ever one code path — forward.
 */
interface MigrationInterface {

	/**
	 * Stable unique identifier for the migration.
	 *
	 * Convention: `{owner}_{yyyymmdd}_{short_slug}`. Example:
	 *  - `core_20260501_add_reminder_column`
	 *  - `addon_analytics_20260515_create_stats_cache`
	 *
	 * The ID becomes the primary key in the applied-migrations option. It
	 * MUST be unique across the whole core+addon ecosystem and MUST NOT
	 * change once released.
	 *
	 * @return string
	 */
	public function getId(): string;

	/**
	 * Short human-readable description, used in admin notices and logs.
	 *
	 * @return string
	 */
	public function getDescription(): string;

	/**
	 * Apply the migration.
	 *
	 * Called exactly once per site. Must be idempotent at the level of
	 * individual DDL statements (e.g. use `ADD COLUMN IF NOT EXISTS`
	 * where supported, or guard with a column-exists check) so that an
	 * accidental re-run does not crash.
	 *
	 * Throwing from `up()` aborts the migration without marking it as
	 * applied, so the registry will attempt it again on the next
	 * bootstrap. Use this behaviour deliberately — swallow-and-continue
	 * should be explicit.
	 *
	 * @param \wpdb $wpdb The WordPress database abstraction.
	 * @return void
	 */
	public function up( \wpdb $wpdb ): void;
}
