<?php
/**
 * Form Data Interface
 *
 * @package Forge12\DoubleOptIn\Integration
 * @since   4.0.0
 */

namespace Forge12\DoubleOptIn\Integration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface FormDataInterface
 *
 * Represents normalized form submission data across all integration types.
 * This interface provides a standardized way to access form data regardless
 * of the underlying form system (CF7, Avada, Elementor, etc.).
 */
interface FormDataInterface {

	/**
	 * Get the form ID.
	 *
	 * @return int The form post ID.
	 */
	public function getFormId(): int;

	/**
	 * Get the form type identifier.
	 *
	 * @return string The form type (e.g., 'cf7', 'avada', 'elementor').
	 */
	public function getFormType(): string;

	/**
	 * Get all submitted form fields.
	 *
	 * @return array<string, mixed> Associative array of field name => value.
	 */
	public function getFields(): array;

	/**
	 * Get a specific field value.
	 *
	 * @param string $key     The field name.
	 * @param mixed  $default Default value if field doesn't exist.
	 *
	 * @return mixed The field value or default.
	 */
	public function getField( string $key, $default = null );

	/**
	 * Check if a field exists.
	 *
	 * @param string $key The field name.
	 *
	 * @return bool True if field exists.
	 */
	public function hasField( string $key ): bool;

	/**
	 * Get uploaded files.
	 *
	 * @return array<string, array> Array of field name => file paths.
	 */
	public function getFiles(): array;

	/**
	 * Get metadata about the submission.
	 *
	 * @return array<string, mixed> Metadata like source URL, timestamp, IP, etc.
	 */
	public function getMeta(): array;

	/**
	 * Get a specific metadata value.
	 *
	 * @param string $key     The metadata key.
	 * @param mixed  $default Default value if key doesn't exist.
	 *
	 * @return mixed The metadata value or default.
	 */
	public function getMetaValue( string $key, $default = null );

	/**
	 * Get the recipient email address.
	 *
	 * @return string The recipient email.
	 */
	public function getRecipientEmail(): string;

	/**
	 * Get the form HTML content.
	 *
	 * @return string The form HTML.
	 */
	public function getFormHtml(): string;

	/**
	 * Convert to array for serialization/storage.
	 *
	 * @return array The complete data as an array.
	 */
	public function toArray(): array;

	/**
	 * Create a new instance with modified data.
	 *
	 * @param array $fields Fields to merge/override.
	 *
	 * @return static A new instance with modified data.
	 */
	public function withFields( array $fields ): self;

	/**
	 * Create a new instance with modified metadata.
	 *
	 * @param array $meta Metadata to merge/override.
	 *
	 * @return static A new instance with modified metadata.
	 */
	public function withMeta( array $meta ): self;
}
