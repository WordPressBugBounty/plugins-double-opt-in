<?php
if ( ! function_exists( 'f12_cf7_doubleoptin_get_installation_uuid' ) ) {
	function f12_cf7_doubleoptin_get_installation_uuid(): string {
		$uuid = get_option( 'f12_cf7_doubleoptin_installation_uuid' );
		if ( empty( $uuid ) ) {
			if ( function_exists( 'wp_generate_uuid4' ) ) {
				$uuid = wp_generate_uuid4();
			} else {
				$uuid = sprintf(
					'%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
					mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),
					mt_rand( 0, 0xffff ),
					mt_rand( 0, 0x0fff ) | 0x4000,
					mt_rand( 0, 0x3fff ) | 0x8000,
					mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )
				);
			}
			update_option( 'f12_cf7_doubleoptin_installation_uuid', $uuid, true );
		}

		return $uuid;
	}
}
