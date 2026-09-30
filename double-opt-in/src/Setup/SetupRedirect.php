<?php
/**
 * Send a new user to the setup wizard once, right after activation.
 *
 * WordPress.org's guideline 11 allows this only within limits: once, never on
 * bulk or network activation, never in a way that traps the user. The rules
 * live in shouldRedirect(), a pure function, so each of them is testable.
 *
 * Sites that already use Double Opt-In (any form with it switched on) are not
 * redirected, and neither is a site where the wizard was finished or skipped.
 *
 * @package Forge12\DoubleOptIn\Setup
 * @since   5.7.0
 */

declare( strict_types=1 );

namespace Forge12\DoubleOptIn\Setup;

use Forge12\DoubleOptIn\Integration\FormIntegrationRegistry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SetupRedirect {

	public const TRANSIENT = 'f12_doi_setup_redirect';

	public const TARGET = 'admin.php?page=f12-doi-admin#/setup';

	/**
	 * Called from the activation hook.
	 */
	public static function onActivation( bool $networkWide, ?bool $cli = null ): void {
		$cli = $cli ?? ( defined( 'WP_CLI' ) && WP_CLI );
		// A CLI activation has no browser to redirect; the transient would
		// ambush whoever opens wp-admin next (provisioning scripts, E2E runs).
		if ( $networkWide || $cli ) {
			return;
		}
		set_transient( self::TRANSIENT, 1, 60 );
	}

	public static function register(): void {
		add_action( 'admin_init', array( self::class, 'maybeRedirect' ), 5 );
	}

	/**
	 * @param array{
	 *     transient: bool,
	 *     bulk: bool,
	 *     network: bool,
	 *     ajax: bool,
	 *     rest: bool,
	 *     cli: bool,
	 *     canManage: bool,
	 *     status: ?string,
	 *     hasActiveForm: bool
	 * } $context
	 */
	public static function shouldRedirect( array $context ): bool {
		if ( empty( $context['transient'] ) ) {
			return false;
		}
		if ( ! empty( $context['bulk'] ) || ! empty( $context['network'] ) ) {
			return false;
		}
		if ( ! empty( $context['ajax'] ) || ! empty( $context['rest'] ) || ! empty( $context['cli'] ) ) {
			return false;
		}
		if ( empty( $context['canManage'] ) ) {
			return false;
		}
		$status = $context['status'] ?? null;
		if ( $status === SetupState::DONE || $status === SetupState::SKIPPED ) {
			return false;
		}
		// No wizard state yet: only a site without any configured form counts as new.
		if ( $status === null && ! empty( $context['hasActiveForm'] ) ) {
			return false;
		}

		/**
		 * Filter whether to open the setup wizard after activation.
		 *
		 * @param bool  $redirect Whether to redirect.
		 * @param array $context  The facts the decision was based on.
		 *
		 * @since 5.7.0
		 */
		return (bool) apply_filters( 'f12_doi_setup_should_redirect', true, $context );
	}

	public static function maybeRedirect(): void {
		if ( ! get_transient( self::TRANSIENT ) ) {
			return;
		}
		// Whatever happens next, the redirect is spent.
		delete_transient( self::TRANSIENT );

		$state   = new SetupState();
		$context = array(
			'transient'     => true,
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only check of WordPress' own bulk-activation marker.
			'bulk'          => isset( $_GET['activate-multi'] ),
			'network'       => is_network_admin(),
			'ajax'          => wp_doing_ajax(),
			'rest'          => defined( 'REST_REQUEST' ) && REST_REQUEST,
			'cli'           => defined( 'WP_CLI' ) && WP_CLI,
			'canManage'     => current_user_can( 'manage_options' ),
			'status'        => $state->exists() ? $state->status() : null,
			'hasActiveForm' => false,
		);
		if ( $context['status'] === null ) {
			$context['hasActiveForm'] = self::hasActiveForm();
		}

		if ( ! self::shouldRedirect( $context ) ) {
			return;
		}

		$state->initialize();
		wp_safe_redirect( admin_url( self::TARGET ) );
		exit;
	}

	/**
	 * Whether any form of any available integration has Double Opt-In on.
	 */
	private static function hasActiveForm(): bool {
		if ( ! class_exists( FormIntegrationRegistry::class ) ) {
			return false;
		}
		foreach ( FormIntegrationRegistry::getInstance()->getAvailable() as $integration ) {
			foreach ( $integration->getForms() as $form ) {
				if ( ! empty( $form['enabled'] ) ) {
					return true;
				}
			}
		}
		return false;
	}
}
