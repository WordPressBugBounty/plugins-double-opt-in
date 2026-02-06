<?php

namespace forge12\contactform7\CF7DoubleOptIn;

use Exception;
use Forge12\Shared\Logger;
use Forge12\Shared\LoggerInterface;

if (!class_exists(__NAMESPACE__ . '\\Telemetry')) {
	class Telemetry {

		/** @var string */
		private $option_key = 'f12_cf7_doubleoptin_telemetry_counters';

		/** @var \Forge12\Shared\LoggerInterface */
		private $logger;

		public function __construct(LoggerInterface $logger) {
			$this->logger = $logger;
		}

		public function get_features(): array {
			include_once(ABSPATH . 'wp-admin/includes/plugin.php');
			return [
				'double-opt-in-pro' => is_plugin_active('double-opt-in-pro/CF7DoubleOptIn.class.php'),
			];
		}

		/**
		 * Increment a counter by name
		 */
		public function increment(string $counter): void {
			$counters = get_option($this->option_key, []);

			if (!is_array($counters)) {
				$counters = maybe_unserialize($counters);
			}
			if (empty($counters) || !is_array($counters)) {
				$counters = [];
			}

			if (!isset($counters[$counter])) {
				$counters[$counter] = 0;
			}

			$counters[$counter]++;

			update_option($this->option_key, $counters, false);

			$this->logger->debug("Telemetry counter incremented", [
				'plugin'  => FORGE12_OPTIN_SLUG,
				'counter' => $counter,
				'value'   => $counters[$counter],
			]);
		}

		/**
		 * Get all counters
		 */
		public function get_counters(): array {
			$counters = get_option($this->option_key, []);
			return is_array($counters) ? $counters : [];
		}

		/**
		 * Reset counters (optional)
		 */
		public function reset(): void {
			update_option($this->option_key, [], false);
			$this->logger->info("Telemetry counters reset", [
				'plugin' => FORGE12_OPTIN_SLUG,
			]);
		}

		/**
		 * Static: build payload
		 */
		public static function build_payload(): array {
			$logger    = Logger::getInstance();
			$telemetry = new self($logger);

			$logger->info("Telemetry payload creation started", [
				'plugin' => FORGE12_OPTIN_SLUG,
			]);

			try {
				$counters = $telemetry->get_counters();

				if (empty($counters)) {
					$logger->info("Telemetry: Counters are empty, initializing as stdClass.", [
						'plugin' => FORGE12_OPTIN_SLUG,
					]);
					$counters = new \stdClass();
				}

				$payload = [
					'installation_uuid' => f12_cf7_doubleoptin_get_installation_uuid(),
					'plugin_slug'       => FORGE12_OPTIN_SLUG,
					'plugin_version'    => FORGE12_OPTIN_VERSION,
					'snapshot_date'     => gmdate('Y-m-d'),
					'settings'          => get_option('f12-doi-settings', []),
					'features'          => $telemetry->get_features(),
					'counters'          => $counters,
					'wp_version'        => get_bloginfo('version'),
					'php_version'       => PHP_VERSION,
					'locale'            => get_locale(),
				];

				$logger->info("Telemetry payload created successfully", [
					'plugin'  => FORGE12_OPTIN_SLUG,
					'payload' => $payload,
				]);

				return $payload;

			} catch (Exception $e) {
				$logger->error("Error creating telemetry payload", [
					'plugin'  => FORGE12_OPTIN_SLUG,
					'message' => $e->getMessage(),
					'trace'   => $e->getTraceAsString(),
				]);

				return [];
			}
		}

		/**
		 * Static: send snapshot
		 */
		public static function send_snapshot(): void {
			$logger  = Logger::getInstance();
			$payload = self::build_payload();

			$logger->debug("Telemetry Payload prepared", [
				'plugin'  => FORGE12_OPTIN_SLUG,
				'payload' => $payload,
			]);

			$response = wp_remote_post('https://silentshield.forge12.com/api/telemetry/snapshot', [
				'headers' => [
					'Content-Type' => 'application/json; charset=utf-8',
				],
				'body'    => wp_json_encode($payload),
				'timeout' => 15,
			]);

			if (is_wp_error($response)) {
				$logger->error("Telemetry failed", [
					'plugin' => FORGE12_OPTIN_SLUG,
					'error'  => $response->get_error_message(),
				]);
				return;
			}

			$code = wp_remote_retrieve_response_code($response);

			if ($code === 201) {
				$logger->info("Telemetry sent successfully", [
					'plugin' => FORGE12_OPTIN_SLUG,
					'code'   => $code,
				]);
			} else {
				$logger->warning("Telemetry unexpected response", [
					'plugin'   => FORGE12_OPTIN_SLUG,
					'code'     => $code,
					'response' => wp_remote_retrieve_body($response),
				]);
			}
		}
	}
}

// Cron Hook registrieren
add_action('f12_cf7_doubleoptin_daily_telemetry', [Telemetry::class, 'send_snapshot']);
