<?php
/**
 * PSR-4 Autoloader for Double Opt-In Plugin
 *
 * @package Forge12\DoubleOptIn
 * @since   4.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

spl_autoload_register( function ( $class ) {
	// Project namespace prefixes and their base directories
	$namespaces = [
		'Forge12\\DoubleOptIn\\' => __DIR__ . '/src/',
	];

	foreach ( $namespaces as $prefix => $base_dir ) {
		// Check if the class uses this namespace prefix
		$len = strlen( $prefix );
		if ( strncmp( $prefix, $class, $len ) !== 0 ) {
			continue;
		}

		// Get the relative class name
		$relative_class = substr( $class, $len );

		// Replace namespace separators with directory separators
		$file = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';

		// If the file exists, require it
		if ( file_exists( $file ) ) {
			require $file;
			return;
		}
	}
} );
