<?php
/**
 * OptIn Confirmed Event
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
 * Class OptInConfirmedEvent
 *
 * Dispatched when an opt-in is confirmed via the confirmation link.
 */
class OptInConfirmedEvent extends Event {

	private int $optInId;
	private string $hash;
	private string $email;
	private string $confirmedIp;
	private int $formId;
	private array $formData;

	/**
	 * Constructor.
	 *
	 * @param int    $optInId     The opt-in record ID.
	 * @param string $hash        The opt-in hash.
	 * @param string $email       The subscriber email.
	 * @param string $confirmedIp The IP address that confirmed.
	 * @param int    $formId      The original form ID.
	 * @param array  $formData    The submitted form field data (key-value pairs).
	 */
	public function __construct(
		int $optInId,
		string $hash,
		string $email,
		string $confirmedIp,
		int $formId,
		array $formData = []
	) {
		parent::__construct();
		$this->optInId     = $optInId;
		$this->hash        = $hash;
		$this->email       = $email;
		$this->confirmedIp = $confirmedIp;
		$this->formId      = $formId;
		$this->formData    = $formData;
	}

	/**
	 * {@inheritdoc}
	 */
	public static function getWordPressHookName(): string {
		return 'f12_cf7_doubleoptin_after_confirm';
	}

	/**
	 * Prevent the EventDispatcher from bridging this event to a WordPress hook.
	 *
	 * The legacy do_action( 'f12_cf7_doubleoptin_after_confirm', $hash, $optIn )
	 * is fired manually in AbstractFormIntegration and OptInFrontend with the
	 * original parameters for backward compatibility. Bridging here would cause
	 * the hook to fire twice.
	 *
	 * @return bool
	 */
	public function shouldBridgeToWordPress(): bool {
		return false;
	}

	public function getOptInId(): int {
		return $this->optInId;
	}

	public function getHash(): string {
		return $this->hash;
	}

	public function getEmail(): string {
		return $this->email;
	}

	public function getConfirmedIp(): string {
		return $this->confirmedIp;
	}

	public function getFormId(): int {
		return $this->formId;
	}

	/**
	 * Get the submitted form field data.
	 *
	 * Returns the deserialized key-value pairs that the user submitted
	 * with the original form (e.g. ['your-name' => 'John', 'your-email' => 'john@example.com']).
	 *
	 * @return array
	 */
	public function getFormData(): array {
		return $this->formData;
	}
}
