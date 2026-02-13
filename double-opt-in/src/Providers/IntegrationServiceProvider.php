<?php
/**
 * Integration Service Provider
 *
 * @package Forge12\DoubleOptIn\Providers
 * @since   4.0.0
 */

namespace Forge12\DoubleOptIn\Providers;

use Forge12\DoubleOptIn\Container\Container;
use Forge12\DoubleOptIn\Container\BootableProviderInterface;
use Forge12\DoubleOptIn\Integration\FormIntegrationRegistry;
use Forge12\DoubleOptIn\Integration\CF7Integration;
use Forge12\DoubleOptIn\Integration\AvadaIntegration;
use Forge12\DoubleOptIn\Frontend\ErrorNotification;
use Forge12\Shared\LoggerInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class IntegrationServiceProvider
 *
 * Registers the form integration registry and core integrations.
 *
 * NOTE: The new integration system is prepared but NOT active by default.
 * The legacy classes (CF7Frontend, AvadaFrontend) remain active for backward compatibility.
 *
 * To switch to the new system, use the filter:
 * add_filter( 'f12_cf7_doubleoptin_use_new_integration_system', '__return_true' );
 *
 * This will disable the legacy classes and activate the new event-based integrations.
 */
class IntegrationServiceProvider implements BootableProviderInterface {

	/**
	 * {@inheritdoc}
	 */
	public function register( Container $container ): void {
		// Register the integration registry as a singleton
		$container->singleton( FormIntegrationRegistry::class, function () use ( $container ) {
			$registry = FormIntegrationRegistry::getInstance();

			if ( $container->has( LoggerInterface::class ) ) {
				$registry->setLogger( $container->get( LoggerInterface::class ) );
			}

			return $registry;
		} );

		// Register CF7 Integration
		$container->singleton( CF7Integration::class, function ( Container $c ) {
			return new CF7Integration(
				$c->get( LoggerInterface::class )
			);
		} );

		// Register Avada Integration
		$container->singleton( AvadaIntegration::class, function ( Container $c ) {
			return new AvadaIntegration(
				$c->get( LoggerInterface::class )
			);
		} );
	}

	/**
	 * {@inheritdoc}
	 */
	public function boot( Container $container ): void {
		/**
		 * Filter to enable the new integration system.
		 *
		 * By default, the new event-based integration system is active.
		 * Set this to false to use the legacy classes for backward compatibility.
		 *
		 * @since 4.0.0
		 *
		 * @param bool $useNewSystem Whether to use the new integration system. Default: true.
		 */
		$useNewSystem = apply_filters( 'f12_cf7_doubleoptin_use_new_integration_system', true );

		if ( ! $useNewSystem ) {
			// Legacy system is active - only register the registry for API access
			// but don't initialize integrations (legacy controllers handle this)
			if ( $container->has( LoggerInterface::class ) ) {
				$container->get( LoggerInterface::class )->debug(
					'New integration system is disabled, legacy classes remain active',
					[ 'plugin' => 'double-opt-in', 'component' => 'integration-provider' ]
				);
			}
			return;
		}

		// Register universal error notification system
		$errorNotification = new ErrorNotification();
		$errorNotification->register();

		$registry = $container->get( FormIntegrationRegistry::class );

		// Register core integrations
		$this->registerCoreIntegrations( $container, $registry );

		// Allow other plugins to register integrations
		do_action( 'f12_cf7_doubleoptin_register_integrations', $registry, $container );

		// Initialize available integrations
		add_action( 'init', function () use ( $registry ) {
			$registry->initialize();
		}, 5 );

		if ( $container->has( LoggerInterface::class ) ) {
			$container->get( LoggerInterface::class )->info(
				'New integration system is active',
				[ 'plugin' => 'double-opt-in', 'component' => 'integration-provider' ]
			);
		}
	}

	/**
	 * Register core integrations.
	 *
	 * @param Container               $container The DI container.
	 * @param FormIntegrationRegistry $registry  The integration registry.
	 *
	 * @return void
	 */
	private function registerCoreIntegrations( Container $container, FormIntegrationRegistry $registry ): void {
		// CF7 Integration
		try {
			$cf7Integration = $container->get( CF7Integration::class );
			$registry->register( $cf7Integration );
		} catch ( \Exception $e ) {
			// Log but don't fail if CF7 integration can't be loaded
			if ( $container->has( LoggerInterface::class ) ) {
				$container->get( LoggerInterface::class )->warning(
					'Failed to register CF7 integration',
					[ 'plugin' => 'double-opt-in', 'error' => $e->getMessage() ]
				);
			}
		}

		// Avada Integration
		try {
			$avadaIntegration = $container->get( AvadaIntegration::class );
			$registry->register( $avadaIntegration );
		} catch ( \Exception $e ) {
			// Log but don't fail if Avada integration can't be loaded
			if ( $container->has( LoggerInterface::class ) ) {
				$container->get( LoggerInterface::class )->warning(
					'Failed to register Avada integration',
					[ 'plugin' => 'double-opt-in', 'error' => $e->getMessage() ]
				);
			}
		}
	}
}
