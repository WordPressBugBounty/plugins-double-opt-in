<?php
/**
 * Email Template Service Provider
 *
 * @package Forge12\DoubleOptIn\Providers
 * @since   4.0.0
 */

namespace Forge12\DoubleOptIn\Providers;

use Forge12\DoubleOptIn\Container\Container;
use Forge12\DoubleOptIn\Container\BootableProviderInterface;
use Forge12\DoubleOptIn\EmailTemplates\EmailTemplatePostType;
use Forge12\DoubleOptIn\EmailTemplates\EmailTemplateRepository;
use Forge12\DoubleOptIn\EmailTemplates\EmailTemplateRestController;
use Forge12\DoubleOptIn\EmailTemplates\EmailHtmlGenerator;
use Forge12\DoubleOptIn\EmailTemplates\EmailTemplateIntegration;
use Forge12\DoubleOptIn\EmailTemplates\BlockRegistry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class EmailTemplateServiceProvider
 *
 * Registers email template services.
 */
class EmailTemplateServiceProvider implements BootableProviderInterface {

	/**
	 * {@inheritdoc}
	 */
	public function register( Container $container ): void {
		// Register Post Type
		$container->singleton( EmailTemplatePostType::class, function () {
			return new EmailTemplatePostType();
		} );

		// Register Repository
		$container->singleton( EmailTemplateRepository::class, function () {
			return new EmailTemplateRepository();
		} );

		// Register HTML Generator
		$container->singleton( EmailHtmlGenerator::class, function () {
			return new EmailHtmlGenerator();
		} );

		// Register REST Controller
		$container->singleton( EmailTemplateRestController::class, function ( Container $c ) {
			return new EmailTemplateRestController(
				$c->get( EmailTemplateRepository::class ),
				$c->get( EmailHtmlGenerator::class )
			);
		} );

		// Register Block Registry
		$container->singleton( BlockRegistry::class, function () {
			return new BlockRegistry();
		} );

		// Register Integration
		$container->singleton( EmailTemplateIntegration::class, function () {
			return new EmailTemplateIntegration();
		} );
	}

	/**
	 * {@inheritdoc}
	 */
	public function boot( Container $container ): void {
		// Initialize Post Type
		$postType = $container->get( EmailTemplatePostType::class );
		$postType->init();

		// Initialize REST Controller
		$restController = $container->get( EmailTemplateRestController::class );
		$restController->init();

		// Initialize Integration with CF7/Avada
		$integration = $container->get( EmailTemplateIntegration::class );
		$integration->init();
	}
}
