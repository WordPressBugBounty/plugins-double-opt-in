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
	 * The consent text (GDPR).
	 *
	 * @var string
	 */
	public string $consentText = '';

	/**
	 * Field mapping for standard placeholders.
	 * Maps doi_* placeholders to actual form field names.
	 * Example: ['doi_email' => 'your-email', 'doi_name' => 'full-name']
	 *
	 * @var array
	 */
	public array $fieldMapping = [];

	/**
	 * Additional settings from extensions (e.g., Pro version).
	 *
	 * @var array
	 */
	public array $extensionData = [];

	/**
	 * Create a DTO from an array.
	 *
	 * @param array $data The data array.
	 *
	 * @return self
	 */
	public static function fromArray( array $data ): self {
		$dto = new self();

		$dto->enabled          = (bool) ( $data['enable'] ?? $data['enabled'] ?? false );
		$dto->sender           = (string) ( $data['sender'] ?? '' );
		$dto->senderName       = (string) ( $data['sender_name'] ?? $data['senderName'] ?? '' );
		$dto->subject          = (string) ( $data['subject'] ?? '' );
		$dto->body             = (string) ( $data['body'] ?? '' );
		$dto->recipient        = (string) ( $data['recipient'] ?? '' );
		$dto->confirmationPage = (int) ( $data['page'] ?? $data['confirmationPage'] ?? -1 );
		$dto->conditions       = (string) ( $data['conditions'] ?? 'disabled' );
		$dto->template         = (string) ( $data['template'] ?? '' );
		$dto->category         = (int) ( $data['category'] ?? 0 );
		$dto->consentText      = (string) ( $data['consent_text'] ?? $data['consentText'] ?? '' );
		$dto->fieldMapping     = (array) ( $data['field_mapping'] ?? $data['fieldMapping'] ?? [] );

		/**
		 * Filter to allow extensions (e.g., Pro version) to add additional fields to the DTO.
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
		$array = [
			'enable'        => $this->enabled ? 1 : 0,
			'sender'        => $this->sender,
			'sender_name'   => $this->senderName,
			'subject'       => $this->subject,
			'body'          => $this->body,
			'recipient'     => $this->recipient,
			'page'          => $this->confirmationPage,
			'conditions'    => $this->conditions,
			'template'      => $this->template,
			'category'      => $this->category,
			'consent_text'  => $this->consentText,
			'field_mapping' => $this->fieldMapping,
		];

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
		$array = [
			'enabled'          => $this->enabled,
			'sender'           => $this->sender,
			'senderName'       => $this->senderName,
			'subject'          => $this->subject,
			'body'             => $this->body,
			'recipient'        => $this->recipient,
			'confirmationPage' => $this->confirmationPage,
			'conditions'       => $this->conditions,
			'template'         => $this->template,
			'category'         => $this->category,
			'consentText'      => $this->consentText,
			'fieldMapping'     => $this->fieldMapping,
		];

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
		$dto = new self();
		$dto->sender = get_bloginfo( 'admin_email' );
		return $dto;
	}

	/**
	 * Check if the DTO has valid settings for sending opt-in emails.
	 *
	 * @return bool
	 */
	public function isValid(): bool {
		return $this->enabled
			&& ! empty( $this->sender )
			&& ! empty( $this->recipient )
			&& ! empty( $this->subject )
			&& ! empty( $this->body );
	}
}
