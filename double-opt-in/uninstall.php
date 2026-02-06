<?php

namespace forge12\contactform7\CF7DoubleOptIn;

use Forge12\Shared\Logger;

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	die;
}

require_once plugin_dir_path( __FILE__ ) . 'logger/logger.php';

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

drop_table_categories();
drop_table_optin();
