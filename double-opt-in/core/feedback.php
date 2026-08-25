<?php
/**
 * Where the plugin sends people who want to tell us something.
 *
 * One place for both destinations, because they are linked from five different
 * spots and a URL that has to be changed in five files is a URL that ends up
 * inconsistent.
 *
 * These addresses ship inside every installed copy and stay there until the site
 * updates, which for a WordPress plugin can be years. They have to be addresses
 * we can redirect later, not addresses that happen to point at today's helpdesk.
 */

namespace forge12\contactform7\CF7DoubleOptIn;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Base of the product site. No language segment — see product_lang().
 */
const PRODUCT_BASE = 'https://www.forge12.com';

/**
 * The product page, without a language segment.
 *
 * forge12.com prefixes the locale itself (a request to /shop/… lands on
 * /de/shop/…), so the short form survives whatever the site does with its
 * language routing later. That matters here more than elsewhere: this URL is
 * used for the plugin headers, and a `Plugin URI:` is a static string that
 * cannot carry any locale logic at all.
 */
const PRODUCT_PATH = '/shop/contact-form-7-double-opt-in';

/**
 * Feedback form — for "this could be better" and "this is broken for me".
 *
 * Deliberately without a language segment, unlike the support link below:
 * only the German page exists (`/de/shop/…/feedback`). Sending an English
 * speaker to a German page is a poor greeting; sending them to a 404 is worse.
 * Once `/en/shop/…/feedback` exists, this can take the same `%s` treatment as
 * SUPPORT_URL and the distinction disappears.
 */
const FEEDBACK_URL = PRODUCT_BASE . PRODUCT_PATH . '/feedback';

/**
 * Support — for "I need help with my installation".
 *
 * Exists in both languages, so this one does resolve the segment. May sit
 * behind the login, since accounts live on the same domain.
 */
const SUPPORT_URL = PRODUCT_BASE . '/%s/account/support';

/**
 * The language segment forge12.com expects.
 *
 * The site serves /de and /en. Shared by every link the plugin builds so they
 * cannot drift apart.
 *
 * @return string 'de' or 'en'.
 */
function product_lang(): string {
	$locale = function_exists( 'determine_locale' ) ? determine_locale() : get_locale();

	return strpos( (string) $locale, 'de' ) === 0 ? 'de' : 'en';
}

/**
 * Query arguments every outbound link carries.
 *
 * The plugin version and nothing else: enough to tell a report about 5.1 from
 * one about 4.0, without saying anything about the site it came from. The
 * `from` value names the entry point so we can see which of them people
 * actually use.
 *
 * @param string $from Which entry point the click came from (e.g. 'review-notice').
 *
 * @return array<string, string>
 */
function link_args( string $from = '' ): array {
	$args = array( 'v' => defined( 'FORGE12_OPTIN_VERSION' ) ? FORGE12_OPTIN_VERSION : '' );

	if ( $from !== '' ) {
		$args['from'] = $from;
	}

	return $args;
}

/**
 * Build a link to the product page.
 *
 * @param string $from Which entry point the click came from.
 *
 * @return string
 */
function get_product_url( string $from = '' ): string {
	$url = add_query_arg( link_args( $from ), PRODUCT_BASE . PRODUCT_PATH );

	/**
	 * Filter a link to the product site.
	 *
	 * @param string $url  The full URL including its query arguments.
	 * @param string $from Which entry point this is.
	 *
	 * @since 5.2.0
	 */
	return (string) apply_filters( 'f12_doi_product_url', $url, $from );
}

/**
 * Build a feedback link.
 *
 * @param string $from Which entry point the click came from.
 *
 * @return string
 */
function get_feedback_url( string $from = '' ): string {
	$url = add_query_arg( link_args( $from ), FEEDBACK_URL );

	/**
	 * Filter the feedback destination.
	 *
	 * @param string $url  The full URL including its query arguments.
	 * @param string $from Which entry point this is.
	 *
	 * @since 5.2.0
	 */
	return (string) apply_filters( 'f12_doi_feedback_url', $url, $from );
}

/**
 * Build a support link. Same rules as get_feedback_url().
 *
 * @param string $from Which entry point the click came from.
 *
 * @return string
 */
function get_support_url( string $from = '' ): string {
	$url = add_query_arg( link_args( $from ), sprintf( SUPPORT_URL, product_lang() ) );

	/**
	 * Filter the support destination.
	 *
	 * @param string $url  The full URL including its query arguments.
	 * @param string $from Which entry point this is.
	 *
	 * @since 5.2.0
	 */
	return (string) apply_filters( 'f12_doi_support_url', $url, $from );
}
