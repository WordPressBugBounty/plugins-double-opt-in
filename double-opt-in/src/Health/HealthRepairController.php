<?php
/**
 * One-click repairs for the states the health checks report.
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
 * Handles the two repairs an operator can trigger from a health check:
 * deactivating the legacy Pro monolith, and clearing bundle-pro's burned
 * one-shot markers.
 *
 * ## admin_post, not REST
 *
 * Both repairs exist for sites that are already in trouble. The React admin
 * may not be reachable there — and the WordPress Site Health screen, which
 * is where a check's action link is rendered, is a plain admin page. A
 * nonce-protected `admin_post` handler works in both places and needs no
 * JavaScript.
 *
 * ## Deactivate, never delete
 *
 * Deactivating is enough to clear the fatal: the redeclare only happens
 * while both plugins load. The folder may stay on disk.
 *
 * Deleting it from here would be actively harmful. `delete_plugins()` runs
 * the target's `uninstall.php`, and the monolith's routine drops
 * `{prefix}f12_cf7_doubleoptin_optout` — the very table addon-opt-out uses.
 * A customer lost their opt-out records to exactly that on 2026-09-09, via
 * the WordPress plugin delete button. The core must not offer a second way
 * to do it. Removing the folder stays a manual step, and the check says so.
 *
 * `deactivate_plugins( ..., true )` passes `$silent = true` so the old
 * plugin's deactivation hook does not run — that code is the half of the
 * plugin we are trying not to execute.
 */
final class HealthRepairController {

	public const ACTION_DEACTIVATE_LEGACY = 'f12_doi_repair_legacy_pro';
	public const ACTION_RESET_MARKERS     = 'f12_doi_repair_pro_markers';

	/**
	 * Query arg carrying the outcome back to the screen we came from.
	 */
	public const RESULT_ARG = 'f12_doi_repair';

	public function register(): void {
		add_action( 'admin_post_' . self::ACTION_DEACTIVATE_LEGACY, array( $this, 'handleDeactivateLegacy' ) );
		add_action( 'admin_post_' . self::ACTION_RESET_MARKERS, array( $this, 'handleResetMarkers' ) );
		add_action( 'admin_notices', array( $this, 'renderResultNotice' ) );
	}

	/**
	 * Nonce-protected URL for one of the repair actions.
	 */
	public static function repairUrl( string $action ): string {
		$url = add_query_arg( 'action', $action, admin_url( 'admin-post.php' ) );

		return wp_nonce_url( $url, $action );
	}

	/**
	 * Request handler: deactivate, then report back.
	 */
	public function handleDeactivateLegacy(): void {
		$this->assertAllowed( self::ACTION_DEACTIVATE_LEGACY );

		$deactivated = $this->deactivateLegacy();

		$this->finish( empty( $deactivated ) ? 'nothing' : 'deactivated' );
	}

	/**
	 * Request handler: clear the markers, then report back.
	 */
	public function handleResetMarkers(): void {
		$this->assertAllowed( self::ACTION_RESET_MARKERS );

		$this->resetMarkers();

		$this->finish( 'markers-reset' );
	}

	/**
	 * Deactivate every legacy monolith that is currently active.
	 *
	 * Separate from the request handler so the repair itself can be
	 * exercised without a nonce, a capability and a redirect that would
	 * end the process.
	 *
	 * @return array<int,string> The plugin files that were deactivated.
	 */
	public function deactivateLegacy(): array {
		$active = LegacyProEnvironment::activeLegacyInstallations();

		if ( empty( $active ) ) {
			return array();
		}

		if ( ! function_exists( 'deactivate_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$files = array();
		foreach ( $active as $entry ) {
			$files[] = $entry['file'];
		}

		// $silent = true: do not run the old plugin's deactivation hook.
		deactivate_plugins( $files, true );

		LegacyMonolithDetector::flushCache();

		return $files;
	}

	/**
	 * Clear the one-shot markers so migration and addon install can run
	 * again. Leaves the licence key and every form setting untouched.
	 */
	public function resetMarkers(): void {
		foreach ( LegacyProEnvironment::ONE_SHOT_MARKERS as $marker ) {
			delete_option( $marker );

			if ( function_exists( 'delete_site_option' ) ) {
				delete_site_option( $marker );
			}
		}

		foreach ( LegacyProEnvironment::STALE_TRANSIENTS as $transient ) {
			delete_transient( $transient );

			if ( function_exists( 'delete_site_transient' ) ) {
				delete_site_transient( $transient );
			}
		}
	}

	/**
	 * Capability + nonce, in that order, before anything is touched.
	 */
	private function assertAllowed( string $action ): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to run this repair.', 'double-opt-in' ) );
		}

		check_admin_referer( $action );
	}

	/**
	 * Back to where the operator came from, carrying the outcome.
	 */
	private function finish( string $result ): void {
		$target = wp_get_referer();

		if ( ! is_string( $target ) || $target === '' ) {
			$target = admin_url( 'site-health.php' );
		}

		wp_safe_redirect( add_query_arg( self::RESULT_ARG, $result, $target ) );
		exit;
	}

	/**
	 * Tell the operator what happened. Deliberately dismissible — unlike
	 * the problem notices, this one is a receipt, not a warning.
	 */
	public function renderResultNotice(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$result = isset( $_GET[ self::RESULT_ARG ] ) ? sanitize_key( wp_unslash( $_GET[ self::RESULT_ARG ] ) ) : '';

		if ( $result === '' ) {
			return;
		}

		$messages = array(
			'deactivated'   => __( 'The old Double Opt-In Pro plugin has been deactivated. You can now activate Double Opt-In Pro 4 and install the modules you need. To remove the old plugin for good, delete its folder over FTP — do not use the WordPress delete button, which would drop your opt-out table.', 'double-opt-in' ),
			'markers-reset' => __( 'The Pro setup markers have been cleared. Licence migration and module installation can run again. Your licence key was not touched.', 'double-opt-in' ),
			'nothing'       => __( 'Nothing to repair — no active copy of the old Double Opt-In Pro plugin was found.', 'double-opt-in' ),
		);

		if ( ! isset( $messages[ $result ] ) ) {
			return;
		}

		printf(
			'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
			esc_html( $messages[ $result ] )
		);
	}
}
