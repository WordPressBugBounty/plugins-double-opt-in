<?php
/**
 * Form Settings Data Transfer Object
 *
 * @package Forge12\DoubleOptIn\FormSettings
 * @since   4.1.0
 */

namespace Forge12\DoubleOptIn\FormSettings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FormSettingsDTO
 *
 * Data Transfer Object for form settings.
 * Provides a type-safe way to handle form configuration data.
 */
class FormSettingsDTO {

	/**
	 * Whether double opt-in is enabled for this form.
	 *
	 * @var bool
	 */
	public bool $enabled = false;

	/**
	 * The sender email address.
	 *
	 * @var string
	 */
	public string $sender = '';

	/**
	 * The sender name.
	 *
	 * @var string
	 */
	public string $senderName = '';

	/**
	 * The email subject.
	 *
	 * @var string
	 */
	public string $subject = '';

	/**
	 * The email body content.
	 *
	 * @var string
	 */
	public string $body = '';

	/**
	 * The recipient field name.
	 *
	 * @var string
	 */
	public string $recipient = '';

	/**
	 * The confirmation page ID.
	 *
	 * @var int
	 */
	public int $confirmationPage = -1;

	/**
	 * The error redirect page ID.
	 * When set to a valid page ID, users are redirected there on OptIn errors
	 * instead of seeing the toast notification.
	 *
	 * @var int
	 */
	public int $errorRedirectPage = -1;

	/**
	 * The dynamic condition field.
	 *
	 * @var string
	 */
	public string $conditions = 'disabled';

	/**
	 * The email template name.
	 *
	 * @var string
	 */
	public string $template = '';

	/**
	 * The category ID.
	 *
	 * @var int
	 */
	public int $category = 0;

	/**
	 * The consent text (GDPR). Wording the user is asked to agree to.
	 *
	 * @var string
	 */
	public string $consentText = '';

	/**
	 * Form-field name that captures the user's explicit consent
	 * acknowledgment (typically a CF7 [acceptance] checkbox). Together
	 * with `consentText` this forms a GDPR Art. 7 evidence chain:
	 *
	 *  - `consentText`  — the wording the user was shown
	 *  - `consentField` — which form field they had to actively confirm
	 *  - `content[consentField]` — the truthy/falsy value at submit time
	 *
	 * Empty string means "no acknowledgment gate" — the consent_text is
	 * stored without a corresponding acknowledgment field. Backward-compat
	 * default; not GDPR-compliant on its own.
	 *
	 * @var string
	 */
	public string $consentField = '';

	/**
	 * Field mapping for standard placeholders.
	 * Maps doi_* placeholders to actual form field names.
	 * Example: ['doi_email' => 'your-email', 'doi_name' => 'full-name']
	 *
	 * @var array
	 */
	public array $fieldMapping = array();

	/**
	 * Additional settings from extensions (e.g., Pro version).
	 *
	 * @var array
	 */
	public array $extensionData = array();

	/**
	 * Create a DTO from an array.
	 *
	 * @param array $data The data array.
	 *
	 * @return self
	 */
	public static function fromArray( array $data ): self {
		$dto = new self();

		$dto->enabled           = (bool) ( $data['enable'] ?? $data['enabled'] ?? false );
		$dto->sender            = (string) ( $data['sender'] ?? '' );
		$dto->senderName        = (string) ( $data['sender_name'] ?? $data['senderName'] ?? '' );
		$dto->subject           = (string) ( $data['subject'] ?? '' );
		$dto->body              = (string) ( $data['body'] ?? '' );
		$dto->recipient         = (string) ( $data['recipient'] ?? '' );
		$dto->confirmationPage  = (int) ( $data['page'] ?? $data['confirmationPage'] ?? -1 );
		$dto->errorRedirectPage = (int) ( $data['error_page'] ?? $data['errorRedirectPage'] ?? -1 );
		$dto->conditions        = (string) ( $data['conditions'] ?? 'disabled' );
		$dto->template          = (string) ( $data['template'] ?? '' );
		$dto->category          = (int) ( $data['category'] ?? 0 );
		$dto->consentText       = (string) ( $data['consent_text'] ?? $data['consentText'] ?? '' );
		$dto->fieldMapping      = (array) ( $data['field_mapping'] ?? $data['fieldMapping'] ?? array() );
		$dto->consentField      = (string) ( $data['consent_field'] ?? $data['consentField'] ?? '' );

		// Addon-contributed extension fields land here via the filter
		// below. Each Pro addon hooks `f12_doi_settings_dto_from_array`
		// and writes its own keys into `$dto->extensionData[...]`. Core
		// itself ships zero hardcoded extension fields — addons that
		// aren't loaded leave their keys absent, and the React form
		// renderers fall back to defaults via `?? value` patterns.
		//
		// Example consumer: addon-unique-email contributes
		// `unique_email_enabled`, `unique_email_behavior`,
		// `unique_email_scope`, `unique_email_message`,
		// `unique_email_redirect_page` from its UniqueEmailAddon::boot().

		/**
		 * Filter to allow addons to add additional fields to the DTO.
		 *
		 * @param FormSettingsDTO $dto  The DTO instance.
		 * @param array           $data The raw data array.
		 *
		 * @since 4.1.0
		 */
		$dto = apply_filters( 'f12_doi_settings_dto_from_array', $dto, $data );

		return $dto;
	}

