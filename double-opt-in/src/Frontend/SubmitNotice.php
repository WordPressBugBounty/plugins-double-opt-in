<?php
/**
 * What the visitor sees right after submitting a double opt-in form.
 *
 * The form plugin's own success message says the message "has been sent",
 * which is wrong until the address is confirmed. This builds the honest
 * version: where the confirmation mail went (masked), what to look for, and
 * — for the big webmail providers — a link straight into the inbox.
 *
 * Plain links from a local list; nothing is requested from anyone.
 *
 * @package Forge12\DoubleOptIn\Frontend
 * @since   5.8.0
 */

declare( strict_types=1 );

namespace Forge12\DoubleOptIn\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SubmitNotice {

	/**
	 * Webmail providers by mail domain. `search` (optional) takes the
	 * url-encoded query in place of `%s`.
	 *
	 * @return array<string, array{name: string, url: string, search?: string}>
	 */
	public static function providers(): array {
		$gmail   = array(
			'name'   => 'Gmail',
			'url'    => 'https://mail.google.com/mail/u/0/',
			'search' => 'https://mail.google.com/mail/u/0/#search/%s',
		);
		$outlook = array(
			'name' => 'Outlook',
			'url'  => 'https://outlook.live.com/mail/0/',
		);
		$gmx     = array(
			'name' => 'GMX',
			'url'  => 'https://www.gmx.net/',
		);
		$yahoo   = array(
			'name' => 'Yahoo Mail',
			'url'  => 'https://mail.yahoo.com/',
		);
		$icloud  = array(
			'name' => 'iCloud Mail',
			'url'  => 'https://www.icloud.com/mail',
		);

		$providers = array(
			'gmail.com'      => $gmail,
			'googlemail.com' => $gmail,
			'outlook.com'    => $outlook,
			'outlook.de'     => $outlook,
			'hotmail.com'    => $outlook,
			'hotmail.de'     => $outlook,
			'live.com'       => $outlook,
			'live.de'        => $outlook,
			'msn.com'        => $outlook,
			'gmx.de'         => $gmx,
			'gmx.net'        => $gmx,
			'gmx.at'         => $gmx,
			'gmx.ch'         => $gmx,
			'web.de'         => array(
				'name' => 'WEB.DE',
				'url'  => 'https://web.de/',
			),
			't-online.de'    => array(
				'name' => 'T-Online',
				'url'  => 'https://email.t-online.de/',
			),
			'yahoo.com'      => $yahoo,
			'yahoo.de'       => $yahoo,
			'ymail.com'      => $yahoo,
			'icloud.com'     => $icloud,
			'me.com'         => $icloud,
			'mac.com'        => $icloud,
		);

		/**
		 * Webmail providers offered as "open your inbox" links after a
		 * double opt-in submission, keyed by lower-case mail domain.
		 *
		 * @since 5.8.0
		 *
		 * @param array $providers domain => array{name, url, search?}.
		 */
		$filtered = apply_filters( 'f12_doi_webmail_providers', $providers );

		return is_array( $filtered ) ? $filtered : $providers;
	}

	/**
	 * `anna.mueller@gmx.de` → `a***@gmx.de`. The visitor sees which address
	 * they typed without the page echoing it in full.
	 */
	public static function mask( string $email ): string {
		$at = strrpos( $email, '@' );
		if ( $at === false || $at === 0 ) {
			return '';
		}

		return substr( $email, 0, 1 ) . '***' . substr( $email, $at );
	}

	/**
	 * The provider for an address, with a search link when the provider
	 * supports one and the sender is known.
	 *
	 * @return array{name: string, url: string}|null
	 */
	public static function inboxFor( string $email, string $sender = '' ): ?array {
		$at = strrpos( $email, '@' );
		if ( $at === false ) {
			return null;
		}

		$domain    = strtolower( trim( substr( $email, $at + 1 ) ) );
		$providers = self::providers();
		if ( ! isset( $providers[ $domain ] ) || ! is_array( $providers[ $domain ] ) ) {
			return null;
		}

		$provider = $providers[ $domain ];
		$name     = (string) ( $provider['name'] ?? '' );
		$url      = (string) ( $provider['url'] ?? '' );
		if ( $name === '' || strpos( $url, 'https://' ) !== 0 ) {
			return null;
		}

		$search = (string) ( $provider['search'] ?? '' );
		if ( $search !== '' && is_email( $sender ) ) {
			// in:anywhere — Gmail's plain search skips Spam, where these mails end up.
			$url = sprintf( $search, rawurlencode( 'from:' . $sender . ' in:anywhere' ) );
		}

		return array(
			'name' => $name,
			'url'  => $url,
		);
	}

	/**
	 * The bare address out of a From value such as `Shop <info@shop.test>`.
	 */
	public static function senderAddress( string $from ): string {
		if ( preg_match( '/<([^>]+)>/', $from, $m ) ) {
			$from = $m[1];
		}
		$from = trim( $from );

		return is_email( $from ) ? $from : '';
	}

