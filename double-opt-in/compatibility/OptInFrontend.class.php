<?php

namespace forge12\contactform7\CF7DoubleOptIn;


use Forge12\DoubleOptIn\Container\Container;
use Forge12\DoubleOptIn\EmailTemplates\PlaceholderMapper;
use Forge12\DoubleOptIn\EventSystem\EventDispatcherInterface;
use Forge12\DoubleOptIn\Events\Lifecycle\OptInConfirmedEvent;
use Forge12\DoubleOptIn\Events\Lifecycle\OptInCreatedEvent;
use Forge12\DoubleOptIn\Service\RateLimiter;
use Forge12\Shared\Logger;
use Forge12\Shared\LoggerInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @deprecated 4.0.0 Use \Forge12\DoubleOptIn\Integration\AbstractFormIntegration instead.
 *
 * This class is maintained for backward compatibility only.
 * New integrations should extend AbstractFormIntegration and implement FormIntegrationInterface.
 *
 * @see \Forge12\DoubleOptIn\Integration\AbstractFormIntegration
 * @see \Forge12\DoubleOptIn\Integration\FormIntegrationInterface
 */
abstract class OptInFrontend {
	/**
	 * The Type of the OptIn Form System
	 *
	 * @var string
	 */
	protected string $type = '';

	private LoggerInterface $logger;

	/**
	 * Validation status from the last validateOptIn() call.
	 *
	 * @var string
	 */
	private static string $validationStatus = '';

	/**
	 * Get the validation status from the last validateOptIn() call.
	 *
	 * @return string One of: '', 'confirmed', 'already_confirmed', 'expired', 'not_found'.
	 */
	public static function getValidationStatus(): string {
		return self::$validationStatus;
	}

	/**
	 * Set the validation status.
	 *
	 * @param string $status The validation status.
	 */
	private function setValidationStatus( string $status ): void {
		self::$validationStatus = $status;
	}

	/**
	 * Constructor for the class.
	 *
	 * This constructor registers the necessary actions to be performed,
	 * by hooking methods of the current class, for the following events:
	 * - 'f12_cf7_doubleoptin_before_send_default_mail'
	 * - 'f12_cf7_doubleoptin_after_send_default_mail'
	 * - 'f12_cf7_doubleoptin_trigger_default_mail'
	 * - 'shutdown'
	 *
	 * @return void
	 */
	public function __construct( LoggerInterface $logger, string $type ) {
		$this->logger = $logger;
		$this->type   = $type;

		$this->get_logger()->debug( 'Base integration constructor called', [
			'plugin' => 'double-opt-in',
			'class'  => __CLASS__,
			'method' => __METHOD__,
			'type'   => $type,
		] );

		add_action( 'f12_cf7_doubleoptin_before_send_default_mail', [ $this, 'beforeSendDefaultMail' ], 10, 1 );
		$this->get_logger()->debug( 'Hook f12_cf7_doubleoptin_before_send_default_mail registered', [
			'plugin' => 'double-opt-in',
		] );

		add_action( 'f12_cf7_doubleoptin_after_send_default_mail', [ $this, 'afterSendDefaultMail' ], 10, 1 );
		$this->get_logger()->debug( 'Hook f12_cf7_doubleoptin_after_send_default_mail registered', [
			'plugin' => 'double-opt-in',
		] );

		add_action( 'f12_cf7_doubleoptin_trigger_default_mail', [ $this, 'sendDefaultMail' ], 10, 1 );
		$this->get_logger()->debug( 'Hook f12_cf7_doubleoptin_trigger_default_mail registered', [
			'plugin' => 'double-opt-in',
		] );

		add_action( 'shutdown', [ $this, 'removeFiles' ] );
		$this->get_logger()->debug( 'Hook shutdown registered for removeFiles', [
			'plugin' => 'double-opt-in',
		] );

		add_action( 'init', [ $this, 'validateOptIn' ] );
		$this->get_logger()->debug( 'Hook init registered for validateOptIn', [
			'plugin' => 'double-opt-in',
		] );

		add_action( 'wp_footer', [ $this, 'renderValidationFeedback' ] );

		// Default feedback handler for validation statuses
		add_action( 'f12_cf7_doubleoptin_validation_feedback', function ( $status ) {
			if ( $status === 'confirmed' ) {
				return;
			}

			$messages = [
				'already_confirmed' => __( 'Your opt-in has already been confirmed.', 'double-opt-in' ),
				'expired'           => __( 'This confirmation link has expired. Please submit the form again.', 'double-opt-in' ),
				'not_found'         => __( 'This confirmation link is invalid.', 'double-opt-in' ),
			];

			$message = $messages[ $status ] ?? '';
			if ( ! empty( $message ) ) {
				echo '<div class="doi-validation-notice doi-notice-' . esc_attr( $status ) . '">';
				echo '<p>' . esc_html( $message ) . '</p>';
				echo '</div>';
			}
		}, 10, 1 );

		add_filter( 'f12_cf7_doubleoptin_get_recipient_' . $this->type, [ $this, 'getRecipient' ], 10, 3 );
		$this->get_logger()->debug( 'Filter f12_cf7_doubleoptin_get_recipient_' . $this->type . ' registered', [
			'plugin' => 'double-opt-in',
		] );
	}


