<?php
/**
 * Form Settings Service
 *
 * @package Forge12\DoubleOptIn\FormSettings
 * @since   4.1.0
 */

namespace Forge12\DoubleOptIn\FormSettings;

use Forge12\DoubleOptIn\EmailTemplates\EmailTemplateRepository;
use Forge12\DoubleOptIn\Integration\FormIntegrationRegistry;
use Forge12\Shared\LoggerInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FormSettingsService
 *
 * Centralized service for managing form settings across all integrations.
 */
class FormSettingsService {

	/**
	 * Post-meta key under which DOI form settings are stored.
	 *
	 * Public so cross-cutting consumers (data-cleanup migrations such
	 * as {@see \Forge12\DoubleOptIn\Migration\MigrationFormCompletenessSweep},
	 * audit tools) can reference the same storage key without
	 * hardcoding the string. The value itself is load-bearing across
	 * the addon ecosystem — changing it would be a separate
	 * data-migration project.
	 *
	 * @var string
	 */
	public const META_KEY = 'f12-cf7-doubleoptin';

	/**
	 * Logger instance.
	 *
	 * @var LoggerInterface
	 */
	private LoggerInterface $logger;

	/**
	 * Registry instance.
	 *
	 * @var FormIntegrationRegistry
	 */
	private FormIntegrationRegistry $registry;

	/**
	 * Validator instance.
	 *
	 * @var FormSettingsValidator
	 */
	private FormSettingsValidator $validator;

	/**
	 * Constructor.
	 *
	 * @param LoggerInterface         $logger    The logger instance.
	 * @param FormIntegrationRegistry $registry  The integration registry.
	 * @param FormSettingsValidator   $validator The validator instance.
	 */
	public function __construct(
		LoggerInterface $logger,
		FormIntegrationRegistry $registry,
		FormSettingsValidator $validator
	) {
		$this->logger    = $logger;
		$this->registry  = $registry;
		$this->validator = $validator;
	}

	/**
	 * Get settings for a specific form.
	 *
	 * @param int $formId The form ID.
	 *
	 * @return FormSettingsDTO The form settings.
	 */
	public function getSettings( int $formId ): FormSettingsDTO {
		$data = get_post_meta( $formId, self::META_KEY, true );

		if ( empty( $data ) || ! is_array( $data ) ) {
			$this->logger->debug(
				'No settings found for form, returning defaults',
				array(
					'plugin'  => 'double-opt-in',
					'form_id' => $formId,
				)
			);
			return FormSettingsDTO::createDefault();
		}

		return FormSettingsDTO::fromArray( $data );
	}

	/**
	 * Save settings for a specific form.
	 *
	 * Note: Validation should be done by the caller (e.g., FormSettingsController)
	 * before calling this method. This avoids double-validation issues when filters
	 * modify the DTO between controller validation and save.
	 *
	 * @param int             $formId   The form ID.
	 * @param FormSettingsDTO $settings The settings to save.
	 *
	 * @return bool True if saved successfully.
	 */
	public function saveSettings( int $formId, FormSettingsDTO $settings ): bool {
		$data = $settings->toArray();

		// Apply filter for backward compatibility
		$data = apply_filters( 'f12_cf7_doubleoptin_save_form', $data );

		$result = update_post_meta( $formId, self::META_KEY, $data );

		// update_post_meta returns false when the value is unchanged,
		// which is not an error. Verify by reading back the meta.
		if ( $result === false ) {
			$saved = get_post_meta( $formId, self::META_KEY, true );
			if ( $saved != $data ) {
				$this->logger->error(
					'Failed to save form settings to database',
					array(
						'plugin'  => 'double-opt-in',
						'form_id' => $formId,
					)
				);
				return false;
			}
		}

		$this->logger->info(
			'Form settings saved',
			array(
				'plugin'  => 'double-opt-in',
				'form_id' => $formId,
				'enabled' => $settings->enabled,
			)
		);

		return true;
	}

