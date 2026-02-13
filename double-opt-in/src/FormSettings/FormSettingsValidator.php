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
		$errors = [];

		// Only validate if enabled
		if ( ! $settings->enabled ) {
			return $errors;
		}

		// Validate sender email
		if ( ! empty( $settings->sender ) && ! $this->isValidEmailOrPlaceholder( $settings->sender ) ) {
			$errors['sender'] = __( 'Invalid sender email format.', 'double-opt-in' );
		}

		// Validate recipient field
		if ( empty( $settings->recipient ) ) {
			$errors['recipient'] = __( 'Recipient field is required.', 'double-opt-in' );
		}

		// Validate subject
		if ( empty( $settings->subject ) ) {
			$errors['subject'] = __( 'Email subject is required.', 'double-opt-in' );
		}

		// Validate body contains opt-in link (skip when a custom template is used,
		// because the template's block structure contains the placeholder)
		if ( empty( $settings->template ) && ! empty( $settings->body ) && strpos( $settings->body, '[doubleoptinlink]' ) === false ) {
			$errors['body'] = __( 'Email body must contain the [doubleoptinlink] placeholder.', 'double-opt-in' );
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

		// Unique Email settings (Pro)
		$dto->extensionData['unique_email_enabled']       = (int) ( $data['unique_email_enabled'] ?? 0 );
		$dto->extensionData['unique_email_behavior']      = in_array( $v = (string) ( $data['unique_email_behavior'] ?? '' ), [ 'silent', 'block', 'redirect' ], true ) ? $v : 'block';
		$dto->extensionData['unique_email_scope']         = in_array( $v = (string) ( $data['unique_email_scope'] ?? '' ), [ 'confirmed', 'all' ], true ) ? $v : 'confirmed';
		$dto->extensionData['unique_email_message']       = sanitize_text_field( (string) ( $data['unique_email_message'] ?? '' ) );
		$dto->extensionData['unique_email_redirect_page'] = (int) ( $data['unique_email_redirect_page'] ?? -1 );

		return $dto;
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
		$allowedHtml['style'] = [];
		$allowedHtml['center'] = [];
		$allowedHtml['table'] = [
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
		];
		$allowedHtml['tr'] = [
			'class' => true,
			'style' => true,
			'align' => true,
			'valign' => true,
		];
		$allowedHtml['td'] = [
			'class'   => true,
			'style'   => true,
			'width'   => true,
			'height'  => true,
			'align'   => true,
			'valign'  => true,
			'bgcolor' => true,
			'colspan' => true,
			'rowspan' => true,
		];
		$allowedHtml['th'] = $allowedHtml['td'];
		$allowedHtml['thead'] = [];
		$allowedHtml['tbody'] = [];
		$allowedHtml['tfoot'] = [];

		return wp_kses( $body, $allowedHtml );
	}
}
