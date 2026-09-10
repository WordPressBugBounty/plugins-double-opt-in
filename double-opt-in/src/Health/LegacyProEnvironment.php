<?php
/**
 * Reads the Pro-related state of this installation.
 *
 * @package Forge12\DoubleOptIn\Health
 * @since   5.5.0
 */

declare( strict_types=1 );

namespace Forge12\DoubleOptIn\Health;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One place to ask "what does the Pro side of this site look like right
 * now?" — shared by {@see LegacyMonolithCheck}, {@see StaleProMarkersCheck}
 * and {@see HealthRepairController} so the three cannot drift apart on what
 * counts as active or stale.
 *
 * Reads `active_plugins` (and the network equivalent) directly instead of
 * calling `is_plugin_active()`. That function lives in
 * `wp-admin/includes/plugin.php`, which is not loaded on every request this
 * code runs in, and pulling it in just to answer a boolean would be the
 * kind of side effect a health check must not have.
 */
final class LegacyProEnvironment {

	/**
	 * bundle-pro's main file, relative to the plugins directory.
	 */
	public const SUCCESSOR_FILE = 'double-opt-in-pro/double-opt-in-pro.php';

	/**
	 * One-shot markers written by bundle-pro's migration and addon
	 * installer.
	 *
	 * bundle-pro ships no `uninstall.php`, so these survive deleting and
	 * reinstalling the plugin. While they stand, neither the licence
	 * migration nor the addon auto-install ever runs again — which is why
	 * "I reinstalled everything" reliably changes nothing for the customers
	 * who end up in this state.
	 *
	 * The bundle licence key is deliberately NOT in this list. Clearing it
	 * would be self-defeating: `MigrationFromMonolith` reads it, and a
	 * self-service repair that logs the customer out of their licence is a
	 * worse ticket than the one it closes. `tools/support/doi-reset.php`
	 * does clear it, but that is a full reset run by support, with a
	 * backup, not a button in the admin.
	 *
	 * @var array<int,string>
	 */
	public const ONE_SHOT_MARKERS = array(
		'f12_doi_pro_migration_complete',
		'f12_doi_pro_migration_autoinstall_pending',
		'f12_doi_addon_installer_state',
		'f12_doi_addon_auto_install_done',
	);

	/**
	 * Cached validation state. Harmless to drop — it is re-fetched.
	 *
	 * @var array<int,string>
	 */
	public const STALE_TRANSIENTS = array(
		'f12_doi_bundle_license_validation',
		'f12_doi_bundle_site_registered',
		'f12_doi_pro_migration_notice_pending',
	);

	/**
	 * Every legacy monolith found on disk.
	 *
	 * @return array<int,array<string,string>>
	 */
	public static function legacyInstallations(): array {
		return LegacyMonolithDetector::scan();
	}

	/**
	 * The subset of those that WordPress is actually loading.
	 *
	 * @return array<int,array<string,string>>
	 */
	public static function activeLegacyInstallations(): array {
		$active = array();

		foreach ( self::legacyInstallations() as $entry ) {
			if ( self::isPluginActive( $entry['file'] ) ) {
				$active[] = $entry;
			}
		}

		return $active;
	}

	/**
	 * Is bundle-pro 4.x currently active?
	 */
	public static function isSuccessorActive(): bool {
		return self::isPluginActive( self::SUCCESSOR_FILE );
	}

	/**
	 * @param string $file `folder/main-file.php`, as WordPress stores it.
	 */
	public static function isPluginActive( string $file ): bool {
		$active = get_option( 'active_plugins', array() );

		if ( is_array( $active ) && in_array( $file, $active, true ) ) {
			return true;
		}

		if ( ! function_exists( 'get_site_option' ) ) {
			return false;
		}

		$network = get_site_option( 'active_sitewide_plugins', array() );

		return is_array( $network ) && isset( $network[ $file ] );
	}

	/**
	 * Which one-shot markers are currently set.
	 *
	 * @return array<int,string>
	 */
	public static function burnedMarkers(): array {
		$found = array();

		foreach ( self::ONE_SHOT_MARKERS as $marker ) {
			if ( get_option( $marker, null ) !== null ) {
				$found[] = $marker;
			}
		}

		return $found;
	}
}
