<?php
/**
 * Admin Panel Interface
 *
 * @package Forge12\DoubleOptIn\Integration
 * @since   4.0.0
 */

namespace Forge12\DoubleOptIn\Integration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface AdminPanelInterface
 *
 * Contract for integrations that provide an admin configuration panel.
 * This interface extends the basic FormIntegrationInterface with admin UI capabilities.
 */
interface AdminPanelInterface {

	/**
	 * Register admin hooks for the panel.
	 *
	 * Called during admin_init to set up editor panels, metaboxes, etc.
	 *
	 * @return void
	 */
	public function registerAdminHooks(): void;

	/**
	 * Enqueue admin scripts and styles.
	 *
	 * @param string $hook The current admin page hook.
	 *
	 * @return void
	 */
	public function enqueueAdminAssets( string $hook ): void;

	/**
	 * Render the admin panel.
	 *
	 * @param mixed $form     The form object (type depends on form system).
	 * @param array $metadata The current form settings.
	 *
	 * @return void
	 */
	public function render( $form, array $metadata ): void;

	/**
	 * Save the admin panel settings.
	 *
	 * @param int   $formId The form ID.
	 * @param array $data   The submitted form data.
	 *
	 * @return bool True if save was successful.
	 */
	public function save( int $formId, array $data ): bool;

	/**
	 * Get the panel title for display in tabs/metaboxes.
	 *
	 * @return string The panel title.
	 */
	public function getPanelTitle(): string;

	/**
	 * Get available email templates.
	 *
	 * @return array<string, string> Template key => Template label.
	 */
	public function getAvailableTemplates(): array;

	/**
	 * Get available categories for the form.
	 *
	 * @return array<int, string> Category ID => Category name.
	 */
	public function getAvailableCategories(): array;
}
