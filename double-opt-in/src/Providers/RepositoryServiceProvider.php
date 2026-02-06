<?php
/**
 * Repository Service Provider
 *
 * @package Forge12\DoubleOptIn\Providers
 * @since   4.0.0
 */

namespace Forge12\DoubleOptIn\Providers;

use Forge12\DoubleOptIn\Container\Container;
use Forge12\DoubleOptIn\Container\ServiceProviderInterface;
use Forge12\DoubleOptIn\Repository\OptInRepository;
use Forge12\DoubleOptIn\Repository\OptInRepositoryInterface;
use Forge12\DoubleOptIn\Service\OptInLinkGenerator;
use Forge12\DoubleOptIn\Service\OptInValidator;
use Forge12\Shared\LoggerInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class RepositoryServiceProvider
 *
 * Registers repository and service bindings.
 */
class RepositoryServiceProvider implements ServiceProviderInterface {

	/**
	 * {@inheritdoc}
	 */
	public function register( Container $container ): void {
		// OptIn Repository
		$container->singleton(
			OptInRepositoryInterface::class,
			function ( Container $c ) {
				global $wpdb;
				return new OptInRepository(
					$wpdb,
					$c->get( LoggerInterface::class )
				);
			}
		);

		// OptIn Link Generator
		$container->singleton(
			OptInLinkGenerator::class,
			function () {
				return new OptInLinkGenerator();
			}
		);

		// OptIn Validator
		$container->singleton(
			OptInValidator::class,
			function ( Container $c ) {
				return new OptInValidator(
					$c->get( LoggerInterface::class )
				);
			}
		);
	}
}
