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

	/**
	 * Constructor.
	 *
	 * @param int    $optInId     The opt-in record ID.
	 * @param string $hash        The opt-in hash.
	 * @param string $email       The subscriber email.
	 * @param string $confirmedIp The IP address that confirmed.
	 * @param int    $formId      The original form ID.
	 */
	public function __construct(
		int $optInId,
		string $hash,
		string $email,
		string $confirmedIp,
		int $formId
	) {
		parent::__construct();
		$this->optInId     = $optInId;
		$this->hash        = $hash;
		$this->email       = $email;
		$this->confirmedIp = $confirmedIp;
		$this->formId      = $formId;
	}

	/**
	 * {@inheritdoc}
	 */
	public static function getWordPressHookName(): string {
		return 'f12_cf7_doubleoptin_after_confirm';
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
}
