<?php
/**
 * WordPress Hook Bridge
 *
 * Bridges existing WordPress hooks to typed events and vice versa.
 *
 * @package Forge12\DoubleOptIn\Bridge
 * @since   4.0.0
 */

namespace Forge12\DoubleOptIn\Bridge;

use Forge12\DoubleOptIn\EventSystem\EventDispatcherInterface;
use Forge12\DoubleOptIn\Events\Lifecycle\OptInCreatedEvent;
use Forge12\DoubleOptIn\Events\Lifecycle\OptInConfirmedEvent;
use Forge12\DoubleOptIn\Events\Lifecycle\OptInDeletedEvent;
use Forge12\DoubleOptIn\Events\Lifecycle\OptInExpiredEvent;
use Forge12\DoubleOptIn\Events\Form\FormSubmittedEvent;
use Forge12\DoubleOptIn\Events\Mail\MailPreparingEvent;
use Forge12\DoubleOptIn\Events\Mail\MailSentEvent;
use Forge12\Shared\LoggerInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WordPressHookBridge
 *
 * Ensures backward compatibility by bridging between WordPress hooks and typed events.
 */
class WordPressHookBridge {

	private EventDispatcherInterface $dispatcher;
	private LoggerInterface $logger;

	/**
	 * Constructor.
	 *
	 * @param EventDispatcherInterface $dispatcher The event dispatcher.
	 * @param LoggerInterface          $logger     The logger.
	 */
	public function __construct( EventDispatcherInterface $dispatcher, LoggerInterface $logger ) {
		$this->dispatcher = $dispatcher;
		$this->logger     = $logger;
	}

	/**
	 * Register all bridges between WordPress hooks and typed events.
	 *
	 * @return void
	 */
	public function registerBridges(): void {
		$this->logger->info( 'Registering WordPress hook bridges', [
			'plugin'    => 'double-opt-in',
			'component' => 'hook-bridge',
		] );

		// Register listeners for legacy hooks that should trigger typed events
		$this->registerLegacyHookListeners();

		// Register action to provide typed events to legacy hook listeners
		$this->registerTypedEventBridges();
	}

	/**
	 * Register listeners for legacy WordPress hooks.
	 *
	 * When legacy code fires do_action(), we can react and dispatch typed events.
	 *
	 * @return void
	 */
	private function registerLegacyHookListeners(): void {
		// Legacy: f12_cf7_doubleoptin_sent (old hook when OptIn is created)
		add_action( 'f12_cf7_doubleoptin_sent', function ( $form, $formId ) {
			$this->logger->debug( 'Legacy hook f12_cf7_doubleoptin_sent received', [
				'plugin'  => 'double-opt-in',
				'form_id' => $formId,
			] );
			// Note: The typed event should be dispatched by the new code,
			// this is just for logging/monitoring legacy usage
		}, 1, 2 );

		// Legacy: f12_cf7_doubleoptin_before_confirm
		add_action( 'f12_cf7_doubleoptin_before_confirm', function ( $hash, $optIn ) {
			$this->logger->debug( 'Legacy hook f12_cf7_doubleoptin_before_confirm received', [
				'plugin' => 'double-opt-in',
				'hash'   => $hash,
			] );
		}, 1, 2 );

		// Legacy: f12_cf7_doubleoptin_after_confirm
		add_action( 'f12_cf7_doubleoptin_after_confirm', function ( $hashOrEvent, $optIn = null ) {
			// Skip if this is already a typed event (dispatched by EventDispatcher)
			if ( $hashOrEvent instanceof OptInConfirmedEvent ) {
				return;
			}

			$this->logger->debug( 'Legacy hook f12_cf7_doubleoptin_after_confirm received', [
				'plugin' => 'double-opt-in',
				'hash'   => is_string( $hashOrEvent ) ? $hashOrEvent : 'event',
			] );
		}, 1, 2 );
	}

