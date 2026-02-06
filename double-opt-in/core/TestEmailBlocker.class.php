<?php
/**
 * Test Email Blocker
 *
 * Blocks emails to test addresses (@example.com) to prevent
 * unnecessary email sending during E2E tests.
 *
 * @package forge12\contactform7\CF7DoubleOptIn
 * @since 3.1.22
 */

namespace forge12\contactform7\CF7DoubleOptIn;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class TestEmailBlocker
 *
 * Prevents emails from being sent to test email addresses.
 * This is useful for E2E testing where we don't want actual emails sent.
 */
class TestEmailBlocker {

	/**
	 * Test email patterns that should be blocked
	 *
	 * @var array
	 */
	private static $blocked_patterns = array(
		'@example.com',      // RFC 2606 reserved domain
		'@example.org',      // RFC 2606 reserved domain
		'@example.net',      // RFC 2606 reserved domain
		'@test.com',         // Common test domain
		'@localhost',        // Local testing
	);

	/**
	 * Initialize the email blocker
	 *
	 * @return void
	 */
	public static function init() {
		// Only block if the setting is enabled or if we're in a testing environment
		if ( self::should_block_test_emails() ) {
			add_filter( 'pre_wp_mail', array( __CLASS__, 'maybe_block_email' ), 10, 2 );
		}
	}

	/**
	 * Check if test email blocking should be enabled
	 *
	 * @return bool
	 */
	private static function should_block_test_emails() {
		// Always enabled for test domains - this is safe and prevents accidental emails
		// Can be disabled via filter if needed
		return apply_filters( 'f12_doi_block_test_emails', true );
	}

	/**
	 * Check if email should be blocked and block it if necessary
	 *
	 * @param null|bool $return Short-circuit return value
	 * @param array     $atts   Email attributes
	 *
	 * @return null|bool Null to continue sending, true to block
	 */
	public static function maybe_block_email( $return, $atts ) {
		// If already blocked by another filter, respect that
		if ( $return === true ) {
			return $return;
		}

		// Ensure $atts is an array
		if ( ! is_array( $atts ) ) {
			return $return;
		}

		$to = isset( $atts['to'] ) ? $atts['to'] : '';

		// Convert to array if string
		if ( is_string( $to ) ) {
			$to = array_map( 'trim', explode( ',', $to ) );
		}

		// Check each recipient
		foreach ( (array) $to as $recipient ) {
			if ( self::is_test_email( $recipient ) ) {
				$subject = isset( $atts['subject'] ) ? $atts['subject'] : '';
				self::log_blocked_email( $recipient, $subject );
				// Return true to short-circuit wp_mail() - email won't be sent
				return true;
			}
		}

		// Return null to continue with normal email sending
		return $return;
	}

	/**
	 * Check if an email address is a test email
	 *
	 * @param string $email Email address to check
	 *
	 * @return bool True if test email
	 */
	public static function is_test_email( $email ) {
		if ( ! is_string( $email ) ) {
			return false;
		}

		$email = strtolower( trim( $email ) );

		// Check against blocked patterns
		foreach ( self::$blocked_patterns as $pattern ) {
			if ( strpos( $email, strtolower( $pattern ) ) !== false ) {
				return true;
			}
		}

		// Check for test- prefix pattern (e.g., test-1234567890-abc@domain.com)
		if ( preg_match( '/^test-\d+-[a-z0-9]+@/i', $email ) ) {
			return true;
		}

		// Allow custom patterns via filter
		$custom_patterns = apply_filters( 'f12_doi_test_email_patterns', array() );
		foreach ( (array) $custom_patterns as $pattern ) {
			if ( strpos( $email, strtolower( $pattern ) ) !== false ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Log blocked email for debugging
	 *
	 * @param string $recipient Email recipient
	 * @param string $subject   Email subject
	 *
	 * @return void
	 */
	private static function log_blocked_email( $recipient, $subject ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log( sprintf(
				'[Double Opt-In] Blocked test email to: %s (Subject: %s)',
				$recipient,
				substr( $subject, 0, 50 )
			) );
		}

		// Fire action for custom logging/tracking
		do_action( 'f12_doi_test_email_blocked', $recipient, $subject );
	}

	/**
	 * Add custom blocked pattern
	 *
	 * @param string $pattern Pattern to block (e.g., '@mydomain.test')
	 *
	 * @return void
	 */
	public static function add_blocked_pattern( $pattern ) {
		if ( ! in_array( $pattern, self::$blocked_patterns, true ) ) {
			self::$blocked_patterns[] = $pattern;
		}
	}

	/**
	 * Get all blocked patterns
	 *
	 * @return array
	 */
	public static function get_blocked_patterns() {
		return self::$blocked_patterns;
	}
}
