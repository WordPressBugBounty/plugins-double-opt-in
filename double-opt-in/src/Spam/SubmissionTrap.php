<?php
/**
 * Honeypot and minimum fill time for double opt-in forms.
 *
 * A double opt-in form is a tool for mail bombing: a bot enters someone
 * else's address and the site sends that person a confirmation mail. The
 * rate limit caps the damage; these two traps stop most bots before any
 * mail is sent, without a captcha. They also protect the reputation of the
 * site's sender domain.
 *
 * Both fields start with an underscore, so CF7 keeps them out of the posted
 * data — they never reach the stored opt-in or a mail. A submission without
 * the fields (cached HTML from before the update, custom markup) passes: a
 * trap that blocked real visitors would be worse than none.
 *
 * @package Forge12\DoubleOptIn\Spam
 * @since   5.8.0
 */

declare( strict_types=1 );

namespace Forge12\DoubleOptIn\Spam;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SubmissionTrap {

	public const HONEYPOT = '_f12_doi_hp';

	public const STAMP = '_f12_doi_ts';

	public const DEFAULT_MIN_SECONDS = 2;

	/**
	 * The hidden fields, rendered into the form.
	 */
	public static function markup( int $now ): string {
		return '<div class="f12-doi-hp" aria-hidden="true" style="position:absolute!important;left:-10000px!important;top:auto!important;width:1px!important;height:1px!important;overflow:hidden!important;">'
			. '<label>' . esc_html__( 'Leave this field empty', 'double-opt-in' )
			. ' <input type="text" name="' . esc_attr( self::HONEYPOT ) . '" value="" tabindex="-1" autocomplete="off" /></label>'
			. '</div>'
			. '<input type="hidden" name="' . esc_attr( self::STAMP ) . '" value="' . esc_attr( self::stamp( $now ) ) . '" />';
	}

	/**
	 * `<time>.<signature>` — the signature keeps a bot from sending a
	 * made-up, old enough time.
	 */
	public static function stamp( int $time ): string {
		return $time . '.' . self::sign( $time );
	}

	/**
	 * Why a submission is a bot, or '' when it passes.
	 *
	 * @param array<string, mixed> $post       Raw request fields.
	 * @param int                  $now        Current time.
	 * @param int                  $minSeconds Minimum time between render and submit.
	 *
	 * @return string '' | 'honeypot' | 'stamp_invalid' | 'too_fast'
	 */
	public static function check( array $post, int $now, int $minSeconds ): string {
		if ( isset( $post[ self::HONEYPOT ] ) && ( ! is_string( $post[ self::HONEYPOT ] ) || trim( $post[ self::HONEYPOT ] ) !== '' ) ) {
			return 'honeypot';
		}

		if ( ! isset( $post[ self::STAMP ] ) ) {
			return '';
		}

		$stamp = is_string( $post[ self::STAMP ] ) ? $post[ self::STAMP ] : '';
		if ( ! preg_match( '/^(\d{9,11})\.([a-f0-9]{16})$/', $stamp, $m ) ) {
			return 'stamp_invalid';
		}

		$time = (int) $m[1];
		if ( ! hash_equals( self::sign( $time ), $m[2] ) || $time > $now + 60 ) {
			return 'stamp_invalid';
		}

		return $now - $time < $minSeconds ? 'too_fast' : '';
	}

	private static function sign( int $time ): string {
		return substr( wp_hash( 'f12_doi_submission_trap|' . $time ), 0, 16 );
	}
}