	/**
	 * Register bridges that convert typed events to legacy hook calls.
	 *
	 * This ensures that code using add_action() on old hooks still works.
	 *
	 * @return void
	 */
	private function registerTypedEventBridges(): void {
		// When OptInCreatedEvent is dispatched, also fire legacy hook
		$this->dispatcher->addListener(
			OptInCreatedEvent::class,
			function ( OptInCreatedEvent $event ) {
				$this->logger->debug( 'Bridging OptInCreatedEvent to legacy hooks', [
					'plugin'   => 'double-opt-in',
					'optin_id' => $event->getOptInId(),
				] );

				/**
				 * Legacy action for backward compatibility.
				 *
				 * @deprecated Use OptInCreatedEvent listener instead.
				 *
				 * @param int    $formId   The form ID.
				 * @param string $formType The form type.
				 * @param int    $optInId  The opt-in ID.
				 */
				do_action(
					'f12_cf7_doubleoptin_optin_created_legacy',
					$event->getFormId(),
					$event->getFormType(),
					$event->getOptInId()
				);
			},
			5 // Low priority so typed listeners run first
		);

		// When OptInConfirmedEvent is dispatched, ensure legacy listeners get data
		$this->dispatcher->addListener(
			OptInConfirmedEvent::class,
			function ( OptInConfirmedEvent $event ) {
				$this->logger->debug( 'OptInConfirmedEvent dispatched', [
					'plugin'   => 'double-opt-in',
					'optin_id' => $event->getOptInId(),
					'hash'     => $event->getHash(),
				] );
			},
			5
		);

		// When OptInDeletedEvent is dispatched
		$this->dispatcher->addListener(
			OptInDeletedEvent::class,
			function ( OptInDeletedEvent $event ) {
				$this->logger->debug( 'OptInDeletedEvent dispatched', [
					'plugin'     => 'double-opt-in',
					'hash'       => $event->getHash(),
					'deleted_by' => $event->getDeletedBy(),
				] );
			},
			5
		);

		// When OptInExpiredEvent is dispatched (from cleanup)
		$this->dispatcher->addListener(
			OptInExpiredEvent::class,
			function ( OptInExpiredEvent $event ) {
				$this->logger->notice( 'OptInExpiredEvent dispatched', [
					'plugin'       => 'double-opt-in',
					'cleanup_type' => $event->getCleanupType(),
					'rows_deleted' => $event->getRowsDeleted(),
				] );
			},
			5
		);

		// When MailPreparingEvent is dispatched
		$this->dispatcher->addListener(
			MailPreparingEvent::class,
			function ( MailPreparingEvent $event ) {
				$this->logger->debug( 'MailPreparingEvent dispatched', [
					'plugin'    => 'double-opt-in',
					'optin_id'  => $event->getOptInId(),
					'recipient' => $event->getRecipient(),
				] );
			},
			5
		);

		// When MailSentEvent is dispatched
		$this->dispatcher->addListener(
			MailSentEvent::class,
			function ( MailSentEvent $event ) {
				$this->logger->info( 'MailSentEvent dispatched', [
					'plugin'    => 'double-opt-in',
					'optin_id'  => $event->getOptInId(),
					'success'   => $event->wasSuccessful(),
					'mail_type' => $event->getMailType(),
				] );
			},
			5
		);

		$this->logger->info( 'Typed event bridges registered', [
			'plugin'    => 'double-opt-in',
			'component' => 'hook-bridge',
		] );
	}

	/**
	 * Get the event dispatcher.
	 *
	 * @return EventDispatcherInterface
	 */
	public function getDispatcher(): EventDispatcherInterface {
		return $this->dispatcher;
	}

	/**
	 * Dispatch an event and ensure legacy hooks are called.
	 *
	 * @param object $event The event to dispatch.
	 *
	 * @return object The dispatched event.
	 */
	public function dispatch( object $event ): object {
		return $this->dispatcher->dispatch( $event );
	}
}
