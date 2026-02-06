<?php
/**
 * Stoppable Event Interface
 *
 * @package Forge12\DoubleOptIn\EventSystem
 * @since   4.0.0
 */

namespace Forge12\DoubleOptIn\EventSystem;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface StoppableEventInterface
 *
 * An Event whose processing may be interrupted when the event has been handled.
 */
interface StoppableEventInterface {

	/**
	 * Is propagation stopped?
	 *
	 * @return bool True if the Event is complete and no further listeners should be called.
	 */
	public function isPropagationStopped(): bool;
}
