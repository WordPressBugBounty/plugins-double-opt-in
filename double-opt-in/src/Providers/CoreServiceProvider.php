<?php
/**
 * Core Service Provider
 *
 * @package Forge12\DoubleOptIn\Providers
 * @since   4.0.0
 */

namespace Forge12\DoubleOptIn\Providers;

use Forge12\DoubleOptIn\Container\Container;
use Forge12\DoubleOptIn\Container\BootableProviderInterface;
use Forge12\Shared\LoggerInterface;
use Forge12\Shared\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CoreServiceProvider
 *
 * Registers core services like Logger, TemplateHandler, Messages.
 */
class CoreServiceProvider implements BootableProviderInterface {

	/**
	 * {@inheritdoc}
	 */
	public function register( Container $container ): void {
		// Logger
		$container->singleton( LoggerInterface::class, function () {
			return Logger::getInstance();
		} );

		// TemplateHandler
		$container->singleton(
			\forge12\contactform7\CF7DoubleOptIn\TemplateHandler::class,
			function () {
				return \forge12\contactform7\CF7DoubleOptIn\TemplateHandler::getInstance();
			}
		);

		// Messages
		$container->singleton(
			\forge12\contactform7\CF7DoubleOptIn\Messages::class,
			function () {
				return \forge12\contactform7\CF7DoubleOptIn\Messages::getInstance();
			}
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function boot( Container $container ): void {
		// Initialize logger in container for debugging
		$container->setLogger( $container->get( LoggerInterface::class ) );
	}
}