	public function get_logger() {
		return $this->logger;
	}

	/**
	 * Retrieves the recipient for a given recipient name, form parameters, and post parameters.
	 *
	 * @param string $recipient     The name of the recipient to retrieve.
	 * @param array  $formParameter The array of form parameters.
	 * @param array  $postParameter The array of post parameters.
	 *
	 * @return string The recipient for the given parameters.
	 */
	abstract public function getRecipient( string $recipient, array $formParameter, array $postParameter ): string;

	/**
	 * Sends a default mail for the given OptIn object.
	 *
	 * This method is declared as abstract, meaning that it must be implemented
	 * by any child class that extends the current class. The method takes
	 * a single parameter, $OptIn, of type OptIn. This parameter represents
	 * the OptIn object for which the default mail needs to be sent.
	 *
	 * This method should be overridden by child classes to define the specific
	 * logic for sending the default mail for the given OptIn object.
	 *
	 * Note that the implementation details of this method vary depending on the
	 * specific subclass. Therefore, the implementation code is not provided here.
	 *
	 * @param OptIn $OptIn The OptIn object for which the default mail needs to be sent.
	 *
	 * @return void
	 */
	abstract public function sendDefaultMail( OptIn $OptIn ): void;

    public function disable_contact_form_7_captcha($is_active){
        if($is_active){
            $this->get_logger()->debug( 'Forge12 CF7Captcha filters and actions removed', ['plugin' => 'double-opt-in']);
            return false;
        }
        $this->get_logger()->debug( 'Forge12 CF7Captcha filters and actions removed', ['plugin' => 'double-opt-in']);
        return $is_active;
    }

	/**
	 * This method is used to perform necessary actions before sending the default mail.
	 *
	 * This method removes the filter for the forge12 spam captcha if the class
	 * '\forge12\contactform7\CF7Captcha\TimerValidatorCF7' exists. It removes the filters 'wpcf7_spam' for the methods
	 * '\forge12\contactform7\CF7Captcha::isSpam' and
	 * '\forge12\contactform7\CF7Captcha\CF7IPLog::isSpam', and also removes the action 'wpcf7_mail_sent' for the
	 * method
	 * '\forge12\contactform7\CF7Captcha\CF7IPLog::doLogIP', if these filters and action exist.
	 *
	 * Additionally, this method removes the filter 'wpcf7_spam' for the method 'wpcf7_recaptcha_verify_response' with
	 * a priority of 9.
	 *
	 * @return void
	 */
	public function beforeSendDefaultMail() {
		$this->get_logger()->debug( 'beforeSendDefaultMail called', [
			'plugin' => 'double-opt-in',
			'class'  => __CLASS__,
			'method' => __METHOD__,
		] );

		// Remove the filter for the Forge12 spam captcha
        add_filter('f12_cf7_captcha_is_installed_cf7', [$this, 'disable_contact_form_7_captcha'], 999, 1);
        $this->get_logger()->debug( 'Forge12 CF7Captcha filters and actions removed', [
            'plugin' => 'double-opt-in',
        ] );

		// Remove the filter for the Google reCAPTCHA validation
		remove_filter( 'wpcf7_spam', 'wpcf7_recaptcha_verify_response', 9 );

		$this->get_logger()->debug( 'Google reCAPTCHA filter removed', [
			'plugin' => 'double-opt-in',
		] );
	}


