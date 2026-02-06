<?php
/**
 * Form Validated Event
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
 * Class FormValidatedEvent
 *
 * Dispatched when form validation is complete.
 */
class FormValidatedEvent extends Event {

	private int $formId;
	private string $formType;
	private bool $isValid;
	private array $errors;
	private string $recipientEmail;

	/**
	 * Constructor.
	 *
	 * @param int    $formId         The form ID.
	 * @param string $formType       The form type.
	 * @param bool   $isValid        Whether validation passed.
	 * @param string $recipientEmail The extracted recipient email.
	 * @param array  $errors         Validation errors if any.
	 */
	public function __construct(
		int $formId,
		string $formType,
		bool $isValid,
		string $recipientEmail,
		array $errors = []
	) {
		parent::__construct();
		$this->formId         = $formId;
		$this->formType       = $formType;
		$this->isValid        = $isValid;
		$this->recipientEmail = $recipientEmail;
		$this->errors         = $errors;
	}

	/**
	 * {@inheritdoc}
	 */
	public static function getWordPressHookName(): string {
		return 'f12_cf7_doubleoptin_form_validated';
	}

	public function getFormId(): int {
		return $this->formId;
	}

	public function getFormType(): string {
		return $this->formType;
	}

	public function isValid(): bool {
		return $this->isValid;
	}

	public function getRecipientEmail(): string {
		return $this->recipientEmail;
	}

	public function getErrors(): array {
		return $this->errors;
	}
}
