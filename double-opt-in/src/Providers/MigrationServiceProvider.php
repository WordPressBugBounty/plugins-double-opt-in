<?php
/**
 * Migration Service Provider
 *
 * @package Forge12\DoubleOptIn\Providers
 * @since   4.3.0
 */

namespace Forge12\DoubleOptIn\Providers;

use Forge12\DoubleOptIn\Container\BootableProviderInterface;
use Forge12\DoubleOptIn\Container\Container;
use Forge12\DoubleOptIn\Migration\MigrationFormCompletenessSweep;
use Forge12\DoubleOptIn\Migration\MigrationRegistry;
use Forge12\Shared\LoggerInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MigrationServiceProvider
 *
 * Registers the MigrationRegistry in the container and schedules the
 * pending-migration runner on `admin_init` priority 20 (after
 * `f12_cf7_doubleoptin_register_addons` fires at plugins_loaded:20,
 * giving addons their chance to register migrations before they run).
 *
 * Running only on `admin_init` keeps DDL out of the frontend request
 * cycle: migrations apply when a site admin visits the admin, which is
 * the same trigger WordPress core uses for database upgrades.
 */
class MigrationServiceProvider implements BootableProviderInterface {

	/**
	 * {@inheritdoc}
	 */
	public function register( Container $container ): void {
		$container->singleton(
			MigrationRegistry::class,
			function () use ( $container ) {
				$registry = MigrationRegistry::getInstance();

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
		// Register core migrations. Addons register their own migrations
		// in their own boot() methods — see MigrationInterface docblock.
		$registry = $container->get( MigrationRegistry::class );
		$registry->register( new MigrationFormCompletenessSweep() );

		add_action(
			'admin_init',
			function () use ( $container ) {
				$registry = $container->get( MigrationRegistry::class );
				$registry->runPending();
			},
			20
		);
	}
}