	/**
	 * Performs actions after sending the default mail.
	 *
	 * In this method, two filters and an action are added to the WordPress hooks system.
	 *
	 * The first filter is added with the hook name 'wpcf7_spam' and the callback
	 * function is set to 'wpcf7_recaptcha_verify_response'. The priority is set to 9
	 * and the number of accepted arguments for the callback function is 2.
	 * This filter is added only if the function 'wpcf7_recaptcha_verifiy_response'
	 * exists.
	 *
	 * The second filter is added with the hook name 'wpcf7_spam' and the callback
	 * function is set to '\forge12\contactform7\CF7Captcha\TimerValidatorCF7::isSpam'.
	 * The priority is set to 100 and the number of accepted arguments for the callback
	 * function is 2. This filter is added only if the class '\forge12\contactform7\CF7Captcha\TimerValidatorCF7'
	 * exists.
	 *
	 * The third filter is added with the hook name 'wpcf7_spam' and the callback
	 * function is set to '\forge12\contactform7\CF7Captcha\CF7IPLog::isSpam'.
	 * The priority is set to 100 and the number of accepted arguments for the callback
	 * function is 2. This filter is added only if the class '\forge12\contactform7\CF7Captcha\CF7IPLog'
	 * exists.
	 *
	 * An action is added with the hook name 'wpcf7_mail_sent' and the callback function
	 * is set to '\forge12\contactform7\CF7Captcha\CF7IPLog::doLogIP'. The priority is set
	 * to 100 and the number of accepted arguments for the callback function is 1.
	 * This action is added only if the class '\forge12\contactform7\CF7Captcha\CF7IPLog' exists.
	 *
	 * @return void
	 */
	public function afterSendDefaultMail() {
		$this->get_logger()->debug( 'afterSendDefaultMail called', [
			'plugin' => 'double-opt-in',
			'class'  => __CLASS__,
			'method' => __METHOD__,
		] );

		// re-add the filter to ensure for all other forms the reCAPTCHA is used
		if ( function_exists( 'wpcf7_recaptcha_verifiy_response' ) ) {
			add_filter( 'wpcf7_spam', 'wpcf7_recaptcha_verify_response', 9, 2 );
			$this->get_logger()->debug( 'Google reCAPTCHA filter re-added', [
				'plugin' => 'double-opt-in',
			] );
		}

		// re-add the filter for the Forge12 spam captcha
		if ( class_exists( '\forge12\contactform7\CF7Captcha\TimerValidatorCF7' ) ) {
			add_filter( 'wpcf7_spam', '\forge12\contactform7\CF7Captcha\TimerValidatorCF7::isSpam', 100, 2 );
			add_filter( 'wpcf7_spam', '\forge12\contactform7\CF7Captcha\CF7IPLog::isSpam', 100, 2 );
			add_action( 'wpcf7_mail_sent', '\forge12\contactform7\CF7Captcha\CF7IPLog::doLogIP', 100, 1 );

			$this->get_logger()->debug( 'Forge12 CF7Captcha filters and actions re-added', [
				'plugin' => 'double-opt-in',
			] );
		}
	}

	/**
	 * Updates the opt-in status by hash.
	 *
	 * @param string     $hash  The opt-in hash.
	 * @param int        $value The opt-in value to set.
	 * @param OptIn|null $OptIn The opt-in object. Optional.
	 *
	 * @return int Returns 0 if the opt-in is already confirmed, 1 if the opt-in is successfully updated.
	 */
	protected function updateOptInByHash( string $hash, int $value, ?OptIn $OptIn = null ): int {
		$this->get_logger()->debug( 'updateOptInByHash called', [
			'plugin' => 'double-opt-in',
			'class'  => __CLASS__,
			'method' => __METHOD__,
			'hash'   => $hash,
			'value'  => $value,
		] );

		$OptIn = $OptIn ?? OptIn::get_by_hash( $hash );

		if ( ! $OptIn ) {
			$this->get_logger()->warning( 'No OptIn found for hash', [
				'plugin' => 'double-opt-in',
				'hash'   => $hash,
			] );
			return 0;
		}

		if ( $OptIn->is_confirmed() ) {
			$this->get_logger()->info( 'OptIn already confirmed, skipping update', [
				'plugin'   => 'double-opt-in',
				'hash'     => $hash,
				'optin_id' => $OptIn->get_id(),
			] );

			do_action( 'f12_cf7_doubleoptin_already_confirmed', $hash, $OptIn );
			return 0;
		}

		do_action( 'f12_cf7_doubleoptin_before_confirm', $hash, $OptIn );

		$OptIn->set_doubleoptin( $value );
		$OptIn->set_updatetime( time() );
		$OptIn->set_ipaddr_confirmation( IPHelper::getIPAdress() );

		$telemetry = new Telemetry( $this->get_logger() );

		$result = $OptIn->save();

		if ( $result ) {
			if ( (int)$value === 1 ) {
				$telemetry->increment( 'confirmed_optins' );

				// Dispatch typed event for new event-driven architecture
				$this->dispatchOptInConfirmedEvent( $OptIn, $hash );
			}

			$this->get_logger()->info( 'OptIn updated successfully', [
				'plugin'   => 'double-opt-in',
				'hash'     => $hash,
				'optin_id' => $OptIn->get_id(),
			] );
			do_action( 'f12_cf7_doubleoptin_after_confirm', $hash, $OptIn );
		} else {
			$this->get_logger()->error( 'Failed to update OptIn', [
				'plugin'   => 'double-opt-in',
				'hash'     => $hash,
				'optin_id' => $OptIn->get_id(),
			] );
		}

		return (int) $result;
	}


