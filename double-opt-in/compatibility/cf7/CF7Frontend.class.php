<?php

namespace forge12\contactform7\CF7DoubleOptIn {
	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	use forge12\plugins\ContactForm7;
	use Forge12\Shared\LoggerInterface;

	/**
	 * Class Frontend
	 * Responsible to handle the frontend of the Double OptIn
	 *
	 * @package forge12\contactform7\CF7DoubleOptIn
	 *
	 * @deprecated 4.0.0 Use \Forge12\DoubleOptIn\Integration\CF7Integration instead.
	 *             This class is maintained for backward compatibility only.
	 * @see \Forge12\DoubleOptIn\Integration\CF7Integration
	 */
	class CF7Frontend extends OptInFrontend {
		protected $OptIn = null;

		/**
		 * Admin constructor.
		 */
		public function __construct( LoggerInterface $logger ) {
			parent::__construct( $logger, 'cf7' );

			$this->get_logger()->debug( 'CF7 integration constructor called', [
				'plugin' => 'double-opt-in',
				'class'  => __CLASS__,
				'method' => __METHOD__,
			] );

			add_action( 'wpcf7_before_send_mail', [ $this, 'onSubmit' ], 5, 3 );
			$this->get_logger()->debug( 'Action wpcf7_before_send_mail registered', [
				'plugin' => 'double-opt-in',
			] );
		}


		/**
		 * Get the recipient email address
		 *
		 * @param string $recipient     The recipient email address
		 * @param array  $formParameter The form parameter array
		 * @param array  $postParameter The post parameter array
		 *
		 * @return string The sanitized recipient email address
		 */
		public function getRecipient( string $recipient, array $formParameter, array $postParameter ): string {
			$this->get_logger()->debug( 'getRecipient called', [
				'plugin'        => 'double-opt-in',
				'class'         => __CLASS__,
				'method'        => __METHOD__,
				'formParameter' => $formParameter,
			] );

			// Ensure the recipient has been defined in the settings
			if ( ! isset( $formParameter['recipient'] ) ) {
				$this->get_logger()->warning( 'Recipient not defined in form parameters, returning fallback', [
					'plugin'    => 'double-opt-in',
					'recipient' => $recipient,
				] );

				return $recipient;
			}

			$recipient = $formParameter['recipient'];

			$recipient = str_replace( [ '[', ']' ], '', $recipient );

			// Get the Recipient from the post parameter
			if ( isset( $postParameter[ $recipient ] ) ) {
				$recipient = sanitize_email( $_POST[ $recipient ] ); // phpcs:ignore WordPress.Security.NonceVerification
				$this->get_logger()->debug( 'Recipient resolved from post parameter', [
					'plugin'    => 'double-opt-in',
					'recipient' => $recipient,
				] );
			} else {
				$this->get_logger()->warning( 'Recipient not found in postParameter, using fallback', [
					'plugin'    => 'double-opt-in',
					'recipient' => $recipient,
				] );
			}

			return $recipient;
		}


		/**
		 * Add File after submission and confirming opt in.
		 *
		 * @param $form
		 * @param $abort
		 * @param $submission
		 *
		 * @return void
		 */
		public function attachExtraAttachments( $form, $abort, $submission ) {
			$this->get_logger()->debug( 'attachExtraAttachments called', [
				'plugin' => 'double-opt-in',
				'class'  => __CLASS__,
				'method' => __METHOD__,
			] );

			if ( $this->OptIn !== null && $this->OptIn->get_files() !== null ) {
				$fileWrapper = maybe_unserialize( $this->OptIn->get_files() );

				if ( is_array( $fileWrapper ) ) {
					foreach ( $fileWrapper as $file ) {
						$submission->add_extra_attachments( $file );

						$this->get_logger()->debug( 'Extra attachment added', [
							'plugin' => 'double-opt-in',
							'file'   => $file,
						] );
					}
				} else {
					$this->get_logger()->warning( 'File wrapper is not an array, skipping attachments', [
						'plugin' => 'double-opt-in',
					] );
				}
			} else {
				$this->get_logger()->debug( 'No OptIn files found, skipping attachments', [
					'plugin' => 'double-opt-in',
				] );
			}
		}


