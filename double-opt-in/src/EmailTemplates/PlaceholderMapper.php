<?php
/**
 * Placeholder Mapper
 *
 * Maps form fields to standardized placeholders for use across all forms.
 *
 * @package Forge12\DoubleOptIn\EmailTemplates
 * @since   4.0.0
 */

namespace Forge12\DoubleOptIn\EmailTemplates;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PlaceholderMapper
 *
 * Provides automatic detection and manual mapping of form fields to standard placeholders.
 */
class PlaceholderMapper {

	/**
	 * Standard placeholder definitions with auto-detection patterns.
	 *
	 * @var array
	 */
	private static array $standardPlaceholders = [
		'doi_email' => [
			'label'    => 'E-Mail',
			'patterns' => [ 'email', 'your-email', 'e-mail', 'mail', 'user-email', 'user_email', 'e_mail' ],
		],
		'doi_name' => [
			'label'    => 'Name (Full)',
			'patterns' => [ 'name', 'your-name', 'full-name', 'fullname', 'full_name', 'your_name' ],
		],
		'doi_first_name' => [
			'label'    => 'First Name',
			'patterns' => [ 'first-name', 'firstname', 'vorname', 'first_name', 'fname', 'given-name' ],
		],
		'doi_last_name' => [
			'label'    => 'Last Name',
			'patterns' => [ 'last-name', 'lastname', 'nachname', 'surname', 'last_name', 'lname', 'family-name' ],
		],
		'doi_phone' => [
			'label'    => 'Phone',
			'patterns' => [ 'phone', 'tel', 'telephone', 'your-phone', 'telefon', 'mobile', 'handy', 'phone_number' ],
		],
		'doi_company' => [
			'label'    => 'Company',
			'patterns' => [ 'company', 'firma', 'organization', 'organisation', 'business', 'company_name', 'unternehmen' ],
		],
		'doi_message' => [
			'label'    => 'Message',
			'patterns' => [ 'message', 'your-message', 'comment', 'nachricht', 'text', 'your_message', 'comments', 'body' ],
		],
		'doi_subject' => [
			'label'    => 'Subject',
			'patterns' => [ 'subject', 'your-subject', 'betreff', 'topic', 'your_subject' ],
		],
		'doi_address' => [
			'label'    => 'Address',
			'patterns' => [ 'address', 'adresse', 'street', 'strasse', 'your-address' ],
		],
		'doi_city' => [
			'label'    => 'City',
			'patterns' => [ 'city', 'stadt', 'ort', 'town' ],
		],
		'doi_zip' => [
			'label'    => 'ZIP/Postal Code',
			'patterns' => [ 'zip', 'plz', 'postal', 'postcode', 'postal_code', 'zipcode' ],
		],
		'doi_country' => [
			'label'    => 'Country',
			'patterns' => [ 'country', 'land', 'nation' ],
		],
	];

	/**
	 * Meta key for storing custom mappings.
	 *
	 * @var string
	 */
	const MAPPING_META_KEY = '_doi_placeholder_mapping';

	/**
	 * Get all standard placeholder definitions.
	 *
	 * @return array
	 */
	public static function getStandardPlaceholders(): array {
		return self::$standardPlaceholders;
	}

	/**
	 * Get placeholder labels for UI display.
	 *
	 * @return array Associative array of placeholder => label.
	 */
	public static function getPlaceholderLabels(): array {
		$labels = [];
		foreach ( self::$standardPlaceholders as $key => $config ) {
			$labels[ $key ] = $config['label'];
		}
		return $labels;
	}