	/**
	 * Add additional placeholder like time, date, subject
	 *
	 * @formatter:off
	 *
	 * @param string $body The content containing the placeholder that will be replaced.
	 * @param OptIn $OptIn The OptIn Object.
	 *
	 * @param array $parameter {
	 *      @type string $formUrl The URL where the form is displayed.
	 *      @type string $subject The Subject of the Form
	 * }
	 *                       #
	 * @formatter:on
	 */
	protected function addPlaceholders( string $body, OptIn $OptIn, array $parameter ): string {
		$this->get_logger()->debug( 'addPlaceholders called', [
			'plugin'    => 'double-opt-in',
			'class'     => __CLASS__,
			'method'    => __METHOD__,
			'optin_id'  => $OptIn->get_id(),
			'parameter' => $parameter,
		] );

		// set the default timezone
		$timezone = get_option( 'timezone_string' );

		// set fallback timezone
		if ( empty( $timezone ) ) {
			$timezone = 'Europe/Berlin';
			$this->get_logger()->debug( 'No timezone set in options, using fallback Europe/Berlin', [
				'plugin' => 'double-opt-in',
			] );
		}

		date_default_timezone_set( $timezone );

		$placeholder = [
			'doubleoptin_form_url'     => $parameter['formUrl']   ?? '',
			'doubleoptin_form_subject' => $parameter['subject']   ?? '',
			'doubleoptin_form_date'    => date( get_option( 'date_format' ) ),
			'doubleoptin_form_time'    => date( get_option( 'time_format' ) ),
			'doubleoptin_form_email'   => get_option( 'admin_email' ),
			'doubleoptinlink'          => $OptIn->get_link_optin( $parameter ),
			'doubleoptoutlink'         => $OptIn->get_link_optout(),
			'doubleoptin_privacy_url'  => $this->getPrivacyPolicyUrl(),
		];


		foreach ( $placeholder as $key => $value ) {
			if ( is_array( $value ) || is_object( $value ) ) {
				// Array/Objekte serialisieren oder in JSON umwandeln
				$value = wp_json_encode( $value );
			}

			$replacement = (string) ( $value ?? '' );

			$body = str_replace( '[' . $key . ']', $replacement, $body );

			// Logging je Platzhalter
			$this->get_logger()->debug( 'Placeholder replaced', [
				'plugin'      => 'double-opt-in',
				'placeholder' => '[' . $key . ']',
				'value'       => $replacement,
			] );
		}

		// Logging nach allen Ersetzungen
		$this->get_logger()->debug( 'System placeholders replaced in body', [
			'plugin'      => 'double-opt-in',
			'placeholders' => array_keys( $placeholder ),
			'body_length' => strlen( $body ),
		] );

		// Replace standard placeholders (doi_email, doi_name, etc.)
		$formData = maybe_unserialize( $OptIn->get_content() );
		if ( is_array( $formData ) ) {
			$body = PlaceholderMapper::replacePlaceholders(
				$body,
				$formData,
				$OptIn->get_cf_form_id(),
				[],
				$this->type
			);

			$this->get_logger()->debug( 'Standard placeholders replaced', [
				'plugin'  => 'double-opt-in',
				'form_id' => $OptIn->get_cf_form_id(),
			] );
		}

		return $body;
	}


