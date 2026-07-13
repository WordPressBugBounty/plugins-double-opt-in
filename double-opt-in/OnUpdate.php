<?php

namespace forge12\contactform7\CF7DoubleOptIn;

use Forge12\Shared\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function onUpdate() {
	$logger = Logger::getInstance();

	$logger->info( 'Running onUpdate routine', [
		'plugin' => 'double-opt-in',
		'class'  => __CLASS__ ?? null,
		'method' => __FUNCTION__,
	] );

	// Read and write the stored version with the SAME scope. Historically
	// the read used get_site_option() (network scope on multisite) while
	// every write below uses update_option() (blog scope) — so on a
	// network-activated multisite the version never read back, and every
	// idempotent block re-ran on each admin load. Blog scope matches the
	// per-site tables created in OnActivation.php.
	$currentVersion = get_option( FORGE12_OPTIN_SLUG . '_version' );

	if ( version_compare( $currentVersion, '1.7' ) < 0 ) {
		$logger->info( 'Updating to version 1.7', [
			'plugin'  => 'double-opt-in',
			'current' => $currentVersion,
			'target'  => '1.7',
		] );

		createTableOptinCategories();
		$logger->debug( 'createTableOptinCategories executed', [
			'plugin' => 'double-opt-in',
		] );

		createTableOptin();
		$logger->debug( 'createTableOptin executed', [
			'plugin' => 'double-opt-in',
		] );

		update_option( FORGE12_OPTIN_SLUG . '_version', '1.7' );
		$logger->info( 'Version updated to 1.7', [
			'plugin' => 'double-opt-in',
		] );
	}

	if ( version_compare( $currentVersion, '2.0' ) < 0 ) {
		$logger->info( 'Updating to version 2.0', [
			'plugin'  => 'double-opt-in',
			'current' => $currentVersion,
			'target'  => '2.0',
		] );

		createTableOptin();
		$logger->debug( 'createTableOptin executed', [
			'plugin' => 'double-opt-in',
		] );

		update_option( FORGE12_OPTIN_SLUG . '_version', '2.0' );
		$logger->info( 'Version updated to 2.0', [
			'plugin' => 'double-opt-in',
		] );
	}

	if ( version_compare( $currentVersion, '3.0.1' ) < 0 ) {
		$logger->info( 'Updating to version 3.0.1', [
			'plugin'  => 'double-opt-in',
			'current' => $currentVersion,
			'target'  => '3.0.1',
		] );

		createTableOptin();
		$logger->debug( 'createTableOptin executed', [
			'plugin' => 'double-opt-in',
		] );

		update_option( FORGE12_OPTIN_SLUG . '_version', '3.0.1' );
		$logger->info( 'Version updated to 3.0.1', [
			'plugin' => 'double-opt-in',
		] );
	}

	if ( version_compare( $currentVersion, '3.2.0' ) < 0 ) {
		$logger->info( 'Updating to version 3.2.0', [
			'plugin'  => 'double-opt-in',
			'current' => $currentVersion,
			'target'  => '3.2.0',
		] );

		createTableOptin();
		$logger->debug( 'createTableOptin executed', [
			'plugin' => 'double-opt-in',
		] );

		update_option( FORGE12_OPTIN_SLUG . '_version', '3.2.0' );
		$logger->info( 'Version updated to 3.2.0', [
			'plugin' => 'double-opt-in',
		] );
	}

	if ( version_compare( $currentVersion, '3.3.0' ) < 0 ) {
		$logger->info( 'Updating to version 3.3.0', [
			'plugin'  => 'double-opt-in',
			'current' => $currentVersion,
			'target'  => '3.3.0',
		] );

		// Migrate Unix timestamps to ISO 8601 format
		global $wpdb;
		$table = $wpdb->prefix . 'f12_cf7_doubleoptin';
		foreach ( [ 'createtime', 'updatetime', 'optouttime' ] as $col ) {
			$wpdb->query(
				"UPDATE {$table} SET {$col} = FROM_UNIXTIME({$col})
				 WHERE {$col} REGEXP '^[0-9]+$' AND {$col} != '' AND {$col} != '0'"
			);
			$logger->debug( "Migrated column {$col} from Unix to ISO 8601", [
				'plugin' => 'double-opt-in',
			] );
		}

		update_option( FORGE12_OPTIN_SLUG . '_version', '3.3.0' );
		$logger->info( 'Version updated to 3.3.0', [
			'plugin' => 'double-opt-in',
		] );
	}

	if ( version_compare( $currentVersion, '3.4.0' ) < 0 ) {
		$logger->info( 'Updating to version 3.4.0', [
			'plugin'  => 'double-opt-in',
			'current' => $currentVersion,
			'target'  => '3.4.0',
		] );

		createTableOptin();
		$logger->debug( 'createTableOptin executed (adds reminder columns)', [
			'plugin' => 'double-opt-in',
		] );

		update_option( FORGE12_OPTIN_SLUG . '_version', '3.4.0' );
		$logger->info( 'Version updated to 3.4.0', [
			'plugin' => 'double-opt-in',
		] );
	}

	if ( version_compare( $currentVersion, '4.2.0' ) < 0 ) {
		$logger->info( 'Updating to version 4.2.0', [
			'plugin'  => 'double-opt-in',
			'current' => $currentVersion,
			'target'  => '4.2.0',
		] );

		createTableAuditLog();
		$logger->debug( 'createTableAuditLog executed', [
			'plugin' => 'double-opt-in',
		] );

		update_option( FORGE12_OPTIN_SLUG . '_version', '4.2.0' );
		$logger->info( 'Version updated to 4.2.0', [
			'plugin' => 'double-opt-in',
		] );
	}

	if ( version_compare( $currentVersion, '5.0.1' ) < 0 ) {
		$logger->info( 'Updating to version 5.0.1 (adds consent_field column)', [
			'plugin'  => 'double-opt-in',
			'current' => $currentVersion,
			'target'  => '5.0.1',
		] );

		// dbDelta detects the new `consent_field` column in
		// createTableOptin()'s schema and ALTERs the existing table
		// to add it. Existing rows get the column's DEFAULT '' so
		// no data migration is needed.
		createTableOptin();
		$logger->debug( 'createTableOptin executed (adds consent_field column)', [
			'plugin' => 'double-opt-in',
		] );

		update_option( FORGE12_OPTIN_SLUG . '_version', '5.0.1' );
		$logger->info( 'Version updated to 5.0.1', [
			'plugin' => 'double-opt-in',
		] );
	}

	// Safety net: Ensure both tables always exist regardless of stored version.
	// Handles edge cases such as database migrations, manual file uploads, or
	// restored backups that are missing the custom tables.
	global $wpdb;
	$optinTable    = $wpdb->prefix . 'f12_cf7_doubleoptin';
	$categoryTable = $wpdb->prefix . 'f12_cf7_doubleoptin_categories';

	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $optinTable ) ) !== $optinTable ) {
		$logger->warning( 'OptIn table missing – recreating.', [ 'plugin' => 'double-opt-in', 'table' => $optinTable ] );
		createTableOptin();
	}

	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $categoryTable ) ) !== $categoryTable ) {
		$logger->warning( 'Categories table missing – recreating.', [ 'plugin' => 'double-opt-in', 'table' => $categoryTable ] );
		createTableOptinCategories();
	}

	$auditTable = $wpdb->prefix . 'f12_cf7_doubleoptin_audit_log';
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $auditTable ) ) !== $auditTable ) {
		$logger->warning( 'Audit log table missing – recreating.', [ 'plugin' => 'double-opt-in', 'table' => $auditTable ] );
		createTableAuditLog();
	}

	// Always pin the stored version to the live plugin version. The
	// version_compare ladder above only writes the target of the last
	// block that ran (e.g. '5.0.1'), so without this line the stored
	// version permanently lags FORGE12_OPTIN_VERSION even after the
	// upgrade completed — including for releases that ship no schema
	// change (e.g. 5.1.0). Unconditional so it self-heals any drift.
	if ( defined( 'FORGE12_OPTIN_VERSION' )
		&& version_compare( (string) $currentVersion, FORGE12_OPTIN_VERSION, '<' ) ) {
		update_option( FORGE12_OPTIN_SLUG . '_version', FORGE12_OPTIN_VERSION );
		$logger->info( 'Stored version pinned to current plugin version', [
			'plugin'  => 'double-opt-in',
			'from'    => $currentVersion,
			'to'      => FORGE12_OPTIN_VERSION,
		] );
	}

	$logger->info( 'onUpdate routine finished', [
		'plugin' => 'double-opt-in',
	] );
}

onUpdate();
