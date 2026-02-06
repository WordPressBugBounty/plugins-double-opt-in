<?php
/**
 * Integration Registered Event
 *
 * @package Forge12\DoubleOptIn\Events\Integration
 * @since   4.0.0
 */

namespace Forge12\DoubleOptIn\Events\Integration;

use Forge12\DoubleOptIn\EventSystem\Event;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class IntegrationRegisteredEvent
 *
 * Dispatched after a form integration is registered in the registry.
 * Useful for extending or modifying integration behavior.
 */
class IntegrationRegisteredEvent extends Event {

	/**
	 * The integration identifier.
	 *
	 * @var string
	 */
	private string $integrationId;

	/**
	 * The integration display name.
	 *
	 * @var string
	 */
	private string $name;

	/**
	 * Whether the integration is available.
	 *
	 * @var bool
	 */
	private bool $isAvailable;

	/**
	 * Constructor.
	 *
	 * @param string $integrationId The integration identifier.
	 * @param string $name          The integration display name.
	 * @param bool   $isAvailable   Whether the integration is available.
	 */
	public function __construct( string $integrationId, string $name, bool $isAvailable ) {
		parent::__construct();
		$this->integrationId = $integrationId;
		$this->name          = $name;
		$this->isAvailable   = $isAvailable;
	}

	/**
	 * {@inheritdoc}
	 */
	public static function getWordPressHookName(): string {
		return 'f12_cf7_doubleoptin_integration_registered';
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
	 * Get the integration display name.
	 *
	 * @return string
	 */
	public function getName(): string {
		return $this->name;
	}

	/**
	 * Check if the integration is available.
	 *
	 * @return bool True if available.
	 */
	public function isAvailable(): bool {
		return $this->isAvailable;
	}
}
