<?php
/**
 * Shortcodes for the confirmation and error pages.
 *
 * - [doi_confirmation_status] says what happened to the link the visitor
 *   just clicked: confirmed, already confirmed, expired or invalid.
 * - [doi_error_message] explains the `?doi_error=<code>` that a refused
 *   submission is redirected with. Up to 5.7 nothing read that parameter.
 * - [doi_field name="…"] greets with a value from the form, only in the
 *   request that confirmed it.
 * - [doi_if status="…"]…[/doi_if] shows its content only for the listed
 *   validation statuses, so one page can carry a text per outcome.
 *
 * Without a shortcode on the page, a failed link still gets its message in
 * front of the content — the new integration system set the status but
 * showed it nowhere.
 *
 * @package Forge12\DoubleOptIn\Frontend
 * @since   5.8.0
 */

declare( strict_types=1 );

namespace Forge12\DoubleOptIn\Frontend;

use Forge12\DoubleOptIn\Integration\AbstractFormIntegration;
use Forge12\DoubleOptIn\Integration\OptInError;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ConfirmationShortcodes {

	/** @var callable(): string */
	private $status;

	/**
	 * Form values of the opt-in confirmed in this request.
	 *
	 * @var array<string, mixed>|null
	 */
	private $confirmedFields = null;

	/** @var bool */
	private $statusShown = false;

	/**
	 * @param callable|null $status Current validation status; defaults to the integration system's.
	 */
	public function __construct( ?callable $status = null ) {
		$this->status = $status ?? array( AbstractFormIntegration::class, 'getValidationStatus' );
	}

	public function register(): void {
		add_shortcode( 'doi_confirmation_status', array( $this, 'renderStatus' ) );
		add_shortcode( 'doi_error_message', array( $this, 'renderError' ) );
		add_shortcode( 'doi_field', array( $this, 'renderField' ) );
		add_shortcode( 'doi_if', array( $this, 'renderIf' ) );

		add_action( 'f12_cf7_doubleoptin_after_confirm', array( $this, 'rememberConfirmed' ), 1, 2 );
		// After do_shortcode (11): by then a [doi_confirmation_status] on the page has run.
		add_filter( 'the_content', array( $this, 'prependFallbackNotice' ), 12 );
	}

	/**
	 * @param mixed $hash  Confirmed hash (unused).
	 * @param mixed $optIn The confirmed opt-in (legacy wrapper or entity).
	 */
	public function rememberConfirmed( $hash, $optIn ): void {
		if ( is_object( $optIn ) && method_exists( $optIn, 'getEntity' ) ) {
			$optIn = $optIn->getEntity();
		}
		if ( is_object( $optIn ) && method_exists( $optIn, 'getContentArray' ) ) {
			$this->confirmedFields = (array) $optIn->getContentArray();
		}
	}

	/**
	 * Default texts per validation status.
	 *
	 * @return array<string, string>
	 */
	public static function statusMessages(): array {
		return array(
			'confirmed'         => __( 'Thank you! Your email address has been confirmed.', 'double-opt-in' ),
			'already_confirmed' => __( 'Your opt-in has already been confirmed.', 'double-opt-in' ),
			'expired'           => __( 'This confirmation link has expired. Please submit the form again.', 'double-opt-in' ),
			'not_found'         => __( 'This confirmation link is invalid.', 'double-opt-in' ),
		);
	}

	/**
	 * [doi_confirmation_status confirmed="…" already_confirmed="…" expired="…" not_found="…" none="…"]
	 *
	 * @param mixed $atts Shortcode attributes.
	 */
	public function renderStatus( $atts = array() ): string {
		$status = (string) call_user_func( $this->status );
		$atts   = shortcode_atts( array_merge( self::statusMessages(), array( 'none' => '' ) ), is_array( $atts ) ? $atts : array(), 'doi_confirmation_status' );

		$this->statusShown = true;

		$key     = $status !== '' && isset( $atts[ $status ] ) ? $status : 'none';
		$message = (string) $atts[ $key ];
		if ( $message === '' ) {
			return '';
		}

		return self::notice( $key === 'none' ? 'none' : $status, $message );
	}

	/**
	 * Visitor-facing texts per OptInError code.
	 *
	 * @return array<string, string>
	 */
	public static function errorMessages(): array {
		$messages = array(
			OptInError::RATE_LIMIT_IP          => __( 'Too many sign-ups from your connection in a short time. Please try again later.', 'double-opt-in' ),
			OptInError::RATE_LIMIT_EMAIL       => __( 'This email address was signed up several times in a short time. Please check your inbox for the confirmation mail or try again later.', 'double-opt-in' ),
			OptInError::RECIPIENT_INVALID      => __( 'This email address cannot receive mail. Please check it and try again.', 'double-opt-in' ),
			OptInError::NO_RECIPIENT           => __( 'No valid email address was entered. Please try again.', 'double-opt-in' ),
			OptInError::UNIQUE_EMAIL_DUPLICATE => __( 'This email address is already signed up.', 'double-opt-in' ),
			OptInError::CONSENT_NOT_GIVEN      => __( 'Please agree to the consent text to sign up.', 'double-opt-in' ),
			OptInError::SAVE_FAILED            => __( 'Your sign-up could not be saved. Please try again later.', 'double-opt-in' ),
			OptInError::SUBMISSION_CANCELLED   => __( 'Your sign-up could not be completed. Please try again later.', 'double-opt-in' ),
		);

		/**
		 * Texts shown by [doi_error_message] per error code.
		 *
		 * @since 5.8.0
		 *
		 * @param array<string, string> $messages code => text.
		 */
		$filtered = apply_filters( 'f12_doi_error_messages', $messages );

		return is_array( $filtered ) ? $filtered : $messages;
	}

	/**
	 * [doi_error_message default="…"]
	 *
	 * @param mixed $atts Shortcode attributes.
	 */
	public function renderError( $atts = array() ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display of a redirect parameter.
		$code = isset( $_GET['doi_error'] ) ? sanitize_key( wp_unslash( (string) $_GET['doi_error'] ) ) : '';
		if ( $code === '' ) {
			return '';
		}

		$atts     = shortcode_atts(
			array( 'default' => __( 'Your sign-up could not be completed. Please try again later.', 'double-opt-in' ) ),
			is_array( $atts ) ? $atts : array(),
			'doi_error_message'
		);
		$messages = self::errorMessages();
		$message  = isset( $messages[ $code ] ) ? (string) $messages[ $code ] : (string) $atts['default'];

		return $message === '' ? '' : self::notice( 'error-' . $code, $message );
	}

	/**
	 * [doi_field name="first-name" default="…"]
	 *
	 * @param mixed $atts Shortcode attributes.
	 */
	public function renderField( $atts = array() ): string {
		$atts = shortcode_atts(
			array(
				'name'    => '',
				'default' => '',
			),
			is_array( $atts ) ? $atts : array(),
			'doi_field'
		);

		$default = esc_html( (string) $atts['default'] );
		if ( $this->confirmedFields === null || (string) call_user_func( $this->status ) !== 'confirmed' ) {
			return $default;
		}

		$value = $this->confirmedFields[ (string) $atts['name'] ] ?? null;
		if ( is_array( $value ) ) {
			$value = implode( ', ', array_map( 'strval', array_filter( $value, 'is_scalar' ) ) );
		}
		if ( ! is_scalar( $value ) || trim( (string) $value ) === '' ) {
			return $default;
		}

		return esc_html( (string) $value );
	}

	/**
	 * [doi_if status="confirmed,already_confirmed"]…[/doi_if]
	 *
	 * Statuses are those of [doi_confirmation_status]; "none" matches a visit
	 * without a confirmation link. Content shown for a real status counts as
	 * the page's status message, so the fallback notice stays away.
	 *
	 * @param mixed       $atts    Shortcode attributes.
	 * @param string|null $content Enclosed content.
	 */
	public function renderIf( $atts = array(), $content = null ): string {
		if ( ! is_string( $content ) || $content === '' ) {
			return '';
		}

		$atts   = shortcode_atts( array( 'status' => '' ), is_array( $atts ) ? $atts : array(), 'doi_if' );
		$wanted = array_filter( array_map( 'trim', explode( ',', strtolower( (string) $atts['status'] ) ) ) );
		$status = (string) call_user_func( $this->status );
		$key    = $status === '' ? 'none' : $status;

		if ( ! in_array( $key, $wanted, true ) ) {
			return '';
		}

		if ( $key !== 'none' ) {
			$this->statusShown = true;
		}

		return do_shortcode( $content );
	}

	/**
	 * A failed link on a page without [doi_confirmation_status]: say so first.
	 *
	 * @param mixed $content Post content.
	 *
	 * @return mixed
	 */
	public function prependFallbackNotice( $content ) {
		if ( $this->statusShown || ! is_string( $content ) || ! is_main_query() || ! in_the_loop() ) {
			return $content;
		}

		$status = (string) call_user_func( $this->status );
		if ( ! in_array( $status, array( 'already_confirmed', 'expired', 'not_found' ), true ) ) {
			return $content;
		}

		/**
		 * Whether a failed confirmation link gets its message in front of the
		 * page content when the page has no [doi_confirmation_status].
		 *
		 * @since 5.8.0
		 *
		 * @param bool   $show   Default true.
		 * @param string $status Validation status.
		 */
		if ( ! apply_filters( 'f12_doi_validation_notice_auto', true, $status ) ) {
			return $content;
		}

		$this->statusShown = true;

		return self::notice( $status, self::statusMessages()[ $status ] ) . $content;
	}

	private static function notice( string $status, string $message ): string {
		return '<div class="doi-validation-notice doi-notice-' . esc_attr( $status ) . '" role="status"><p>'
			. esc_html( $message )
			. '</p></div>';
	}
}
