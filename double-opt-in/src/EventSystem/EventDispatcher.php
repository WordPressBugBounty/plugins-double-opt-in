<?php
/**
 * Event Dispatcher Implementation
 *
 * @package Forge12\DoubleOptIn\EventSystem
 * @since   4.0.0
 */

namespace Forge12\DoubleOptIn\EventSystem;

use Forge12\Shared\LoggerInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class EventDispatcher
 *
 * Dispatches events to registered listeners with WordPress hook bridging support.
 */
class EventDispatcher implements EventDispatcherInterface {

	/**
	 * Registered listeners grouped by event name and priority.
	 *
	 * @var array<string, array<int, callable[]>>
	 */
	private array $listeners = [];

	/**
	 * Sorted listeners cache.
	 *
	 * @var array<string, callable[]>
	 */
	private array $sorted = [];

	/**
	 * Logger instance.
	 *
	 * @var LoggerInterface
	 */
	private LoggerInterface $logger;

	/**
	 * Whether to bridge events to WordPress hooks.
	 *
	 * @var bool
	 */
	private bool $bridgeToWordPress;

	/**
	 * Constructor.
	 *
	 * @param LoggerInterface $logger            The logger instance.
	 * @param bool            $bridgeToWordPress Whether to bridge to WordPress hooks (default: true).
	 */
	public function __construct( LoggerInterface $logger, bool $bridgeToWordPress = true ) {
		$this->logger            = $logger;
		$this->bridgeToWordPress = $bridgeToWordPress;
	}

	/**
	 * {@inheritdoc}
	 */
	public function dispatch( object $event ): object {
		$eventName = get_class( $event );

		$this->logger->debug( 'Dispatching event', [
			'plugin'       => 'double-opt-in',
			'component'    => 'event-dispatcher',
			'event'        => $eventName,
			'triggered_by' => $event instanceof Event ? $event->getTriggeredBy() : null,
		] );

		// Bridge to WordPress hooks for backward compatibility
		if ( $this->bridgeToWordPress && $event instanceof Event && $event->shouldBridgeToWordPress() ) {
			$wpHookName = $event::getWordPressHookName();
			if ( ! empty( $wpHookName ) ) {
				/**
				 * Fire the WordPress action for backward compatibility.
				 *
				 * This allows existing code using add_action() to continue working.
				 */
				do_action( $wpHookName, $event );

				$this->logger->debug( 'WordPress hook bridged', [
					'plugin'    => 'double-opt-in',
					'component' => 'event-dispatcher',
					'wp_hook'   => $wpHookName,
				] );
			}
		}

		// Dispatch to typed event listeners
		$listeners = $this->getListeners( $eventName );

		foreach ( $listeners as $listener ) {
			if ( $event instanceof StoppableEventInterface && $event->isPropagationStopped() ) {
				$this->logger->debug( 'Event propagation stopped', [
					'plugin'    => 'double-opt-in',
					'component' => 'event-dispatcher',
					'event'     => $eventName,
				] );
				break;
			}

			try {
				$listener( $event );
			} catch ( \Throwable $e ) {
				$this->logger->error( 'Event listener threw exception', [
					'plugin'    => 'double-opt-in',
					'component' => 'event-dispatcher',
					'event'     => $eventName,
					'exception' => $e->getMessage(),
				] );
			}
		}

		return $event;
	}

	/**
	 * {@inheritdoc}
	 */
	public function addListener( string $eventName, callable $listener, int $priority = 10 ): void {
		$this->listeners[ $eventName ][ $priority ][] = $listener;
		unset( $this->sorted[ $eventName ] );

		$this->logger->debug( 'Listener registered', [
			'plugin'    => 'double-opt-in',
			'component' => 'event-dispatcher',
			'event'     => $eventName,
			'priority'  => $priority,
		] );
	}

	/**
	 * {@inheritdoc}
	 */
	public function removeListener( string $eventName, callable $listener ): void {
		if ( ! isset( $this->listeners[ $eventName ] ) ) {
			return;
		}

		foreach ( $this->listeners[ $eventName ] as $priority => &$listeners ) {
			$key = array_search( $listener, $listeners, true );
			if ( $key !== false ) {
				unset( $listeners[ $key ] );
				unset( $this->sorted[ $eventName ] );
			}
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function hasListeners( string $eventName ): bool {
		return ! empty( $this->listeners[ $eventName ] );
	}

	/**
	 * {@inheritdoc}
	 */
	public function getListeners( string $eventName ): array {
		if ( ! isset( $this->listeners[ $eventName ] ) ) {
			return [];
		}

		if ( ! isset( $this->sorted[ $eventName ] ) ) {
			$this->sortListeners( $eventName );
		}

		return $this->sorted[ $eventName ];
	}

	/**
	 * Sort listeners by priority.
	 *
	 * Higher priority numbers execute first (like WordPress hooks).
	 *
	 * @param string $eventName The event name.
	 *
	 * @return void
	 */
	private function sortListeners( string $eventName ): void {
		$this->sorted[ $eventName ] = [];

		if ( isset( $this->listeners[ $eventName ] ) ) {
			krsort( $this->listeners[ $eventName ] ); // Higher priority first
			foreach ( $this->listeners[ $eventName ] as $listeners ) {
				foreach ( $listeners as $listener ) {
					$this->sorted[ $eventName ][] = $listener;
				}
			}
		}
	}

	/**
	 * Register a listener from a WordPress hook.
	 *
	 * This method allows bridging existing WordPress hooks to typed events.
	 *
	 * @param string $wpHookName The WordPress hook name.
	 * @param string $eventClass The event class to listen for.
	 *
	 * @return void
	 */
	public function bridgeWordPressHook( string $wpHookName, string $eventClass ): void {
		add_action( $wpHookName, function ( ...$args ) use ( $eventClass ) {
			// If the first argument is already our typed event, skip
			if ( isset( $args[0] ) && $args[0] instanceof Event ) {
				return;
			}

			$this->logger->debug( 'WordPress hook received (legacy)', [
				'plugin'    => 'double-opt-in',
				'component' => 'event-dispatcher',
				'wp_hook'   => $eventClass::getWordPressHookName(),
			] );
		}, 1 );
	}
}
