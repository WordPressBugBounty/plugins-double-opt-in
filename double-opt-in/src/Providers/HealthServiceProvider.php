<?php
/**
 * Health Service Provider
 *
 * @package Forge12\DoubleOptIn\Providers
 * @since   5.3.0
 */

namespace Forge12\DoubleOptIn\Providers;

use Forge12\DoubleOptIn\Container\BootableProviderInterface;
use Forge12\DoubleOptIn\Container\Container;
use Forge12\DoubleOptIn\Health\DatabaseTableHealthCheck;
use Forge12\DoubleOptIn\Health\HealthCheckRegistry;
use Forge12\DoubleOptIn\Health\SiteHealthIntegration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class HealthServiceProvider
 *
 * Wires the health-check registry and its Site Health surfaces.
 *
 * Registration of *addon* checks happens through the
 * `f12_doi_health_checks` filter, which the registry applies lazily on
 * first read — so this provider does not need to run after addon boot,
 * and addons do not need a Core new enough to know about this API.
 *
 * Only the Core's own tables are registered here. They are the three
 * that `OnActivation.php` creates; if one of them is missing the plugin
 * is broken in a way that has, historically, only shown up as a silent
 * failure much later.
 */
class HealthServiceProvider implements BootableProviderInterface {

	/**
	 * {@inheritdoc}
	 */
	public function register( Container $container ): void {
		$container->singleton(
			HealthCheckRegistry::class,
			function () {
				return new HealthCheckRegistry();
			}
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function boot( Container $container ): void {
		$registry = $container->get( HealthCheckRegistry::class );

		$optInsUrl = admin_url( 'admin.php?page=f12-doi-admin' );

		$registry->register(
			new DatabaseTableHealthCheck(
				'f12_doi_table_optin',
				'core',
				'f12_cf7_doubleoptin',
				__( 'Opt-in records', 'double-opt-in' ),
				__( 'Open Double Opt-In', 'double-opt-in' ),
				$optInsUrl
			)
		);

		$registry->register(
			new DatabaseTableHealthCheck(
				'f12_doi_table_categories',
				'core',
				'f12_cf7_doubleoptin_categories',
				__( 'Opt-in categories', 'double-opt-in' ),
				__( 'Open Double Opt-In', 'double-opt-in' ),
				$optInsUrl
			)
		);

		$registry->register(
			new DatabaseTableHealthCheck(
				'f12_doi_table_audit_log',
				'core',
				'f12_cf7_doubleoptin_audit_log',
				__( 'Consent audit log', 'double-opt-in' ),
				__( 'Open Double Opt-In', 'double-opt-in' ),
				$optInsUrl
			)
		);

		( new SiteHealthIntegration( $registry ) )->register();
	}
}
