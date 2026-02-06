<?php
/**
 * Form Integration Interface
 *
 * @package Forge12\DoubleOptIn\Integration
 * @since   4.0.0
 */

namespace Forge12\DoubleOptIn\Integration;

use forge12\contactform7\CF7DoubleOptIn\OptIn;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface FormIntegrationInterface
 *
 * Contract for all form integration implementations.
 * Each form system (CF7, Avada, Elementor, etc.) must implement this interface.
 */
interface FormIntegrationInterface {

	/**
	 * Get the unique identifier for this integration.
	 *
	 * @return string The integration identifier (e.g., 'cf7', 'avada', 'elementor').
	 */
	public function getIdentifier(): string;

	/**
	 * Get a human-readable name for this integration.
	 *
	 * @return string The integration display name.
	 */
	public function getName(): string;

	/**
	 * Check if the integration's dependencies are available.
	 *
	 * @return bool True if the required plugin/theme is active.
	 */
	public function isAvailable(): bool;

	/**
	 * Register WordPress hooks for this integration.
	 *
	 * Called during plugin initialization to set up all necessary hooks.
	 *
	 * @return void
	 */
	public function registerHooks(): void;

	/**
	 * Process a form submission and create normalized FormData.
	 *
	 * @param mixed $context The form-system-specific submission context.
	 *
	 * @return FormDataInterface|null The normalized form data or null if processing fails.
	 */
	public function processSubmission( $context ): ?FormDataInterface;

	/**
	 * Resolve the recipient email address from form data.
	 *
	 * @param FormDataInterface $formData      The normalized form data.
	 * @param array             $formParameter The form configuration parameters.
	 *
	 * @return string The recipient email address.
	 */
	public function resolveRecipient( FormDataInterface $formData, array $formParameter ): string;

	/**
	 * Send the opt-in confirmation mail.
	 *
	 * @param OptIn             $optIn         The opt-in record.
	 * @param FormDataInterface $formData      The form data.
	 * @param array             $formParameter The form configuration.
	 *
	 * @return bool True if the mail was sent successfully.
	 */
	public function sendOptInMail( OptIn $optIn, FormDataInterface $formData, array $formParameter ): bool;

	/**
	 * Send the original (default) mail after opt-in confirmation.
	 *
	 * @param OptIn $optIn The confirmed opt-in record.
	 *
	 * @return void
	 */
	public function sendConfirmationMail( OptIn $optIn ): void;

	/**
	 * Check if double opt-in is enabled for a specific form.
	 *
	 * @param int $formId The form ID.
	 *
	 * @return bool True if opt-in is enabled.
	 */
	public function isOptInEnabled( int $formId ): bool;

	/**
	 * Get the form configuration parameters.
	 *
	 * @param int $formId The form ID.
	 *
	 * @return array The configuration parameters.
	 */
	public function getFormParameter( int $formId ): array;

	/**
	 * Get all form field names/tags for a specific form.
	 *
	 * Used for building admin UI dropdowns.
	 *
	 * @param int|string $formId The form ID (can be composite for some integrations like Elementor).
	 *
	 * @return array<string, string> Array of field name => field label.
	 */
	public function getFormFields( $formId ): array;

	/**
	 * Get the priority for hook registration.
	 *
	 * Lower values execute earlier. Default is 10.
	 *
	 * @return int The hook priority.
	 */
	public function getHookPriority(): int;

	/**
	 * Get all forms for this integration.
	 *
	 * Returns an array of forms with their basic information and DOI status.
	 *
	 * @since 4.1.0
	 *
	 * @return array<array{id: int, title: string, integration: string, enabled: bool, edit_url: string}>
	 */
	public function getForms(): array;

	/**
	 * Get the title of a specific form.
	 *
	 * @since 4.1.0
	 *
	 * @param int|string $formId The form ID (can be composite for some integrations like Elementor).
	 *
	 * @return string The form title.
	 */
	public function getFormTitle( $formId ): string;

	/**
	 * Get the edit URL for a specific form.
	 *
	 * @since 4.1.0
	 *
	 * @param int|string $formId The form ID (can be composite for some integrations like Elementor).
	 *
	 * @return string The edit URL.
	 */
	public function getFormEditUrl( $formId ): string;
}