	/**
	 * Toggle the enabled state of a form.
	 *
	 * Saves directly without full validation since the toggle only changes
	 * the enabled state. Full validation is applied when saving all settings
	 * via the configuration panel.
	 *
	 * @param int $formId The form ID.
	 *
	 * @return bool The new enabled state.
	 */
	public function toggleEnabled( int $formId ): bool {
		$settings          = $this->getSettings( $formId );
		$settings->enabled = ! $settings->enabled;

		// Save directly without full validation - toggle only changes the enabled state.
		// Full form validation (subject, body, recipient, etc.) is enforced when
		// saving via the configuration panel.
		$data = $settings->toArray();
		$data = apply_filters( 'f12_cf7_doubleoptin_save_form', $data );
		update_post_meta( $formId, self::META_KEY, $data );

		$this->logger->info(
			'Form toggle state changed',
			array(
				'plugin'  => 'double-opt-in',
				'form_id' => $formId,
				'enabled' => $settings->enabled,
			)
		);

		return $settings->enabled;
	}

	/**
	 * Get all forms from all available integrations.
	 *
	 * @return array Array of form data grouped by integration.
	 */
	public function getAllForms(): array {
		$result = array();

		foreach ( $this->registry->getAvailable() as $identifier => $integration ) {
			$forms = $integration->getForms();

			if ( ! empty( $forms ) ) {
				$result[ $identifier ] = array(
					'name'  => $integration->getName(),
					'forms' => $forms,
				);
			}
		}

		$this->logger->debug(
			'Retrieved all forms from integrations',
			array(
				'plugin'            => 'double-opt-in',
				'integration_count' => count( $result ),
			)
		);

		return $result;
	}

	/**
	 * Get a flat list of all forms.
	 *
	 * @return array Array of form data.
	 */
	public function getAllFormsFlat(): array {
		$forms = array();

		foreach ( $this->registry->getAvailable() as $identifier => $integration ) {
			$integrationForms = $integration->getForms();

			foreach ( $integrationForms as $form ) {
				$form['integration_name'] = $integration->getName();
				$forms[]                  = $form;
			}
		}

		return $forms;
	}

	/**
	 * Get form data including settings.
	 *
	 * @param int|string $formId      The form ID (can be composite for Elementor: "123_abc456").
	 * @param string     $integration The integration identifier.
	 *
	 * @return array|null The form data or null if not found.
	 */
	public function getFormData( $formId, string $integration = '' ): ?array {
		// Check if this is a composite ID (e.g., Elementor: "123_abc456")
		$isCompositeId = is_string( $formId ) && strpos( $formId, '_' ) !== false;
		$postId        = $isCompositeId ? (int) explode( '_', $formId )[0] : (int) $formId;

		// For composite IDs, try to find Elementor integration first
		if ( $isCompositeId && empty( $integration ) ) {
			$integration = 'elementor';
		}

		$integrationInstance = $this->registry->findForForm( $postId, $integration );

		// If not found and composite ID, try to get Elementor integration directly
		if ( ! $integrationInstance && $isCompositeId ) {
			$integrationInstance = $this->registry->get( 'elementor' );
		}

		if ( ! $integrationInstance ) {
			$this->logger->warning(
				'Integration not found for form',
				array(
					'plugin'      => 'double-opt-in',
					'form_id'     => $formId,
					'post_id'     => $postId,
					'integration' => $integration,
				)
			);
			return null;
		}

		$post = get_post( $postId );
		if ( ! $post ) {
			return null;
		}

		// For Elementor, use the post ID for settings storage
		$settingsId = $isCompositeId ? $postId : (int) $formId;
		$settings   = $this->getSettings( $settingsId );
		$fields     = $integrationInstance->getFormFields( $formId );

		// Get title - for Elementor, try to get the form name from the widget
		$title = $post->post_title;
		if ( $isCompositeId && method_exists( $integrationInstance, 'getFormTitle' ) ) {
			$formTitle = $integrationInstance->getFormTitle( $formId );
			if ( ! empty( $formTitle ) ) {
				$title = $formTitle;
			}
		}

		// For Elementor forms, override the enabled status based on actual DOI action presence
		// (not from post_meta, but from Elementor's submit_actions)
		$settingsArray = $settings->toCamelCaseArray();
		$debugInfo     = array(
			'isCompositeId'         => $isCompositeId,
			'integrationIdentifier' => $integrationInstance->getIdentifier(),
			'originalEnabled'       => $settingsArray['enabled'] ?? false,
		);

		// Historical behaviour: for Elementor composite IDs we used to
		// override $settingsArray['enabled'] with the widget-based
		// isOptInEnabled() value (which reads the submit_actions list
		// from Elementor's _elementor_data). That override silently
		// re-enabled the master toggle after the user had disabled it
		// in our React UI — the FormSettingsPage Switch would flip back
		// to green on the next refetch because the widget action stayed
		// in place. With the completeness-gate (plan
		// doi-completeness-gate.md §2.2 + §2.5) post_meta is now the
		// authoritative source for what the React UI controls, so the
		// override has been removed.
		//
		// Known semantic gap (tracked in plan/feature-ideas.md): at
		// runtime, ElementorIntegration::isOptInEnabled() still consults
		// the widget action list, not the post_meta enable flag. That
		// means the React toggle and Elementor's submit-time DOI
		// activation can diverge until we either sync the widget on
		// save or make the runtime check post_meta-aware. Out of scope
		// for the completeness-gate ship; tracked as a follow-up.

		// Convert associative fields array to [{name, label}] format for the frontend.
		// `name` is force-cast to string because WPForms uses integer field IDs
		// (`$formData['fields'][4]`) and `json_encode` would otherwise emit `"name": 4`
		// (number). On the React side, `Set.has(consentField)` then misses because the
		// stored `consentField` is always a string after sanitize_key, but the field
		// list contains numbers — the validator fires its "field does not exist" banner
		// even immediately after the user picked the field from the dropdown
		// (user-reported 2026-05-13).
		$fieldsList = array();
		foreach ( $fields as $name => $label ) {
			$fieldsList[] = array(
				'name'  => (string) $name,
				'label' => $label,
			);
		}

		return array(
			'id'              => $formId,
			'title'           => $title,
			'integration'     => $integrationInstance->getIdentifier(),
			'integrationName' => $integrationInstance->getName(),
			'editUrl'         => $integrationInstance->getFormEditUrl( $formId ),
			'settings'        => $settingsArray,
			'fields'          => $fieldsList,
		);
	}

