<?php
/**
 * Form Submission Event
 *
 * @package Forge12\DoubleOptIn\Events\Integration
 * @since   4.0.0
 */

namespace Forge12\DoubleOptIn\Events\Integration;

use Forge12\DoubleOptIn\EventSystem\Event;
use Forge12\DoubleOptIn\Integration\FormDataInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FormSubmissionEvent
 *
 * Dispatched before an OptIn record is created for a form submission.
 * Listeners can modify the form data or skip the opt-in process entirely.
 */
class FormSubmissionEvent extends Event {

	/**
	 * The form data.
	 *
	 * @var FormDataInterface
	 */
	private FormDataInterface $formData;

	/**
	 * The integration identifier.
	 *
	 * @var string
	 */
	private string $integrationId;

	/**
	 * Whether to skip opt-in creation.
	 *
	 * @var bool
	 */
	private bool $skipOptIn = false;

	/**
	 * Reason for skipping opt-in.
	 *
	 * @var string
	 */
	private string $skipReason = '';

	/**
	 * Constructor.
	 *
	 * @param FormDataInterface $formData      The normalized form data.
	 * @param string            $integrationId The integration identifier (e.g., 'cf7', 'avada').
	 */
	public function __construct( FormDataInterface $formData, string $integrationId ) {
		parent::__construct();
		$this->formData      = $formData;
		$this->integrationId = $integrationId;
	}

	/**
	 * {@inheritdoc}
	 */
	public static function getWordPressHookName(): string {
		return 'f12_cf7_doubleoptin_form_submission';
	}

	/**
	 * Get the form data.
	 *
	 * @return FormDataInterface
	 */
	public function getFormData(): FormDataInterface {
		return $this->formData;
	}

	/**
	 * Set modified form data.
	 *
	 * @param FormDataInterface $formData The modified form data.
	 *
	 * @return void
	 */
	public function setFormData( FormDataInterface $formData ): void {
		$this->formData = $formData;
	}

	/**
	 * Get the integration identifier.
	 *
	 * @return string
	 */
	public function getIntegrationId(): string {
		return $this->integrationId;
	}

	/**
	 * Get the form ID.
	 *
	 * @return int
	 */
	public function getFormId(): int {
		return $this->formData->getFormId();
	}

	/**
	 * Get the form type.
	 *
	 * @return string
	 */
	public function getFormType(): string {
		return $this->formData->getFormType();
	}

	/**
	 * Skip the opt-in creation for this submission.
	 *
	 * @param string $reason Optional reason for skipping.
	 *
	 * @return void
	 */
	public function skipOptIn( string $reason = '' ): void {
		$this->skipOptIn  = true;
		$this->skipReason = $reason;
	}

	/**
	 * Check if opt-in should be skipped.
	 *
	 * @return bool True if opt-in should be skipped.
	 */
	public function shouldSkipOptIn(): bool {
		return $this->skipOptIn;
	}

	/**
	 * Get the reason for skipping opt-in.
	 *
	 * @return string The skip reason.
	 */
	public function getSkipReason(): string {
		return $this->skipReason;
	}

	/**
	 * Get a specific form field value.
	 *
	 * @param string $key     The field name.
	 * @param mixed  $default Default value if field doesn't exist.
	 *
	 * @return mixed The field value.
	 */
	public function getField( string $key, $default = null ) {
		return $this->formData->getField( $key, $default );
	}

	/**
	 * Check if a form field exists.
	 *
	 * @param string $key The field name.
	 *
	 * @return bool True if field exists.
	 */
	public function hasField( string $key ): bool {
		return $this->formData->hasField( $key );
	}
}
