<?php
/**
 * Health check: the pre-4.x Pro monolith is not loaded alongside the addons.
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
 * Reports the state that produces "There has been a critical error on this
 * website" after a customer upgrades from Pro 3.x to Pro 4.x.
 *
 * The monolith and the addons declare the same class names in the same
 * namespace. With both active, whichever loads second used to be a fatal;
 * since Core 5.5.0 the compatibility loader skips the duplicate instead
 * ({@see \forge12\contactform7\CF7DoubleOptIn\Compatibility::registerComponents}).
 * That keeps the site up — but it also means one of the two copies is
 * silently not running, so the state still has to be fixed, and now there
 * is an admin left standing to fix it in.
 *
 * Severity is deliberately split:
 *
 *  - **active** → `critical`. Something is not running, and which half it
 *    is depends on load order. That is not a state anyone should stay in.
 *  - **on disk but deactivated** → `recommended`. Nothing is broken right
 *    now, but one click on "Activate" brings it all back.
 */
final class LegacyMonolithCheck implements HealthCheckInterface {

	public function getId(): string {
		return 'f12_doi_legacy_pro_monolith';
	}

	public function getLabel(): string {
		return __( 'Old Double Opt-In Pro plugin', 'double-opt-in' );
	}

	public function getPackage(): string {
		return 'core';
	}

	public function run(): HealthCheckResult {
		$installed = LegacyProEnvironment::legacyInstallations();

		if ( empty( $installed ) ) {
			return new HealthCheckResult(
				HealthCheckResult::STATUS_GOOD,
				__( 'No outdated Pro plugin is installed', 'double-opt-in' ),
				__( 'The pre-4.0 version of Double Opt-In Pro is not present on this site. Nothing to do.', 'double-opt-in' ),
				'none'
			);
		}

		$active = LegacyProEnvironment::activeLegacyInstallations();

		if ( ! empty( $active ) ) {
			return new HealthCheckResult(
				HealthCheckResult::STATUS_CRITICAL,
				__( 'An outdated Double Opt-In Pro plugin is active', 'double-opt-in' ),
				sprintf(
					/* translators: 1: plugin folder name, 2: version number. */
					__( 'The plugin folder "%1$s" contains Double Opt-In Pro %2$s. That version ships program parts under the same names as the current modules, so with both active one of the two is not running, and older releases fail with "There has been a critical error on this website". Deactivate it — your consent records are stored separately and are not affected.', 'double-opt-in' ),
					$this->describeFolders( $active ),
					$this->describeVersions( $active )
				),
				'active:' . $this->describeFolders( $active ),
				__( 'Deactivate the old plugin now', 'double-opt-in' ),
				HealthRepairController::repairUrl( HealthRepairController::ACTION_DEACTIVATE_LEGACY )
			);
		}

		return new HealthCheckResult(
			HealthCheckResult::STATUS_RECOMMENDED,
			__( 'An outdated Double Opt-In Pro plugin is still installed', 'double-opt-in' ),
			sprintf(
				/* translators: 1: plugin folder name, 2: version number. */
				__( 'The folder "%1$s" still holds Double Opt-In Pro %2$s. It is deactivated, so nothing is broken right now, but activating it again would collide with the current modules. Remove the folder over FTP or SSH. Important: do not delete it through the WordPress plugin screen — that version\'s uninstall routine drops the opt-out database table, and the records in it are gone for good.', 'double-opt-in' ),
				$this->describeFolders( $installed ),
				$this->describeVersions( $installed )
			),
			'inactive:' . $this->describeFolders( $installed )
		);
	}

	/**
	 * @param array<int,array<string,string>> $entries
	 */
	private function describeFolders( array $entries ): string {
		$folders = array();

		foreach ( $entries as $entry ) {
			$folders[] = $entry['folder'];
		}

		return implode( ', ', $folders );
	}

	/**
	 * @param array<int,array<string,string>> $entries
	 */
	private function describeVersions( array $entries ): string {
		$versions = array();

		foreach ( $entries as $entry ) {
			$versions[] = $entry['version'] !== '' ? $entry['version'] : __( 'unknown version', 'double-opt-in' );
		}

		return implode( ', ', $versions );
	}
}
