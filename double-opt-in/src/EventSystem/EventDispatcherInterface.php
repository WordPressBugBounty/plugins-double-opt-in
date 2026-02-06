<?php
/**
 * Event Dispatcher Interface
 *
 * @package Forge12\DoubleOptIn\EventSystem
 * @since   4.0.0
 */

namespace Forge12\DoubleOptIn\EventSystem;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface EventDispatcherInterface
 *
 * PSR-14 inspired event dispatcher with WordPress hook bridging support.
 */
interface EventDispatcherInterface {

	/**
	 * Dispatch an event to all registered listeners.
	 *
	 * @param object $event The event to dispatch.
	 *
	 * @return object The same event object, potentially modified by listeners.
	 */
	public function dispatch( object $event ): object;

	/**
	 * Add a listener for a specific event.
	 *
	 * @param string   $eventName The fully qualified class name of the event.
	 * @param callable $listener  The listener callable.
	 * @param int      $priority  Higher priority = earlier execution (like WordPress hooks).
	 *
	 * @return void
	 */
	public function addListener( string $eventName, callable $listener, int $priority = 10 ): void;

	/**
	 * Remove a listener.
	 *
	 * @param string   $eventName The event class name.
	 * @param callable $listener  The listener to remove.
	 *
	 * @return void
	 */
	public function removeListener( string $eventName, callable $listener ): void;

	/**
	 * Check if an event has any listeners.
	 *
	 * @param string $eventName The event class name.
	 *
	 * @return bool
	 */
	public function hasListeners( string $eventName ): bool;

	/**
	 * Get all listeners for an event, sorted by priority.
	 *
	 * @param string $eventName The event class name.
	 *
	 * @return callable[]
	 */
	public function getListeners( string $eventName ): array;
}
