<?php
/**
 * OptIn Link Generator Service
 *
 * @package Forge12\DoubleOptIn\Service
 * @since   4.0.0
 */

namespace Forge12\DoubleOptIn\Service;

use Forge12\DoubleOptIn\Entity\OptIn;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class OptInLinkGenerator
 *
 * Generates various links related to OptIn operations.
 */
class OptInLinkGenerator {

	/**
	 * Generate the opt-in confirmation link.
	 *
	 * @param OptIn $optIn     The OptIn entity.
	 * @param array $parameter Optional parameters (page, formUrl).
	 *
	 * @return string The confirmation URL.
	 */
	public function generateOptInLink( OptIn $optIn, array $parameter = [] ): string {
		if ( empty( $optIn->getHash() ) ) {
			return '';
		}

		$pageId = $parameter['page'] ?? -1;

		if ( $pageId > 0 ) {
			$url = get_permalink( $pageId );
		} elseif ( ! empty( $parameter['formUrl'] ) ) {
			$url = $parameter['formUrl'];
		} else {
			$url = home_url();
		}

		return add_query_arg( 'optin', $optIn->getHash(), $url );
	}

	/**
	 * Generate the opt-out link.
	 *
	 * @param OptIn $optIn The OptIn entity.
	 *
	 * @return string The opt-out URL.
	 */
	public function generateOptOutLink( OptIn $optIn ): string {
		if ( empty( $optIn->getHash() ) ) {
			return '';
		}

		$settings = \forge12\contactform7\CF7DoubleOptIn\CF7DoubleOptIn::getInstance()->getSettings();
		$pageId   = $settings['optout_page'] ?? -1;

		if ( $pageId > 0 ) {
			$url = get_permalink( $pageId );
		} else {
			$url = home_url();
		}

		return add_query_arg( 'optout', $optIn->getHash(), $url );
	}

	/**
	 * Generate the admin view link.
	 *
	 * @param OptIn $optIn The OptIn entity.
	 *
	 * @return string The admin view URL.
	 */
	public function generateAdminViewLink( OptIn $optIn ): string {
		if ( empty( $optIn->getHash() ) ) {
			return '';
		}

		return admin_url( 'admin.php?page=f12-cf7-doubleoptin_optin_view&hash=' . $optIn->getHash() );
	}

	/**
	 * Generate the admin delete link with nonce.
	 *
	 * @param OptIn $optIn The OptIn entity.
	 *
	 * @return string The admin delete URL with nonce.
	 */
	public function generateAdminDeleteLink( OptIn $optIn ): string {
		if ( empty( $optIn->getHash() ) ) {
			return '';
		}

		return wp_nonce_url(
			admin_url( 'admin.php?page=f12-cf7-doubleoptin_optins&option=delete&hash=' . $optIn->getHash() ),
			'doi-delete-optin-' . $optIn->getHash()
		);
	}

	/**
	 * Generate the export link.
	 *
	 * @param OptIn $optIn The OptIn entity.
	 *
	 * @return string The export URL.
	 */
	public function generateExportLink( OptIn $optIn ): string {
		if ( empty( $optIn->getHash() ) ) {
			return '';
		}

		return wp_nonce_url(
			admin_url( 'admin.php?page=f12-cf7-doubleoptin_optins&option=export&hash=' . $optIn->getHash() ),
			'doi-export-optin-' . $optIn->getHash()
		);
	}

	/**
	 * Add placeholder values to text.
	 *
	 * Replaces [doubleoptinlink], [doubleoptoutlink] etc. in text.
	 *
	 * @param string $text      The text with placeholders.
	 * @param OptIn  $optIn     The OptIn entity.
	 * @param array  $parameter Optional parameters.
	 *
	 * @return string The text with replaced placeholders.
	 */
	public function replacePlaceholders( string $text, OptIn $optIn, array $parameter = [] ): string {
		$replacements = [
			'[doubleoptinlink]'   => $this->generateOptInLink( $optIn, $parameter ),
			'[doubleoptoutlink]'  => $this->generateOptOutLink( $optIn ),
			'[doubleoptinhash]'   => $optIn->getHash(),
			'[doubleoptin_email]' => $optIn->getEmail(),
		];

		return str_replace(
			array_keys( $replacements ),
			array_values( $replacements ),
			$text
		);
	}
}
