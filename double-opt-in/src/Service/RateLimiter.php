<?php
/**
 * Rate Limiter Service
 *
 * Rate-limiting via WordPress Transients for opt-in creation.
 *
 * @package Forge12\DoubleOptIn\Service
 * @since   3.3.0
 */

namespace Forge12\DoubleOptIn\Service;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class RateLimiter
 *
 * Provides rate-limiting functionality using WordPress transients.
 */
class RateLimiter {

	/**
	 * Check if an action is allowed within the rate limit.
	 *
	 * @param string $type          The type of limit (e.g., 'ip', 'email').
	 * @param string $identifier    The identifier to limit (e.g., IP address, email).
	 * @param int    $maxAttempts   Maximum number of attempts allowed.
	 * @param int    $windowMinutes Time window in minutes.
	 *
	 * @return bool True if the action is allowed.
	 */
	public function isAllowed( string $type, string $identifier, int $maxAttempts, int $windowMinutes ): bool {
		if ( $maxAttempts <= 0 ) {
			return true;
		}

		$key     = 'doi_rate_' . $type . '_' . md5( $identifier );
		$current = (int) get_transient( $key );

		if ( $current >= $maxAttempts ) {
			return false;
		}

		set_transient( $key, $current + 1, $windowMinutes * 60 );

		return true;
	}

	/**
	 * Get the number of remaining attempts.
	 *
	 * @param string $type        The type of limit.
	 * @param string $identifier  The identifier.
	 * @param int    $maxAttempts Maximum number of attempts allowed.
	 *
	 * @return int The remaining attempts.
	 */
	public function getRemainingAttempts( string $type, string $identifier, int $maxAttempts ): int {
		$key     = 'doi_rate_' . $type . '_' . md5( $identifier );
		$current = (int) get_transient( $key );

		return max( 0, $maxAttempts - $current );
	}
}