	/**
	 * Everything the frontend script renders, translated here.
	 *
	 * @return array{masked: string, lines: string[], inbox: array{name: string, url: string, label: string}|null}
	 */
	public static function build( string $email, string $sender = '', string $subject = '' ): array {
		$masked = self::mask( $email );
		$lines  = array();

		if ( $sender !== '' && $subject !== '' ) {
			$lines[] = sprintf(
				/* translators: 1: sender address, 2: mail subject */
				__( 'Look for a mail from %1$s with the subject “%2$s”. If it is not in your inbox, check the spam folder.', 'double-opt-in' ),
				$sender,
				$subject
			);
		} else {
			$lines[] = __( 'If it is not in your inbox, check the spam folder.', 'double-opt-in' );
		}
		$lines[] = __( 'Typo in the address? Just fill in the form again.', 'double-opt-in' );

		$inbox = self::inboxFor( $email, $sender );
		if ( $inbox !== null ) {
			$inbox['label'] = sprintf(
				/* translators: %s: webmail provider, e.g. Gmail */
				__( 'Open %s', 'double-opt-in' ),
				$inbox['name']
			);
		}

		return array(
			'masked' => $masked,
			'lines'  => $lines,
			'inbox'  => $inbox,
		);
	}

	/**
	 * Hand the notice to extensions, then keep only what the script can
	 * render safely.
	 *
	 * The context names the opt-in so an add-on can act on it (addon-reminder
	 * offers "send it again"); the visitor's browser never sees the id or
	 * the confirmation hash — an add-on that needs to refer back to the
	 * opt-in signs its own token into the action's `data`.
	 *
	 * @param array{masked: string, lines: string[], inbox: array<string, string>|null} $notice  From build().
	 * @param array{form_id: int, optin_id: int, integration: string}                    $context Who submitted what.
	 *
	 * @return array{masked: string, lines: string[], inbox: array<string, string>|null, actions: array<int, array<string, mixed>>}
	 */
	public static function extend( array $notice, array $context ): array {
		$notice['actions'] = array();

		/**
		 * The confirmation hint after a double opt-in submission, before it
		 * goes to the browser.
		 *
		 * `actions` takes links (`type` link, `url` https) and buttons
		 * (`type` button); a button fires the DOM event
		 * `f12-doi-notice-action` with its `id` and `data` when clicked and
		 * can stay disabled for `wait` seconds.
		 *
		 * @since 5.8.0
		 *
		 * @param array $notice  masked, lines, inbox, actions.
		 * @param array $context form_id, optin_id, integration — server side only.
		 */
		$filtered = apply_filters( 'f12_doi_submit_notice_data', $notice, $context );
		if ( ! is_array( $filtered ) ) {
			return $notice;
		}

		$lines = array();
		foreach ( (array) ( $filtered['lines'] ?? array() ) as $line ) {
			if ( is_scalar( $line ) && (string) $line !== '' ) {
				$lines[] = (string) $line;
			}
		}

		return array(
			'masked'  => $notice['masked'],
			'lines'   => $lines,
			'inbox'   => $notice['inbox'],
			'actions' => self::actions( $filtered['actions'] ?? array() ),
		);
	}

	/**
	 * @param mixed $actions As returned by the filter.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function actions( $actions ): array {
		$clean = array();

		foreach ( is_array( $actions ) ? $actions : array() as $action ) {
			if ( ! is_array( $action ) ) {
				continue;
			}

			$id    = sanitize_key( (string) ( $action['id'] ?? '' ) );
			$label = is_scalar( $action['label'] ?? null ) ? trim( (string) $action['label'] ) : '';
			$type  = ( $action['type'] ?? '' ) === 'link' ? 'link' : 'button';
			if ( $id === '' || $label === '' ) {
				continue;
			}

			$entry = array(
				'id'    => $id,
				'type'  => $type,
				'label' => $label,
			);

			if ( $type === 'link' ) {
				$url = (string) ( $action['url'] ?? '' );
				if ( strpos( $url, 'https://' ) !== 0 ) {
					continue;
				}
				$entry['url'] = $url;
			} else {
				$entry['wait'] = max( 0, min( 600, (int) ( $action['wait'] ?? 0 ) ) );
				$entry['data'] = array();
				foreach ( (array) ( $action['data'] ?? array() ) as $key => $value ) {
					if ( is_scalar( $value ) ) {
						$entry['data'][ sanitize_key( (string) $key ) ] = $value;
					}
				}
			}

			$clean[] = $entry;
		}

		return $clean;
	}

	/**
	 * The success message that replaces the form plugin's default "sent".
	 */
	public static function message( string $masked ): string {
		if ( $masked === '' ) {
			return __( 'Almost done: please confirm your address. We have sent you a mail with a confirmation link.', 'double-opt-in' );
		}

		return sprintf(
			/* translators: %s: masked email address, e.g. a***@gmx.de */
			__( 'Almost done: please confirm your address. We have sent a confirmation link to %s.', 'double-opt-in' ),
			$masked
		);
	}
}