	/**
	 * Convert the DTO to an array.
	 *
	 * Uses the legacy key names for backward compatibility with post_meta storage.
	 *
	 * @return array
	 */
	public function toArray(): array {
		$array = array(
			'enable'        => $this->enabled ? 1 : 0,
			'sender'        => $this->sender,
			'sender_name'   => $this->senderName,
			'subject'       => $this->subject,
			'body'          => $this->body,
			'recipient'     => $this->recipient,
			'page'          => $this->confirmationPage,
			'error_page'    => $this->errorRedirectPage,
			'conditions'    => $this->conditions,
			'template'      => $this->template,
			'category'      => $this->category,
			'consent_text'  => $this->consentText,
			'consent_field' => $this->consentField,
			'field_mapping' => $this->fieldMapping,
		);

		// Merge extension data
		$array = array_merge( $array, $this->extensionData );

		/**
		 * Filter to allow extensions (e.g., Pro version) to add additional fields to the array.
		 *
		 * @param array           $array The array representation.
		 * @param FormSettingsDTO $dto   The DTO instance.
		 *
		 * @since 4.1.0
		 */
		return apply_filters( 'f12_doi_settings_dto_to_array', $array, $this );
	}

	/**
	 * Convert the DTO to a camelCase array.
	 *
	 * Useful for JSON API responses.
	 *
	 * @return array
	 */
	public function toCamelCaseArray(): array {
		$array = array(
			'enabled'           => $this->enabled,
			'sender'            => $this->sender,
			'senderName'        => $this->senderName,
			'subject'           => $this->subject,
			'body'              => $this->body,
			'recipient'         => $this->recipient,
			'confirmationPage'  => $this->confirmationPage,
			'errorRedirectPage' => $this->errorRedirectPage,
			'conditions'        => $this->conditions,
			'template'          => $this->template,
			'category'          => $this->category,
			'consentText'       => $this->consentText,
			'consentField'      => $this->consentField,
			'fieldMapping'      => $this->fieldMapping,
		);

		// Merge extension data
		$array = array_merge( $array, $this->extensionData );

		/**
		 * Filter to allow extensions (e.g., Pro version) to add additional fields to the camelCase array.
		 *
		 * @param array           $array The camelCase array representation.
		 * @param FormSettingsDTO $dto   The DTO instance.
		 *
		 * @since 4.1.0
		 */
		return apply_filters( 'f12_doi_settings_dto_to_camel_case_array', $array, $this );
	}

	/**
	 * Create a DTO with default values.
	 *
	 * @return self
	 */
	public static function createDefault(): self {
		$dto         = new self();
		$dto->sender = get_bloginfo( 'admin_email' );
		return $dto;
	}

	/**
	 * Return the list of required-field IDs that are missing or invalid
	 * for DOI to function end-to-end.
	 *
	 * Distinct from {@see FormSettingsValidator::validate()} — the Validator
	 * runs at REST-save time on raw input. This method answers a different
	 * question: "if this form is enabled right now, will DOI actually
	 * work?" — the answer drives the UI completeness-gate that auto-disables
	 * misconfigured forms and surfaces a "configuration incomplete" badge.
	 *
	 * Hart-required set (decisions locked 2026-05-12, see
	 * plan/doi-completeness-gate.md §5):
	 *  - `recipient` — without it the integration aborts with NO_RECIPIENT
	 *    (Avada/GF/WPForms) or silently falls back to the literal string
	 *    'email' (Elementor's hardcoded default).
	 *  - `subject` — empty subjects are accepted by wp_mail but mark the
	 *    mail as spam in most clients.
	 *  - `body_or_template` — either the body contains the
	 *    `[doubleoptinlink]` placeholder OR a non-empty `template` is set.
	 *    If both are missing the user has no clickable confirmation link
	 *    and DOI is unusable.
	 *
	 * NOT in this set:
	 *  - `enabled` — incomplete-AND-enabled is the exact bug we want to
	 *    detect, not part of the "incomplete" definition.
	 *  - `sender` — soft-required (per §5.3): leaving it empty falls back
	 *    to the WP admin email, which is functional, just not ideal.
	 *
	 * Pro addons can append integration-specific entries via the
	 * `f12_doi_form_missing_required_fields` filter (per §5.5).
	 *
	 * @return array<int,string>  Stable string IDs ('recipient', 'subject',
	 *                            'body_or_template', or addon-contributed).
	 *                            Empty array = configuration is complete.
	 *
	 * @since 4.5.0
	 */
	public function getMissingRequiredFields(): array {
		$missing = array();

		if ( empty( $this->recipient ) ) {
			$missing[] = 'recipient';
		}

		if ( empty( $this->subject ) ) {
			$missing[] = 'subject';
		}

		$hasBodyWithLink = ! empty( $this->body )
			&& strpos( $this->body, '[doubleoptinlink]' ) !== false;
		$hasTemplate     = ! empty( $this->template );
		if ( ! $hasBodyWithLink && ! $hasTemplate ) {
			$missing[] = 'body_or_template';
		}

		/**
		 * Filter to let addons append integration-specific
		 * required-field IDs.
		 *
		 * @since 4.5.0
		 *
		 * @param array<int,string> $missing Already-collected required-field IDs.
		 * @param FormSettingsDTO   $dto     The DTO being inspected.
		 */
		return apply_filters( 'f12_doi_form_missing_required_fields', $missing, $this );
	}

	/**
	 * Convenience wrapper. True iff {@see getMissingRequiredFields()} is empty.
	 *
	 * Does NOT check `$this->enabled` — "complete but disabled" is a
	 * legitimate state for a just-created form. The completeness-gate
	 * uses this to decide whether `enabled` may flip to true.
	 *
	 * @since 4.5.0
	 */
	public function isFunctionallyComplete(): bool {
		return empty( $this->getMissingRequiredFields() );
	}
}
