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

	$currentVersion = get_site_option( FORGE12_OPTIN_SLUG . '_version' );

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

	$logger->info( 'onUpdate routine finished', [
		'plugin' => 'double-opt-in',
	] );
}

onUpdate();
