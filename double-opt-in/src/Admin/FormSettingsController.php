<?php
/**
 * Form Settings Controller
 *
 * @package Forge12\DoubleOptIn\Admin
 * @since   4.1.0
 */

namespace Forge12\DoubleOptIn\Admin;

use Forge12\DoubleOptIn\FormSettings\FormSettingsDTO;
use Forge12\DoubleOptIn\FormSettings\FormSettingsService;
use Forge12\DoubleOptIn\FormSettings\FormSettingsValidator;
use Forge12\Shared\LoggerInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FormSettingsController
 *
 * Handles AJAX requests for form settings management.
 */
class FormSettingsController {

	/**
	 * Logger instance.
	 *
	 * @var LoggerInterface
	 */
	private LoggerInterface $logger;

	/**
	 * Form settings service.
	 *
	 * @var FormSettingsService
	 */
	private FormSettingsService $service;

	/**
	 * Form settings validator.
	 *
	 * @var FormSettingsValidator
	 */
	private FormSettingsValidator $validator;

	/**
	 * Constructor.
	 *
	 * @param LoggerInterface       $logger    The logger instance.
	 * @param FormSettingsService   $service   The settings service.
	 * @param FormSettingsValidator $validator The validator.
	 */
	public function __construct(
		LoggerInterface $logger,
		FormSettingsService $service,
		FormSettingsValidator $validator
	) {
		$this->logger    = $logger;
		$this->service   = $service;
		$this->validator = $validator;
	}

	/**
	 * Register AJAX actions.
	 *
	 * @return void
	 */
	public function registerActions(): void {
		add_action( 'wp_ajax_doi_get_form_settings', array( $this, 'getFormSettings' ) );
		add_action( 'wp_ajax_doi_save_form_settings', array( $this, 'saveFormSettings' ) );
		add_action( 'wp_ajax_doi_toggle_form', array( $this, 'toggleForm' ) );
		add_action( 'wp_ajax_doi_get_all_forms', array( $this, 'getAllForms' ) );
	}

	/**
	 * Get settings for a specific form.
	 *
	 * @return void
	 */
	public function getFormSettings(): void {
		$this->verifyNonce();
		$this->checkCapability();

		// Keep as string to support composite IDs (e.g., Elementor: "123_abc456")
		$formId      = isset( $_POST['form_id'] ) ? sanitize_text_field( $_POST['form_id'] ) : '';
		$integration = isset( $_POST['integration'] ) ? sanitize_text_field( $_POST['integration'] ) : '';

		if ( empty( $formId ) ) {
			$this->sendError( __( 'Invalid form ID.', 'double-opt-in' ) );
		}

		$formData = $this->service->getFormData( $formId, $integration );

		if ( ! $formData ) {
			$this->sendError( __( 'Form not found.', 'double-opt-in' ) );
		}

		// Add additional data for the panel
		$formData['templates']       = $this->service->getAvailableTemplates( $formId );
		$formData['categories']      = $this->service->getAvailableCategories();
		$formData['pages']           = $this->service->getAvailablePages();
		$formData['templateDetails'] = $this->service->getTemplateDetails();

		/**
		 * Filter to allow extensions (e.g., Pro version) to add additional data to the form settings response.
		 *
		 * @param array $formData The form data array.
		 * @param int   $formId   The form ID.
		 *
		 * @since 4.1.0
		 */
		$formData = apply_filters( 'f12_doi_form_settings_data', $formData, $formId );

		$this->sendSuccess( $formData );
	}

