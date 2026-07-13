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
		$container->singleton(
			EmailTemplatePostType::class,
			function () {
				return new EmailTemplatePostType();
			}
		);

		// Register Repository
		$container->singleton(
			EmailTemplateRepository::class,
			function () {
				return new EmailTemplateRepository();
			}
		);

		// Register HTML Generator
		$container->singleton(
			EmailHtmlGenerator::class,
			function () {
				return new EmailHtmlGenerator();
			}
		);

		// Register REST Controller
		$container->singleton(
			EmailTemplateRestController::class,
			function ( Container $c ) {
				return new EmailTemplateRestController(
					$c->get( EmailTemplateRepository::class ),
					$c->get( EmailHtmlGenerator::class )
				);
			}
		);

		// Register Block Registry
		$container->singleton(
			BlockRegistry::class,
			function () {
				return new BlockRegistry();
			}
		);

		// Register Integration
		$container->singleton(
			EmailTemplateIntegration::class,
			function () {
				return new EmailTemplateIntegration();
			}
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function boot( Container $container ): void {
		// Initialize Post Type — needed even on free sites so that
		// existing template posts in wp_posts continue to behave like
		// the registered post type (admin column, capabilities, etc.).
		$postType = $container->get( EmailTemplatePostType::class );
		$postType->init();

		// REST controller registration is OWNED BY addon-email-editor:
		// its boot() picks the controller up from the same container
		// and calls init() only when the addon is licensed + active.
		// Free / unlicensed sites get no API surface, which is the
		// intent of Pro-gating the editor + templates feature.

		// Initialize Integration with CF7/Avada — used by the email
		// pipeline for templates referenced in form settings, so it
		// stays in Core (otherwise free users couldn't send templated
		// confirmation mails for templates created earlier or via the
		// legacy UI).
		$integration = $container->get( EmailTemplateIntegration::class );
		$integration->init();
	}
}
