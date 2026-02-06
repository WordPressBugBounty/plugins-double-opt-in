<?php
/**
 * Service Provider Interface
 *
 * @package Forge12\DoubleOptIn\Container
 * @since   4.0.0
 */

namespace Forge12\DoubleOptIn\Container;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface ServiceProviderInterface
 *
 * Service providers are responsible for registering bindings in the container.
 */
interface ServiceProviderInterface {

	/**
	 * Register bindings in the container.
	 *
	 * This method is called before boot.
	 *
	 * @param Container $container The DI container.
	 *
	 * @return void
	 */
	public function register( Container $container ): void;
}
