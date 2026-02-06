<?php

namespace forge12\contactform7\CF7DoubleOptIn;

use Forge12\Shared\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class IPHelper {
	/**
	 * Retrieves the IP address of the current client.
	 *
	 * The method checks if the IP address is obtained from a shared internet, proxy, or remote address.
	 *
	 * @return string The IP address of the current client.
	 */
	public static function getIPAdress(): string {
		$logger = Logger::getInstance();
		$logger->info( 'Attempting to determine the user\'s IP address.', [
			'plugin' => 'double-opt-in',
		] );

		//whether ip is from share internet
		if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
			$ip_address = sanitize_text_field( $_SERVER['HTTP_CLIENT_IP'] );
			$logger->debug( 'IP address found from HTTP_CLIENT_IP.', [
				'plugin' => 'double-opt-in',
				'source' => 'HTTP_CLIENT_IP',
				'ip'     => $ip_address,
			] );
		} //whether ip is from proxy
		elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$ip_address = sanitize_text_field( $_SERVER['HTTP_X_FORWARDED_FOR'] );
			$logger->debug( 'IP address found from HTTP_X_FORWARDED_FOR.', [
				'plugin' => 'double-opt-in',
				'source' => 'HTTP_X_FORWARDED_FOR',
				'ip'     => $ip_address,
			] );
		} //whether ip is from remote address
		else {
			$ip_address = sanitize_text_field( $_SERVER['REMOTE_ADDR'] );
			$logger->debug( 'IP address found from REMOTE_ADDR.', [
				'plugin' => 'double-opt-in',
				'source' => 'REMOTE_ADDR',
				'ip'     => $ip_address,
			] );
		}

		$logger->info( 'IP address successfully determined.', [
			'plugin' => 'double-opt-in',
			'final_ip' => $ip_address,
		] );

		return $ip_address;
	}
}