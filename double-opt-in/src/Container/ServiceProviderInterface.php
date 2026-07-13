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
 * @internal
 *
 * Service providers are responsible for registering bindings in the container.
 * Used by Core to wire its own services. Addons do NOT implement this —
 * they register their services inline inside {@see \Forge12\DoubleOptIn\Addon\AddonInterface::boot()}.
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