	/**
	 * Add Stylesheets
	 */
	public function validateOptIn(): bool {
		$this->get_logger()->debug( 'validateOptIn started', [
			'plugin' => 'double-opt-in',
		] );

		/**
		 * Skip if the hash has not been submitted.
		 */
		if ( ! isset( $_GET['optin'] ) ) {
			$this->get_logger()->debug( 'No optin hash found in request, skipping', [
				'plugin' => 'double-opt-in',
			] );
			return false;
		}

		/**
		 * Get the Hash
		 */
		$hash = sanitize_text_field( $_GET['optin'] );

		/**
		 * Load the OptIn
		 */
		$OptIn = OptIn::get_by_hash( $hash );

		/**
		 * Skip if the OptIn does not exist
		 */
		if ( null == $OptIn ) {
			$this->get_logger()->warning( 'OptIn not found for hash', [
				'plugin' => 'double-opt-in',
				'hash'   => $hash,
			] );
			$this->setValidationStatus( 'not_found' );
			return false;
		}

		/**
		 * Skip if the OptIn is not from Type cf7.
		 */
		if ( ! $OptIn->isType( $this->type ) ) {
			$this->get_logger()->warning( 'OptIn type mismatch', [
				'plugin'   => 'double-opt-in',
				'hash'     => $hash,
				'type'     => $this->type,
				'optin_id' => $OptIn->get_id(),
			] );
			return false;
		}

		$this->get_logger()->debug( 'OptIn type found', [
			'plugin'   => 'double-opt-in',
			'hash'     => $hash,
			'type'     => $this->type,
			'optin_id' => $OptIn->get_id(),
		] );

		/**
		 * Check if the token has expired.
		 */
		$settings    = CF7DoubleOptIn::getInstance()->getSettings();
		$expiryHours = (int) ( $settings['token_expiry_hours'] ?? 48 );
		if ( $expiryHours > 0 && ( time() - (int) $OptIn->get_createtime() ) > ( $expiryHours * 3600 ) ) {
			$this->get_logger()->info( 'OptIn token expired', [
				'plugin'       => 'double-opt-in',
				'hash'         => $hash,
				'optin_id'     => $OptIn->get_id(),
				'expiry_hours' => $expiryHours,
			] );
			do_action( 'f12_cf7_doubleoptin_token_expired', $hash, $OptIn );
			$this->setValidationStatus( 'expired' );
			return false;
		}

		/**
		 * Check if already confirmed (before calling updateOptInByHash).
		 */
		if ( $OptIn->is_confirmed() ) {
			$this->get_logger()->info( 'OptIn already confirmed', [
				'plugin'   => 'double-opt-in',
				'hash'     => $hash,
				'optin_id' => $OptIn->get_id(),
			] );
			do_action( 'f12_cf7_doubleoptin_already_confirmed', $hash, $OptIn );
			$this->setValidationStatus( 'already_confirmed' );
			return false;
		}

		/**
		 * Confirm the OptIn.
		 */
		if ( $this->updateOptInByHash( $hash, 1, $OptIn ) <= 0 ) {
			$this->get_logger()->info( 'OptIn update failed', [
				'plugin'   => 'double-opt-in',
				'hash'     => $hash,
				'optin_id' => $OptIn->get_id(),
			] );
			return false;
		}

		$this->setValidationStatus( 'confirmed' );

		/**
		 * Enable / Disable default mail.
		 *
		 * @param bool $status Enable (true) or disable (false) the default mail.
		 * @param int  $postId The ID of the Post / Form.
		 *
		 * @since 2.3.3
		 */
		if ( ! apply_filters( 'f12_cf7_doubleoptin_send_default_mail', true, $OptIn->get_cf_form_id() ) ) {
			$this->get_logger()->info( 'Default mail disabled for OptIn', [
				'plugin'   => 'double-opt-in',
				'form_id'  => $OptIn->get_cf_form_id(),
				'optin_id' => $OptIn->get_id(),
			] );
			return false;
		}

		$this->get_logger()->debug( 'Triggering before_send_default_mail hook', [
			'plugin'   => 'double-opt-in',
			'optin_id' => $OptIn->get_id(),
		] );
		do_action( 'f12_cf7_doubleoptin_before_send_default_mail', $OptIn );

		$this->get_logger()->info( 'Triggering send_default_mail hook', [
			'plugin'   => 'double-opt-in',
			'optin_id' => $OptIn->get_id(),
		] );
		do_action( 'f12_cf7_doubleoptin_trigger_default_mail', $OptIn );

		$this->get_logger()->debug( 'Triggering after_send_default_mail hook', [
			'plugin'   => 'double-opt-in',
			'optin_id' => $OptIn->get_id(),
		] );
		do_action( 'f12_cf7_doubleoptin_after_send_default_mail', $OptIn );

		return true;
	}



	/**
	 * Store the files
	 *
	 * @param array $inFiles
	 *
	 * @return array
	 */
	private function maybeStoreFiles( array $inFiles ): array {
		$this->get_logger()->debug( 'maybeStoreFiles called', [
			'plugin'   => 'double-opt-in',
			'class'    => __CLASS__,
			'method'   => __METHOD__,
			'files_in' => $inFiles,
		] );

		$outFiles = [];

		if ( empty( $inFiles ) ) {
			$this->get_logger()->debug( 'No files provided to maybeStoreFiles', [
				'plugin' => 'double-opt-in',
			] );
			return $outFiles;
		}

		foreach ( $inFiles as $key => $subfiles ) {
			foreach ( $subfiles as $file ) {
				$newFile = $this->copyAndRenameFile( $file );
				if ( $newFile ) {
					$outFiles[] = $newFile;
					$this->get_logger()->debug( 'File stored successfully', [
						'plugin'   => 'double-opt-in',
						'original' => $file,
						'new'      => $newFile,
					] );
				} else {
					$this->get_logger()->warning( 'File could not be stored', [
						'plugin'   => 'double-opt-in',
						'original' => $file,
					] );
				}
			}
		}

		$this->get_logger()->debug( 'maybeStoreFiles completed', [
			'plugin'    => 'double-opt-in',
			'files_out' => $outFiles,
		] );

		return $outFiles;
	}


	/**
	 * Copy and rename a file
	 *
	 * @param string $file The path to the file to copy and rename
	 *
	 * @return string|null The path to the copied and renamed file, or null if the copy operation failed
	 */
	private function copyAndRenameFile( string $file ): ?string {
		$this->get_logger()->debug( 'copyAndRenameFile called', [
			'plugin' => 'double-opt-in',
			'class'  => __CLASS__,
			'method' => __METHOD__,
			'file'   => $file,
		] );

		$newFile = explode( '/', $file );
		$name    = $newFile[ count( $newFile ) - 1 ];
		$name    = time() . '_' . $name;
		$newFile[ count( $newFile ) - 1 ] = $name;
		$newFile = implode( "/", $newFile );

		if ( copy( $file, $newFile ) ) {
			$this->get_logger()->info( 'File copied and renamed successfully', [
				'plugin'    => 'double-opt-in',
				'original'  => $file,
				'new_file'  => $newFile,
			] );
			return $newFile;
		}

		$this->get_logger()->error( 'Failed to copy and rename file', [
			'plugin'   => 'double-opt-in',
			'original' => $file,
			'target'   => $newFile,
		] );

		return null;
	}


