<?php
/**
 * Form Settings Service Provider
 *
 * @package Forge12\DoubleOptIn\Providers
 * @since   4.1.0
 */

namespace Forge12\DoubleOptIn\Providers;

use Forge12\DoubleOptIn\Admin\FormSettingsController;
use Forge12\DoubleOptIn\Container\BootableProviderInterface;
use Forge12\DoubleOptIn\Container\Container;
use Forge12\DoubleOptIn\FormSettings\FormSettingsDTO;
use Forge12\DoubleOptIn\FormSettings\FormSettingsService;
use Forge12\DoubleOptIn\FormSettings\FormSettingsValidator;
use Forge12\DoubleOptIn\Integration\FormIntegrationRegistry;
use Forge12\Shared\LoggerInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FormSettingsServiceProvider
 *
 * Registers form settings services in the DI container.
 */
class FormSettingsServiceProvider implements BootableProviderInterface {

	/**
	 * Register services in the container.
	 *
	 * @param Container $container The DI container.
	 *
	 * @return void
	 */
	public function register( Container $container ): void {
		// Register validator as singleton
		$container->singleton( FormSettingsValidator::class, function () {
			return new FormSettingsValidator();
		} );

		// Register service as singleton
		$container->singleton( FormSettingsService::class, function ( Container $c ) {
			return new FormSettingsService(
				$c->get( LoggerInterface::class ),
				FormIntegrationRegistry::getInstance(),
				$c->get( FormSettingsValidator::class )
			);
		} );

		// Register controller as singleton
		$container->singleton( FormSettingsController::class, function ( Container $c ) {
			return new FormSettingsController(
				$c->get( LoggerInterface::class ),
				$c->get( FormSettingsService::class ),
				$c->get( FormSettingsValidator::class )
			);
		} );
	}

	/**
	 * Boot services.
	 *
	 * @param Container $container The DI container.
	 *
	 * @return void
	 */
	public function boot( Container $container ): void {
		// Register AJAX actions
		$controller = $container->get( FormSettingsController::class );
		$controller->registerActions();

		// Note: UIForms is automatically loaded by the UI class
		// since it follows the UI*.class.php naming convention in the ui/ directory
	}
}
