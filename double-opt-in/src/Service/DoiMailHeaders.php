<?php
/**
 * Lets extensions add headers to the mails of the double opt-in itself.
 *
 * @package Forge12\DoubleOptIn\Service
 * @since   5.8.0
 */

declare( strict_types=1 );

namespace Forge12\DoubleOptIn\Service;

use Forge12\DoubleOptIn\Events\Lifecycle\OptInCreatedEvent;
use Forge12\DoubleOptIn\EventSystem\EventDispatcherInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The confirmation mail leaves through five integrations and two resend
 * paths, each building its own headers. Instead of a hook in each of
 * them, this recognises the mail on its way through wp_mail() — the way
 * OptInMailTracker recognises its failure — and runs its headers through
 * `f12_doi_mail_headers`. addon-opt-out adds List-Unsubscribe there.
 *
 * A mail is expected after an opt-in is created (`confirmation`) or when
 * a sender announces one (`resend`, `reminder`). The first wp_mail() to
 * that recipient is it; the expectation then ends, as it does when the
 * integration reports the confirmation mail as sent. Other mails of the
 * request — notifications, a second form — pass untouched.
 *
 * @api Core API 4.6.0
 */
class DoiMailHeaders {

	public const FILTER = 'f12_doi_mail_headers';

	public const KIND_CONFIRMATION = 'confirmation';
	public const KIND_RESEND       = 'resend';
	public const KIND_REMINDER     = 'reminder';

	/** @var int */
	private $optInId = 0;

	/** @var string */
	private $recipient = '';

	/** @var string */
	private $kind = '';

	public function register( EventDispatcherInterface $dispatcher ): void {
		$dispatcher->addListener(
			OptInCreatedEvent::class,
			function ( OptInCreatedEvent $event ): void {
				$this->expect( $event->getOptInId(), $event->getEmail(), self::KIND_CONFIRMATION );
			}
		);
		add_filter( 'wp_mail', array( $this, 'filterMail' ), 20 );
		add_action( 'f12_cf7_doubleoptin_sent', array( $this, 'forget' ), 100, 0 );
	}

	/**
	 * Announce that the next mail to $recipient is a double opt-in mail.
	 */
	public function expect( int $optInId, string $recipient, string $kind ): void {
		$this->optInId   = $optInId;
		$this->recipient = OptInMailTracker::normalise( $recipient );
		$this->kind      = $kind;
	}

	public function forget(): void {
		$this->optInId   = 0;
		$this->recipient = '';
		$this->kind      = '';
	}

	/**
	 * @param mixed $atts wp_mail() arguments.
	 *
	 * @return mixed
	 */
	public function filterMail( $atts ) {
		if ( $this->optInId <= 0 || ! is_array( $atts ) ) {
			return $atts;
		}

		$to = $atts['to'] ?? array();
		$to = is_array( $to ) ? $to : explode( ',', (string) $to );
		if ( ! in_array( $this->recipient, array_map( array( OptInMailTracker::class, 'normalise' ), $to ), true ) ) {
			return $atts;
		}

		$optInId = $this->optInId;
		$kind    = $this->kind;
		$this->forget();

		// Nobody listening: the mail leaves exactly as built.
		if ( ! has_filter( self::FILTER ) ) {
			return $atts;
		}

		$headers = self::lines( $atts['headers'] ?? array() );

		/**
		 * Headers of a double opt-in mail (confirmation, resend, reminder).
		 *
		 * Return header lines (`Name: value`). Line breaks inside a line
		 * are removed.
		 *
		 * @since 5.8.0
		 *
		 * @param string[] $headers Header lines as they stand.
		 * @param int      $optInId The opt-in the mail belongs to.
		 * @param string   $kind    confirmation, resend or reminder.
		 */
		$filtered = apply_filters( 'f12_doi_mail_headers', $headers, $optInId, $kind );

		$atts['headers'] = is_array( $filtered ) ? self::lines( $filtered ) : $headers;

		return $atts;
	}

	/**
	 * wp_mail() takes headers as a newline-separated string or an array.
	 *
	 * @param mixed $headers
	 *
	 * @return string[]
	 */
	private static function lines( $headers ): array {
		if ( ! is_array( $headers ) ) {
			$headers = explode( "\n", str_replace( "\r\n", "\n", (string) $headers ) );
		}

		$lines = array();
		foreach ( $headers as $line ) {
			if ( ! is_scalar( $line ) ) {
				continue;
			}
			$line = trim( str_replace( array( "\r", "\n" ), ' ', (string) $line ) );
			if ( $line !== '' && strpos( $line, ':' ) !== false ) {
				$lines[] = $line;
			}
		}

		return $lines;
	}
}