		/**
		 * Send the default email for the given OptIn. (Original Mail after Opt-In Confirmation)
		 *
		 * @param OptIn $OptIn The OptIn object containing the email content and form ID.
		 *
		 * @return void
		 */
		public function sendDefaultMail( OptIn $OptIn ): void {
			$this->get_logger()->debug( 'sendDefaultMail called', [
				'plugin'   => 'double-opt-in',
				'class'    => __CLASS__,
				'method'   => __METHOD__,
				'optin_id' => $OptIn->get_id(),
				'form_id'  => $OptIn->get_cf_form_id(),
			] );

			if ( ! function_exists( 'wpcf7' ) ) {
				$this->get_logger()->warning( 'wpcf7 function not available, aborting sendDefaultMail', [
					'plugin' => 'double-opt-in',
				] );

				return;
			}

			$this->OptIn = $OptIn;

			$data  = maybe_unserialize( $OptIn->get_content() );
			$_POST = SanitizeHelper::sanitize_array( $data );

			$ContactForm = \WPCF7_ContactForm::get_instance( $OptIn->get_cf_form_id() );
			if ( ! $ContactForm ) {
				$this->get_logger()->warning( 'Contact form not found, aborting sendDefaultMail', [
					'plugin'   => 'double-opt-in',
					'form_id'  => $OptIn->get_cf_form_id(),
					'optin_id' => $OptIn->get_id(),
				] );

				return;
			}

			add_action( 'wpcf7_before_send_mail', [ $this, 'attachExtraAttachments' ], 10, 3 );
			$this->get_logger()->debug( 'Hook wpcf7_before_send_mail registered for attachments', [
				'plugin' => 'double-opt-in',
			] );

			$submission = \WPCF7_Submission::get_instance( $ContactForm );

			if ( $submission ) {
				$this->get_logger()->info( 'WPCF7 submission prepared for sendDefaultMail', [
					'plugin'   => 'double-opt-in',
					'form_id'  => $OptIn->get_cf_form_id(),
					'optin_id' => $OptIn->get_id(),
				] );
			} else {
				$this->get_logger()->warning( 'Failed to prepare WPCF7 submission', [
					'plugin'   => 'double-opt-in',
					'form_id'  => $OptIn->get_cf_form_id(),
					'optin_id' => $OptIn->get_id(),
				] );
			}
		}


