<?php
/**
 * Mail Status Service Provider
 *
 * Records whether each confirmation mail reached the mail server and
 * reports recent failures in Site Health.
 *
 * @package Forge12\DoubleOptIn\Providers
 * @since   5.8.0
 */

declare( strict_types=1 );

namespace Forge12\DoubleOptIn\Providers;

use Forge12\DoubleOptIn\Container\BootableProviderInterface;
use Forge12\DoubleOptIn\Container\Container;
use Forge12\DoubleOptIn\EventSystem\EventDispatcherInterface;
use Forge12\DoubleOptIn\FormSettings\FormSettingsService;
use Forge12\DoubleOptIn\Health\MailFailuresHealthCheck;
use Forge12\DoubleOptIn\Health\SenderDomainCheck;
use Forge12\DoubleOptIn\Repository\OptInMailStatusRepository;
use Forge12\DoubleOptIn\Repository\OptInRepositoryInterface;
use Forge12\DoubleOptIn\Repository\OptInStatsRepository;
use Forge12\DoubleOptIn\Service\ConfirmationMailResender;
use Forge12\DoubleOptIn\Service\DoiMailHeaders;
use Forge12\DoubleOptIn\Service\OptInMailTracker;
use Forge12\DoubleOptIn\Service\StuckOptInsReport;
use Forge12\DoubleOptIn\Setup\FormDefaults;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MailStatusServiceProvider implements BootableProviderInterface {

	/**
	 * {@inheritdoc}
	 */
	public function register( Container $container ): void {
		$container->singleton(
			OptInMailStatusRepository::class,
			static function () {
				global $wpdb;
				return new OptInMailStatusRepository( $wpdb );
			}
		);
		$container->singleton(
			OptInStatsRepository::class,
			static function () {
				global $wpdb;
				return new OptInStatsRepository( $wpdb );
			}
		);
		$container->singleton(
			StuckOptInsReport::class,
			static function ( Container $c ) {
				return new StuckOptInsReport( $c->get( OptInStatsRepository::class ), $c->get( OptInMailStatusRepository::class ) );
			}
		);
		$container->singleton(
			DoiMailHeaders::class,
			static function () {
				return new DoiMailHeaders();
			}
		);
		$container->singleton(
			ConfirmationMailResender::class,
			static function ( Container $c ) {
				return new ConfirmationMailResender(
					$c->get( OptInRepositoryInterface::class ),
					static function ( int $formId ): array {
						return (array) \forge12\contactform7\CF7DoubleOptIn\CF7DoubleOptIn::getInstance()->getParameter( $formId );
					},
					$c->get( DoiMailHeaders::class )
				);
			}
		);
		$container->singleton(
			OptInMailTracker::class,
			static function ( Container $c ) {
				return new OptInMailTracker( $c->get( OptInMailStatusRepository::class ) );
			}
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function boot( Container $container ): void {
		$container->get( OptInMailTracker::class )->register( $container->get( EventDispatcherInterface::class ) );
		$container->get( DoiMailHeaders::class )->register( $container->get( EventDispatcherInterface::class ) );

		// A new failure should show up in Site Health right away, not after
		// the hourly cache runs out.
		add_action(
			OptInMailTracker::RESULT_ACTION,
			static function ( $optInId, $sent ) {
				if ( ! $sent ) {
					delete_transient( MailFailuresHealthCheck::CACHE_KEY );
				}
			},
			20,
			2
		);
		add_action(
			'wp_mail_failed',
			static function () {
				delete_transient( MailFailuresHealthCheck::CACHE_KEY );
			}
		);

		// Dashboard: share of unconfirmed opt-ins, when it is high (C2).
		add_filter(
			'f12_doi_rest_dashboard_stats',
			static function ( $data ) use ( $container ) {
				return $container->get( StuckOptInsReport::class )->addToDashboard( $data );
			}
		);

		// A changed sender should be re-checked on the next Site Health visit.
		add_action(
			'updated_post_meta',
			static function ( $metaId, $postId, $metaKey ) {
				if ( $metaKey === FormSettingsService::META_KEY ) {
					delete_transient( SenderDomainCheck::CACHE_KEY );
				}
			},
			10,
			3
		);

		add_filter(
			'f12_doi_health_checks',
			static function ( $checks ) use ( $container ) {
				$checks   = is_array( $checks ) ? $checks : array();
				$checks[] = new MailFailuresHealthCheck( $container->get( OptInMailStatusRepository::class ) );
				$checks[] = new SenderDomainCheck(
					static function () use ( $container ): array {
						return self::activeSenders( $container );
					}
				);
				return $checks;
			}
		);
	}

	/**
	 * Sender addresses of the forms with Double Opt-In switched on; the
	 * setup wizard's default when none is.
	 *
	 * Elementor forms have composite ids and are not covered.
	 *
	 * @return string[]
	 */
	private static function activeSenders( Container $container ): array {
		$senders = array();

		if ( $container->has( FormSettingsService::class ) ) {
			$service = $container->get( FormSettingsService::class );
			foreach ( $service->getAllFormsFlat() as $form ) {
				if ( ! empty( $form['enabled'] ) && is_numeric( $form['id'] ?? null ) ) {
					$senders[] = $service->getSettings( (int) $form['id'] )->sender;
				}
			}
		}

		if ( $senders === array() ) {
			$senders[] = FormDefaults::get()['sender'];
		}

		return $senders;
	}
}
