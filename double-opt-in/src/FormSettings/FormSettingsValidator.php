<?php
/**
 * Form Settings Validator
 *
 * @package Forge12\DoubleOptIn\FormSettings
 * @since   4.1.0
 */

namespace Forge12\DoubleOptIn\FormSettings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FormSettingsValidator
 *
 * Validates form settings before saving.
 */
class FormSettingsValidator {

	/**
	 * Validate form settings.
	 *
	 * @param FormSettingsDTO $settings The settings to validate.
	 *
	 * @return array Array of validation errors. Empty if valid.
	 */
	public function validate( FormSettingsDTO $settings ): array {
		$errors = array();

		// Only validate if enabled
		if ( ! $settings->enabled ) {
			return $errors;
		}

		// Hart-required fields — delegate to the DTO so save-time and
		// completeness-gate share one source of truth (plan/doi-completeness-gate.md §2.1).
		// The DTO returns stable string IDs; we map them to translatable messages here.
		foreach ( $settings->getMissingRequiredFields() as $field ) {
			$errors[ $field ] = $this->messageForMissingField( $field );
		}

		// Format-only checks live in the Validator (the DTO is shape-only,
		// no I/O — page existence and category lookups need wpdb).

		// Validate sender email format (soft-required: empty is OK,
		// falls back to WP admin email at runtime — plan §5.3)
		if ( ! empty( $settings->sender ) && ! $this->isValidEmailOrPlaceholder( $settings->sender ) ) {
			$errors['sender'] = __( 'Invalid sender email format.', 'double-opt-in' );
		}

		// Validate confirmation page
		if ( $settings->confirmationPage > 0 ) {
			$page = get_post( $settings->confirmationPage );
			if ( ! $page || $page->post_type !== 'page' ) {
				$errors['confirmationPage'] = __( 'Invalid confirmation page selected.', 'double-opt-in' );
			}
		}

		// Validate error redirect page
		if ( $settings->errorRedirectPage > 0 ) {
			$page = get_post( $settings->errorRedirectPage );
			if ( ! $page || $page->post_type !== 'page' ) {
				$errors['errorRedirectPage'] = __( 'Invalid error redirect page selected.', 'double-opt-in' );
			}
		}

		// Validate category
		if ( $settings->category > 0 ) {
			$category = \forge12\contactform7\CF7DoubleOptIn\Category::get_by_id( $settings->category );
			if ( ! $category ) {
				$errors['category'] = __( 'Invalid category selected.', 'double-opt-in' );
			}
		}

		return $errors;
	}

	/**
	 * Sanitize form settings.
	 *
	 * @param array $data Raw input data.
	 *
	 * @return FormSettingsDTO Sanitized settings DTO.
	 */
	public function sanitize( array $data ): FormSettingsDTO {
		$dto = new FormSettingsDTO();

		$dto->enabled = ! empty( $data['enabled'] ) || ! empty( $data['enable'] );

		$dto->sender = isset( $data['sender'] )
			? $this->sanitizeEmailOrPlaceholder( $data['sender'] )
			: '';

		$dto->senderName = isset( $data['senderName'] ) || isset( $data['sender_name'] )
			? sanitize_text_field( $data['senderName'] ?? $data['sender_name'] )
			: '';

		$dto->subject = isset( $data['subject'] )
			? sanitize_text_field( $data['subject'] )
			: '';

		$dto->body = isset( $data['body'] )
			? $this->sanitizeBody( $data['body'] )
			: '';

		$dto->recipient = isset( $data['recipient'] )
			? sanitize_text_field( $data['recipient'] )
			: '';

		$dto->confirmationPage = isset( $data['confirmationPage'] ) || isset( $data['page'] )
			? (int) ( $data['confirmationPage'] ?? $data['page'] )
			: -1;

		$dto->errorRedirectPage = isset( $data['errorRedirectPage'] ) || isset( $data['error_page'] )
			? (int) ( $data['errorRedirectPage'] ?? $data['error_page'] )
			: -1;

		$dto->conditions = isset( $data['conditions'] )
			? sanitize_text_field( $data['conditions'] )
			: 'disabled';

		$dto->template = isset( $data['template'] )
			? sanitize_text_field( $data['template'] )
			: '';

		$dto->category = isset( $data['category'] )
			? absint( $data['category'] )
			: 0;

		$dto->consentText = isset( $data['consentText'] ) || isset( $data['consent_text'] )
			? sanitize_textarea_field( $data['consentText'] ?? $data['consent_text'] )
			: '';

		// Consent acknowledgment field — name of the form field that
		// captures the user's explicit consent (e.g. CF7 [acceptance]).
		// Stored as a sanitize_key string since it must match a real
		// form-field name at submit time.
		$dto->consentField = isset( $data['consentField'] ) || isset( $data['consent_field'] )
			? sanitize_key( (string) ( $data['consentField'] ?? $data['consent_field'] ) )
			: '';

		// Field-mapping (placeholder-tag → form-field-name) for the
		// Mapping tab. Sanitize each key + value to text-safe strings
		// since both end up in the email body / database meta.
		$rawMapping = $data['fieldMapping'] ?? $data['field_mapping'] ?? array();
		if ( is_array( $rawMapping ) ) {
			$mapping = array();
			foreach ( $rawMapping as $key => $value ) {
				$mapping[ sanitize_key( (string) $key ) ] = sanitize_text_field( (string) $value );
			}
			$dto->fieldMapping = $mapping;
		}

		/**
		 * Filter to allow addons to sanitize and contribute their own
		 * extensionData fields. Mirrors `f12_doi_settings_dto_from_array`
		 * (load-side) so save and load are symmetric — both go through
		 * the same addon-side filter chain. An addon that registers one
		 * filter without the other silently loses data on roundtrip.
		 *
		 * @since 4.4.0
		 *
		 * @param FormSettingsDTO $dto  The DTO populated with Core fields.
		 * @param array           $data The raw input array.
		 */
		$dto = apply_filters( 'f12_doi_settings_dto_sanitize', $dto, $data );

		return $dto;
	}

