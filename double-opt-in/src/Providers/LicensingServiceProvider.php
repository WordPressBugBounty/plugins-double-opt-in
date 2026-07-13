<?php
/**
 * Licensing Service Provider
 *
 * @package Forge12\DoubleOptIn\Providers
 * @since   4.3.0
 */

namespace Forge12\DoubleOptIn\Providers;

use Forge12\DoubleOptIn\Container\BootableProviderInterface;
use Forge12\DoubleOptIn\Container\Container;
use Forge12\DoubleOptIn\Licensing\AddonLicenseRegistry;
use Forge12\DoubleOptIn\Licensing\AddonLicenseRegistryInterface;
use Forge12\Shared\LoggerInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class LicensingServiceProvider
 *
 * Registers the addon license registry in the container so addons and
 * license providers can resolve it.
 *
 * Binds both the interface and the concrete class to the same singleton
 * instance. Addons should depend on the interface; license providers may
 * depend on either.
 */
class LicensingServiceProvider implements BootableProviderInterface {

	/**
	 * {@inheritdoc}
	 */
	public function register( Container $container ): void {
		$container->singleton(
			AddonLicenseRegistry::class,
			function () use ( $container ) {
				$registry = AddonLicenseRegistry::getInstance();

				if ( $container->has( LoggerInterface::class ) ) {
					$registry->setLogger( $container->get( LoggerInterface::class ) );
				}

				return $registry;
			}
		);

		// Alias the interface to the same instance so addons can depend on
		// the abstraction. Using a factory avoids a circular reference and
		// resolves through the singleton binding above.
		$container->singleton(
			AddonLicenseRegistryInterface::class,
			function () use ( $container ) {
				return $container->get( AddonLicenseRegistry::class );
			}
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function boot( Container $container ): void {
		// No runtime wiring is needed at boot: the registry is pure state
		// populated by license providers at their own boot time.
	}
}
