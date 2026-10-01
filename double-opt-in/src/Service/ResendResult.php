<?php
/**
 * Outcome of re-sending a confirmation mail.
 *
 * @package Forge12\DoubleOptIn\Service
 * @since   5.8.0
 */

declare( strict_types=1 );

namespace Forge12\DoubleOptIn\Service;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Either sent, or the reason it was not. The reasons are stable strings so
 * callers can phrase them for their own audience: the admin learns that a
 * record is already confirmed, a visitor must never learn anything.
 *
 * @api
 */
final class ResendResult {

	public const SENT         = 'sent';
	public const NOT_FOUND    = 'not_found';
	public const CONFIRMED    = 'confirmed';
	public const OPTED_OUT    = 'opted_out';
	public const NO_BODY      = 'no_body';
	public const NO_RECIPIENT = 'no_recipient';
	public const SEND_FAILED  = 'send_failed';

	/** @var string */
	private $reason;

	private function __construct( string $reason ) {
		$this->reason = $reason;
	}

	public static function sent(): self {
		return new self( self::SENT );
	}

	public static function refused( string $reason ): self {
		return new self( $reason );
	}

	public function isSent(): bool {
		return $this->reason === self::SENT;
	}

	public function getReason(): string {
		return $this->reason;
	}
}