	/**
	 * Auto-detect field mapping based on field names.
	 *
	 * @param array $fieldNames Array of form field names.
	 * @return array Detected mapping [ 'doi_email' => 'your-email', ... ].
	 */
	public static function autoDetectMapping( array $fieldNames ): array {
		$mapping = [];
		$usedFields = [];

		foreach ( self::$standardPlaceholders as $placeholder => $config ) {
			foreach ( $config['patterns'] as $pattern ) {
				foreach ( $fieldNames as $fieldName ) {
					// Skip already mapped fields
					if ( in_array( $fieldName, $usedFields, true ) ) {
						continue;
					}

					// Check for exact match or partial match
					$normalizedField = strtolower( str_replace( [ '-', '_' ], '', $fieldName ) );
					$normalizedPattern = strtolower( str_replace( [ '-', '_' ], '', $pattern ) );

					if ( $normalizedField === $normalizedPattern || strpos( $normalizedField, $normalizedPattern ) !== false ) {
						$mapping[ $placeholder ] = $fieldName;
						$usedFields[] = $fieldName;
						break 2; // Found match, move to next placeholder
					}
				}
			}
		}

		return $mapping;
	}

	/**
	 * Get custom mapping for a form.
	 *
	 * @param int    $formId Form ID.
	 * @param string $formType Form type ('cf7' or 'avada').
	 * @return array Custom mapping array.
	 */
	public static function getCustomMapping( int $formId, string $formType = 'cf7' ): array {
		$optionKey = self::getOptionKey( $formId, $formType );
		$mapping = get_option( $optionKey, [] );
		return is_array( $mapping ) ? $mapping : [];
	}

	/**
	 * Save custom mapping for a form.
	 *
	 * @param int    $formId  Form ID.
	 * @param array  $mapping Mapping array.
	 * @param string $formType Form type ('cf7' or 'avada').
	 * @return bool Success status.
	 */
	public static function saveCustomMapping( int $formId, array $mapping, string $formType = 'cf7' ): bool {
		$optionKey = self::getOptionKey( $formId, $formType );
		// Filter out empty mappings
		$mapping = array_filter( $mapping );
		return update_option( $optionKey, $mapping );
	}

	/**
	 * Get the effective mapping for a form (custom + auto-detected).
	 *
	 * @param int    $formId     Form ID.
	 * @param array  $fieldNames Available field names.
	 * @param string $formType   Form type ('cf7' or 'avada').
	 * @return array Merged mapping array.
	 */
	public static function getEffectiveMapping( int $formId, array $fieldNames, string $formType = 'cf7' ): array {
		$autoMapping = self::autoDetectMapping( $fieldNames );
		$customMapping = self::getCustomMapping( $formId, $formType );

		// Custom mapping takes precedence over auto-detection
		return array_merge( $autoMapping, $customMapping );
	}

	/**
	 * Replace standard placeholders in content with actual values.
	 *
	 * @param string $content       Content with placeholders.
	 * @param array  $formData      Form submission data.
	 * @param int    $formId        Form ID.
	 * @param array  $fieldNames    Available field names (optional, extracted from formData if not provided).
	 * @param string $formType      Form type ('cf7', 'avada', 'elementor').
	 * @param array  $customMapping Optional custom mapping from form settings (takes precedence).
	 * @return string Content with placeholders replaced.
	 */
	public static function replacePlaceholders(
		string $content,
		array $formData,
		int $formId,
		array $fieldNames = [],
		string $formType = 'cf7',
		array $customMapping = []
	): string {
		// Extract field names from form data if not provided
		if ( empty( $fieldNames ) ) {
			$fieldNames = array_keys( $formData );
		}

		// Try to get mapping from central form settings if not provided
		if ( empty( $customMapping ) ) {
			$centralSettings = get_post_meta( $formId, 'f12-cf7-doubleoptin', true );
			if ( is_array( $centralSettings ) && ! empty( $centralSettings['field_mapping'] ) ) {
				$customMapping = $centralSettings['field_mapping'];
			}
		}

		// Get the effective mapping (custom + auto-detected)
		$autoMapping = self::autoDetectMapping( $fieldNames );
		// Custom mapping takes precedence over auto-detection
		$mapping = array_merge( $autoMapping, $customMapping );

		// Replace each standard placeholder
		foreach ( self::$standardPlaceholders as $placeholder => $config ) {
			$tag = '[' . $placeholder . ']';

			if ( strpos( $content, $tag ) === false ) {
				continue;
			}

			$value = '';
			if ( isset( $mapping[ $placeholder ] ) ) {
				$mappedField = $mapping[ $placeholder ];
				// Remove brackets if present (e.g., "[email]" -> "email")
				$mappedField = trim( $mappedField, '[]' );

				if ( isset( $formData[ $mappedField ] ) ) {
					$value = $formData[ $mappedField ];
					// Handle arrays (multi-select, checkboxes)
					if ( is_array( $value ) ) {
						$value = implode( ', ', $value );
					}
				}
			}

			$content = str_replace( $tag, esc_html( $value ), $content );
		}

		return $content;
	}

