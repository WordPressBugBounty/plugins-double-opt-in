<?php
/**
 * OptIn Created Event
 *
 * @package Forge12\DoubleOptIn\Events\Lifecycle
 * @since   4.0.0
 */

namespace Forge12\DoubleOptIn\Events\Lifecycle;

use Forge12\DoubleOptIn\EventSystem\Event;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class OptInCreatedEvent
 *
 * Dispatched when a new opt-in record is created after form submission.
 */
class OptInCreatedEvent extends Event {

	private int $optInId;
	private int $formId;
	private string $formType;
	private string $email;
	private string $hash;
	private array $formData;

	/**
	 * Constructor.
	 *
	 * @param int    $optInId  The opt-in record ID.
	 * @param int    $formId   The form ID.
	 * @param string $formType The form type (cf7, avada, etc.).
	 * @param string $email    The subscriber email.
	 * @param string $hash     The opt-in hash.
	 * @param array  $formData The submitted form data.
	 */
	public function __construct(
		int $optInId,
		int $formId,
		string $formType,
		string $email,
		string $hash,
		array $formData = []
	) {
		parent::__construct();
		$this->optInId  = $optInId;
		$this->formId   = $formId;
		$this->formType = $formType;
		$this->email    = $email;
		$this->hash     = $hash;
		$this->formData = $formData;
	}

	/**
	 * {@inheritdoc}
	 */
	public static function getWordPressHookName(): string {
		return 'f12_cf7_doubleoptin_created';
	}

	public function getOptInId(): int {
		return $this->optInId;
	}

	public function getFormId(): int {
		return $this->formId;
	}

	public function getFormType(): string {
		return $this->formType;
	}

	public function getEmail(): string {
		return $this->email;
	}

	public function getHash(): string {
		return $this->hash;
	}

	public function getFormData(): array {
		return $this->formData;
	}
}
