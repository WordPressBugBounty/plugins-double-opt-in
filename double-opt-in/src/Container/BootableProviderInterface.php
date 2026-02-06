<?php
/**
 * Bootable Service Provider Interface
 *
 * @package Forge12\DoubleOptIn\Container
 * @since   4.0.0
 */

namespace Forge12\DoubleOptIn\Container;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface BootableProviderInterface
 *
 * Providers implementing this interface will have their boot method called
 * after all providers have been registered.
 */
interface BootableProviderInterface extends ServiceProviderInterface {

	/**
	 * Bootstrap the service.
	 *
	 * Called after all providers are registered.
	 *
	 * @param Container $container The DI container.
	 *
	 * @return void
	 */
	public function boot( Container $container ): void;
}
