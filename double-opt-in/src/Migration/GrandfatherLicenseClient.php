<?php
/**
 * Grandfather License Client
 *
 * HTTP client wrapper around `POST /v1/license/grandfather/avada` on
 * api.forge12.com. Used by {@see AvadaDeprecationNotice} when a site
 * admin clicks "Claim free grandfather license" on the migration notice.
 *
 * Intentionally thin — no retries, no caching beyond the immediate result.
 * Callers persist the returned license key; repeated calls for the same
 * site return `already_claimed` from the server and are safe.
 *
 * @package Forge12\DoubleOptIn\Migration
 * @since   4.99.0
 */

namespace Forge12\DoubleOptIn\Migration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class GrandfatherLicenseClient {

	private const ENDPOINT_URL = 'https://api.forge12.com/v1/license/grandfather/avada';
	private const TIMEOUT      = 15;

	/**
	 * Attempt to claim a grandfather license for this site.
	 *
	 * @param string               $siteUrl      Canonical site URL (`home_url()`).
	 * @param string               $adminEmail   Site admin email for records.
	 * @param array<string, mixed> $attestation  Self-reported attestation block.
	 * @return array{
	 *   success: bool,
	 *   status: string,
	 *   licenseKey: string,
	 *   message: string
	 * }
	 */
	public function claimAvada( string $siteUrl, string $adminEmail, array $attestation ): array {
		$response = wp_remote_post(
			self::ENDPOINT_URL,
			array(
				'timeout' => self::TIMEOUT,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode(
					array(
						'siteUrl'     => $siteUrl,
						'adminEmail'  => $adminEmail,
						'attestation' => $attestation,
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'success'    => false,
				'status'     => 'network_error',
				'licenseKey' => '',
				'message'    => $response->get_error_message(),
			);
		}

		$statusCode = wp_remote_retrieve_response_code( $response );
		$body       = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $body ) ) {
			return array(
				'success'    => false,
				'status'     => 'bad_response',
				'licenseKey' => '',
				'message'    => __( 'Server returned an unreadable response.', 'double-opt-in' ),
			);
		}

		// Cutoff expired → HTTP 410 with { status: 'cutoff_expired', message }
		if ( $statusCode === 410 ) {
			return array(
				'success'    => false,
				'status'     => $body['status'] ?? 'cutoff_expired',
				'licenseKey' => '',
				'message'    => $body['message'] ?? __( 'The claim window has closed.', 'double-opt-in' ),
			);
		}

		// Already claimed → HTTP 200 with { issued: false, status: 'already_claimed' }
		if ( isset( $body['status'] ) && $body['status'] === 'already_claimed' ) {
			return array(
				'success'    => false,
				'status'     => 'already_claimed',
				'licenseKey' => '',
				'message'    => $body['message'] ?? __( 'A grandfather license was already issued for this site.', 'double-opt-in' ),
			);
		}

		// Happy path → { issued: true, licenseKey, licenseType, validUntil }
		if ( ! empty( $body['issued'] ) && ! empty( $body['licenseKey'] ) ) {
			return array(
				'success'    => true,
				'status'     => 'issued',
				'licenseKey' => (string) $body['licenseKey'],
				'message'    => sprintf(
					/* translators: %s: ISO date */
					__( 'Grandfather license issued, valid until %s.', 'double-opt-in' ),
					$body['validUntil'] ?? '2099-12-31'
				),
			);
		}

		// Generic server error (500-ish) with our error shape.
		return array(
			'success'    => false,
			'status'     => (string) ( $body['errorCode'] ?? 'unknown_error' ),
			'licenseKey' => '',
			'message'    => (string) ( $body['message'] ?? __( 'Unknown server error.', 'double-opt-in' ) ),
		);
	}
}
