<?php
/**
 * REST routes for follow-up status and manual retry.
 *
 * @package Forge12\DoubleOptIn\Admin
 * @since   5.6.0
 */

declare( strict_types=1 );

namespace Forge12\DoubleOptIn\Admin;

use Forge12\DoubleOptIn\FollowUp\FollowUpAttempt;
use Forge12\DoubleOptIn\FollowUp\FollowUpCoordinator;
use forge12\contactform7\CF7DoubleOptIn\OptIn;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * `GET  f12-doi/v1/optins/{id}/follow-ups`       status + per-action rows
 * `POST f12-doi/v1/optins/{id}/follow-ups/retry` run failed actions again
 *
 * Both require `manage_options`. CSRF protection is WordPress' REST
 * cookie authentication: without a valid `X-WP-Nonce` (`wp_rest`) the
 * request runs as user 0 and fails the capability check.
 */
class FollowUpRestController {

	public const API_NAMESPACE = 'f12-doi/v1';

	/** @var FollowUpCoordinator */
	private $coordinator;

	/** @var callable(int):?OptIn */
	private $loader;

	/**
	 * @param callable|null $loader Loads an opt-in by id (test seam).
	 */
	public function __construct( FollowUpCoordinator $coordinator, ?callable $loader = null ) {
		$this->coordinator = $coordinator;
		$this->loader      = $loader ?? static function ( int $id ): ?OptIn {
			return OptIn::get_by_id( $id );
		};
	}

	public function init(): void {
		add_action( 'rest_api_init', array( $this, 'registerRoutes' ) );
	}

	public function checkPermission(): bool {
		return current_user_can( 'manage_options' );
	}

	public function registerRoutes(): void {
		$idArg = array(
			'id' => array(
				'validate_callback' => static function ( $p ) {
					return is_numeric( $p );
				},
			),
		);

		register_rest_route(
			self::API_NAMESPACE,
			'/optins/(?P<id>[\d]+)/follow-ups',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'getStatus' ),
				'permission_callback' => array( $this, 'checkPermission' ),
				'args'                => $idArg,
			)
		);

		register_rest_route(
			self::API_NAMESPACE,
			'/optins/(?P<id>[\d]+)/follow-ups/retry',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'retry' ),
				'permission_callback' => array( $this, 'checkPermission' ),
				'args'                => array_merge(
					$idArg,
					array(
						'include_unknown' => array(
							'type'    => 'boolean',
							'default' => false,
						),
						'action_ids'      => array(
							'type'    => 'array',
							'items'   => array( 'type' => 'string' ),
							'default' => array(),
						),
					)
				),
			)
		);
	}

	/**
	 * @param \WP_REST_Request $request
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function getStatus( $request ) {
		$optIn = ( $this->loader )( (int) $request->get_param( 'id' ) );
		if ( ! $optIn ) {
			return new \WP_Error( 'not_found', __( 'Opt-In not found.', 'double-opt-in' ), array( 'status' => 404 ) );
		}

		return $this->respond( $optIn );
	}

	/**
	 * @param \WP_REST_Request $request
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function retry( $request ) {
		$optIn = ( $this->loader )( (int) $request->get_param( 'id' ) );
		if ( ! $optIn ) {
			return new \WP_Error( 'not_found', __( 'Opt-In not found.', 'double-opt-in' ), array( 'status' => 404 ) );
		}

		if ( ! $optIn->is_confirmed() ) {
			return new \WP_Error( 'not_confirmed', __( 'Follow-up actions only run for confirmed opt-ins.', 'double-opt-in' ), array( 'status' => 409 ) );
		}

		if ( $this->coordinator->adapterFor( $optIn ) === null ) {
			return new \WP_Error( 'integration_unavailable', __( 'The form integration of this opt-in is not active.', 'double-opt-in' ), array( 'status' => 409 ) );
		}

		$actionIds = array();
		foreach ( (array) $request->get_param( 'action_ids' ) as $actionId ) {
			// Same alphabet as FollowUpAction ids; sanitize_key() would
			// strip the ':' separator.
			$actionId = strtolower( (string) $actionId );
			if ( $actionId !== '' && strlen( $actionId ) <= 100 && ! preg_match( '/[^a-z0-9_:.\-]/', $actionId ) ) {
				$actionIds[] = $actionId;
			}
		}
		$includeUnknown = (bool) $request->get_param( 'include_unknown' );

		$options = array( 'include_unknown' => $includeUnknown );
		if ( ! empty( $actionIds ) ) {
			$options['action_ids'] = $actionIds;
		}

		// The coordinator writes the `follow_up.manual_retry` audit event.
		$this->coordinator->run( $optIn, FollowUpAttempt::TRIGGER_MANUAL, $options );

		return $this->respond( $optIn );
	}

	/**
	 * Same envelope as AdminRestController (`{success, data}`).
	 */
	private function respond( OptIn $optIn ): \WP_REST_Response {
		return new \WP_REST_Response(
			array(
				'success' => true,
				'data'    => $this->payload( $optIn ),
			),
			200
		);
	}

	/**
	 * Structural status only — no form values, recipients or tokens.
	 *
	 * @return array<string, mixed>
	 */
	public function payload( OptIn $optIn ): array {
		$status              = $this->coordinator->statusFor( $optIn->get_id(), $optIn->is_confirmed() );
		$status['optin_id']  = $optIn->get_id();
		$status['form_id']   = $optIn->get_cf_form_id();
		$status['confirmed'] = $optIn->is_confirmed();
		$status['managed']   = $this->coordinator->adapterFor( $optIn ) !== null;
		$status['versions']  = self::versions();
		return $status;
	}

	/**
	 * Plugin versions for the support diagnosis export.
	 *
	 * @return array<string, string>
	 */
	private static function versions(): array {
		$versions = array(
			'core'      => defined( 'FORGE12_OPTIN_VERSION' ) ? (string) FORGE12_OPTIN_VERSION : '',
			'core_api'  => defined( 'F12_DOI_CORE_API_VERSION' ) ? (string) F12_DOI_CORE_API_VERSION : '',
			'wordpress' => isset( $GLOBALS['wp_version'] ) ? (string) $GLOBALS['wp_version'] : '',
			'php'       => PHP_VERSION,
		);
		foreach ( array(
			'elementor'     => 'F12_DOI_ELEMENTOR_VERSION',
			'avada'         => 'F12_DOI_AVADA_VERSION',
			'wpforms'       => 'F12_DOI_WPFORMS_VERSION',
			'gravity_forms' => 'F12_DOI_GRAVITY_FORMS_VERSION',
		) as $key => $constant ) {
			if ( defined( $constant ) ) {
				$versions[ 'addon_' . $key ] = (string) constant( $constant );
			}
		}
		foreach ( array(
			'elementor_pro' => 'ELEMENTOR_PRO_VERSION',
			'cf7'           => 'WPCF7_VERSION',
			'wpforms'       => 'WPFORMS_VERSION',
		) as $key => $constant ) {
			if ( defined( $constant ) ) {
				$versions[ $key ] = (string) constant( $constant );
			}
		}
		if ( class_exists( 'GFForms' ) && isset( \GFForms::$version ) ) {
			$versions['gravityforms'] = (string) \GFForms::$version;
		}
		return $versions;
	}
}