	/**
	 * Create the OptIn
	 *
	 * @param int    $formId    The identifier of the form
	 * @param string $formHtml  The HTML code of the form
	 * @param array  $parameter The Post Parameter of the form.
	 * @param array  $files     The Files attached to the form.
	 *
	 * @return OptIn|null
	 */
	protected function maybeCreateOptIn( int $formId, string $formHtml, array $parameter, array $files = array() ): ?OptIn {
		$this->get_logger()->debug( 'maybeCreateOptIn called', [
			'plugin'   => 'double-opt-in',
			'class'    => __CLASS__,
			'method'   => __METHOD__,
			'formId'   => $formId,
			'formHtml' => substr( $formHtml, 0, 200 ),
			'files'    => $files,
		] );

		/**
		 * Mögliche Dateien speichern, um sie während der Opt-In-Bestätigung vorzuhalten
		 */
		$files = $this->maybeStoreFiles( $files );
		$this->get_logger()->debug( 'Files checked and possibly stored', [
			'plugin' => 'double-opt-in',
			'files'  => $files,
		] );

		/**
		 * Filter, um die Parameter vor dem Speichern in der Datenbank zu manipulieren
		 */
		$parameter = \apply_filters( 'f12_cf7_doubleoptin_add_request_parameter', $parameter );
		$this->get_logger()->debug( 'Request parameters filtered', [
			'plugin'    => 'double-opt-in',
			'parameter' => $parameter,
		] );

		/**
		 * Globale Einstellungen für das Formular abrufen
		 */
		$formParameter = CF7DoubleOptIn::getInstance()->getParameter( $formId );

		/**
		 * Filter, um den Empfänger zu ermitteln, bevor das OptIn-Objekt erstellt wird
		 */
		$recipient = \apply_filters( 'f12_cf7_doubleoptin_get_recipient_' . $this->type, '', $formParameter, $parameter );

		/**
		 * Wenn keine E-Mail-Adresse gefunden wurde, Abbruch
		 */
		if ( empty( $recipient ) ) {
			$this->get_logger()->warning( 'No recipient found, skipping OptIn creation', [
				'plugin' => 'double-opt-in',
			] );
			return null;
		}

		/**
		 * Rate-Limiting: Check IP and email limits before creating OptIn.
		 */
		$rateLimiter  = new RateLimiter();
		$ratSettings  = CF7DoubleOptIn::getInstance()->getSettings();
		$rateLimitIp  = (int) ( $ratSettings['rate_limit_ip'] ?? 5 );
		$rateLimitEmail = (int) ( $ratSettings['rate_limit_email'] ?? 3 );
		$rateLimitWindow = (int) ( $ratSettings['rate_limit_window'] ?? 60 );

		$ip = IPHelper::getIPAdress();
		if ( ! $rateLimiter->isAllowed( 'ip', $ip, $rateLimitIp, $rateLimitWindow ) ) {
			$this->get_logger()->warning( 'Rate limit exceeded for IP', [
				'plugin' => 'double-opt-in',
				'ip'     => $ip,
				'formId' => $formId,
			] );
			do_action( 'f12_cf7_doubleoptin_rate_limited', 'ip', $ip, $formId );
			return null;
		}

		if ( ! $rateLimiter->isAllowed( 'email', $recipient, $rateLimitEmail, $rateLimitWindow ) ) {
			$this->get_logger()->warning( 'Rate limit exceeded for email', [
				'plugin' => 'double-opt-in',
				'email'  => $recipient,
				'formId' => $formId,
			] );
			do_action( 'f12_cf7_doubleoptin_rate_limited', 'email', $recipient, $formId );
			return null;
		}

		/**
		 * Consent-Text aus den Form-Settings als Snapshot laden
		 */
		$consentText = '';
		try {
			$container      = \Forge12\DoubleOptIn\Container\Container::getInstance();
			$settingsService = $container->get( \Forge12\DoubleOptIn\FormSettings\FormSettingsService::class );
			$formSettings   = $settingsService->getSettings( $formId );
			$consentText    = $formSettings->consentText ?? '';
		} catch ( \Exception $e ) {
			$this->get_logger()->debug( 'Could not load consent text from FormSettings', [
				'plugin' => 'double-opt-in',
				'error'  => $e->getMessage(),
			] );
		}

		/**
		 * Eigenschaften des OptIn-Objekts festlegen
		 */
		$properties = [
			'cf_form_id'      => $formId,
			'doubleoptin'     => 0,
			'createtime'      => time(),
			'content'         => maybe_serialize( $parameter ),
			'files'           => maybe_serialize( $files ),
			'ipaddr_register' => IPHelper::getIPAdress(),
			'category'        => (int) $formParameter['category'],
			'form'            => $formHtml,
			'email'           => $recipient,
			'consent_text'    => $consentText,
		];

		$this->get_logger()->debug( 'OptIn properties created', [
			'plugin'     => 'double-opt-in',
			'properties' => $properties,
		] );

		/**
		 * OptIn-Objekt erstellen
		 */
		$OptIn = new OptIn($this->get_logger(), $properties );
		$this->get_logger()->debug( 'OptIn object instantiated', [
			'plugin' => 'double-opt-in',
			'OptIn'  => $OptIn,
		] );

		/**
		 * OptIn speichern
		 */
		if ( $OptIn->save() ) {
			$this->get_logger()->info( 'OptIn object saved successfully', [
				'plugin' => 'double-opt-in',
				'OptIn'  => $OptIn,
			] );

			// Dispatch typed event for new event-driven architecture
			$this->dispatchOptInCreatedEvent( $OptIn, $formId );

			return $OptIn;
		}

		$this->get_logger()->error( 'Failed to save OptIn object', [
			'plugin' => 'double-opt-in',
		] );

		do_action( 'f12_cf7_doubleoptin_creation_failed', $formId, $recipient );

		return null;
	}