	/**
	 * Save settings for a specific form.
	 *
	 * @return void
	 */
	public function saveFormSettings(): void {
		$this->verifyNonce();
		$this->checkCapability();

		// Keep as string to support composite IDs (e.g., Elementor: "123_abc456")
		$formId = isset( $_POST['form_id'] ) ? sanitize_text_field( $_POST['form_id'] ) : '';

		if ( empty( $formId ) ) {
			$this->sendError( __( 'Invalid form ID.', 'double-opt-in' ) );
		}

		// For composite IDs, extract the post ID for storage
		$storageId = strpos( $formId, '_' ) !== false ? (int) explode( '_', $formId )[0] : (int) $formId;

		// Parse settings from POST data
		$settingsData = isset( $_POST['settings'] ) ? $_POST['settings'] : array();

		if ( is_string( $settingsData ) ) {
			$settingsData = json_decode( wp_unslash( $settingsData ), true );
		}

		// Capture current state BEFORE sanitize so we can detect
		// enabled-state transitions for the completeness-gate (plan §2.2).
		$oldSettings = $this->service->getSettings( $storageId );
		$wasEnabled  = $oldSettings->enabled;

		// Sanitize and create DTO
		$settings = $this->validator->sanitize( $settingsData );

		// Completeness-gate (plan/doi-completeness-gate.md §2.2) — runs
		// BEFORE the format-validator so we surface the more specific
		// INCOMPLETE_CONFIG error code on the explicit enable-attempt
		// path. Auto-disable applies on the silent-drift path (was
		// enabled, save makes it incomplete).
		$missingRequired = $settings->getMissingRequiredFields();
		$autoDisabled    = false;

		if ( $settings->enabled && ! empty( $missingRequired ) ) {
			if ( ! $wasEnabled ) {
				// Explicit enable-attempt with incomplete config — block.
				$this->sendIncompleteConfigError( $missingRequired );
				return; // sendIncompleteConfigError() exits via wp_send_json
			}

			// Was enabled, save makes it incomplete — auto-disable rather
			// than block, so the user's other edits aren't lost. Surface
			// via the response so the UI can render a notification.
			$settings->enabled = false;
			$autoDisabled      = true;

			/**
			 * Fired when a form save would have left the form enabled with
			 * an incomplete config, and the controller auto-disabled it
			 * instead.
			 *
			 * @since 4.5.0
			 *
			 * @param string $formId  The form ID (composite for Elementor).
			 * @param array  $missing The list of missing required-field IDs.
			 */
			do_action( 'f12_doi_form_auto_disabled_incomplete', $formId, $missingRequired );

			$this->logger->warning(
				'Form auto-disabled — save would have left it enabled with incomplete config',
				array(
					'plugin'  => 'double-opt-in',
					'form_id' => $formId,
					'missing' => $missingRequired,
				)
			);
		}

		// Validate (format-only checks remaining after completeness-gate)
		$errors = $this->validator->validate( $settings );
		if ( ! empty( $errors ) ) {
			$this->sendError( __( 'Validation failed.', 'double-opt-in' ), $errors );
		}

		/**
		 * Filter to allow extensions (e.g., Pro version) to modify settings before saving.
		 *
		 * @param FormSettingsDTO $settings     The settings DTO.
		 * @param int             $storageId    The storage ID (post ID for settings).
		 * @param array           $settingsData The raw settings data from POST.
		 *
		 * @since 4.1.0
		 */
		$settings = apply_filters( 'f12_doi_form_settings_before_save', $settings, $storageId, $settingsData );

		// Save using the storage ID (post ID for composite IDs)
		$result = $this->service->saveSettings( $storageId, $settings );

		if ( ! $result ) {
			$this->sendError( __( 'Failed to save settings.', 'double-opt-in' ) );
		}

		// Detect integration type from form ID format
		$integration = '';
		if ( strpos( $formId, '_' ) !== false ) {
			$integration = 'elementor';
		}

		/**
		 * Action fired after form settings have been saved.
		 * Allows extensions (e.g., Pro version) to save additional data.
		 *
		 * @param string          $formId       The form ID (can be composite for Elementor).
		 * @param FormSettingsDTO $settings     The settings DTO.
		 * @param array           $settingsData The raw settings data from POST.
		 * @param string          $integration  The integration identifier (e.g., 'elementor').
		 *
		 * @since 4.1.0
		 */
		do_action( 'f12_doi_form_settings_saved', $formId, $settings, $settingsData, $integration );

		$this->logger->info(
			'Form settings saved via AJAX',
			array(
				'plugin'  => 'double-opt-in',
				'form_id' => $formId,
			)
		);

		$this->sendSuccess(
			array(
				'message'      => $autoDisabled
					? __( 'Settings saved. Double Opt-In was auto-disabled because the configuration is incomplete.', 'double-opt-in' )
					: __( 'Settings saved successfully.', 'double-opt-in' ),
				'enabled'      => $settings->enabled,
				'autoDisabled' => $autoDisabled,
				'missing'      => array_values( $missingRequired ),
			)
		);
	}

