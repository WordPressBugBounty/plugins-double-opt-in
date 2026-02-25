<?php
/**
 * GDPR Service Provider
 *
 * @package Forge12\DoubleOptIn\Providers
 * @since   3.2.0
 */

namespace Forge12\DoubleOptIn\Providers;

use Forge12\DoubleOptIn\Admin\SingleConsentExportController;
use Forge12\DoubleOptIn\Container\BootableProviderInterface;
use Forge12\DoubleOptIn\Container\Container;
use Forge12\DoubleOptIn\Repository\OptInRepositoryInterface;
use Forge12\DoubleOptIn\Service\PrivacyIntegration;
use Forge12\Shared\LoggerInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GdprServiceProvider
 *
 * Registers all GDPR-related services in the DI container.
 */
class GdprServiceProvider implements BootableProviderInterface {

	/**
	 * {@inheritdoc}
	 */
	public function register( Container $container ): void {
		$container->singleton( PrivacyIntegration::class, function ( Container $c ) {
			return new PrivacyIntegration(
				$c->get( LoggerInterface::class ),
				$c->get( OptInRepositoryInterface::class )
			);
		} );

		$container->singleton( SingleConsentExportController::class, function ( Container $c ) {
			return new SingleConsentExportController(
				$c->get( LoggerInterface::class ),
				$c->get( OptInRepositoryInterface::class )
			);
		} );
	}

	/**
	 * {@inheritdoc}
	 */
	public function boot( Container $container ): void {
		// Register WordPress Privacy Tools hooks
		$container->get( PrivacyIntegration::class )->register();

		// Register single-record consent export (fallback if Pro is not active)
		$container->get( SingleConsentExportController::class )->registerActions();
	}
}