	/**
	 * Map a missing-required-field ID (as returned by
	 * {@see FormSettingsDTO::getMissingRequiredFields()}) to a translatable
	 * user-facing error message.
	 *
	 * Addon-contributed IDs fall through to a generic message; addons
	 * that need bespoke wording should filter the messages array via
	 * `f12_doi_form_missing_field_messages` (registered at apply_filters
	 * time below).
	 */
	private function messageForMissingField( string $field ): string {
		$messages = apply_filters(
			'f12_doi_form_missing_field_messages',
			array(
				'recipient'        => __( 'Recipient field is required.', 'double-opt-in' ),
				'subject'          => __( 'Email subject is required.', 'double-opt-in' ),
				'body_or_template' => __( 'Email body must contain the [doubleoptinlink] placeholder or a template must be selected.', 'double-opt-in' ),
			)
		);

		return $messages[ $field ] ?? __( 'Required field is missing.', 'double-opt-in' );
	}

	/**
	 * Check if a value is a valid email or placeholder.
	 *
	 * @param string $value The value to check.
	 *
	 * @return bool True if valid.
	 */
	private function isValidEmailOrPlaceholder( string $value ): bool {
		// Check for placeholder format [field_name]
		if ( preg_match( '/^\[.+\]$/', $value ) ) {
			return true;
		}

		return is_email( $value ) !== false;
	}

	/**
	 * Sanitize an email or placeholder value.
	 *
	 * @param string $value The value to sanitize.
	 *
	 * @return string Sanitized value.
	 */
	private function sanitizeEmailOrPlaceholder( string $value ): string {
		$value = trim( $value );

		// If it's a placeholder, sanitize as text
		if ( preg_match( '/^\[.+\]$/', $value ) ) {
			return sanitize_text_field( $value );
		}

		// Otherwise sanitize as email
		$email = sanitize_email( $value );
		return $email ?: $value;
	}

	/**
	 * Sanitize email body content.
	 *
	 * Allows HTML but removes dangerous content.
	 *
	 * @param string $body The body content.
	 *
	 * @return string Sanitized body.
	 */
	private function sanitizeBody( string $body ): string {
		// Allow HTML tags used in email templates
		$allowedHtml = wp_kses_allowed_html( 'post' );

		// Add additional tags commonly used in emails
		$allowedHtml['style']  = array();
		$allowedHtml['center'] = array();
		$allowedHtml['table']  = array(
			'class'       => true,
			'id'          => true,
			'style'       => true,
			'width'       => true,
			'height'      => true,
			'cellpadding' => true,
			'cellspacing' => true,
			'border'      => true,
			'align'       => true,
			'bgcolor'     => true,
		);
		$allowedHtml['tr']     = array(
			'class'  => true,
			'style'  => true,
			'align'  => true,
			'valign' => true,
		);
		$allowedHtml['td']     = array(
			'class'   => true,
			'style'   => true,
			'width'   => true,
			'height'  => true,
			'align'   => true,
			'valign'  => true,
			'bgcolor' => true,
			'colspan' => true,
			'rowspan' => true,
		);
		$allowedHtml['th']     = $allowedHtml['td'];
		$allowedHtml['thead']  = array();
		$allowedHtml['tbody']  = array();
		$allowedHtml['tfoot']  = array();

		return wp_kses( $body, $allowedHtml );
	}
}
