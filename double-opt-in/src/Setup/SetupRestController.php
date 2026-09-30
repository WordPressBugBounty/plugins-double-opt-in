<?php
/**
 * REST routes of the setup wizard.
 *
 * `GET  f12-doi/v1/setup`            prefilled values, forms, detected plugins
 * `POST f12-doi/v1/setup/step`       save one step {step, data}
 * `POST f12-doi/v1/setup/test-mail`  send the configured mail to the current user
 * `POST f12-doi/v1/setup/finish`     write form settings, mark the wizard done
 * `POST f12-doi/v1/setup/skip`       leave the wizard for later
 * `POST f12-doi/v1/setup/restart`    start again with the saved values
 *
 * All require `manage_options`. CSRF protection is WordPress' REST cookie
 * authentication: without a valid `X-WP-Nonce` the request runs as user 0
 * and fails the capability check.
 *
 * @package Forge12\DoubleOptIn\Setup
 * @since   5.7.0
 */

declare( strict_types=1 );

namespace Forge12\DoubleOptIn\Setup;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SetupRestController {

	public const API_NAMESPACE = 'f12-doi/v1';

	/** @var SetupService */
	private $service;

	/** @var SetupState */
	private $state;

	public function __construct( SetupService $service, SetupState $state ) {
		$this->service = $service;
		$this->state   = $state;
	}

	public function init(): void {
		add_action( 'rest_api_init', array( $this, 'registerRoutes' ) );
	}

	public function checkPermission(): bool {
		return current_user_can( 'manage_options' );
	}

	public function registerRoutes(): void {
		register_rest_route(
			self::API_NAMESPACE,
			'/setup',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'getOverview' ),
				'permission_callback' => array( $this, 'checkPermission' ),
			)
		);
		register_rest_route(
			self::API_NAMESPACE,
			'/setup/step',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'saveStep' ),
				'permission_callback' => array( $this, 'checkPermission' ),
				'args'                => array(
					'step' => array(
						'required'          => true,
						'validate_callback' => static function ( $value ) {
							return is_string( $value ) && array_key_exists( $value, SetupService::STEPS );
						},
					),
				),
			)
		);
		foreach ( array(
			'test-mail' => 'sendTestMail',
			'finish'    => 'finish',
			'skip'      => 'skip',
			'restart'   => 'restart',
		) as $route => $method ) {
			register_rest_route(
				self::API_NAMESPACE,
				'/setup/' . $route,
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, $method ),
					'permission_callback' => array( $this, 'checkPermission' ),
				)
			);
		}
	}

	public function getOverview(): \WP_REST_Response {
		return $this->respond(
			array(
				'ok'     => true,
				'errors' => array(),
				'data'   => $this->service->overview(),
			)
		);
	}

	public function saveStep( \WP_REST_Request $request ): \WP_REST_Response {
		$data = $request->get_param( 'data' );
		return $this->respond(
			$this->service->saveStep(
				(string) $request->get_param( 'step' ),
				is_array( $data ) ? $data : array()
			)
		);
	}

	public function sendTestMail(): \WP_REST_Response {
		return $this->respond( $this->service->sendTestMail() );
	}

	public function finish(): \WP_REST_Response {
		return $this->respond( $this->service->finish() );
	}

	public function skip(): \WP_REST_Response {
		$this->state->skip();
		return $this->respond(
			array(
				'ok'     => true,
				'errors' => array(),
				'data'   => array( 'status' => $this->state->status() ),
			)
		);
	}

	public function restart(): \WP_REST_Response {
		$this->state->restart();
		return $this->respond(
			array(
				'ok'     => true,
				'errors' => array(),
				'data'   => array( 'status' => $this->state->status() ),
			)
		);
	}

	/**
	 * @param array{ok: bool, errors: array<string, string>, data?: array<string, mixed>} $result
	 */
	private function respond( array $result ): \WP_REST_Response {
		if ( ! empty( $result['ok'] ) ) {
			return new \WP_REST_Response(
				array(
					'success' => true,
					'data'    => $result['data'] ?? array(),
				),
				200
			);
		}

		$errors  = $result['errors'] ?? array();
		$message = $errors !== array() ? (string) reset( $errors ) : __( 'The setup step could not be saved.', 'double-opt-in' );
		return new \WP_REST_Response(
			array(
				'success' => false,
				'message' => $message,
				'code'    => 'SETUP_INVALID',
				'errors'  => $errors,
			),
			422
		);
	}
}
