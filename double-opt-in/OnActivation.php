<?php

namespace forge12\contactform7\CF7DoubleOptIn;

use Forge12\Shared\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Create the table to store the opt ins.
 *
 * @return void
 */
function createTableOptIn() {
	global $wpdb;

	$logger = Logger::getInstance();

	$tableName   = 'f12_cf7_doubleoptin';
	$wpTableName = $wpdb->prefix . $tableName;

	$logger->info( 'Creating database table', [
		'plugin'     => 'double-opt-in',
		'table_name' => $wpTableName,
		'class'      => __CLASS__ ?? null,
		'method'     => __FUNCTION__,
	] );

	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

	$sql = "CREATE TABLE " . $wpTableName . " (
        id int(11) NOT NULL auto_increment, 
        cf_form_id int(11) NOT NULL, 
        doubleoptin int(2), 
        content LONGTEXT, 
        files LONGTEXT,
        hash VARCHAR(255),
        ipaddr_register varchar(255) NOT NULL DEFAULT '',
        ipaddr_confirmation varchar(255) NOT NULL DEFAULT '',
        ipaddr_optout varchar(255) NOT NULL DEFAULT '',
        createtime varchar(255) DEFAULT '', 
        updatetime varchar(255) DEFAULT '',
        optouttime varchar(255) DEFAULT '',
        category int(11),
        email varchar(255),
        form LONGTEXT,
        mail_optin LONGTEXT,
        consent_text TEXT,
        reminder_sent_at varchar(255) DEFAULT '',
        mail_reminder LONGTEXT,
        PRIMARY KEY  (id)
    )";

	dbDelta( $sql );

	$logger->info( 'Database table created or updated', [
		'plugin'     => 'double-opt-in',
		'table_name' => $wpTableName,
		'class'      => __CLASS__ ?? null,
		'method'     => __FUNCTION__,
	] );
}


/**
 * Create the table for the categories
 *
 * @return void
 */
function createTableOptinCategories() {
	global $wpdb;

	$logger = Logger::getInstance();

	$tableName   = 'f12_cf7_doubleoptin_categories';
	$wpTableName = $wpdb->prefix . $tableName;

	$logger->info( 'Creating database table', [
		'plugin'     => 'double-opt-in',
		'table_name' => $wpTableName,
		'class'      => __CLASS__ ?? null,
		'method'     => __FUNCTION__,
	] );

	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

	$sql = "CREATE TABLE " . $wpTableName . " (
        id int(11) NOT NULL auto_increment, 
        name varchar(255),
        createtime varchar(255) DEFAULT '', 
        updatetime varchar(255) DEFAULT '',
        PRIMARY KEY  (id)
    )";

	dbDelta( $sql );

	$logger->info( 'Database table created or updated', [
		'plugin'     => 'double-opt-in',
		'table_name' => $wpTableName,
		'class'      => __CLASS__ ?? null,
		'method'     => __FUNCTION__,
	] );
}


/**
 * On Activation create the custom table to store the required information
 * for the double opt in system.
 */
function onActivation( $network_wide = false ) {
	$logger = Logger::getInstance();

	$logger->info( 'Plugin activation started', [
		'plugin'       => 'double-opt-in',
		'network_wide' => $network_wide,
		'class'        => __CLASS__ ?? null,
		'method'       => __FUNCTION__,
	] );

	if ( is_multisite() && $network_wide ) {
		$sites = get_sites( [ 'fields' => 'ids', 'number' => 0 ] );
		foreach ( $sites as $site_id ) {
			switch_to_blog( $site_id );
			createTableOptin();
			createTableOptinCategories();
			restore_current_blog();
		}
	} else {
		createTableOptin();
		createTableOptinCategories();
	}

	$logger->info( 'Plugin activation completed', [
		'plugin'       => 'double-opt-in',
		'network_wide' => $network_wide,
		'class'        => __CLASS__ ?? null,
		'method'       => __FUNCTION__,
	] );
}

register_activation_hook( plugin_dir_path(__FILE__).'CF7DoubleOptIn.class.php', '\forge12\contactform7\CF7DoubleOptIn\onActivation' );

add_action( 'wp_initialize_site', function ( $new_site ) {
	if ( ! is_plugin_active_for_network(
		plugin_basename( __DIR__ . '/CF7DoubleOptIn.class.php' )
	) ) {
		return;
	}
	switch_to_blog( $new_site->blog_id );
	createTableOptin();
	createTableOptinCategories();
	restore_current_blog();
}, 900 );