	/**
	 * Validate if the optin is enabled.
	 */
	protected function isOptinEnabled( int $formId ): bool {
		// Disable optin sending if the optin flag is set.
		if ( isset( $_GET['optin'] ) ) {
			$this->get_logger()->debug( 'Optin disabled due to optin flag in GET request', [
				'plugin' => 'double-opt-in',
				'class'  => __CLASS__,
				'method' => __METHOD__,
			] );
			return false;
		}

		$parameter = CF7DoubleOptIn::getInstance()->getParameter( $formId );

		if ( (int) $parameter['enable'] != 1 ) {
			$this->get_logger()->debug( 'Optin not enabled in form parameter', [
				'plugin'    => 'double-opt-in',
				'class'     => __CLASS__,
				'method'    => __METHOD__,
				'parameter' => $parameter,
			] );
			return false;
		}

		// Check the custom condition
		if ( isset( $parameter['conditions'] ) ) {
			$condition = sanitize_text_field( $parameter['conditions'] );

			if ( ( $condition != 'disable' && $condition !== 'disabled' ) && ( ! isset( $_POST[ $condition ] ) || empty( $_POST[ $condition ] ) ) ) {
				$this->get_logger()->debug( 'Optin disabled due to unmet custom condition', [
					'plugin'    => 'double-opt-in',
					'class'     => __CLASS__,
					'method'    => __METHOD__,
					'condition' => $condition,
					'post_keys' => array_keys( $_POST ),
				] );
				return false;
			}
		}

		$this->get_logger()->debug( 'Optin enabled', [
			'plugin' => 'double-opt-in',
			'class'  => __CLASS__,
			'method' => __METHOD__,
		] );

		return true;
	}


	/**
	 * Render validation feedback in the frontend via wp_footer.
	 *
	 * Fires the 'f12_cf7_doubleoptin_validation_feedback' action for customization,
	 * and provides a default inline notice for non-confirmed statuses.
	 *
	 * @return void
	 */
	public function renderValidationFeedback(): void {
		$status = self::getValidationStatus();
		if ( empty( $status ) ) {
			return;
		}

		/**
		 * Allow themes/plugins to handle the validation feedback display.
		 *
		 * @param string $status The validation status.
		 *
		 * @since 3.3.0
		 */
		do_action( 'f12_cf7_doubleoptin_validation_feedback', $status );
	}