		/**
		 * On Form Submit add the double optin if enabled
		 *
		 * @param $form       \WPCF7_ContactForm
		 * @param bool
		 * @param $submission \WPCF7_Submission
		 */
		public function onSubmit( $form, &$abort, $submission ) {
			$this->get_logger()->debug( 'onSubmit called', [
				'plugin'  => 'double-opt-in',
				'class'   => __CLASS__,
				'method'  => __METHOD__,
				'form_id' => $form->id(),
			] );

			$parameter = CF7DoubleOptIn::getInstance()->getParameter( $form->id() );

			if ( $this->isOptinEnabled( $form->id() ) ) {
				$this->get_logger()->debug( 'Opt-in enabled, processing', [
					'plugin'  => 'double-opt-in',
					'form_id' => $form->id(),
				] );

				// Remove Contact Form 7 DB hook to ensure the optin mail is not saved
				remove_action( 'wpcf7_before_send_mail', 'cfdb7_before_send_mail' );

				if ( apply_filters( 'f12_cf7_doubleoptin_skip_option', false, $form->id(), $submission->get_posted_data(), $this->type ) ) {
					$this->get_logger()->info( 'Opt-in skipped by filter', [
						'plugin'  => 'double-opt-in',
						'form_id' => $form->id(),
					] );

					return;
				}

				$OptIn = $this->maybeCreateOptIn(
					$form->id(),
					$form->form_html(),
					$submission->get_posted_data(),
					$submission->uploaded_files()
				);

				if ( ! $OptIn ) {
					$this->get_logger()->warning( 'OptIn object could not be created', [
						'plugin'  => 'double-opt-in',
						'form_id' => $form->id(),
					] );

					return;
				}

				$parameter['formUrl'] = $submission->get_meta( 'url' );

				/**
				 * Filter to render custom email templates.
				 *
				 * Allows replacing the email body with content from custom templates.
				 *
				 * @since 4.0.0
				 *
				 * @param string $body      The email body content.
				 * @param string $template  The template key (e.g., 'blank', 'custom_123').
				 * @param array  $parameter The form parameters.
				 * @param OptIn  $OptIn     The OptIn object.
				 */
				$body = apply_filters( 'f12_cf7_doubleoptin_template_body', $parameter['body'], $parameter['template'], $parameter, $OptIn );

				$body = $this->addPlaceholders( $body, $OptIn, $parameter );
				$body = apply_filters( 'f12_cf7_doubleoptin_body', $body );

				$OptIn->set_mail_optin( $body );
				$OptIn->save();

				$args = apply_filters( 'f12-cf7-doubleoptin-cf7-args', [
					'subject'            => $parameter['subject'],
					'body'               => $body,
					'sender'             => $parameter['sender'],
					'sender_name'        => $parameter['sender_name'],
					'recipient'          => $parameter['recipient'],
					'use_html'           => true,
					'additional_headers' => '',
				] );

				if ( ! empty( $parameter['sender_name'] ) ) {
					$args['additional_headers'] .= 'From: ' . $args['sender_name'] . ' <' . $args['sender'] . '>';
				}

				\WPCF7_Mail::send( $args, 'mail' );
				$this->get_logger()->info( 'OptIn mail sent', [
					'plugin'    => 'double-opt-in',
					'form_id'   => $form->id(),
					'recipient' => $args['recipient'],
					'subject'   => $args['subject'],
				] );

				$telemetry = new Telemetry( $this->get_logger() );
				$telemetry->increment( 'total_optins' );
				$telemetry->increment( 'cf7_optins' );

				add_filter( 'wpcf7_skip_mail', '__return_true' );
				do_action( 'f12_cf7_doubleoptin_sent', $form, $form->id() );

			} elseif ( isset( $_GET['optin'] ) ) {
				$hash  = sanitize_text_field( $_GET['optin'] );
				$OptIn = OptIn::get_by_hash( $hash );

				if ( $OptIn === null ) {
					$this->get_logger()->warning( 'OptIn not found for hash', [
						'plugin' => 'double-opt-in',
						'hash'   => $hash,
					] );

					return;
				}

				$files = maybe_unserialize( $OptIn->get_files() );

				if ( empty( $files ) ) {
					$this->get_logger()->debug( 'No files found for OptIn', [
						'plugin'  => 'double-opt-in',
						'optinId' => $OptIn->get_id(),
					] );

					return;
				}

				foreach ( $files as $file ) {
					if ( empty( $file ) ) {
						continue;
					}

					if ( apply_filters( 'f12_cf7_doubleoptin_files_mail_1', true, $OptIn ) ) {
						$submission->add_extra_attachments( $file );
						$this->get_logger()->debug( 'Attachment added to mail_1', [
							'plugin' => 'double-opt-in',
							'file'   => $file,
						] );
					}

					if ( apply_filters( 'f12_cf7_doubleoptin_files_mail_2', true, $OptIn ) ) {
						$submission->add_extra_attachments( $file, 'mail_2' );
						$this->get_logger()->debug( 'Attachment added to mail_2', [
							'plugin' => 'double-opt-in',
							'file'   => $file,
						] );
					}
				}
			}
		}
	}
}