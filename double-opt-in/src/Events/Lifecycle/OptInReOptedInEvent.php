<?php
/**
 * OptIn Re-Opted-In Event
 *
 * @package Forge12\DoubleOptIn\Events\Lifecycle
 * @since   5.8.0
 */

declare( strict_types=1 );

namespace Forge12\DoubleOptIn\Events\Lifecycle;

use Forge12\DoubleOptIn\EventSystem\Event;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Dispatched when a withdrawn consent is given again (addon-opt-out's
 * "reactivate" in the subscriber's list). The counterpart of
 * {@see OptInOptedOutEvent}; fired only when the opt-in actually changed.
 *
 * @api Core API 4.6.0
 */
class OptInReOptedInEvent extends Event {

	/** @var int */
	private $optInId;

	/** @var string */
	private $hash;

	/** @var string */
	private $email;

	/** @var int */
	private $formId;

	public function __construct( int $optInId, string $hash, string $email, int $formId ) {
		parent::__construct();
		$this->optInId = $optInId;
		$this->hash    = $hash;
		$this->email   = $email;
		$this->formId  = $formId;
	}

	/**
	 * {@inheritdoc}
	 */
	public static function getWordPressHookName(): string {
		return 'f12_doi_optin_reopted_in';
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

	public function getFormId(): int {
		return $this->formId;
	}
}
