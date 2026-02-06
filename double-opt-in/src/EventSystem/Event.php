<?php
/**
 * Base Event Class
 *
 * @package Forge12\DoubleOptIn\EventSystem
 * @since   4.0.0
 */

namespace Forge12\DoubleOptIn\EventSystem;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Event
 *
 * Base class for all domain events. Provides propagation control and metadata.
 */
abstract class Event implements StoppableEventInterface {

	/**
	 * Whether propagation has been stopped.
	 *
	 * @var bool
	 */
	private bool $propagationStopped = false;

	/**
	 * When the event occurred.
	 *
	 * @var \DateTimeImmutable
	 */
	private \DateTimeImmutable $occurredAt;

	/**
	 * Source that triggered this event.
	 *
	 * @var string|null
	 */
	private ?string $triggeredBy = null;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->occurredAt = new \DateTimeImmutable();
	}

	/**
	 * Stop event propagation.
	 *
	 * After calling this method, no further listeners will be invoked.
	 *
	 * @return void
	 */
	public function stopPropagation(): void {
		$this->propagationStopped = true;
	}

	/**
	 * {@inheritdoc}
	 */
	public function isPropagationStopped(): bool {
		return $this->propagationStopped;
	}

	/**
	 * Get when the event occurred.
	 *
	 * @return \DateTimeImmutable
	 */
	public function getOccurredAt(): \DateTimeImmutable {
		return $this->occurredAt;
	}

	/**
	 * Get the source that triggered this event.
	 *
	 * @return string|null
	 */
	public function getTriggeredBy(): ?string {
		return $this->triggeredBy;
	}

	/**
	 * Set the source that triggered this event.
	 *
	 * @param string $source The source identifier.
	 *
	 * @return self
	 */
	public function setTriggeredBy( string $source ): self {
		$this->triggeredBy = $source;
		return $this;
	}

	/**
	 * Get the WordPress hook name for backward compatibility.
	 *
	 * This method must return the legacy WordPress action/filter name
	 * so that existing hooks continue to work.
	 *
	 * @return string The WordPress hook name.
	 */
	abstract public static function getWordPressHookName(): string;

	/**
	 * Get the event name (class name by default).
	 *
	 * @return string
	 */
	public static function getEventName(): string {
		return static::class;
	}
}
