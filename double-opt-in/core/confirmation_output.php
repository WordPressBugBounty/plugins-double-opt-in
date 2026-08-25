<?php
/**
 * A place to put something on the page a subscriber lands on after confirming.
 *
 * The plugin had none. Confirmation happens on an ordinary WordPress page via
 * `?optin=<hash>`; there is no endpoint of our own, no redirect, and on success
 * nothing at all is printed — deliberately, so the site owner's page is left
 * alone. That is a reasonable default and it stays the default: this file only
 * offers a hook, and prints nothing unless something is attached to it.
 *
 * Why a hook rather than direct output: the credit link is the first consumer,
 * but "the visitor just confirmed" is generally useful — a thank-you note, a
 * tracking pixel, a coupon. Anyone can attach to it without patching the
 * confirmation path, which is exactly what we had to avoid doing ourselves.
 */

namespace forge12\contactform7\CF7DoubleOptIn;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whether a confirmation happened in this request.
 *
 * A function-static rather than a global: the value is meaningless outside this
 * request and nothing else has any business writing it.
 *
 * @param bool|null $set Pass true to record a confirmation; null to read.
 *
 * @return bool
 */
function confirmation_happened( ?bool $set = null ): bool {
	static $confirmed = false;

	if ( $set === true ) {
		$confirmed = true;
	}

	return $confirmed;
}

/**
 * Remember the confirmation.
 *
 * `f12_cf7_doubleoptin_after_confirm` is the right signal because it is the one
 * both paths agree on: the modern integrations fire it in
 * AbstractFormIntegration::validateOptIn(), and the legacy OptInFrontend fires
 * it too. Hooking the integrations individually would have missed whichever one
 * is active on any given site — and CF7/Avada run the new path while Elementor
 * still runs the old one.
 *
 * It also only fires on an actual state change: an already-confirmed or expired
 * link does not reach it, so nothing is printed for those.
 */
function note_confirmation(): void {
	confirmation_happened( true );
}

add_action( 'f12_cf7_doubleoptin_after_confirm', __NAMESPACE__ . '\note_confirmation' );

/**
 * Print whatever is attached, at the end of the page.
 *
 * Runs on `wp_footer`, which is safely after the confirmation: every
 * integration handles `?optin=` on `init`.
 */
function render_confirmation_output(): void {
	if ( ! confirmation_happened() ) {
		return;
	}

	/**
	 * Filter the markup shown after a successful opt-in confirmation.
	 *
	 * Returning an empty string — the default — prints nothing at all.
	 *
	 * @param string $html Markup to print. Empty by default.
	 *
	 * @since 5.2.0
	 */
	$html = (string) apply_filters( 'f12_doi_confirmation_output', '' );

	if ( $html === '' ) {
		return;
	}

	// Escaped here as well as at the source. The filter is public, so this
	// output point is only as safe as the least careful thing hooked to it.
	echo wp_kses_post( $html );
}

add_action( 'wp_footer', __NAMESPACE__ . '\render_confirmation_output', 20 );
