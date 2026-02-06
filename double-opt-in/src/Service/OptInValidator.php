<?php
/**
 * OptIn Validator Service
 *
 * @package Forge12\DoubleOptIn\Service
 * @since   4.0.0
 */

namespace Forge12\DoubleOptIn\Service;

use Forge12\DoubleOptIn\Entity\OptIn;
use Forge12\Shared\LoggerInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class OptInValidator
 *
 * Validates OptIn data before persistence.
 */
class OptInValidator {

	private LoggerInterface $logger;

	/**
	 * Validation errors from last validate() call.
	 *
	 * @var array<string, string>
	 */
	private array $errors = [];

	/**
	 * Constructor.
	 *
	 * @param LoggerInterface $logger The logger.
	 */
	public function __construct( LoggerInterface $logger ) {
		$this->logger = $logger;
	}

	/**
	 * Validate an OptIn entity.
	 *
	 * @param OptIn $optIn The entity to validate.
	 *
	 * @return bool True if valid.
	 */
	public function validate( OptIn $optIn ): bool {
		$this->errors = [];

		$this->validateEmail( $optIn );
		$this->validateFormId( $optIn );
		$this->validateContent( $optIn );

		if ( ! empty( $this->errors ) ) {
			$this->logger->warning( 'OptIn validation failed', [
				'plugin' => 'double-opt-in',
				'errors' => $this->errors,
			] );
			return false;
		}

		return true;
	}

	/**
	 * Get validation errors from last validate() call.
	 *
	 * @return array<string, string>
	 */
	public function getErrors(): array {
		return $this->errors;
	}

	/**
	 * Validate email address.
	 *
	 * @param OptIn $optIn The entity.
	 */
	private function validateEmail( OptIn $optIn ): void {
		$email = $optIn->getEmail();

		if ( empty( $email ) ) {
			$this->errors['email'] = __( 'Email address is required.', 'double-opt-in' );
			return;
		}

		if ( ! is_email( $email ) ) {
			$this->errors['email'] = __( 'Invalid email address format.', 'double-opt-in' );
		}
	}

	/**
	 * Validate form ID.
	 *
	 * @param OptIn $optIn The entity.
	 */
	private function validateFormId( OptIn $optIn ): void {
		if ( $optIn->getFormId() <= 0 ) {
			$this->errors['formId'] = __( 'Form ID is required.', 'double-opt-in' );
		}
	}

	/**
	 * Validate content encoding.
	 *
	 * @param OptIn $optIn The entity.
	 */
	private function validateContent( OptIn $optIn ): void {
		$content = $optIn->getContent();

		if ( ! empty( $content ) && ! $this->isValidUtf8( $content ) ) {
			$this->errors['content'] = __( 'Content contains invalid characters.', 'double-opt-in' );
		}
	}

	/**
	 * Check if string is valid UTF-8.
	 *
	 * @param string $string The string to check.
	 *
	 * @return bool
	 */
	private function isValidUtf8( string $string ): bool {
		return mb_check_encoding( $string, 'UTF-8' );
	}

	/**
	 * Validate hash format.
	 *
	 * @param string $hash The hash to validate.
	 *
	 * @return bool
	 */
	public function isValidHash( string $hash ): bool {
		if ( empty( $hash ) ) {
			return false;
		}

		// Hash should be base64 encoded
		$decoded = base64_decode( $hash, true );

		return $decoded !== false && strlen( $decoded ) > 0;
	}

	/**
	 * Validate IP address format.
	 *
	 * @param string $ip The IP to validate.
	 *
	 * @return bool
	 */
	public function isValidIp( string $ip ): bool {
		return filter_var( $ip, FILTER_VALIDATE_IP ) !== false;
	}

	/**
	 * Check if content size is within database limits.
	 *
	 * @param string $content The content to check.
	 *
	 * @return bool
	 */
	public function isWithinSizeLimit( string $content ): bool {
		global $wpdb;

		// Get max_allowed_packet if available
		$maxPacket = $wpdb->get_var( "SHOW VARIABLES LIKE 'max_allowed_packet'" );

		if ( $maxPacket === null ) {
			// Default to 1MB if we can't determine
			$maxPacket = 1048576;
		}

		// Leave some margin (80% of max)
		$limit = (int) $maxPacket * 0.8;

		return strlen( $content ) <= $limit;
	}
}
