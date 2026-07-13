<?php

namespace forge12\contactform7\CF7DoubleOptIn;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class IPHelper {
	/**
	 * Resolve the current client IP.
	 *
	 * Security: `REMOTE_ADDR` is the only value a remote client cannot spoof.
	 * `X-Forwarded-For` (and friends) are attacker-controllable, so we only
	 * consult XFF when the direct peer (`REMOTE_ADDR`) is itself a trusted
	 * proxy — configured via the `f12_doi_trusted_proxies` filter (list of
	 * IPs / CIDR ranges). Default is an EMPTY list, i.e. REMOTE_ADDR only.
	 * Sites behind a CDN/reverse proxy must register their proxy ranges:
	 *
	 *   add_filter( 'f12_doi_trusted_proxies', fn() => array( '173.245.48.0/20' ) );
	 *
	 * Rationale: many WordPress plugins were IP-spoofable by trusting XFF
	 * blindly — this value feeds the opt-in rate limiter and is stored as
	 * GDPR consent evidence, so it must not be forgeable.
	 *
	 * @return string
	 */
	public static function getIPAdress(): string {
		$trusted = function_exists( 'apply_filters' )
			? (array) apply_filters( 'f12_doi_trusted_proxies', array() )
			: array();

		return self::resolveClientIp( isset( $_SERVER ) ? (array) $_SERVER : array(), $trusted );
	}

	/**
	 * Pure IP resolution — no WordPress dependency, so it is unit-testable.
	 *
	 * @param array $server         A `$_SERVER`-shaped array.
	 * @param array $trustedProxies IPs / IPv4-CIDR ranges considered trusted.
	 * @return string A validated IP, or '' when nothing valid is present.
	 */
	public static function resolveClientIp( array $server, array $trustedProxies = array() ): string {
		$remote = isset( $server['REMOTE_ADDR'] ) ? trim( (string) $server['REMOTE_ADDR'] ) : '';
		$remote = filter_var( $remote, FILTER_VALIDATE_IP ) ? $remote : '';

		// Only walk the forwarded chain when the direct peer is a known proxy.
		if ( $remote !== ''
			&& ! empty( $trustedProxies )
			&& self::ipInRanges( $remote, $trustedProxies )
			&& ! empty( $server['HTTP_X_FORWARDED_FOR'] ) ) {

			$hops = array_reverse(
				array_map( 'trim', explode( ',', (string) $server['HTTP_X_FORWARDED_FOR'] ) )
			);
			foreach ( $hops as $hop ) {
				// First hop that is a valid IP and NOT itself a trusted proxy
				// is the real client.
				if ( filter_var( $hop, FILTER_VALIDATE_IP ) && ! self::ipInRanges( $hop, $trustedProxies ) ) {
					return $hop;
				}
			}
		}

		return $remote;
	}

	/**
	 * Match an IP against a list of exact IPs and IPv4 CIDR ranges.
	 * (IPv6 is matched exactly; extend here if IPv6 CIDR is needed.)
	 *
	 * @param string $ip
	 * @param array  $ranges
	 * @return bool
	 */
	private static function ipInRanges( string $ip, array $ranges ): bool {
		foreach ( $ranges as $range ) {
			$range = trim( (string) $range );
			if ( $range === '' ) {
				continue;
			}
			if ( strpos( $range, '/' ) === false ) {
				if ( $ip === $range ) {
					return true;
				}
				continue;
			}

			list( $subnet, $bits ) = array_pad( explode( '/', $range, 2 ), 2, '' );
			$bits       = (int) $bits;
			$ipLong     = ip2long( $ip );
			$subnetLong = ip2long( $subnet );
			if ( $ipLong === false || $subnetLong === false || $bits < 0 || $bits > 32 ) {
				continue; // Not IPv4 or malformed CIDR — skip.
			}
			$mask = $bits === 0 ? 0 : ( -1 << ( 32 - $bits ) );
			if ( ( $ipLong & $mask ) === ( $subnetLong & $mask ) ) {
				return true;
			}
		}
		return false;
	}
}
