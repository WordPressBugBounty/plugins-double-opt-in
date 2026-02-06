<?php
/**
 * Function: f12_debug_log
 * Version: 1.0.0
 * Description: Logs debug messages to the error log if WP_DEBUG is enabled. Optionally includes context data.
 */
if (!function_exists('f12_debug_log')) {
	/**
	 * Logs a debug message with optional context.
	 *
	 * @param string $message The debug message to log.
	 * @param array $context Additional context data to include in the log (optional).
	 * @return void
	 */
	function f12_debug_log(string $message, array $context = []): void
	{
		if (!defined('F12_DOI_DEBUG') || F12_DOI_DEBUG !== true) {
			return;
		}

		$context_str = !empty($context) ? json_encode($context) : '';
		$log_entry = sprintf("%s %s\n", $message, $context_str);
		error_log($log_entry);
	}
}