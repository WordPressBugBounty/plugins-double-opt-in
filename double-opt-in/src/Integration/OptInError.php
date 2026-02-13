<?php
/**
 * OptIn Error Value Object
 *
 * @package Forge12\DoubleOptIn\Integration
 * @since   4.2.0
 */

namespace Forge12\DoubleOptIn\Integration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class OptInError
 *
 * Immutable value object representing an error that occurred during OptIn creation.
 * Provides typed error codes and translatable default messages for each error scenario.
 */
class OptInError {

	/**
	 * Error code: Form submission was cancelled by a listener (FormSubmissionEvent).
	 */
	public const SUBMISSION_CANCELLED = 'submission_cancelled';

	/**
	 * Error code: No valid recipient email address found in the form data.
	 */
	public const NO_RECIPIENT = 'no_recipient';

	/**
	 * Error code: Rate limit exceeded for the submitting IP address.
	 */
	public const RATE_LIMIT_IP = 'rate_limit_ip';

	/**
	 * Error code: Rate limit exceeded for the recipient email address.
	 */
	public const RATE_LIMIT_EMAIL = 'rate_limit_email';

	/**
	 * Error code: Recipient email validation failed (e.g. MX check).
	 */
	public const RECIPIENT_INVALID = 'recipient_invalid';

	/**
	 * Error code: Duplicate email rejected by Unique Email validator.
	 */
	public const UNIQUE_EMAIL_DUPLICATE = 'unique_email_duplicate';

	/**
	 * Error code: Failed to save the OptIn record to the database.
	 */
	public const SAVE_FAILED = 'save_failed';

	/**
	 * The error code.
	 *
	 * @var string
	 */
	private string $code;

	/**
	 * The human-readable error message.
	 *
	 * @var string
	 */
	private string $message;

	/**
	 * Additional context data.
	 *
	 * @var array
	 */
	private array $context;

	/**
	 * Constructor.
	 *
	 * @param string $code    The error code.
	 * @param string $message The error message.
	 * @param array  $context Additional context data.
	 */
	public function __construct( string $code, string $message, array $context = [] ) {
		$this->code    = $code;
		$this->message = $message;
		$this->context = $context;
	}

	/**
	 * Create an OptInError from an error code with a default message.
	 *
	 * @param string $code    The error code.
	 * @param array  $context Additional context data.
	 *
	 * @return self
	 */
	public static function fromCode( string $code, array $context = [] ): self {
		$messages = self::getDefaultMessages();
		$message  = $messages[ $code ] ?? __( 'An unknown error occurred.', 'double-opt-in' );

		return new self( $code, $message, $context );
	}

	/**
	 * Get the error code.
	 *
	 * @return string
	 */
	public function getCode(): string {
		return $this->code;
	}

	/**
	 * Get the human-readable error message.
	 *
	 * @return string
	 */
	public function getMessage(): string {
		return $this->message;
	}

	/**
	 * Get additional context data.
	 *
	 * @return array
	 */
	public function getContext(): array {
		return $this->context;
	}

	/**
	 * Get the default translatable messages for all error codes.
	 *
	 * @return array<string, string>
	 */
	private static function getDefaultMessages(): array {
		return [
			self::SUBMISSION_CANCELLED => __( 'The form submission has been cancelled.', 'double-opt-in' ),
			self::NO_RECIPIENT         => __( 'No valid email address was found.', 'double-opt-in' ),
			self::RATE_LIMIT_IP        => __( 'Too many requests. Please try again later.', 'double-opt-in' ),
			self::RATE_LIMIT_EMAIL     => __( 'Too many requests for this email address. Please try again later.', 'double-opt-in' ),
			self::RECIPIENT_INVALID        => __( 'The email address could not be verified.', 'double-opt-in' ),
			self::UNIQUE_EMAIL_DUPLICATE   => __( 'This email address has already been used.', 'double-opt-in' ),
			self::SAVE_FAILED          => __( 'An error occurred. Please try again.', 'double-opt-in' ),
		];
	}
}