	/**
	 * Toggle the enabled state of a form.
	 *
	 * @return void
	 */
	public function toggleForm(): void {
		$this->verifyNonce();
		$this->checkCapability();

		// Keep as string to support composite IDs (e.g., Elementor: "123_abc456")
		$formId = isset( $_POST['form_id'] ) ? sanitize_text_field( $_POST['form_id'] ) : '';

		if ( empty( $formId ) ) {
			$this->sendError( __( 'Invalid form ID.', 'double-opt-in' ) );
		}

		// For composite IDs, extract the post ID for storage
		$storageId = strpos( $formId, '_' ) !== false ? (int) explode( '_', $formId )[0] : (int) $formId;

		// Completeness-gate before toggle-to-enabled (plan §2.3).
		// Toggling-to-disabled is always allowed; toggling-to-enabled is
		// blocked when the config is incomplete.
		$currentSettings = $this->service->getSettings( $storageId );
		if ( ! $currentSettings->enabled ) {
			$missing = $currentSettings->getMissingRequiredFields();
			if ( ! empty( $missing ) ) {
				$this->sendIncompleteConfigError( $missing );
				return;
			}
		}

		$newState = $this->service->toggleEnabled( $storageId );

		// Detect integration type from form ID format
		$integration = '';
		if ( strpos( $formId, '_' ) !== false ) {
			$integration = 'elementor';
		}

		/**
		 * Action fired after form DOI state has been toggled.
		 * Allows extensions to sync the state with the form system (e.g., Elementor submit_actions).
		 *
		 * @param string $formId      The form ID (can be composite for Elementor).
		 * @param bool   $enabled     The new enabled state.
		 * @param string $integration The integration identifier (e.g., 'elementor').
		 *
		 * @since 4.1.0
		 */
		do_action( 'f12_doi_form_toggled', $formId, $newState, $integration );

		$this->logger->info(
			'Form toggle via AJAX',
			array(
				'plugin'     => 'double-opt-in',
				'form_id'    => $formId,
				'storage_id' => $storageId,
				'enabled'    => $newState,
			)
		);

		$this->sendSuccess(
			array(
				'enabled' => $newState,
				'message' => $newState
					? __( 'Double Opt-In enabled.', 'double-opt-in' )
					: __( 'Double Opt-In disabled.', 'double-opt-in' ),
			)
		);
	}

	/**
	 * Get all forms from all integrations.
	 *
	 * @return void
	 */
	public function getAllForms(): void {
		$this->verifyNonce();
		$this->checkCapability();

		$forms = $this->service->getAllForms();

		$this->sendSuccess(
			array(
				'forms' => $forms,
			)
		);
	}

	/**
	 * Verify the AJAX nonce.
	 *
	 * @return void
	 */
	private function verifyNonce(): void {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['nonce'] ), 'doi_form_settings' ) ) {
			$this->logger->warning(
				'Nonce verification failed',
				array(
					'plugin' => 'double-opt-in',
				)
			);
			$this->sendError( __( 'Security check failed.', 'double-opt-in' ), array(), 403 );
		}
	}

	/**
	 * Check user capability.
	 *
	 * @return void
	 */
	private function checkCapability(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			$this->logger->warning(
				'Unauthorized access attempt',
				array(
					'plugin' => 'double-opt-in',
				)
			);
			$this->sendError( __( 'You do not have permission to perform this action.', 'double-opt-in' ), array(), 403 );
		}
	}

	/**
	 * Send a success response.
	 *
	 * @param array $data The response data.
	 *
	 * @return void
	 */
	private function sendSuccess( array $data ): void {
		wp_send_json_success( $data );
	}

	/**
	 * Send an error response.
	 *
	 * @param string $message    The error message.
	 * @param array  $errors     Additional error details.
	 * @param int    $statusCode HTTP status code.
	 *
	 * @return void
	 */
	private function sendError( string $message, array $errors = array(), int $statusCode = 400 ): void {
		wp_send_json_error(
			array(
				'message' => $message,
				'errors'  => $errors,
			),
			$statusCode
		);
	}

	/**
	 * Send a structured INCOMPLETE_CONFIG error response for the
	 * completeness-gate (plan/doi-completeness-gate.md §2.2 + §2.3).
	 *
	 * Distinct from {@see sendError()} because the React UI needs to
	 * distinguish "this save was rejected because the form is missing
	 * required fields" from "this save had a generic validation error"
	 * — the former triggers the Toast + "open configuration" CTA from
	 * §2.7, the latter falls through to inline field-level errors.
	 *
	 * Status 422 (Unprocessable Entity): the request was syntactically
	 * valid but semantically incomplete.
	 *
	 * @param array<int,string> $missing  Stable required-field IDs
	 *                                    ('recipient', 'subject',
	 *                                    'body_or_template', addon-contributed).
	 */
	private function sendIncompleteConfigError( array $missing ): void {
		wp_send_json_error(
			array(
				'code'    => 'INCOMPLETE_CONFIG',
				'message' => __(
					'Cannot enable Double Opt-In: configuration is incomplete. Please fill in all required fields first.',
					'double-opt-in'
				),
				'missing' => array_values( $missing ),
			),
			422
		);
	}
}
