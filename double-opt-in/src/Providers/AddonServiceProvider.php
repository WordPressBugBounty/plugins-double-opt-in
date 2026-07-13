<?php
/**
 * Addon Service Provider
 *
 * Registers the AddonRegistry in the container and wires up the addon
 * bootstrap lifecycle.
 *
 * @package Forge12\DoubleOptIn\Providers
 * @since   4.3.0
 */

namespace Forge12\DoubleOptIn\Providers;

use Forge12\DoubleOptIn\Addon\AddonRegistry;
use Forge12\DoubleOptIn\Container\BootableProviderInterface;
use Forge12\DoubleOptIn\Container\Container;
use Forge12\Shared\LoggerInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AddonServiceProvider
 *
 * Lifecycle:
 *   1. {@see register()} puts the AddonRegistry into the container.
 *   2. {@see boot()} schedules the `f12_cf7_doubleoptin_register_addons`
 *      action for `plugins_loaded` priority 20. This is one priority step
 *      later than the core instantiation at priority 10, giving addon
 *      plugins that also hook at priority 10 time to load.
 *   3. After addons register, the registry is booted so each addon's
 *      own {@see \Forge12\DoubleOptIn\Addon\AddonInterface::boot()} runs
 *      with the fully wired container.
 */
class AddonServiceProvider implements BootableProviderInterface {

	/**
	 * {@inheritdoc}
	 */
	public function register( Container $container ): void {
		$container->singleton(
			AddonRegistry::class,
			function () use ( $container ) {
				$registry = AddonRegistry::getInstance();

				if ( $container->has( LoggerInterface::class ) ) {
					$registry->setLogger( $container->get( LoggerInterface::class ) );
				}

				return $registry;
			}
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function boot( Container $container ): void {
		$registry = $container->get( AddonRegistry::class );

		/*
		 * Decoupling rationale: boot() itself runs during Container::boot(),
		 * which fires at plugins_loaded priority 10 while the main plugin is
		 * being instantiated. Addon plugins that hook on plugins_loaded:10
		 * may not have loaded yet, so we defer the registration window by
		 * one priority step. Late-registering addons after priority 20 will
		 * still be picked up by the register() method's late-boot path.
		 */
		add_action(
			'plugins_loaded',
			function () use ( $registry, $container ) {
				/**
				 * Fires once per request, giving addon plugins the opportunity
				 * to register themselves with the AddonRegistry.
				 *
				 * @since 4.3.0
				 *
				 * @param AddonRegistry $registry  The addon registry.
				 * @param Container     $container The core DI container, for
				 *                                 addons that need it at
				 *                                 registration time (rare —
				 *                                 prefer AddonInterface::boot()).
				 */
				do_action( 'f12_cf7_doubleoptin_register_addons', $registry, $container );

				$registry->bootAll( $container );
			},
			20
		);
	}
}
