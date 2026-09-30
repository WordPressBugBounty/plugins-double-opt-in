<?php
/**
 * Sender defaults for forms that have none of their own.
 *
 * Set in the first step of the setup wizard. Without them a form that gets
 * Double Opt-In switched on later starts with the admin address and no
 * sender name, and WordPress fills the gap with "WordPress" — one of the
 * recurring support questions.
 *
 * A value a form already has is never replaced.
 *
 * @package Forge12\DoubleOptIn\Setup
 * @since   5.7.0
 */

declare( strict_types=1 );

namespace Forge12\DoubleOptIn\Setup;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FormDefaults {

	public const OPTION = 'f12_doi_form_defaults';

	/**
	 * @return array{sender: string, sender_name: string}
	 */
	public static function get(): array {
		$stored = get_option( self::OPTION, array() );
		$stored = is_array( $stored ) ? $stored : array();

		return array(
			'sender'      => isset( $stored['sender'] ) ? (string) $stored['sender'] : '',
			'sender_name' => isset( $stored['sender_name'] ) ? (string) $stored['sender_name'] : '',
		);
	}

	/**
	 * Store sanitized defaults. An invalid address is dropped, the name kept.
	 */
	public static function save( string $sender, string $senderName ): void {
		$sender = sanitize_email( $sender );

		update_option(
			self::OPTION,
			array(
				'sender'      => is_email( $sender ) ? $sender : '',
				'sender_name' => sanitize_text_field( $senderName ),
			),
			false
		);
	}

	public static function register(): void {
		add_filter( 'f12_cf7_doubleoptin_get_parameter', array( self::class, 'fillParameters' ) );
		add_filter( 'f12-cf7-doubleoptin-cf7-args', array( self::class, 'fillMailArgs' ) );
	}

	/**
	 * Defaults for a form without saved settings (legacy runtime path).
	 *
	 * @param mixed $data Parameter defaults before the form's own meta is merged in.
	 *
	 * @return mixed
	 */
	public static function fillParameters( $data ) {
		if ( ! is_array( $data ) ) {
			return $data;
		}
		return self::fill( $data, 'sender', 'sender_name' );
	}

	/**
	 * Fill an empty sender name at send time, so a form saved before the
	 * defaults existed does not go out as "WordPress".
	 *
	 * @param mixed $args CF7 mail arguments.
	 *
	 * @return mixed
	 */
	public static function fillMailArgs( $args ) {
		if ( ! is_array( $args ) ) {
			return $args;
		}
		$defaults = self::get();
		if ( trim( (string) ( $args['sender_name'] ?? '' ) ) === '' && $defaults['sender_name'] !== '' ) {
			$args['sender_name'] = $defaults['sender_name'];
		}
		return $args;
	}

	/**
	 * @param array<string, mixed> $data
	 *
	 * @return array<string, mixed>
	 */
	private static function fill( array $data, string $senderKey, string $nameKey ): array {
		$defaults = self::get();
		if ( $defaults['sender'] !== '' && self::isFallbackSender( (string) ( $data[ $senderKey ] ?? '' ) ) ) {
			$data[ $senderKey ] = $defaults['sender'];
		}
		if ( $defaults['sender_name'] !== '' && trim( (string) ( $data[ $nameKey ] ?? '' ) ) === '' ) {
			$data[ $nameKey ] = $defaults['sender_name'];
		}
		return $data;
	}

	/**
	 * Empty, or the admin address Core puts in when nothing was chosen.
	 */
	private static function isFallbackSender( string $sender ): bool {
		return $sender === '' || $sender === (string) get_bloginfo( 'admin_email' );
	}

	/**
	 * Apply the defaults to a new, unsaved settings object.
	 *
	 * @param string $sender     Current sender (the admin address by default).
	 * @param string $senderName Current sender name.
	 *
	 * @return array{0: string, 1: string}
	 */
	public static function forNewForm( string $sender, string $senderName ): array {
		$filled = self::fill(
			array(
				'sender'      => $sender,
				'sender_name' => $senderName,
			),
			'sender',
			'sender_name'
		);
		return array( (string) $filled['sender'], (string) $filled['sender_name'] );
	}
}
