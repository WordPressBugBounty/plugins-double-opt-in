<?php
/**
 * Abstract Form Integration
 *
 * @package Forge12\DoubleOptIn\Integration
 * @since   4.0.0
 */

namespace Forge12\DoubleOptIn\Integration;

use Forge12\DoubleOptIn\Container\Container;
use Forge12\DoubleOptIn\EmailTemplates\PlaceholderMapper;
use Forge12\DoubleOptIn\EventSystem\EventDispatcherInterface;
use Forge12\DoubleOptIn\Events\Integration\FormSubmissionEvent;
use Forge12\DoubleOptIn\Events\Lifecycle\OptInConfirmedEvent;
use Forge12\DoubleOptIn\Events\Lifecycle\OptInCreatedEvent;
use Forge12\DoubleOptIn\Frontend\ErrorNotification;
use Forge12\DoubleOptIn\Service\RateLimiter;
use forge12\contactform7\CF7DoubleOptIn\CF7DoubleOptIn;
use forge12\contactform7\CF7DoubleOptIn\IPHelper;
use forge12\contactform7\CF7DoubleOptIn\OptIn;
use forge12\contactform7\CF7DoubleOptIn\OptInFrontend;
use forge12\contactform7\CF7DoubleOptIn\Telemetry;
use Forge12\Shared\LoggerInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AbstractFormIntegration
 *
 * Base class providing common functionality for all form integrations.
 * Extracted from the legacy OptInFrontend class to provide reusable logic.
 */
abstract class AbstractFormIntegration implements FormIntegrationInterface {

	/**
	 * Logger instance.
	 *
	 * @var LoggerInterface
	 */
	protected LoggerInterface $logger;

	/**
	 * Validation status from the last validateOptIn() call.
	 *
	 * @var string
	 */
	private static string $validationStatus = '';

	/**
	 * Stored hCaptcha CF7 instance for restore after mail send.
	 *
	 * @var object|null
	 */
	private $hcaptchaCf7Instance = null;

	/**
	 * Stored hCaptcha CF7 filter priority for restore after mail send.
	 *
	 * @var int
	 */
	private int $hcaptchaCf7Priority = 20;

	/**
	 * Last error from the most recent createOptIn() call.
	 *
	 * @var OptInError|null
	 */
	private static ?OptInError $lastError = null;

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
	private static function setValidationStatus( string $status ): void {
		self::$validationStatus = $status;
	}

	/**
	 * Get the last error from the most recent createOptIn() call.
	 *
	 * @return OptInError|null The error, or null if no error occurred.
	 */
	public static function getLastError(): ?OptInError {
		return self::$lastError;
	}

	/**
	 * Clear the last error.
	 */
	private static function clearLastError(): void {
		self::$lastError = null;
	}

	/**
	 * Set the last error and store it for the frontend notification system.
	 *
	 * @param OptInError $error  The error that occurred.
	 * @param int        $formId The form ID.
	 */
	private static function setLastError( OptInError $error, int $formId ): void {
		self::$lastError = $error;
		ErrorNotification::store( $error, $formId );
	}

	/**
	 * Get the recipient validation error from the last createOptIn() call.
	 *
	 * @deprecated Use getLastError() instead.
	 *
	 * @return string The error message, or empty string if no error.
	 */
	public static function getLastRecipientValidationError(): string {
		if ( self::$lastError && self::$lastError->getCode() === OptInError::RECIPIENT_INVALID ) {
			return self::$lastError->getMessage();
		}
		return '';
	}

	/**
	 * Constructor.
	 *
	 * @param LoggerInterface $logger The logger instance.
	 */
	public function __construct( LoggerInterface $logger ) {
		$this->logger = $logger;
	}

