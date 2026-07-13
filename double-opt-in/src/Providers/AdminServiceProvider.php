<?php
/**
 * Admin Service Provider
 *
 * @package Forge12\DoubleOptIn\Providers
 * @since   4.2.0
 */

namespace Forge12\DoubleOptIn\Providers;

use Forge12\DoubleOptIn\Container\Container;
use Forge12\DoubleOptIn\Container\BootableProviderInterface;
use Forge12\DoubleOptIn\Admin\AdminNoticeIncompleteForms;
use Forge12\DoubleOptIn\Admin\AdminPageController;
use Forge12\DoubleOptIn\Admin\AdminRestController;
use Forge12\DoubleOptIn\Audit\AuditLogger;
use Forge12\DoubleOptIn\FormSettings\FormSettingsService;
use Forge12\DoubleOptIn\FormSettings\FormSettingsValidator;
use Forge12\Shared\LoggerInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AdminServiceProvider
 *
 * Registers admin REST API and audit services.
 */
class AdminServiceProvider implements BootableProviderInterface {

	/**
	 * {@inheritdoc}
	 */
	public function register( Container $container ): void {
		// Register Admin REST Controller
		$container->singleton(
			AdminRestController::class,
			function ( Container $c ) {
				return new AdminRestController(
					$c->get( LoggerInterface::class ),
					$c->get( FormSettingsService::class ),
					$c->get( FormSettingsValidator::class )
				);
			}
		);

		// Register Admin Page Controller (React SPA)
		$container->singleton(
			AdminPageController::class,
			function () {
				return new AdminPageController();
			}
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function boot( Container $container ): void {
		// Initialize Admin REST Controller
		$restController = $container->get( AdminRestController::class );
		$restController->init();

		// Initialize Admin Page Controller (React SPA)
		$pageController = $container->get( AdminPageController::class );
		$pageController->init();

		// Register audit log hooks
		AuditLogger::registerHooks();

		// Render the completeness-sweep admin notice for any forms that
		// the MigrationFormCompletenessSweep auto-disabled at upgrade
		// time. Notice persists until dismissed (plan §2.4).
		( new AdminNoticeIncompleteForms() )->register();
	}
}
