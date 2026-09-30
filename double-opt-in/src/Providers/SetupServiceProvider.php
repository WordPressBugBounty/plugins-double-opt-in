<?php
/**
 * Setup Wizard Service Provider
 *
 * @package Forge12\DoubleOptIn\Providers
 * @since   5.7.0
 */

declare( strict_types=1 );

namespace Forge12\DoubleOptIn\Providers;

use Forge12\DoubleOptIn\Container\BootableProviderInterface;
use Forge12\DoubleOptIn\Container\Container;
use Forge12\DoubleOptIn\FormSettings\FormSettingsService;
use Forge12\DoubleOptIn\Setup\FormDefaults;
use Forge12\DoubleOptIn\Setup\FormPluginDetector;
use Forge12\DoubleOptIn\Setup\SetupMailComposer;
use Forge12\DoubleOptIn\Setup\SetupRedirect;
use Forge12\DoubleOptIn\Setup\SetupRestController;
use Forge12\DoubleOptIn\Setup\SetupService;
use Forge12\DoubleOptIn\Setup\SetupState;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the setup wizard: state, REST routes, activation redirect and
 * the sender defaults it stores.
 */
class SetupServiceProvider implements BootableProviderInterface {

	/**
	 * {@inheritdoc}
	 */
	public function register( Container $container ): void {
		$container->singleton(
			SetupState::class,
			static function () {
				return new SetupState();
			}
		);
		$container->singleton(
			FormPluginDetector::class,
			static function () {
				return new FormPluginDetector();
			}
		);
		$container->singleton(
			SetupService::class,
			static function ( Container $c ) {
				return new SetupService(
					$c->get( SetupState::class ),
					$c->get( FormSettingsService::class ),
					$c->get( FormPluginDetector::class ),
					new SetupMailComposer()
				);
			}
		);
		$container->singleton(
			SetupRestController::class,
			static function ( Container $c ) {
				return new SetupRestController(
					$c->get( SetupService::class ),
					$c->get( SetupState::class )
				);
			}
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function boot( Container $container ): void {
		$container->get( SetupRestController::class )->init();
		SetupRedirect::register();
		FormDefaults::register();
	}
}
