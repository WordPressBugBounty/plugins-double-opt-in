<?php
/**
 * Records whether the confirmation mail of an opt-in reached the mail server.
 *
 * Up to 5.7 no integration looked at the result of sending the confirmation
 * mail: the opt-in was stored first, the mail went out (or not), and a
 * failure stayed invisible — the most frequent support topic. This tracker
 * needs no change in the form add-ons:
 *
 *   1. OptInCreatedEvent (fired by every integration before sending) names
 *      the opt-in and its recipient.
 *   2. `wp_mail_failed` for that recipient marks it failed, with the error.
 *   3. `f12_cf7_doubleoptin_sent` (fired by every integration after the
 *      send attempt) marks it sent, unless it failed in between.
 *
 * Paths that know the result themselves (CF7's return value, resends) report
 * it through the action `f12_doi_optin_mail_result`.
 *
 * @package Forge12\DoubleOptIn\Service
 * @since   5.8.0
 */

declare( strict_types=1 );

namespace Forge12\DoubleOptIn\Service;

use Forge12\DoubleOptIn\Events\Lifecycle\OptInCreatedEvent;
use Forge12\DoubleOptIn\EventSystem\EventDispatcherInterface;
use Forge12\DoubleOptIn\Repository\OptInMailStatusRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OptInMailTracker {

	public const RESULT_ACTION = 'f12_doi_optin_mail_result';

	/** @var OptInMailStatusRepository */
	private $repository;

	/** @var int */
	private $currentId = 0;

	/** @var string */
	private $currentRecipient = '';

	/** @var bool Whether the current opt-in already has a recorded outcome. */
	private $recorded = false;

	public function __construct( OptInMailStatusRepository $repository ) {
		$this->repository = $repository;
	}

	public function register( EventDispatcherInterface $dispatcher ): void {
		$dispatcher->addListener( OptInCreatedEvent::class, array( $this, 'onCreated' ) );
		add_action( 'wp_mail_failed', array( $this, 'onMailFailed' ) );
		add_action( 'f12_cf7_doubleoptin_sent', array( $this, 'onSent' ), 99, 0 );
		add_action( self::RESULT_ACTION, array( $this, 'record' ), 10, 3 );
	}

	public function onCreated( OptInCreatedEvent $event ): void {
		$this->currentId        = $event->getOptInId();
		$this->currentRecipient = self::normalise( $event->getEmail() );
		$this->recorded         = false;
	}

	/**
	 * @param mixed $error A WP_Error from wp_mail().
	 */
	public function onMailFailed( $error ): void {
		if ( $this->currentId <= 0 || $this->recorded || ! is_object( $error ) || ! method_exists( $error, 'get_error_message' ) ) {
			return;
		}
		$data = method_exists( $error, 'get_error_data' ) ? $error->get_error_data() : array();
		$to   = is_array( $data ) && isset( $data['to'] ) ? (array) $data['to'] : array();

		$recipients = array_map( array( self::class, 'normalise' ), $to );
		if ( ! in_array( $this->currentRecipient, $recipients, true ) ) {
			// Another mail of the same request (a notification, a reminder).
			return;
		}

		$this->record( $this->currentId, false, (string) $error->get_error_message() );
	}

	public function onSent(): void {
		if ( $this->currentId <= 0 || $this->recorded ) {
			return;
		}
		$this->record( $this->currentId, true );
	}

	/**
	 * Store an outcome. Also the handler of `f12_doi_optin_mail_result`.
	 *
	 * @param mixed $optInId Opt-in ID.
	 * @param mixed $sent    Whether wp_mail() accepted the mail.
	 * @param mixed $error   Error text for a failure.
	 */
	public function record( $optInId, $sent, $error = '' ): void {
		$optInId = (int) $optInId;
		if ( $optInId <= 0 ) {
			return;
		}
		// First outcome of the current opt-in wins: wp_mail_failed carries
		// the mail server's own message and fires before the integration
		// reports its (generic) result.
		if ( $optInId === $this->currentId && $this->recorded ) {
			return;
		}
		$this->repository->mark(
			$optInId,
			$sent ? OptInMailStatusRepository::SENT : OptInMailStatusRepository::FAILED,
			$sent ? '' : ( (string) $error !== '' ? (string) $error : __( 'The mail could not be handed to the mail server.', 'double-opt-in' ) )
		);
		if ( $optInId === $this->currentId ) {
			$this->recorded = true;
		}
	}

	/**
	 * Lower-case address out of "Name <a@b.c>" or "a@b.c".
	 *
	 * @param mixed $address
	 */
	public static function normalise( $address ): string {
		$address = (string) $address;
		if ( preg_match( '/<([^>]+)>/', $address, $m ) ) {
			$address = $m[1];
		}
		return strtolower( trim( $address ) );
	}
}
