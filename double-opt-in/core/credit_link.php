<?php
/**
 * The optional "Double Opt-In by Forge12" credit on the confirmation page.
 *
 * Off by default, and it has to stay that way. WordPress.org's plugin guidelines
 * are explicit: "All 'Powered By' or credit displays and links included in the
 * plugin code must be optional and default to *not* show on users' front-facing
 * websites", and the choice has to be made through "clearly stated and
 * understandable choices, not buried in the terms of use or documentation". A
 * pre-enabled credit is grounds for removal from the directory, so the default
 * below is not a preference — it is the condition for being listed at all.
 *
 * Kept in its own file rather than woven into the confirmation path: the feature
 * is one setting, one URL and one line of markup, and holding it together makes
 * it as easy to remove as it was to add. It attaches through the same public
 * filter a third party would use.
 */

namespace forge12\contactform7\CF7DoubleOptIn;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Setting key inside `f12-doi-settings`.
 */
const CREDIT_SETTING_KEY = 'credit_link';

/**
 * Whether the site owner has asked for the credit to be shown.
 *
 * Absent means off. Only an explicit 1 turns it on — the reverse of the
 * plugin's other defaults, deliberately.
 */
function is_credit_enabled(): bool {
	$settings = get_option( 'f12-doi-settings', array() );

	if ( ! is_array( $settings ) || ! isset( $settings[ CREDIT_SETTING_KEY ] ) ) {
		return false;
	}

	return (int) $settings[ CREDIT_SETTING_KEY ] === 1;
}

/**
 * Where the credit points.
 */
function get_credit_url(): string {
	/**
	 * Filter the destination of the credit link specifically.
	 *
	 * Kept alongside the general f12_doi_product_url filter, which has already
	 * run by this point: someone overriding where the credit on their
	 * confirmation page points should not have to special-case every other link
	 * to the product site as well.
	 *
	 * @param string $url The full URL including its query arguments.
	 *
	 * @since 5.2.0
	 */
	return (string) apply_filters( 'f12_doi_credit_url', get_product_url( 'confirmation-credit' ) );
}

/**
 * The credit markup, or an empty string when it is switched off.
 *
 * `rel="nofollow"` on purpose. The point of the link is that a curious site
 * owner can follow it, not that it passes ranking signals from sites whose
 * owners agreed to a small thank-you — claiming the latter would make every
 * installation look like a paid link scheme, which is precisely how search
 * engines describe links distributed through plugins.
 *
 * @param bool $force Render even when the setting is off. Only for the preview
 *                    in the admin notice: someone deciding whether to switch
 *                    this on is really asking "will it make my page look
 *                    cluttered", and the honest answer is to show them the
 *                    exact thing rather than describe it.
 *
 * @return string
 */
function get_credit_markup( bool $force = false ): string {
	if ( ! $force && ! is_credit_enabled() ) {
		return '';
	}

	$markup = sprintf(
		'<p class="f12-doi-credit"><a href="%s" target="_blank" rel="nofollow noopener">%s</a></p>',
		esc_url( get_credit_url() ),
		esc_html__( 'Double Opt-In by Forge12', 'double-opt-in' )
	);

	/**
	 * Filter the credit markup.
	 *
	 * @param string $markup The rendered credit, or '' when switched off.
	 * @param bool   $force  Whether rendering was forced for a preview.
	 *
	 * @since 5.2.0
	 */
	return (string) apply_filters( 'f12_doi_credit_markup', $markup, $force );
}

/**
 * Attach the credit to the confirmation page.
 *
 * Typed as mixed rather than string on purpose: this hangs on a public filter,
 * so another plugin earlier in the chain can hand over anything at all. Passing
 * that through untouched is better than breaking someone's page over a credit.
 *
 * @param mixed $html Whatever the confirmation output point has collected.
 *
 * @return mixed
 */
function append_credit( $html ) {
	if ( ! is_string( $html ) ) {
		return $html;
	}

	$credit = get_credit_markup();

	return $credit === '' ? $html : $html . $credit;
}

add_filter( 'f12_doi_confirmation_output', __NAMESPACE__ . '\append_credit', 20 );