	/**
	 * Get the logger instance.
	 *
	 * @return LoggerInterface
	 */
	protected function getLogger(): LoggerInterface {
		return $this->logger;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getHookPriority(): int {
		return 10;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getFormParameter( int $formId ): array {
		return CF7DoubleOptIn::getInstance()->getParameter( $formId );
	}

	/**
	 * {@inheritdoc}
	 */
	public function isOptInEnabled( int $formId ): bool {
		// Disable if opt-in confirmation is in progress
		if ( isset( $_GET['optin'] ) ) {
			$this->getLogger()->debug( 'Opt-in disabled due to optin flag in GET request', [
				'plugin' => 'double-opt-in',
				'class'  => static::class,
			] );
			return false;
		}

		$parameter = $this->getFormParameter( $formId );

		if ( (int) ( $parameter['enable'] ?? 0 ) !== 1 ) {
			$this->getLogger()->debug( 'Opt-in not enabled in form parameter', [
				'plugin'  => 'double-opt-in',
				'form_id' => $formId,
			] );
			return false;
		}

		// Check the custom condition
		if ( isset( $parameter['conditions'] ) ) {
			$condition = sanitize_text_field( $parameter['conditions'] );

			if ( ( $condition !== 'disable' && $condition !== 'disabled' )
				&& ( ! isset( $_POST[ $condition ] ) || empty( $_POST[ $condition ] ) ) ) {
				$this->getLogger()->debug( 'Opt-in disabled due to unmet custom condition', [
					'plugin'    => 'double-opt-in',
					'condition' => $condition,
				] );
				return false;
			}
		}

		return true;
	}

	/**
	 * Create an OptIn record from form data.
	 *
	 * @param FormDataInterface $formData      The normalized form data.
	 * @param array             $formParameter The form configuration.
	 *
	 * @return OptIn|null The created OptIn or null on failure.
	 */
	protected function createOptIn( FormDataInterface $formData, array $formParameter ): ?OptIn {
		$this->getLogger()->debug( 'Creating OptIn record', [
			'plugin'    => 'double-opt-in',
			'class'     => static::class,
			'form_id'   => $formData->getFormId(),
			'form_type' => $formData->getFormType(),
		] );

		// Clear previous error
		self::clearLastError();

		// Dispatch FormSubmissionEvent to allow modifications/cancellation

		$event = $this->dispatchFormSubmissionEvent( $formData );
		if ( $event && $event->shouldSkipOptIn() ) {
			$this->getLogger()->info( 'OptIn skipped by FormSubmissionEvent', [
				'plugin'  => 'double-opt-in',
				'form_id' => $formData->getFormId(),
			] );
			self::setLastError(
				OptInError::fromCode( OptInError::SUBMISSION_CANCELLED, [ 'form_id' => $formData->getFormId() ] ),
				$formData->getFormId()
			);
			return null;
		}

		// Store uploaded files
		$files = $this->storeFiles( $formData->getFiles() );

		// Filter parameters before saving
		$fields = apply_filters( 'f12_cf7_doubleoptin_add_request_parameter', $formData->getFields() );

		// Resolve recipient email
		$recipient = $this->resolveRecipient( $formData, $formParameter );

		if ( empty( $recipient ) ) {
			$this->getLogger()->warning( 'No recipient found, skipping OptIn creation', [
				'plugin'  => 'double-opt-in',
				'form_id' => $formData->getFormId(),
			] );
			self::setLastError(
				OptInError::fromCode( OptInError::NO_RECIPIENT, [ 'form_id' => $formData->getFormId() ] ),
				$formData->getFormId()
			);
			return null;
		}

		// Rate-Limiting: Check IP and email limits before creating OptIn
		$rateLimiter     = new RateLimiter();
		$settings        = CF7DoubleOptIn::getInstance()->getSettings();
		$rateLimitIp     = (int) ( $settings['rate_limit_ip'] ?? 5 );
		$rateLimitEmail  = (int) ( $settings['rate_limit_email'] ?? 3 );
		$rateLimitWindow = (int) ( $settings['rate_limit_window'] ?? 60 );

		$ip = IPHelper::getIPAdress();
		if ( ! $rateLimiter->isAllowed( 'ip', $ip, $rateLimitIp, $rateLimitWindow ) ) {
			$this->getLogger()->warning( 'Rate limit exceeded for IP', [
				'plugin'  => 'double-opt-in',
				'ip'      => $ip,
				'form_id' => $formData->getFormId(),
			] );
			do_action( 'f12_cf7_doubleoptin_rate_limited', 'ip', $ip, $formData->getFormId() );
			self::setLastError(
				OptInError::fromCode( OptInError::RATE_LIMIT_IP, [ 'ip' => $ip, 'form_id' => $formData->getFormId() ] ),
				$formData->getFormId()
			);
			return null;
		}

		if ( ! $rateLimiter->isAllowed( 'email', $recipient, $rateLimitEmail, $rateLimitWindow ) ) {
			$this->getLogger()->warning( 'Rate limit exceeded for email', [
				'plugin'  => 'double-opt-in',
				'email'   => $recipient,
				'form_id' => $formData->getFormId(),
			] );
			do_action( 'f12_cf7_doubleoptin_rate_limited', 'email', $recipient, $formData->getFormId() );
			self::setLastError(
				OptInError::fromCode( OptInError::RATE_LIMIT_EMAIL, [ 'email' => $recipient, 'form_id' => $formData->getFormId() ] ),
				$formData->getFormId()
			);
			return null;
		}

		// Validate recipient (extensible via Pro MX check)
		$recipientValid = apply_filters(
			'f12_cf7_doubleoptin_validate_recipient',
			true,
			$recipient,
			$formData
		);

		if ( $recipientValid !== true ) {
			$errorMsg  = is_string( $recipientValid ) ? $recipientValid : '';
			$errorCode = OptInError::RECIPIENT_INVALID;

			// Unique Email rejection gets its own error code
			if ( $errorMsg === 'unique_email_rejected' ) {
				$errorCode = OptInError::UNIQUE_EMAIL_DUPLICATE;
				$errorMsg  = OptInError::fromCode( OptInError::UNIQUE_EMAIL_DUPLICATE )->getMessage();
			}

			$this->getLogger()->warning( 'Recipient validation failed', [
				'plugin'  => 'double-opt-in',
				'email'   => $recipient,
				'form_id' => $formData->getFormId(),
				'reason'  => $errorMsg,
			] );
			do_action( 'f12_cf7_doubleoptin_recipient_invalid', $recipient, $formData->getFormId(), $errorMsg );
			self::setLastError(
				new OptInError(
					$errorCode,
					! empty( $errorMsg ) ? $errorMsg : OptInError::fromCode( OptInError::RECIPIENT_INVALID )->getMessage(),
					[ 'email' => $recipient, 'form_id' => $formData->getFormId() ]
				),
				$formData->getFormId()
			);
			return null;
		}

		// Create OptIn properties
		$properties = [
			'cf_form_id'      => $formData->getFormId(),
			'doubleoptin'     => 0,
			'createtime'      => time(),
			'content'         => maybe_serialize( $fields ),
			'files'           => maybe_serialize( $files ),
			'ipaddr_register' => IPHelper::getIPAdress(),
			'category'        => (int) ( $formParameter['category'] ?? 0 ),
			'form'            => $formData->getFormHtml(),
			'email'           => $recipient,
		];

		$optIn = new OptIn( $this->getLogger(), $properties );

		if ( $optIn->save() ) {
			$this->getLogger()->info( 'OptIn record created successfully', [
				'plugin'   => 'double-opt-in',
				'optin_id' => $optIn->get_id(),
				'form_id'  => $formData->getFormId(),
			] );

			// Dispatch typed event
			$this->dispatchOptInCreatedEvent( $optIn, $formData );

			// Track telemetry
			$telemetry = new Telemetry( $this->getLogger() );
			$telemetry->increment( 'total_optins' );
			$telemetry->increment( $this->getIdentifier() . '_optins' );

			return $optIn;
		}

		$this->getLogger()->error( 'Failed to save OptIn record', [
			'plugin'  => 'double-opt-in',
			'form_id' => $formData->getFormId(),
		] );

		do_action( 'f12_cf7_doubleoptin_creation_failed', $formData->getFormId(), $recipient );

		self::setLastError(
			OptInError::fromCode( OptInError::SAVE_FAILED, [ 'form_id' => $formData->getFormId() ] ),
			$formData->getFormId()
		);

		return null;
	}

	/**
	 * Prepare the opt-in mail body with placeholders replaced.
	 *
	 * @param string $body          The mail body template.
	 * @param OptIn  $optIn         The OptIn record.
	 * @param array  $formParameter The form configuration.
	 *
	 * @return string The processed mail body.
	 */
	protected function prepareMailBody( string $body, OptIn $optIn, array $formParameter ): string {
		// Replace system placeholders
		$body = $this->addSystemPlaceholders( $body, $optIn, $formParameter );

		// Replace form field placeholders
		$formData = maybe_unserialize( $optIn->get_content() );
		if ( is_array( $formData ) ) {
			$body = PlaceholderMapper::replacePlaceholders(
				$body,
				$formData,
				$optIn->get_cf_form_id(),
				[],
				$this->getIdentifier()
			);
		}

		return $body;
	}

	/**
	 * Add system placeholders to the mail body.
	 *
	 * @param string $body          The mail body.
	 * @param OptIn  $optIn         The OptIn record.
	 * @param array  $formParameter The form configuration.
	 *
	 * @return string The body with placeholders replaced.
	 */
	protected function addSystemPlaceholders( string $body, OptIn $optIn, array $formParameter ): string {
		$timezone = get_option( 'timezone_string' );
		if ( empty( $timezone ) ) {
			$timezone = 'Europe/Berlin';
		}
		date_default_timezone_set( $timezone );

		$placeholders = [
			'doubleoptin_form_url'     => $formParameter['formUrl'] ?? '',
			'doubleoptin_form_subject' => $formParameter['subject'] ?? '',
			'doubleoptin_form_date'    => date( get_option( 'date_format' ) ),
			'doubleoptin_form_time'    => date( get_option( 'time_format' ) ),
			'doubleoptin_form_email'   => get_option( 'admin_email' ),
			'doubleoptinlink'          => $optIn->get_link_optin( $formParameter ),
			'doubleoptoutlink'         => $optIn->get_link_optout(),
		];

		foreach ( $placeholders as $key => $value ) {
			if ( is_array( $value ) || is_object( $value ) ) {
				$value = wp_json_encode( $value );
			}
			$body = str_replace( '[' . $key . ']', (string) $value, $body );
		}

		return $body;
	}

	/**
	 * Store uploaded files for later use after opt-in confirmation.
	 *
	 * @param array $files The uploaded files.
	 *
	 * @return array The stored file paths.
	 */
	protected function storeFiles( array $files ): array {
		$storedFiles = [];

		if ( empty( $files ) ) {
			return $storedFiles;
		}

		foreach ( $files as $key => $fileList ) {
			if ( ! is_array( $fileList ) ) {
				$fileList = [ $fileList ];
			}

			foreach ( $fileList as $file ) {
				if ( empty( $file ) || ! is_file( $file ) ) {
					continue;
				}

				$newFile = $this->copyAndRenameFile( $file );
				if ( $newFile ) {
					$storedFiles[] = $newFile;
				}
			}
		}

		return $storedFiles;
	}

	/**
	 * Allowed MIME types for file uploads stored with opt-in records.
	 */
	private const ALLOWED_MIME_TYPES = [
		'jpg'  => 'image/jpeg',
		'jpeg' => 'image/jpeg',
		'png'  => 'image/png',
		'gif'  => 'image/gif',
		'webp' => 'image/webp',
		'pdf'  => 'application/pdf',
		'doc'  => 'application/msword',
		'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
		'txt'  => 'text/plain',
		'csv'  => 'text/csv',
	];

	/**
	 * Copy and rename a file with a unique, non-guessable name.
	 *
	 * Validates the file MIME type against an allowlist before copying.
	 *
	 * @param string $file The source file path.
	 *
	 * @return string|null The new file path or null on failure/rejection.
	 */
	private function copyAndRenameFile( string $file ): ?string {
		$allowedMimes = apply_filters( 'f12_cf7_doubleoptin_allowed_mime_types', self::ALLOWED_MIME_TYPES );

		$fileType = wp_check_filetype_and_ext( $file, wp_basename( $file ), $allowedMimes );

		if ( empty( $fileType['type'] ) || empty( $fileType['ext'] ) ) {
			$this->getLogger()->warning( 'File rejected: MIME type not allowed', [
				'plugin'   => 'double-opt-in',
				'original' => $file,
			] );
			return null;
		}

		$pathParts = explode( '/', $file );
		array_pop( $pathParts );
		$newName     = bin2hex( random_bytes( 16 ) ) . '.' . $fileType['ext'];
		$pathParts[] = $newName;
		$newFile     = implode( '/', $pathParts );

		if ( copy( $file, $newFile ) ) {
			$this->getLogger()->debug( 'File copied successfully', [
				'plugin'   => 'double-opt-in',
				'original' => $file,
				'new_file' => $newFile,
			] );
			return $newFile;
		}

		$this->getLogger()->error( 'Failed to copy file', [
			'plugin'   => 'double-opt-in',
			'original' => $file,
		] );

		return null;
	}

	/**
	 * Validate and confirm an opt-in by hash.
	 *
	 * @param string $hash The opt-in hash.
	 *
	 * @return bool True if the opt-in was confirmed successfully.
	 */
	public function validateOptIn( string $hash ): bool {
		$optIn = OptIn::get_by_hash( $hash );

		if ( ! $optIn ) {
			$this->getLogger()->warning( 'OptIn not found for hash', [
				'plugin' => 'double-opt-in',
				'hash'   => $hash,
			] );
			self::setValidationStatus( 'not_found' );
			return false;
		}

		// Check if this opt-in belongs to this integration
		if ( ! $optIn->isType( $this->getIdentifier() ) ) {
			return false;
		}

		// Check if the token has expired
		$settings    = CF7DoubleOptIn::getInstance()->getSettings();
		$expiryHours = (int) ( $settings['token_expiry_hours'] ?? 48 );
		if ( $expiryHours > 0 && ( time() - (int) $optIn->get_createtime() ) > ( $expiryHours * 3600 ) ) {
			$this->getLogger()->info( 'OptIn token expired', [
				'plugin'       => 'double-opt-in',
				'optin_id'     => $optIn->get_id(),
				'expiry_hours' => $expiryHours,
			] );
			do_action( 'f12_cf7_doubleoptin_token_expired', $hash, $optIn );
			self::setValidationStatus( 'expired' );
			return false;
		}

		// Skip if already confirmed
		if ( $optIn->is_confirmed() ) {
			$this->getLogger()->info( 'OptIn already confirmed', [
				'plugin'   => 'double-opt-in',
				'optin_id' => $optIn->get_id(),
			] );
			do_action( 'f12_cf7_doubleoptin_already_confirmed', $hash, $optIn );
			self::setValidationStatus( 'already_confirmed' );
			return false;
		}

		// Confirm the opt-in
		do_action( 'f12_cf7_doubleoptin_before_confirm', $hash, $optIn );

		$optIn->set_doubleoptin( 1 );
		$optIn->set_updatetime( time() );
		$optIn->set_ipaddr_confirmation( IPHelper::getIPAdress() );

		if ( ! $optIn->save() ) {
			$this->getLogger()->error( 'Failed to confirm OptIn', [
				'plugin'   => 'double-opt-in',
				'optin_id' => $optIn->get_id(),
			] );
			return false;
		}

		self::setValidationStatus( 'confirmed' );

		// Track telemetry
		$telemetry = new Telemetry( $this->getLogger() );
		$telemetry->increment( 'confirmed_optins' );

		// Dispatch event
		$this->dispatchOptInConfirmedEvent( $optIn, $hash );

		do_action( 'f12_cf7_doubleoptin_after_confirm', $hash, $optIn );

		// Send the original mail if enabled
		if ( apply_filters( 'f12_cf7_doubleoptin_send_default_mail', true, $optIn->get_cf_form_id() ) ) {
			do_action( 'f12_cf7_doubleoptin_before_send_default_mail', $optIn );
			$this->sendConfirmationMail( $optIn );
			do_action( 'f12_cf7_doubleoptin_after_send_default_mail', $optIn );
		}

		$this->getLogger()->info( 'OptIn confirmed successfully', [
			'plugin'   => 'double-opt-in',
			'optin_id' => $optIn->get_id(),
		] );

		return true;
	}

	/**
	 * Remove stored files after processing.
	 *
	 * @param OptIn $optIn The opt-in record.
	 *
	 * @return void
	 */
	public function removeStoredFiles( OptIn $optIn ): void {
		$files = maybe_unserialize( $optIn->get_files() );

		if ( empty( $files ) || ! is_array( $files ) ) {
			return;
		}

		foreach ( $files as $file ) {
			if ( empty( $file ) || ! is_file( $file ) ) {
				continue;
			}

			if ( unlink( $file ) ) {
				$this->getLogger()->debug( 'File removed successfully', [
					'plugin' => 'double-opt-in',
					'file'   => $file,
				] );
			} else {
				$this->getLogger()->warning( 'Failed to remove file', [
					'plugin' => 'double-opt-in',
					'file'   => $file,
				] );
			}
		}
	}

	/**
	 * Disable spam protection hooks before sending confirmation mail.
	 *
	 * @return void
	 */
	protected function beforeSendConfirmationMail(): void {
		// Disable CF7 validation for confirmation mail resend.
		// CF7 re-runs all form validations (required fields, quiz, acceptance checkboxes)
		// when creating a WPCF7_Submission instance. Since this is a confirmed opt-in
		// (not a real form submit), these validations must be bypassed.
		add_filter( 'wpcf7_validate', [ $this, 'clearValidationResult' ], 999 );
		add_filter( 'wpcf7_spam', '__return_false', 0 );
		add_filter( 'wpcf7_skip_spam_check', '__return_true', 0 );

		// Disable CF7 Captcha if present
		add_filter( 'f12_cf7_captcha_is_installed_cf7', '__return_false', 999 );

		// Remove reCAPTCHA filter
		remove_filter( 'wpcf7_spam', 'wpcf7_recaptcha_verify_response', 9 );

		// Remove hCaptcha validation filter
		$this->removeHCaptchaFilter();

		$this->getLogger()->debug( 'Validation and spam protection disabled for confirmation mail', [
			'plugin' => 'double-opt-in',
		] );
	}

	/**
	 * Clear CF7 validation result to bypass field validation during confirmation mail.
	 *
	 * @param \WPCF7_Validation $result The validation result.
	 *
	 * @return \WPCF7_Validation A clean validation result with no errors.
	 */
	public function clearValidationResult( $result ) {
		return new \WPCF7_Validation();
	}

	/**
	 * Re-enable spam protection hooks after sending confirmation mail.
	 *
	 * @return void
	 */
	protected function afterSendConfirmationMail(): void {
		// Re-enable CF7 validation
		remove_filter( 'wpcf7_validate', [ $this, 'clearValidationResult' ], 999 );
		remove_filter( 'wpcf7_spam', '__return_false', 0 );
		remove_filter( 'wpcf7_skip_spam_check', '__return_true', 0 );

		// Re-add reCAPTCHA filter
		if ( function_exists( 'wpcf7_recaptcha_verify_response' ) ) {
			add_filter( 'wpcf7_spam', 'wpcf7_recaptcha_verify_response', 9, 2 );
		}

		// Re-add CF7 Captcha hooks
		if ( class_exists( '\forge12\contactform7\CF7Captcha\TimerValidatorCF7' ) ) {
			add_filter( 'wpcf7_spam', '\forge12\contactform7\CF7Captcha\TimerValidatorCF7::isSpam', 100, 2 );
			add_filter( 'wpcf7_spam', '\forge12\contactform7\CF7Captcha\CF7IPLog::isSpam', 100, 2 );
			add_action( 'wpcf7_mail_sent', '\forge12\contactform7\CF7Captcha\CF7IPLog::doLogIP', 100, 1 );
		}

		// Re-add hCaptcha validation filter
		$this->restoreHCaptchaFilter();

		$this->getLogger()->debug( 'Validation and spam protection re-enabled', [
			'plugin' => 'double-opt-in',
		] );
	}

	/**
	 * Remove hCaptcha CF7 validation filter and store the instance for later restore.
	 *
	 * @return void
	 */
	private function removeHCaptchaFilter(): void {
		if ( ! class_exists( '\HCaptcha\CF7\CF7' ) ) {
			return;
		}

		global $wp_filter;

		if ( ! isset( $wp_filter['wpcf7_validate'] ) ) {
			return;
		}

		foreach ( $wp_filter['wpcf7_validate']->callbacks as $priority => $hooks ) {
			foreach ( $hooks as $key => $hook ) {
				if ( is_array( $hook['function'] ) && $hook['function'][0] instanceof \HCaptcha\CF7\CF7 ) {
					$this->hcaptchaCf7Instance = $hook['function'][0];
					$this->hcaptchaCf7Priority = $priority;
					remove_filter( 'wpcf7_validate', $hook['function'], $priority );
					$this->getLogger()->debug( 'hCaptcha CF7 validation filter removed', [
						'plugin' => 'double-opt-in',
					] );

					return;
				}
			}
		}
	}

	/**
	 * Re-add hCaptcha CF7 validation filter if it was previously removed.
	 *
	 * @return void
	 */
	private function restoreHCaptchaFilter(): void {
		if ( isset( $this->hcaptchaCf7Instance ) ) {
			add_filter( 'wpcf7_validate', [ $this->hcaptchaCf7Instance, 'verify_hcaptcha' ], $this->hcaptchaCf7Priority, 2 );
			$this->getLogger()->debug( 'hCaptcha CF7 validation filter re-added', [
				'plugin' => 'double-opt-in',
			] );
			unset( $this->hcaptchaCf7Instance, $this->hcaptchaCf7Priority );
		}
	}

	/**
	 * Dispatch FormSubmissionEvent.
	 *
	 * @param FormDataInterface $formData The form data.
	 *
	 * @return FormSubmissionEvent|null The event or null if dispatcher unavailable.
	 */
	protected function dispatchFormSubmissionEvent( FormDataInterface $formData ): ?FormSubmissionEvent {
		try {
			$container = Container::getInstance();
			if ( $container->has( EventDispatcherInterface::class ) ) {
				$dispatcher = $container->get( EventDispatcherInterface::class );
				$event = new FormSubmissionEvent(
					$formData,
					$this->getIdentifier()
				);
				$dispatcher->dispatch( $event );
				return $event;
			}
		} catch ( \Exception $e ) {
			$this->getLogger()->warning( 'Failed to dispatch FormSubmissionEvent', [
				'plugin' => 'double-opt-in',
				'error'  => $e->getMessage(),
			] );
		}
		return null;
	}

	/**
	 * Dispatch OptInCreatedEvent.
	 *
	 * @param OptIn             $optIn    The created opt-in.
	 * @param FormDataInterface $formData The form data.
	 *
	 * @return void
	 */
	protected function dispatchOptInCreatedEvent( OptIn $optIn, FormDataInterface $formData ): void {
		try {
			$container = Container::getInstance();
			if ( $container->has( EventDispatcherInterface::class ) ) {
				$dispatcher = $container->get( EventDispatcherInterface::class );
				$event = new OptInCreatedEvent(
					$optIn->get_id(),
					$formData->getFormId(),
					$this->getIdentifier(),
					$optIn->get_email(),
					$optIn->get_hash(),
					$formData->getFields()
				);
				$dispatcher->dispatch( $event );
			}
		} catch ( \Exception $e ) {
			$this->getLogger()->warning( 'Failed to dispatch OptInCreatedEvent', [
				'plugin' => 'double-opt-in',
				'error'  => $e->getMessage(),
			] );
		}
	}

	/**
	 * Dispatch OptInConfirmedEvent.
	 *
	 * @param OptIn  $optIn The confirmed opt-in.
	 * @param string $hash  The opt-in hash.
	 *
	 * @return void
	 */
	protected function dispatchOptInConfirmedEvent( OptIn $optIn, string $hash ): void {
		try {
			$container = Container::getInstance();
			if ( $container->has( EventDispatcherInterface::class ) ) {
				$dispatcher = $container->get( EventDispatcherInterface::class );

				$formData = maybe_unserialize( $optIn->get_content() );

				$event = new OptInConfirmedEvent(
					$optIn->get_id(),
					$hash,
					$optIn->get_email(),
					$optIn->get_ipaddr_confirmation(),
					(int) $optIn->get_cf_form_id(),
					is_array( $formData ) ? $formData : []
				);
				$dispatcher->dispatch( $event );
			}
		} catch ( \Exception $e ) {
			$this->getLogger()->warning( 'Failed to dispatch OptInConfirmedEvent', [
				'plugin' => 'double-opt-in',
				'error'  => $e->getMessage(),
			] );
		}
	}

	/**
	 * Get the post type for this integration's forms.
	 *
	 * @since 4.1.0
	 *
	 * @return string The post type.
	 */
	abstract protected function getPostType(): string;

	/**
	 * {@inheritdoc}
	 */
	public function getForms(): array {
		$posts = get_posts( [
			'post_type'      => $this->getPostType(),
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'orderby'        => 'title',
			'order'          => 'ASC',
		] );

		$forms = [];
		foreach ( $posts as $post ) {
			$forms[] = [
				'id'          => $post->ID,
				'title'       => $post->post_title,
				'integration' => $this->getIdentifier(),
				'enabled'     => $this->isOptInEnabled( $post->ID ),
				'edit_url'    => $this->getFormEditUrl( $post->ID ),
			];
		}

		$this->getLogger()->debug( 'Retrieved forms for integration', [
			'plugin'      => 'double-opt-in',
			'integration' => $this->getIdentifier(),
			'count'       => count( $forms ),
		] );

		return $forms;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getFormTitle( $formId ): string {
		$post = get_post( (int) $formId );
		return $post ? $post->post_title : '';
	}

	/**
	 * {@inheritdoc}
	 */
	public function getFormEditUrl( $formId ): string {
		return get_edit_post_link( (int) $formId, 'raw' ) ?: '';
	}
}
