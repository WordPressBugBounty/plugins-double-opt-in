<?php
/**
 * Classifies the transport outcome of an internal HTTP replay.
 *
 * @package Forge12\DoubleOptIn\FollowUp
 * @since   5.6.0
 */

declare( strict_types=1 );

namespace Forge12\DoubleOptIn\FollowUp;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Turns (transport error, HTTP status, body) into either a decoded
 * JSON payload with `success === true`, or a FollowUpResult explaining
 * why the handler's own result is not available.
 *
 * The central distinction is *did the request possibly execute?*
 *  - Not sent (DNS, connection refused, connect timeout) → nothing ran,
 *    safe to retry automatically.
 *  - Sent, then timeout / 5xx / garbage → it may have run → `unknown`.
 *  - Rejected before the handler (401/403/404/3xx/429) → nothing ran,
 *    but retrying won't help until the cause is fixed → permanent.
 */
final class LoopbackClassifier {

	/** cURL: operation timed out. */
	private const CURLE_OPERATION_TIMEDOUT = 28;

	/** @var array<string, mixed>|null */
	private $json;

	/** @var FollowUpResult|null */
	private $failure;

	/** @var int */
	private $httpStatus;

	/** @var string */
	private $responseKind;

	private function __construct( ?array $json, ?FollowUpResult $failure, int $httpStatus, string $responseKind ) {
		$this->json         = $json;
		$this->failure      = $failure;
		$this->httpStatus   = $httpStatus;
		$this->responseKind = $responseKind;
	}

	/**
	 * @param int    $transportErrno cURL errno (0 = no transport error).
	 * @param bool   $requestSent    Whether the request left the client
	 *                               (cURL: CURLINFO_PRETRANSFER_TIME > 0).
	 * @param int    $httpStatus     0 when no response was received.
	 * @param string $body           Raw response body.
	 */
	public static function classify( int $transportErrno, bool $requestSent, int $httpStatus, string $body ): self {
		if ( $transportErrno !== 0 ) {
			if ( ! $requestSent ) {
				return self::fail( FollowUpResult::failedRetryable( 'transport_connect_failed' ), 0, FollowUpResult::RESPONSE_NONE );
			}
			$code = $transportErrno === self::CURLE_OPERATION_TIMEDOUT ? 'transport_timeout_unknown' : 'transport_error_unknown';
			return self::fail( FollowUpResult::unknown( $code ), $httpStatus, FollowUpResult::RESPONSE_NONE );
		}

		$trimmed = trim( $body );
		$kind    = FollowUpResult::RESPONSE_EMPTY;
		$json    = null;
		if ( $trimmed !== '' ) {
			$decoded = json_decode( $trimmed, true );
			if ( is_array( $decoded ) ) {
				$json = $decoded;
				$kind = ( isset( $decoded['success'] ) && $decoded['success'] === true )
					? FollowUpResult::RESPONSE_JSON_SUCCESS
					: FollowUpResult::RESPONSE_JSON_ERROR;
			} else {
				$kind = FollowUpResult::RESPONSE_INVALID_JSON;
			}
		}

		if ( $httpStatus === 401 || $httpStatus === 403 ) {
			return self::fail( FollowUpResult::failedPermanent( 'http_forbidden' ), $httpStatus, $kind );
		}
		if ( $httpStatus === 404 ) {
			return self::fail( FollowUpResult::failedPermanent( 'http_not_found' ), $httpStatus, $kind );
		}
		if ( $httpStatus === 429 ) {
			return self::fail( FollowUpResult::failedPermanent( 'http_rate_limited' ), $httpStatus, $kind );
		}
		if ( $httpStatus >= 300 && $httpStatus < 400 ) {
			// Redirects are never followed: the target could be another
			// host, and a redirect is not the handler's answer.
			return self::fail( FollowUpResult::failedPermanent( 'http_redirect' ), $httpStatus, $kind );
		}
		if ( $httpStatus >= 500 ) {
			return self::fail( FollowUpResult::unknown( 'http_server_error' ), $httpStatus, $kind );
		}
		if ( $httpStatus >= 400 ) {
			return self::fail( FollowUpResult::failedPermanent( 'http_client_error' ), $httpStatus, $kind );
		}
		if ( $httpStatus < 200 ) {
			return self::fail( FollowUpResult::unknown( 'invalid_response' ), $httpStatus, $kind );
		}

		if ( $json === null ) {
			// 200 with no JSON: a fatal error or output buffer mishap
			// after the handler possibly ran.
			return self::fail( FollowUpResult::unknown( 'invalid_response' ), $httpStatus, $kind );
		}

		return new self( $json, null, $httpStatus, $kind );
	}

	private static function fail( FollowUpResult $result, int $httpStatus, string $kind ): self {
		return new self( null, $result->withTransport( $httpStatus, $kind ), $httpStatus, $kind );
	}

	/**
	 * The handler answered with parseable JSON (success true OR false).
	 */
	public function hasHandlerAnswer(): bool {
		return $this->json !== null;
	}

	public function isHandlerSuccess(): bool {
		return $this->responseKind === FollowUpResult::RESPONSE_JSON_SUCCESS;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function getJson(): ?array {
		return $this->json;
	}

	/**
	 * Transport-level failure, or null when the handler answered.
	 */
	public function getFailure(): ?FollowUpResult {
		return $this->failure;
	}

	public function getHttpStatus(): int {
		return $this->httpStatus;
	}

	public function getResponseKind(): string {
		return $this->responseKind;
	}
}
