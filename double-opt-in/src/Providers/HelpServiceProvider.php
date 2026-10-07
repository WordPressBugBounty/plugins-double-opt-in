<?php
/**
 * Help Service Provider — in-plugin documentation.
 *
 * @package Forge12\DoubleOptIn\Providers
 * @since   5.11.0
 */

declare( strict_types=1 );

namespace Forge12\DoubleOptIn\Providers;

use Forge12\DoubleOptIn\Container\BootableProviderInterface;
use Forge12\DoubleOptIn\Container\Container;
use Forge12\DoubleOptIn\Help\HelpRestController;
use Forge12\DoubleOptIn\Help\HelpSourceLocator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HelpServiceProvider implements BootableProviderInterface {

	/**
	 * {@inheritdoc}
	 */
	public function register( Container $container ): void {
		$container->singleton(
			HelpRestController::class,
			static function () {
				return new HelpRestController( new HelpSourceLocator() );
			}
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function boot( Container $container ): void {
		$container->get( HelpRestController::class )->init();
	}
}
