<?php
/**
 * Outcome of one follow-up action.
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
 * Immutable result an adapter reports for one action.
 *
 * Carries only structural diagnostics — a machine error code, an HTTP
 * status, a response class and an entry reference. Never form values,
 * recipients, tokens or third-party error text: those can contain
 * personal data and this object ends up in the audit log.
 */
final class FollowUpResult {

	/**
	 * Response classes for HTTP-based adapters.
	 */
	public const RESPONSE_JSON_SUCCESS = 'json_success';
	public const RESPONSE_JSON_ERROR   = 'json_error';
	public const RESPONSE_INVALID_JSON = 'invalid_json';
	public const RESPONSE_EMPTY        = 'empty';
	public const RESPONSE_NONE         = '';

	/** @var string */
	private $status;

	/** @var string */
	private $errorCode;

	/** @var bool */
	private $retryable;

	/** @var int */
	private $httpStatus;

	/** @var string */
	private $responseKind;

	/** @var string */
	private $entryRef;

	/** @var string */
	private $evidence;

	private function __construct( string $status, string $errorCode, bool $retryable, int $httpStatus, string $responseKind, string $entryRef, string $evidence ) {
		$this->status       = $status;
		$this->errorCode    = self::normaliseCode( $errorCode );
		$this->retryable    = $retryable;
		$this->httpStatus   = $httpStatus;
		$this->responseKind = $responseKind;
		$this->entryRef     = substr( $entryRef, 0, 100 );
		$this->evidence     = self::normaliseCode( $evidence );
	}

	/**
	 * The action demonstrably completed.
	 *
	 * @param string $evidence What the success is based on, e.g.
	 *                         `wp_mail_true`, `entry_id`, `no_exception`.
	 * @param string $entryRef ID of a record the action created, if any.
	 */
	public static function succeeded( string $evidence, string $entryRef = '' ): self {
		return new self( FollowUpStatus::SUCCEEDED, '', false, 0, self::RESPONSE_NONE, $entryRef, $evidence );
	}

	/**
	 * The action was deliberately not executed.
	 */
	public static function skipped( string $reason ): self {
		return new self( FollowUpStatus::SKIPPED, $reason, false, 0, self::RESPONSE_NONE, '', '' );
	}

	/**
	 * The action did not run and running it again is safe.
	 */
	public static function failedRetryable( string $errorCode ): self {
		return new self( FollowUpStatus::FAILED_RETRYABLE, $errorCode, true, 0, self::RESPONSE_NONE, '', '' );
	}

	/**
	 * The action failed; retrying without fixing the cause is pointless.
	 */
	public static function failedPermanent( string $errorCode, string $entryRef = '' ): self {
		return new self( FollowUpStatus::FAILED_PERMANENT, $errorCode, false, 0, self::RESPONSE_NONE, $entryRef, '' );
	}

	/**
	 * The action may or may not have run.
	 */
	public static function unknown( string $errorCode ): self {
		return new self( FollowUpStatus::UNKNOWN, $errorCode, false, 0, self::RESPONSE_NONE, '', '' );
	}

	/**
	 * Copy with transport diagnostics attached.
	 */
	public function withTransport( int $httpStatus, string $responseKind ): self {
		$copy               = clone $this;
		$copy->httpStatus   = $httpStatus;
		$copy->responseKind = $responseKind;
		return $copy;
	}

	/**
	 * Copy with a different status, keeping diagnostics — used when a
	 * retryable failure has exhausted its retries.
	 */
	public function withStatus( string $status ): self {
		$copy            = clone $this;
		$copy->status    = $status;
		$copy->retryable = $status === FollowUpStatus::FAILED_RETRYABLE;
		return $copy;
	}

	public function getStatus(): string {
		return $this->status;
	}

	public function getErrorCode(): string {
		return $this->errorCode;
	}

	public function isRetryable(): bool {
		return $this->retryable;
	}

	public function getHttpStatus(): int {
		return $this->httpStatus;
	}

	public function getResponseKind(): string {
		return $this->responseKind;
	}

	public function getEntryRef(): string {
		return $this->entryRef;
	}

	public function getEvidence(): string {
		return $this->evidence;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function toArray(): array {
		return array(
			'status'        => $this->status,
			'error_code'    => $this->errorCode,
			'retryable'     => $this->retryable,
			'http_status'   => $this->httpStatus,
			'response_kind' => $this->responseKind,
			'entry_ref'     => $this->entryRef,
			'evidence'      => $this->evidence,
		);
	}

	/**
	 * Error codes are machine identifiers: [a-z0-9_:.-], at most 64
	 * characters. Anything else is replaced as a whole rather than
	 * "cleaned" — replacing single characters would turn
	 * `max@example.com` into a still readable `max_example.com`. A code
	 * is never the place for a message.
	 */
	private static function normaliseCode( string $code ): string {
		if ( $code === '' ) {
			return '';
		}
		$code = strtolower( $code );
		if ( ! preg_match( '/^[a-z0-9_:.\-]{1,64}$/', $code ) ) {
			return 'unclassified_error';
		}
		return $code;
	}
}
