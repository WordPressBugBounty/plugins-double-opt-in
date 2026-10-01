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

		// The template REST routes (editing) belong to the email editor
		// add-on since Core 5.8 / add-on 1.1.0. Core keeps what renders
		// saved templates at send time.

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

		// The template REST routes live in addon-email-editor (1.1.0+),
		// which registers them itself. Core ships no editing code.

		// Initialize Integration with CF7/Avada — used by the email
		// pipeline for templates referenced in form settings, so it
		// stays in Core (otherwise free users couldn't send templated
		// confirmation mails for templates created earlier or via the
		// legacy UI).
		$integration = $container->get( EmailTemplateIntegration::class );
		$integration->init();
	}
}
