<?php
/**
 * Re-sends the stored confirmation mail of an opt-in.
 *
 * @package Forge12\DoubleOptIn\Service
 * @since   5.8.0
 */

declare( strict_types=1 );

namespace Forge12\DoubleOptIn\Service;

use Forge12\DoubleOptIn\Repository\OptInRepositoryInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One implementation for every path that re-sends the confirmation mail:
 * the admin REST route, the legacy admin AJAX button and, from Welle 2 on,
 * the visitor asking for it again (addon-reminder).
 *
 * The mail is the one stored on the record (`mail_optin`), so the link in
 * it is the original one. Subject and sender come from the current form
 * settings, as before.
 *
 * Checks no capability: who may trigger a resend is the caller's business.
 *
 * @api
 */
class ConfirmationMailResender {

	/** @var OptInRepositoryInterface */
	private $repository;

	/** @var callable(int):array<string, mixed> */
	private $formSettings;

	/** @var DoiMailHeaders|null */
	private $headers;

	/**
	 * @param OptInRepositoryInterface           $repository   Opt-in storage.
	 * @param callable(int):array<string, mixed> $formSettings Form id → settings with
	 *                                                         `subject`, `sender`, `sender_name`.
	 * @param DoiMailHeaders|null                $headers      Marks the mail for `f12_doi_mail_headers`.
	 */
	public function __construct( OptInRepositoryInterface $repository, callable $formSettings, ?DoiMailHeaders $headers = null ) {
		$this->repository   = $repository;
		$this->formSettings = $formSettings;
		$this->headers      = $headers;
	}

	public function resend( int $optInId ): ResendResult {
		$optIn = $optInId > 0 ? $this->repository->findById( $optInId ) : null;
		if ( $optIn === null ) {
			return ResendResult::refused( ResendResult::NOT_FOUND );
		}
		if ( $optIn->isConfirmed() ) {
			return ResendResult::refused( ResendResult::CONFIRMED );
		}
		// A withdrawn consent is not asked for again behind the person's back.
		if ( $optIn->isOptedOut() ) {
			return ResendResult::refused( ResendResult::OPTED_OUT );
		}

		$stored = $optIn->getMailOptIn();
		if ( $stored === '' ) {
			return ResendResult::refused( ResendResult::NO_BODY );
		}

		$settings = (array) call_user_func( $this->formSettings, $optIn->getFormId() );
		$to       = $optIn->getEmail();
		$body     = $stored;
		$subject  = (string) ( $settings['subject'] ?? '' );
		$from     = self::from( (string) ( $settings['sender'] ?? '' ), (string) ( $settings['sender_name'] ?? '' ) );

		// Older writers stored a structured payload instead of the body.
		$payload = maybe_unserialize( $stored );
		if ( is_array( $payload ) ) {
			$to      = (string) ( $payload['to'] ?? $to );
			$body    = (string) ( $payload['body'] ?? '' );
			$subject = (string) ( $payload['subject'] ?? $subject );
			$from    = (string) ( $payload['from'] ?? $from );
		}

		if ( $body === '' ) {
			return ResendResult::refused( ResendResult::NO_BODY );
		}
		if ( $to === '' ) {
			return ResendResult::refused( ResendResult::NO_RECIPIENT );
		}

		$headers = array( 'Content-Type: text/html; charset=UTF-8' );
		if ( $from !== '' ) {
			$headers[] = 'From: ' . $from;
		}

		if ( $this->headers !== null ) {
			$this->headers->expect( $optIn->getId(), $to, DoiMailHeaders::KIND_RESEND );
		}

		$sent = (bool) wp_mail(
			$to,
			$subject !== '' ? $subject : __( 'Please confirm your opt-in', 'double-opt-in' ),
			$body,
			$headers
		);

		if ( $this->headers !== null ) {
			// A mail that never reached wp_mail's filter must not tag the next one.
			$this->headers->forget();
		}

		// Record the outcome on the opt-in (OptInMailTracker).
		do_action( 'f12_doi_optin_mail_result', $optIn->getId(), $sent, '' );

		return $sent ? ResendResult::sent() : ResendResult::refused( ResendResult::SEND_FAILED );
	}

	private static function from( string $email, string $name ): string {
		if ( $email === '' ) {
			return '';
		}
		return $name !== '' ? $name . ' <' . $email . '>' : $email;
	}
}
