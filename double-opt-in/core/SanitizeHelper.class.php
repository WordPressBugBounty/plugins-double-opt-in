<?php

namespace forge12\contactform7\CF7DoubleOptIn;

use Forge12\Shared\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SanitizeHelper {
	/**
	 * Sanitizes an array by sanitizing the keys and values recursively.
	 *
	 * @param array $data The array to be sanitized.
	 *
	 * @return array The sanitized array.
	 */
	public static function sanitize_array( $data ): array {
		$logger = Logger::getInstance();
		$logger->info( 'Starting sanitization of an array.', [
			'plugin' => 'double-opt-in',
			'data_type' => gettype($data),
		] );

		if ( ! is_array( $data ) ) {
			$logger->warning( 'Provided data is not an array. Returning an empty array.', [
				'plugin' => 'double-opt-in',
				'data_type' => gettype($data),
			] );
			return [];
		}

		$sanitized_data = [];
		foreach ( $data as $key => $value ) {
			$sanitized_key = sanitize_text_field( $key );

			$do_sanitize_key_filter = apply_filters('f12_cf7_doubleoptin_do_sanitize_key_'.$key, true);
			$logger->debug( 'Applying sanitization filter for key: ' . $key, [
				'plugin' => 'double-opt-in',
				'filter_result' => $do_sanitize_key_filter,
			] );

			// Use a more explicit check for 'body' to avoid issues if the filter returns false for a different reason
			if ( ! $do_sanitize_key_filter || $key === 'body' ) {
				$sanitized_data[$key] = $value;
				$logger->info( 'Skipping sanitization for key "' . $key . '" based on filter or key name.', [
					'plugin' => 'double-opt-in',
				] );
				continue;
			}

			if ( is_array( $value ) ) {
				$sanitized_data[ $sanitized_key ] = self::sanitize_array( $value );
				$logger->debug( 'Recursively sanitizing array value for key: ' . $sanitized_key, [
					'plugin' => 'double-opt-in',
				] );
			} else {
				$sanitized_data[ $sanitized_key ] = wp_kses_post( $value );
				$logger->debug( 'Sanitizing string value for key "' . $sanitized_key . '" using wp_kses_post.', [
					'plugin' => 'double-opt-in',
				] );
			}
		}

		$logger->notice( 'Array sanitization completed successfully.', [
			'plugin' => 'double-opt-in',
		] );

		return $sanitized_data;
	}

}