	/**
	 * Removes files associated with the optin parameter.
	 *
	 * This method checks if the optin parameter is set and
	 * loads the OptIn object based on the hash value. If
	 * the OptIn does not exist or no files are found,
	 * the method will return. Otherwise, it will iterate
	 * through the files and delete each one.
	 *
	 * @return void
	 */
	public function removeFiles(): void {
		/**
		 * Skip if the optin parameter is not set.
		 */
		if ( ! isset( $_GET['optin'] ) ) {
			$this->get_logger()->debug( 'No optin parameter found, skipping file removal', [
				'plugin' => 'double-opt-in',
				'class'  => __CLASS__,
				'method' => __METHOD__,
			] );
			return;
		}

		$hash = sanitize_text_field( wp_unslash( $_GET['optin'] ) );

		/**
		 * Load the OptIn
		 */
		$OptIn = OptIn::get_by_hash( $hash );

		/**
		 * Skip if the OptIn does not exist
		 */
		if ( null == $OptIn ) {
			$this->get_logger()->warning( 'OptIn not found, skipping file removal', [
				'plugin' => 'double-opt-in',
				'class'  => __CLASS__,
				'method' => __METHOD__,
				'hash'   => $hash,
			] );
			return;
		}

		/**
		 * Load all files
		 */
		$files = maybe_unserialize( $OptIn->get_files() );

		/**
		 * Skip if no files found
		 */
		if ( empty( $files ) ) {
			$this->get_logger()->debug( 'No files found in OptIn, skipping removal', [
				'plugin' => 'double-opt-in',
				'class'  => __CLASS__,
				'method' => __METHOD__,
				'hash'   => $hash,
			] );
			return;
		}

		foreach ( $files as $file ) {
			/**
			 * Skip if empty
			 */
			if ( empty( $file ) ) {
				continue;
			}

			/**
			 * Skip if no file found
			 */
			if ( ! is_file( $file ) ) {
				$this->get_logger()->warning( 'File not found, skipping', [
					'plugin' => 'double-opt-in',
					'class'  => __CLASS__,
					'method' => __METHOD__,
					'file'   => $file,
				] );
				continue;
			}

			/**
			 * Delete the file
			 */
			if ( unlink( $file ) ) {
				$this->get_logger()->info( 'File deleted successfully', [
					'plugin' => 'double-opt-in',
					'class'  => __CLASS__,
					'method' => __METHOD__,
					'file'   => $file,
				] );
			} else {
				$this->get_logger()->error( 'Could not delete file', [
					'plugin' => 'double-opt-in',
					'class'  => __CLASS__,
					'method' => __METHOD__,
					'file'   => $file,
				] );
			}
		}
	}

	/**
	 * Get the privacy policy URL.
	 *
	 * Fallback chain: Plugin setting → WordPress Privacy Policy page → empty string.
	 *
	 * @return string
	 */
	private function getPrivacyPolicyUrl(): string {
		$settings = CF7DoubleOptIn::getInstance()->getSettings();
		$pageId   = (int) ( $settings['privacy_policy_page'] ?? 0 );

		if ( $pageId > 0 ) {
			$url = get_permalink( $pageId );
			if ( $url ) {
				return $url;
			}
		}

		// Fallback to WordPress privacy policy page
		$wpPrivacyPageId = (int) get_option( 'wp_page_for_privacy_policy', 0 );
		if ( $wpPrivacyPageId > 0 ) {
			$url = get_permalink( $wpPrivacyPageId );
			if ( $url ) {
				return $url;
			}
		}

		return '';
	}

	/**
	 * Dispatch OptInCreatedEvent via the new event system.
	 *
	 * @param OptIn $optIn  The created OptIn object.
	 * @param int   $formId The form ID.
	 *
	 * @since 4.0.0
	 */
	protected function dispatchOptInCreatedEvent( OptIn $optIn, int $formId ): void {
		try {
			$container = Container::getInstance();
			if ( $container->has( EventDispatcherInterface::class ) ) {
				$dispatcher = $container->get( EventDispatcherInterface::class );
				$event = new OptInCreatedEvent(
					$optIn->get_id(),
					$formId,
					$this->type,
					$optIn->get_email(),
					$optIn->get_hash()
				);
				$dispatcher->dispatch( $event );

				$this->get_logger()->debug( 'OptInCreatedEvent dispatched', [
					'plugin'   => 'double-opt-in',
					'optin_id' => $optIn->get_id(),
					'form_id'  => $formId,
				] );
			}
		} catch ( \Exception $e ) {
			$this->get_logger()->warning( 'Failed to dispatch OptInCreatedEvent', [
				'plugin' => 'double-opt-in',
				'error'  => $e->getMessage(),
			] );
		}
	}

	/**
	 * Dispatch OptInConfirmedEvent via the new event system.
	 *
	 * @param OptIn  $optIn The confirmed OptIn object.
	 * @param string $hash  The opt-in hash.
	 *
	 * @since 4.0.0
	 */
	protected function dispatchOptInConfirmedEvent( OptIn $optIn, string $hash ): void {
		try {
			$container = Container::getInstance();
			if ( $container->has( EventDispatcherInterface::class ) ) {
				$dispatcher = $container->get( EventDispatcherInterface::class );
				$event = new OptInConfirmedEvent(
					$optIn->get_id(),
					$hash,
					$optIn->get_email(),
					$optIn->get_ipaddr_confirmation(),
					(int) $optIn->get_cf_form_id()
				);
				$dispatcher->dispatch( $event );

				$this->get_logger()->debug( 'OptInConfirmedEvent dispatched', [
					'plugin'   => 'double-opt-in',
					'optin_id' => $optIn->get_id(),
					'hash'     => $hash,
				] );
			}
		} catch ( \Exception $e ) {
			$this->get_logger()->warning( 'Failed to dispatch OptInConfirmedEvent', [
				'plugin' => 'double-opt-in',
				'error'  => $e->getMessage(),
			] );
		}
	}
}