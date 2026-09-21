<?php
/**
 * Follow-up Service Provider
 *
 * @package Forge12\DoubleOptIn\Providers
 * @since   5.6.0
 */

declare( strict_types=1 );

namespace Forge12\DoubleOptIn\Providers;

use Forge12\DoubleOptIn\Admin\FollowUpRestController;
use Forge12\DoubleOptIn\Container\BootableProviderInterface;
use Forge12\DoubleOptIn\Container\Container;
use Forge12\DoubleOptIn\FollowUp\FollowUpAdapterRegistry;
use Forge12\DoubleOptIn\FollowUp\FollowUpCoordinator;
use Forge12\DoubleOptIn\Health\DatabaseTableHealthCheck;
use Forge12\DoubleOptIn\Health\HealthCheckRegistry;
use Forge12\DoubleOptIn\Repository\FollowUpRepository;
use Forge12\DoubleOptIn\Repository\FollowUpRepositoryInterface;
use Forge12\DoubleOptIn\Repository\FollowUpSchema;
use Forge12\Shared\LoggerInterface;
use forge12\contactform7\CF7DoubleOptIn\OptIn;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wires the follow-up coordinator: container bindings, the legacy
 * accessor, cron (single retries + hourly sweep), deletion cascade and
 * the admin REST routes.
 *
 * Must be registered BEFORE AddonServiceProvider so the adapter
 * registry exists when the form addons boot.
 */
class FollowUpServiceProvider implements BootableProviderInterface {

	/**
	 * {@inheritdoc}
	 */
	public function register( Container $container ): void {
		$container->singleton(
			FollowUpRepositoryInterface::class,
			function () {
				global $wpdb;
				return new FollowUpRepository( $wpdb );
			}
		);

		$container->singleton(
			FollowUpAdapterRegistry::class,
			function () {
				return new FollowUpAdapterRegistry();
			}
		);

		$container->singleton(
			FollowUpCoordinator::class,
			function ( Container $c ) {
				return new FollowUpCoordinator(
					$c->get( FollowUpRepositoryInterface::class ),
					$c->get( FollowUpAdapterRegistry::class ),
					$c->get( LoggerInterface::class )
				);
			}
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function boot( Container $container ): void {
		$coordinator = $container->get( FollowUpCoordinator::class );
		FollowUpCoordinator::setInstance( $coordinator );

		$loader = static function ( int $id ): ?OptIn {
			return OptIn::get_by_id( $id );
		};

		add_action(
			FollowUpCoordinator::CRON_RETRY_HOOK,
			static function ( $optInId ) use ( $coordinator, $loader ) {
				$optIn = $loader( (int) $optInId );
				if ( $optIn ) {
					$coordinator->run( $optIn, \Forge12\DoubleOptIn\FollowUp\FollowUpAttempt::TRIGGER_CRON );
				}
			},
			10,
			1
		);

		add_action(
			FollowUpCoordinator::CRON_SWEEP_HOOK,
			static function () use ( $coordinator, $loader ) {
				if ( FollowUpSchema::exists() ) {
					$coordinator->sweep( $loader );
				}
			}
		);

		add_action(
			'init',
			static function () {
				if ( ! wp_next_scheduled( FollowUpCoordinator::CRON_SWEEP_HOOK ) ) {
					wp_schedule_event( time() + 300, 'hourly', FollowUpCoordinator::CRON_SWEEP_HOOK );
				}
			}
		);

		// Deletion cascade. `$row` is the opt-in row (array or object)
		// passed by every core deletion path; the sweep's orphan cleanup
		// covers paths that do not fire this action.
		add_action(
			'f12_doi_optin_pre_delete',
			static function ( $row ) use ( $coordinator ) {
				$id = 0;
				if ( is_array( $row ) ) {
					$id = (int) ( $row['id'] ?? 0 );
				} elseif ( is_object( $row ) && isset( $row->id ) ) {
					$id = (int) $row->id;
				}
				$coordinator->forget( $id );
			},
			20,
			1
		);

		// Dev-mode confirmation reset (WP_DEBUG only): drop the status too,
		// otherwise the re-confirmation finds every action already done.
		add_action(
			'f12_doi_optin_confirmation_reset',
			static function ( $optInId ) use ( $coordinator ) {
				$coordinator->forget( (int) $optInId );
			},
			10,
			1
		);

		( new FollowUpRestController( $coordinator, $loader ) )->init();

		// Labels are translated, so register on init — boot() runs on
		// plugins_loaded, before translations may be loaded.
		if ( $container->has( HealthCheckRegistry::class ) ) {
			$healthRegistry = $container->get( HealthCheckRegistry::class );
			add_action(
				'init',
				static function () use ( $healthRegistry ) {
					$healthRegistry->register(
						new DatabaseTableHealthCheck(
							'f12_doi_table_followup',
							'core',
							FollowUpSchema::TABLE_NAME,
							__( 'Follow-up action status', 'double-opt-in' ),
							__( 'Open Double Opt-In', 'double-opt-in' ),
							admin_url( 'admin.php?page=f12-doi-admin' )
						)
					);
				},
				20
			);
		}
	}
}
