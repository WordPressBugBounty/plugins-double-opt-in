<?php
/**
 * Health check: no leftover Pro setup markers block a fresh install.
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
 * Explains the second half of the "I reinstalled everything and nothing
 * changed" ticket.
 *
 * bundle-pro records that it has migrated the licence and installed the
 * bundled addons in one-shot options. It ships no `uninstall.php`, so
 * deleting the plugin leaves those options behind — and a fresh install
 * then finds them already set and skips both steps. The customer ends up
 * with a Pro plugin that has no licence and no modules, and the SPA's
 * install button answering `rest_no_route`, because the route lives in the
 * bundle that never finished starting.
 *
 * Only flagged while bundle-pro is NOT active: with it running, the markers
 * describe a completed setup and are exactly what they should be. The
 * narrow rule is on purpose — a repair offered to someone whose site is
 * fine is a repair that gets clicked for the wrong reason.
 */
final class StaleProMarkersCheck implements HealthCheckInterface {

	public function getId(): string {
		return 'f12_doi_pro_setup_markers';
	}

	public function getLabel(): string {
		return __( 'Pro setup markers', 'double-opt-in' );
	}

	public function getPackage(): string {
		return 'core';
	}

	public function run(): HealthCheckResult {
		$markers = LegacyProEnvironment::burnedMarkers();

		if ( empty( $markers ) ) {
			return new HealthCheckResult(
				HealthCheckResult::STATUS_GOOD,
				__( 'No leftover Pro setup markers', 'double-opt-in' ),
				__( 'Nothing in the database would block a Pro installation from setting itself up.', 'double-opt-in' ),
				'none'
			);
		}

		if ( LegacyProEnvironment::isSuccessorActive() ) {
			return new HealthCheckResult(
				HealthCheckResult::STATUS_GOOD,
				__( 'Pro setup markers belong to the running installation', 'double-opt-in' ),
				__( 'Double Opt-In Pro is active and has completed its setup. The stored markers are expected.', 'double-opt-in' ),
				'owned:' . count( $markers )
			);
		}

		return new HealthCheckResult(
			HealthCheckResult::STATUS_RECOMMENDED,
			__( 'Leftover Pro setup markers will block a new installation', 'double-opt-in' ),
			__( 'Double Opt-In Pro is not active, but this site still stores the markers saying its licence migration and module installation have already run. A fresh install would find them and skip both steps — no licence, no modules, and the "Install" button on the Add-ons screen failing with a routing error. Clearing them lets the setup run again. Your licence key and all form settings stay untouched.', 'double-opt-in' ),
			'stale:' . count( $markers ),
			__( 'Clear the setup markers', 'double-opt-in' ),
			HealthRepairController::repairUrl( HealthRepairController::ACTION_RESET_MARKERS )
		);
	}
}
