<?php
/**
 * OptIn Opted-Out Event
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
 * Dispatched when a confirmed consent is withdrawn.
 *
 * The core never withdraws a consent itself; addon-opt-out does and fires
 * this, so that other add-ons (webhooks, newsletter sync) can follow
 * without depending on it. Fired once per opt-in that actually changed —
 * a second click on an old link fires nothing.
 *
 * @api Core API 4.6.0
 */
class OptInOptedOutEvent extends Event {

	public const SOURCE_LINK      = 'link';
	public const SOURCE_BULK      = 'bulk';
	public const SOURCE_ONE_CLICK = 'one-click';

	/** @var int */
	private $optInId;

	/** @var string */
	private $hash;

	/** @var string */
	private $email;

	/** @var int */
	private $formId;

	/** @var string */
	private $source;

	/**
	 * @param int    $optInId Opt-in id.
	 * @param string $hash    Opt-in hash.
	 * @param string $email   Subscriber address.
	 * @param int    $formId  Form the consent was given in.
	 * @param string $source  How it was withdrawn, one of the SOURCE_* constants.
	 */
	public function __construct( int $optInId, string $hash, string $email, int $formId, string $source ) {
		parent::__construct();
		$this->optInId = $optInId;
		$this->hash    = $hash;
		$this->email   = $email;
		$this->formId  = $formId;
		$this->source  = $source;
	}

	/**
	 * {@inheritdoc}
	 */
	public static function getWordPressHookName(): string {
		return 'f12_doi_optin_opted_out';
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

	public function getSource(): string {
		return $this->source;
	}
}
