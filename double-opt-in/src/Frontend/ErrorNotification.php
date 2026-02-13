<?php
/**
 * Universal Error Notification System
 *
 * Provides a form-plugin-agnostic mechanism to display OptIn creation errors
 * to the user via an AJAX endpoint and frontend JavaScript notification.
 *
 * @package Forge12\DoubleOptIn\Frontend
 * @since   4.2.0
 */

namespace Forge12\DoubleOptIn\Frontend;

use Forge12\DoubleOptIn\Integration\OptInError;
use forge12\contactform7\CF7DoubleOptIn\IPHelper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ErrorNotification
 *
 * Stores OptIn errors in a short-lived transient keyed by client fingerprint
 * and exposes an AJAX endpoint for the frontend JS to retrieve them.
 *
 * Flow:
 *  1. createOptIn() fails → AbstractFormIntegration calls ErrorNotification::store()
 *  2. Form plugin shows its own response (success or generic error)
 *  3. Frontend JS detects form submission completed
 *  4. JS calls AJAX endpoint `doi_check_submission_error`
 *  5. Endpoint returns the error (if any) and deletes the transient
 *  6. JS displays a notification overlay
 */
class ErrorNotification {

	/**
	 * Transient prefix for error storage.
	 */
	private const TRANSIENT_PREFIX = 'doi_error_';

	/**
	 * How long the error transient lives (in seconds).
	 */
	private const TRANSIENT_TTL = 60;

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'wp_ajax_doi_check_submission_error', [ $this, 'handleAjax' ] );
		add_action( 'wp_ajax_nopriv_doi_check_submission_error', [ $this, 'handleAjax' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueueAssets' ] );
	}

	/**
	 * Store an OptInError for later retrieval by the frontend.
	 *
	 * @param OptInError $error  The error to store.
	 * @param int        $formId The form ID that caused the error.
	 *
	 * @return void
	 */
	public static function store( OptInError $error, int $formId ): void {
		$key = self::getTransientKey();

		set_transient( $key, [
			'code'              => $error->getCode(),
			'message'           => $error->getMessage(),
			'form_id'           => $formId,
			'time'              => time(),
			'hide_confirmation' => (bool) apply_filters( 'f12_cf7_doubleoptin_show_validation_error', false ),
		], self::TRANSIENT_TTL );
	}

	/**
	 * Retrieve and delete the stored error for the current client.
	 *
	 * @return array|null The error data or null if none exists.
	 */
	public static function retrieve(): ?array {
		$key  = self::getTransientKey();
		$data = get_transient( $key );

		if ( ! is_array( $data ) || empty( $data['code'] ) ) {
			return null;
		}

		delete_transient( $key );

		return $data;
	}

	/**
	 * AJAX handler: check for a stored submission error.
	 *
	 * @return void
	 */
	public function handleAjax(): void {
		check_ajax_referer( 'doi_error_notification', 'nonce' );

		$data = self::retrieve();

		if ( ! $data ) {
			wp_send_json_success( [ 'error' => null ] );
			return;
		}

		// Allow message customization via the same filter used by integrations
		$optInError = OptInError::fromCode( $data['code'] );
		$message    = apply_filters(
			'f12_cf7_doubleoptin_error_message',
			$data['message'],
			$optInError,
			$data['form_id']
		);

		$response = [
			'error' => [
				'code'              => $data['code'],
				'message'           => $message,
				'hide_confirmation' => ! empty( $data['hide_confirmation'] ),
			],
		];

		// Add redirect URL if configured for this form
		if ( ! empty( $data['form_id'] ) ) {
			$redirectUrl = self::getErrorRedirectUrl( (int) $data['form_id'], $data['code'] );
			if ( $redirectUrl ) {
				$response['redirect_url'] = $redirectUrl;
			}
		}

		wp_send_json_success( $response );
	}

	/**
	 * Enqueue frontend assets on pages that may contain forms.
	 *
	 * The universal error notification is enabled by default via the
	 * `f12_cf7_doubleoptin_enable_error_notification` filter (default: true).
	 * This is independent of the `f12_cf7_doubleoptin_show_validation_error`
	 * filter which controls native per-plugin error display.
	 *
	 * @return void
	 */
	public function enqueueAssets(): void {
		if ( ! apply_filters( 'f12_cf7_doubleoptin_enable_error_notification', true ) ) {
			return;
		}

		wp_enqueue_style(
			'doi-error-notification',
			plugins_url( 'core/assets/doi-error-notification.css', F12_DOUBLEOPTIN_PLUGIN_FILE ),
			[],
			defined( 'FORGE12_OPTIN_VERSION' ) ? FORGE12_OPTIN_VERSION : '4.2.0'
		);

		wp_enqueue_script(
			'doi-error-notification',
			plugins_url( 'core/assets/doi-error-notification.js', F12_DOUBLEOPTIN_PLUGIN_FILE ),
			[],
			defined( 'FORGE12_OPTIN_VERSION' ) ? FORGE12_OPTIN_VERSION : '4.2.0',
			true
		);

		wp_localize_script( 'doi-error-notification', 'doiErrorNotification', [
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'doi_error_notification' ),
		] );
	}

	/**
	 * Get the error redirect URL for a given form.
	 *
	 * Looks up the form's error_page setting from post_meta and builds
	 * a redirect URL with the error code as a query parameter.
	 *
	 * @param int    $formId    The form post ID.
	 * @param string $errorCode The error code (e.g. 'rate_limit_ip').
	 *
	 * @return string The redirect URL, or empty string if not configured.
	 */
	private static function getErrorRedirectUrl( int $formId, string $errorCode ): string {
		$meta = get_post_meta( $formId, 'f12-cf7-doubleoptin', true );

		if ( empty( $meta ) || ! is_array( $meta ) ) {
			return '';
		}

		// Unique email duplicate → check dedicated redirect page first
		if ( $errorCode === OptInError::UNIQUE_EMAIL_DUPLICATE ) {
			$uePageId = (int) ( $meta['unique_email_redirect_page'] ?? -1 );
			if ( $uePageId > 0 ) {
				$permalink = get_permalink( $uePageId );
				if ( $permalink ) {
					return add_query_arg( 'doi_error', sanitize_key( $errorCode ), $permalink );
				}
			}
		}

		// Fallback to general error_page
		$pageId = (int) ( $meta['error_page'] ?? -1 );

		if ( $pageId <= 0 ) {
			return '';
		}

		$permalink = get_permalink( $pageId );

		if ( ! $permalink ) {
			return '';
		}

		return add_query_arg( 'doi_error', sanitize_key( $errorCode ), $permalink );
	}

	/**
	 * Build the transient key for the current client.
	 *
	 * Uses IP + User-Agent to identify the client without cookies or sessions.
	 * The key is short-lived (60s) so collision risk is negligible.
	 *
	 * @return string The transient key.
	 */
	public static function getTransientKey(): string {
		$ip        = class_exists( IPHelper::class ) ? IPHelper::getIPAdress() : ( $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0' );
		$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

		return self::TRANSIENT_PREFIX . md5( $ip . '|' . $userAgent );
	}
}
