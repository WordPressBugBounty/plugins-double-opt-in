<?php
/**
 * Event Service Provider
 *
 * @package Forge12\DoubleOptIn\Providers
 * @since   4.0.0
 */

namespace Forge12\DoubleOptIn\Providers;

use Forge12\DoubleOptIn\Container\Container;
use Forge12\DoubleOptIn\Container\BootableProviderInterface;
use Forge12\DoubleOptIn\EventSystem\EventDispatcher;
use Forge12\DoubleOptIn\EventSystem\EventDispatcherInterface;
use Forge12\DoubleOptIn\Bridge\WordPressHookBridge;
use Forge12\Shared\LoggerInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class EventServiceProvider
 *
 * Registers the event dispatcher and sets up event listeners.
 */
class EventServiceProvider implements BootableProviderInterface {

	/**
	 * {@inheritdoc}
	 */
	public function register( Container $container ): void {
		// Event Dispatcher
		$container->singleton(
			EventDispatcherInterface::class,
			function ( Container $c ) {
				return new EventDispatcher(
					$c->get( LoggerInterface::class ),
					true // Bridge to WordPress hooks for BC
				);
			}
		);

		// WordPress Hook Bridge
		$container->singleton(
			WordPressHookBridge::class,
			function ( Container $c ) {
				return new WordPressHookBridge(
					$c->get( EventDispatcherInterface::class ),
					$c->get( LoggerInterface::class )
				);
			}
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function boot( Container $container ): void {
		$dispatcher = $container->get( EventDispatcherInterface::class );

		// Initialize the WordPress Hook Bridge
		$hookBridge = $container->get( WordPressHookBridge::class );
		$hookBridge->registerBridges();

		/**
		 * Action: f12_cf7_doubleoptin_register_event_listeners
		 *
		 * Allows external code to register event listeners.
		 *
		 * @param EventDispatcherInterface $dispatcher The event dispatcher.
		 * @param WordPressHookBridge      $hookBridge The hook bridge for BC.
		 *
		 * @since 4.0.0
		 */
		do_action( 'f12_cf7_doubleoptin_register_event_listeners', $dispatcher, $hookBridge );
	}
}
