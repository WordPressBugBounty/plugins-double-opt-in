<?php
/**
 * Form Submitted Event
 *
 * @package Forge12\DoubleOptIn\Events\Form
 * @since   4.0.0
 */

namespace Forge12\DoubleOptIn\Events\Form;

use Forge12\DoubleOptIn\EventSystem\Event;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FormSubmittedEvent
 *
 * Dispatched when a form is submitted before opt-in creation.
 * Listeners can modify this event to skip opt-in creation.
 */
class FormSubmittedEvent extends Event {

	private int $formId;
	private string $formType;
	private array $postedData;
	private array $uploadedFiles;
	private string $formUrl;
	private bool $shouldCreateOptIn = true;
	private ?string $skipReason = null;

	/**
	 * Constructor.
	 *
	 * @param int    $formId        The form ID.
	 * @param string $formType      The form type (cf7, avada, etc.).
	 * @param array  $postedData    The submitted form data.
	 * @param array  $uploadedFiles The uploaded files.
	 * @param string $formUrl       The URL where the form was submitted.
	 */
	public function __construct(
		int $formId,
		string $formType,
		array $postedData,
		array $uploadedFiles,
		string $formUrl
	) {
		parent::__construct();
		$this->formId        = $formId;
		$this->formType      = $formType;
		$this->postedData    = $postedData;
		$this->uploadedFiles = $uploadedFiles;
		$this->formUrl       = $formUrl;
	}

	/**
	 * {@inheritdoc}
	 */
	public static function getWordPressHookName(): string {
		return 'f12_cf7_doubleoptin_form_submitted';
	}

	public function getFormId(): int {
		return $this->formId;
	}

	public function getFormType(): string {
		return $this->formType;
	}

	public function getPostedData(): array {
		return $this->postedData;
	}

	public function getUploadedFiles(): array {
		return $this->uploadedFiles;
	}

	public function getFormUrl(): string {
		return $this->formUrl;
	}

	/**
	 * Check if opt-in should be created.
	 *
	 * @return bool
	 */
	public function shouldCreateOptIn(): bool {
		return $this->shouldCreateOptIn;
	}

	/**
	 * Skip opt-in creation for this submission.
	 *
	 * @param string $reason The reason for skipping.
	 *
	 * @return void
	 */
	public function skipOptInCreation( string $reason = '' ): void {
		$this->shouldCreateOptIn = false;
		$this->skipReason        = $reason;
	}

	/**
	 * Get the reason for skipping.
	 *
	 * @return string|null
	 */
	public function getSkipReason(): ?string {
		return $this->skipReason;
	}
}