	/**
	 * Get available templates for a form.
	 *
	 * @param int|string $formId The form ID (can be composite for Elementor).
	 *
	 * @return array Array of template key => label.
	 */
	public function getAvailableTemplates( $formId ): array {
		// Handle composite IDs
		$postId = is_string( $formId ) && strpos( $formId, '_' ) !== false
			? (int) explode( '_', $formId )[0]
			: (int) $formId;

		$integration = $this->registry->findForForm( $postId );

		// For Elementor composite IDs, try to get the integration directly
		if ( ! $integration && is_string( $formId ) && strpos( $formId, '_' ) !== false ) {
			$integration = $this->registry->get( 'elementor' );
		}

		if ( $integration && method_exists( $integration, 'getAvailableTemplates' ) ) {
			return $integration->getAvailableTemplates();
		}

		// Default templates
		return array(
			'blank'           => 'blank',
			'newsletter_en'   => 'newsletter_en',
			'newsletter_en_2' => 'newsletter_en_2',
			'newsletter_en_3' => 'newsletter_en_3',
		);
	}

	/**
	 * Get available categories.
	 *
	 * @return array Array of category ID => name.
	 */
	public function getAvailableCategories(): array {
		$categories = array( 0 => __( 'Please select', 'double-opt-in' ) );

		$list = \forge12\contactform7\CF7DoubleOptIn\Category::get_list(
			array(
				'perPage' => -1,
				'orderBy' => 'name',
				'order'   => 'ASC',
			),
			$numberOfPages
		);

		foreach ( $list as $category ) {
			$categories[ $category->get_id() ] = $category->get_name();
		}

		return $categories;
	}

	/**
	 * Get template details for preview in the settings panel.
	 *
	 * Returns an array of custom templates with id, title, and thumbnail.
	 *
	 * @return array
	 */
	public function getTemplateDetails(): array {
		$repository = new EmailTemplateRepository();
		$templates  = $repository->findAll(
			array(
				'post_status' => array( 'publish', 'draft' ),
			)
		);

		$details = array();
		foreach ( $templates as $template ) {
			$key             = 'custom_' . $template['id'];
			$details[ $key ] = array(
				'id'        => $template['id'],
				'title'     => $template['title'],
				'thumbnail' => $template['thumbnail'],
				'editUrl'   => admin_url( 'admin.php?page=f12-doi-admin#/email-templates/' . $template['id'] . '/edit' ),
			);
		}

		return $details;
	}

	/**
	 * Get available pages for confirmation page selection.
	 *
	 * @return array Array of page ID => title.
	 */
	public function getAvailablePages(): array {
		$pages = array( -1 => __( 'Default', 'double-opt-in' ) );

		$allPages = get_pages();
		foreach ( $allPages as $page ) {
			$pages[ $page->ID ] = $page->post_title;
		}

		return $pages;
	}
}
