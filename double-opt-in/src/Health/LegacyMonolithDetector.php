<?php
/**
 * Finds installations of the pre-4.x Pro monolith.
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
 * Locates the legacy "Double Opt-In ... Pro" monolith (<= 3.7) on disk.
 *
 * The monolith declares the same class names in the same namespace as the
 * compat classes of addon-elementor and addon-opt-out. Loading both is a
 * fatal `Cannot redeclare class`, which takes wp-admin with it — the state
 * behind every "critical error after the Pro update" ticket.
 *
 * ## Why the folder name is not the criterion
 *
 * It cannot be. Every candidate name has been used by two different
 * products over the years:
 *
 * | Folder                | used by                                     |
 * |-----------------------|---------------------------------------------|
 * | `double-opt-in-pro`   | Pro 3.0.0 - 3.2.0 **and** bundle-pro 4.x    |
 * | `f12-cf7-doubleoptin` | free <= 2.x **and** Pro 2.x, 3.6, 3.7       |
 * | `double-opt-in`       | free >= 3.0.0, including 5.x                |
 *
 * The directory layout is no better: 3.1 keeps licensing in
 * `core/License.class.php`, 3.7 moved it to `vendor/`. And the main file
 * name is shared outright — the free plugin still ships
 * `CF7DoubleOptIn.class.php` today, so anything keying on that alone would
 * happily flag the free plugin the operator is currently running.
 *
 * Two signals do separate them, and both are needed:
 *
 *  1. `Text Domain: double-opt-in-pro` in a `CF7DoubleOptIn.class.php`.
 *     Reliable from 3.0.0 on. bundle-pro 4.x carries the same text domain
 *     but lives in `double-opt-in-pro.php`, and the version gate keeps it
 *     out regardless.
 *  2. A sibling `ui/UILicense.class.php`. Needed because the header alone
 *     misses the whole 2.x line: Pro 2.3.3 through 2.4.0 shipped
 *     `Text Domain: double-opt-in` — the free plugin's domain — while
 *     carrying the Pro licensing code. Verified against 2.3.3, 2.3.7,
 *     2.4.0, 3.0.0, 3.2.0 and 3.7.1; the free plugin has never shipped
 *     that file in any version.
 */
final class LegacyMonolithDetector {

	/**
	 * The monolith's main file, in every version that ever shipped.
	 */
	private const MAIN_FILE = 'CF7DoubleOptIn.class.php';

	/**
	 * The header value that tells the monolith from the free plugin —
	 * from 3.0.0 on. See LICENSE_UI_FILE for why that is not enough.
	 */
	private const PRO_TEXT_DOMAIN = 'double-opt-in-pro';

	/**
	 * Licensing screen, relative to the plugin folder. Shipped by every
	 * Pro monolith from 2.3.3 to 3.7.1, by no free version ever.
	 */
	private const LICENSE_UI_FILE = 'ui/UILicense.class.php';

	/**
	 * First major version that is NOT the monolith. bundle-pro starts at 4.
	 */
	private const SUCCESSOR_MAJOR = 4;

	/**
	 * Per-request memo, keyed by scanned directory.
	 *
	 * Health checks run on every admin page load, so the directory scan
	 * must not repeat within a request. It is cheap either way: one
	 * `is_file()` per plugin folder, and a header read only for the one
	 * or two folders that actually carry the file.
	 *
	 * @var array<string,array<int,array<string,string>>>
	 */
	private static $memo = array();

	/**
	 * All legacy monolith installations found under $pluginDir.
	 *
	 * @param string $pluginDir Absolute path to the plugins directory.
	 *                          Injectable so this is testable without
	 *                          WordPress.
	 *
	 * @return array<int,array<string,string>> One entry per find, with
	 *                                         `folder`, `file` (the
	 *                                         plugin_basename-style
	 *                                         `folder/main.php`), `path`,
	 *                                         `name` and `version`.
	 */
	public static function scan( string $pluginDir = '' ): array {
		if ( $pluginDir === '' ) {
			$pluginDir = defined( 'WP_PLUGIN_DIR' ) ? WP_PLUGIN_DIR : '';
		}
		if ( $pluginDir === '' || ! is_dir( $pluginDir ) ) {
			return array();
		}

		$pluginDir = rtrim( $pluginDir, '/\\' );

		if ( isset( self::$memo[ $pluginDir ] ) ) {
			return self::$memo[ $pluginDir ];
		}

		$found = array();

		foreach ( (array) glob( $pluginDir . '/*', GLOB_ONLYDIR ) as $folder ) {
			if ( ! is_string( $folder ) ) {
				continue;
			}

			$main = $folder . '/' . self::MAIN_FILE;
			if ( ! is_file( $main ) ) {
				continue;
			}

			$header       = self::readHeader( $main );
			$hasLicenseUi = is_file( $folder . '/' . self::LICENSE_UI_FILE );

			if ( ! self::isLegacyMonolith( $header, $hasLicenseUi ) ) {
				continue;
			}

			$found[] = array(
				'folder'  => basename( $folder ),
				'file'    => basename( $folder ) . '/' . self::MAIN_FILE,
				'path'    => $folder,
				'name'    => $header['Plugin Name'] ?? '',
				'version' => $header['Version'] ?? '',
			);
		}

		self::$memo[ $pluginDir ] = $found;

		return $found;
	}

	/**
	 * Drop the memo. Needed after a repair inside the same request —
	 * otherwise the check keeps reporting the pre-repair state.
	 */
	public static function flushCache(): void {
		self::$memo = array();
	}

	/**
	 * Does this folder hold the pre-4.x Pro monolith?
	 *
	 * @param array<string,string> $header       Parsed plugin header.
	 * @param bool                 $hasLicenseUi Whether the folder ships
	 *                                           the Pro licensing screen.
	 */
	private static function isLegacyMonolith( array $header, bool $hasLicenseUi ): bool {
		$isProDomain = ( $header['Text Domain'] ?? '' ) === self::PRO_TEXT_DOMAIN;

		if ( ! $isProDomain && ! $hasLicenseUi ) {
			return false;
		}

		$version = $header['Version'] ?? '';
		if ( $version === '' ) {
			// Either signal without a readable version still means the
			// monolith — no other product ever shipped that combination.
			// Treated as a find rather than ignored, because missing the
			// collision is worse than naming one folder for nothing.
			return true;
		}

		return (int) $version < self::SUCCESSOR_MAJOR;
	}

	/**
	 * Read plugin headers without executing the file.
	 *
	 * Deliberately not `get_file_data()`: this runs while another plugin
	 * may already have fataled the request, it is called from unit tests
	 * with no WordPress loaded, and `get_file_data()` passes its result
	 * through `extra_plugin_headers`-style filters that a broken third
	 * party could interfere with. The regex is the same one WordPress
	 * uses, and the read is capped at the header block.
	 *
	 * @return array<string,string>
	 */
	private static function readHeader( string $file ): array {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$handle = @fopen( $file, 'r' );
		if ( ! $handle ) {
			return array();
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread
		$head = (string) fread( $handle, 8192 );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		fclose( $handle );

		$out = array();

		foreach ( array( 'Plugin Name', 'Version', 'Text Domain' ) as $field ) {
			$matches = array();
			if ( preg_match( '/^[ \t\/*#@]*' . preg_quote( $field, '/' ) . ':(.*)$/mi', $head, $matches ) ) {
				$out[ $field ] = trim( $matches[1] );
			}
		}

		return $out;
	}
}
