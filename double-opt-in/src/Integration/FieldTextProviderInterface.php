<?php
/**
 * Optional for form integrations: the text a visitor reads next to a field.
 *
 * The consent text stored with each opt-in is typed in by hand. When it no
 * longer matches the wording next to the form's checkbox, the proof records
 * a sentence the visitor never saw. With this text the form settings can
 * say so and copy the real wording over.
 *
 * Optional on purpose: integrations that do not implement it lose nothing,
 * and add-ons can also answer through the `f12_doi_form_field_texts` filter.
 *
 * @package Forge12\DoubleOptIn\Integration
 * @since   5.9.0
 */

declare( strict_types=1 );

namespace Forge12\DoubleOptIn\Integration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface FieldTextProviderInterface {

	/**
	 * Visible text per field name, plain text. Fields without one are left out.
	 *
	 * @param int|string $formId The form ID (composite for Elementor).
	 *
	 * @return array<string, string>
	 */
	public function getFieldTexts( $formId ): array;
}
