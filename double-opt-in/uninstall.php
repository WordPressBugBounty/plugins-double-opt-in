<?php

namespace forge12\contactform7\CF7DoubleOptIn;

use Forge12\Shared\Logger;

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	die;
}

require_once plugin_dir_path( __FILE__ ) . 'logger/logger.php';

/**
 * Whether the admin asked to KEEP opt-in data when deleting the plugin.
 *
 * Defaults to KEEP: data is dropped ONLY when the
 * `keep_data_on_uninstall` setting is explicitly 0. A site that never
 * touched the setting (key absent) keeps its data — deleting the plugin
 * must never silently destroy GDPR consent records.
 *
 * @return bool True if the opt-in tables should be preserved.
 */
function should_keep_data_on_uninstall(): bool {
	$settings = get_option( 'f12-doi-settings', array() );

	if ( ! is_array( $settings ) || ! array_key_exists( 'keep_data_on_uninstall', $settings ) ) {
		return true; // key absent → safe default: keep.
	}

	return (int) $settings['keep_data_on_uninstall'] !== 0;
}

function drop_table_optin() {
	global $wpdb;

	$logger = Logger::getInstance();

	$tableName   = 'f12_cf7_doubleoptin';
	$wpTableName = $wpdb->prefix . $tableName;

	$logger->info( 'Dropping table if exists', [
		'plugin'     => 'double-opt-in',
		'table_name' => $wpTableName,
		'class'      => __CLASS__ ?? null,
		'method'     => __FUNCTION__,
	] );

	$wpdb->query( "DROP TABLE IF EXISTS " . esc_sql( $wpTableName ) );

	$logger->info( 'Table dropped', [
		'plugin'     => 'double-opt-in',
		'table_name' => $wpTableName,
	] );
}

function drop_table_categories() {
	global $wpdb;

	$logger = Logger::getInstance();

	$tableName   = 'f12_cf7_doubleoptin_categories';
	$wpTableName = $wpdb->prefix . $tableName;

	$logger->info( 'Dropping table if exists', [
		'plugin'     => 'double-opt-in',
		'table_name' => $wpTableName,
		'class'      => __CLASS__ ?? null,
		'method'     => __FUNCTION__,
	] );

	$wpdb->query( "DROP TABLE IF EXISTS " . esc_sql( $wpTableName ) );

	$logger->info( 'Table dropped', [
		'plugin'     => 'double-opt-in',
		'table_name' => $wpTableName,
	] );
}

if ( should_keep_data_on_uninstall() ) {
	Logger::getInstance()->info( 'Uninstall: keeping opt-in data (keep_data_on_uninstall enabled).', [
		'plugin' => 'double-opt-in',
	] );
} else {
	Logger::getInstance()->info( 'Uninstall: deleting opt-in data (keep_data_on_uninstall disabled by admin).', [
		'plugin' => 'double-opt-in',
	] );
	drop_table_categories();
	drop_table_optin();
}

// Purely operational options with no value after removal. Admin-set config
// (f12-doi-settings) is intentionally left in place, and cross-package options
// (bundle-pro licence, addon settings) are owned by their own uninstallers.
delete_option( 'f12_cf7_doubleoptin_installed_at' );
delete_option( 'f12_cf7_doubleoptin_installation_uuid' );