	/**
	 * Get option key for storing mapping.
	 *
	 * @param int    $formId   Form ID.
	 * @param string $formType Form type.
	 * @return string Option key.
	 */
	private static function getOptionKey( int $formId, string $formType ): string {
		return "doi_placeholder_mapping_{$formType}_{$formId}";
	}

	/**
	 * Get all available placeholders for the email editor.
	 *
	 * @return array Array of placeholder info for UI.
	 */
	public static function getAvailablePlaceholdersForEditor(): array {
		$placeholders = [];

		// Add standard placeholders
		foreach ( self::$standardPlaceholders as $key => $config ) {
			$placeholders[] = [
				'tag'         => '[' . $key . ']',
				'label'       => $config['label'],
				'category'    => 'form_fields',
				'description' => sprintf( __( 'Auto-detected or mapped %s field', 'double-opt-in' ), $config['label'] ),
			];
		}

		// Add system placeholders
		$systemPlaceholders = [
			[
				'tag'         => '[doubleoptinlink]',
				'label'       => __( 'Confirmation Link', 'double-opt-in' ),
				'category'    => 'system',
				'description' => __( 'Link to confirm the opt-in', 'double-opt-in' ),
			],
			[
				'tag'         => '[doubleoptoutlink]',
				'label'       => __( 'Opt-out Link', 'double-opt-in' ),
				'category'    => 'system',
				'description' => __( 'Link to opt-out/unsubscribe', 'double-opt-in' ),
			],
			[
				'tag'         => '[doubleoptin_form_date]',
				'label'       => __( 'Submission Date', 'double-opt-in' ),
				'category'    => 'system',
				'description' => __( 'Date of form submission', 'double-opt-in' ),
			],
			[
				'tag'         => '[doubleoptin_form_time]',
				'label'       => __( 'Submission Time', 'double-opt-in' ),
				'category'    => 'system',
				'description' => __( 'Time of form submission', 'double-opt-in' ),
			],
			[
				'tag'         => '[doubleoptin_form_url]',
				'label'       => __( 'Form URL', 'double-opt-in' ),
				'category'    => 'system',
				'description' => __( 'URL where the form was submitted', 'double-opt-in' ),
			],
			[
				'tag'         => '[doubleoptin_privacy_url]',
				'label'       => __( 'Privacy Policy URL', 'double-opt-in' ),
				'category'    => 'system',
				'description' => __( 'URL to the privacy policy page (GDPR)', 'double-opt-in' ),
			],
		];

		return array_merge( $placeholders, $systemPlaceholders );
	}

	/**
	 * Extract field names from a CF7 form.
	 *
	 * @param int $formId CF7 form ID.
	 * @return array Array of field names.
	 */
	public static function extractCF7FieldNames( int $formId ): array {
		if ( ! function_exists( 'wpcf7_contact_form' ) ) {
			return [];
		}

		$contactForm = wpcf7_contact_form( $formId );
		if ( ! $contactForm ) {
			return [];
		}

		$tags = $contactForm->scan_form_tags();
		$fieldNames = [];

		foreach ( $tags as $tag ) {
			if ( ! empty( $tag->name ) ) {
				$fieldNames[] = $tag->name;
			}
		}

		return $fieldNames;
	}
}
