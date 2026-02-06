<?php

namespace forge12\contactform7\CF7DoubleOptIn {

	use Forge12\Shared\LoggerInterface;

	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	/**
	 * Class Frontend
	 * Responsible to handle the frontend of the Double OptIn
	 *
	 * @package forge12\contactform7\CF7DoubleOptIn
	 *
	 * @deprecated 4.0.0 Use \Forge12\DoubleOptIn\Integration\AvadaIntegration instead.
	 *             This class is maintained for backward compatibility only.
	 * @see \Forge12\DoubleOptIn\Integration\AvadaIntegration
	 */
	class AvadaFrontend extends OptInFrontend {
		/**
		 * Admin constructor.
		 */
		public function __construct( LoggerInterface $logger ) {
			parent::__construct( $logger, 'avada' );

			$this->get_logger()->debug( 'Avada integration constructor called', [
				'plugin' => 'double-opt-in',
				'class'  => __CLASS__,
				'method' => __METHOD__,
				'optin'  => $_GET['optin'] ?? null,
			] );

			if ( ! isset( $_GET['optin'] ) ) {
				add_filter( 'fusion_form_send_mail_args', [ $this, 'onSubmit' ], 10, 3 );
				$this->get_logger()->debug( 'Filter fusion_form_send_mail_args registered', [
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
				'form_id'  => $OptIn->get_cf_form_id(),
				'optin_id' => $OptIn->get_id(),
			] );

			$formData = maybe_unserialize( $OptIn->get_content() );
			$formData = SanitizeHelper::sanitize_array( $formData );

			if ( ! isset( $formData['data'] ) ) {
				$this->get_logger()->warning( 'No form data available in OptIn, aborting sendDefaultMail', [
					'plugin'   => 'double-opt-in',
					'form_id'  => $OptIn->get_cf_form_id(),
					'optin_id' => $OptIn->get_id(),
				] );
				return;
			}

			$data           = $formData['data'];
			$formDataString = [];
			foreach ( $data as $key => $value ) {
				if ( is_array( $value ) ) {
					$value = implode( ',', $value );
				}
				$formDataString[] = $key . '=' . $value;
			}
			$formDataString = implode( "&", $formDataString );

			$_POST = [
				'formData'           => $formDataString,
				'field_labels'       => json_encode( $formData['field_labels'] ),
				'field_types'        => json_encode( $formData['field_types'] ),
				'hidden_field_names' => json_encode( $formData['hidden_field_names'] ),
				'form_id'            => $OptIn->get_cf_form_id(),
			];

			$this->get_logger()->debug( 'Prepared $_POST for Avada form submission', [
				'plugin'   => 'double-opt-in',
				'form_id'  => $OptIn->get_cf_form_id(),
				'optin_id' => $OptIn->get_id(),
			] );

			require_once( 'AvadaFormSubmit.class.php' );

			$AvadaFormSubmit = new AvadaFormSubmit();

			$AvadaFormSubmit->submit( $OptIn->get_cf_form_id(), [] );

			$this->get_logger()->info( 'Avada form submitted via sendDefaultMail', [
				'plugin'   => 'double-opt-in',
				'form_id'  => $OptIn->get_cf_form_id(),
				'optin_id' => $OptIn->get_id(),
			] );
		}

		/**
		 * On Form Submit add the double optin if enabled
		 *
		 * @param array $parameter
		 * @param int   $post_id
		 * @param array $data
		 */
		public function onSubmit( $form_parameter, $submission_id, $form_data ) {
			$this->get_logger()->debug( 'onSubmit called', [
				'plugin'        => 'double-opt-in',
				'class'         => __CLASS__,
				'method'        => __METHOD__,
				'submission_id' => $submission_id,
				'post_data'     => $_POST ?? [],
			] );

			$post_id = isset( $_POST['form_id'] ) ? absint( sanitize_text_field( wp_unslash( $_POST['form_id'] ) ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification

			if ( ! $post_id ) {
				$this->get_logger()->warning( 'No form_id found in POST, skipping onSubmit', [
					'plugin' => 'double-opt-in',
				] );
				return $form_parameter;
			}

			$parameter = CF7DoubleOptIn::getInstance()->getParameter( $post_id );

			if ( $this->isOptinEnabled( $post_id ) ) {
				$form = get_post( $post_id );

				if ( ! $form ) {
					$this->get_logger()->warning( 'Form post not found, skipping onSubmit', [
						'plugin'  => 'double-opt-in',
						'post_id' => $post_id,
					] );
					return $form_parameter;
				}

				$form_data['form_parameter'] = $form_parameter;

				if ( apply_filters( 'f12_cf7_doubleoptin_skip_option', false, $post_id, $form_data, $this->type ) ) {
					$this->get_logger()->info( 'Opt-in skipped by filter', [
						'plugin'  => 'double-opt-in',
						'post_id' => $post_id,
					] );
					die( wp_json_encode( [ 'status' => 'error', 'info' => 'opt-in skipped' ] ) );
				}

				$OptIn = $this->maybeCreateOptIn(
					$post_id,
					do_shortcode( $form->post_content ),
					$form_data,
					$form_parameter['attachments']
				);

				if ( ! $OptIn ) {
					$this->get_logger()->warning( 'OptIn object could not be created', [
						'plugin'  => 'double-opt-in',
						'post_id' => $post_id,
					] );
					return $form_parameter;
				}

				$parameter['formUrl'] = $form_data['submission']['source_url'];

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

				$headers  = "MIME-Version: 1.0\r\n";
				$headers .= "Content-type: text/html; charset=UTF-8\r\n";

				$args = apply_filters( 'f12-cf7-doubleoptin-cf7-args', [
					'subject'            => $parameter['subject'],
					'body'               => $body,
					'sender'             => $parameter['sender'],
					'sender_name'        => $parameter['sender_name'],
					'recipient'          => $form_data['data'][ $parameter['recipient'] ] ?? '',
					'use_html'           => true,
					'additional_headers' => $headers
				] );

				$args['additional_headers'] .= sprintf( 'From: %s <%s>' . "\r\n", $args['sender_name'], $args['sender'] );

				\wp_mail(
					$args['recipient'],
					$args['subject'],
					$args['body'],
					$args['additional_headers']
				);

				$telemetry = new Telemetry( $this->get_logger() );
				$telemetry->increment( 'total_optins' );
				$telemetry->increment( 'avada_optins' );

				$this->get_logger()->info( 'OptIn mail sent', [
					'plugin'    => 'double-opt-in',
					'post_id'   => $post_id,
					'recipient' => $args['recipient'],
					'subject'   => $args['subject'],
				] );

				do_action( 'f12_cf7_doubleoptin_sent', $form, $post_id );

				die( wp_json_encode( [ 'status' => 'success', 'info' => 'opt-in send' ] ) );
			}

			$this->get_logger()->debug( 'Opt-in not enabled for form', [
				'plugin'  => 'double-opt-in',
				'post_id' => $post_id,
			] );

			return $form_parameter;
		}


		/**
		 * Retrieves the recipient value from the given parameters.
		 *
		 * @param string $recipient     The recipient parameter.
		 * @param array  $formParameter The form parameters.
		 * @param array  $postParameter The post parameters.
		 *
		 * @return string The recipient value if it exists in the post parameters, or an empty string if it doesn't.
		 */
		public function getRecipient( string $recipient, array $formParameter, array $postParameter ): string {
			$this->get_logger()->debug( 'getRecipient called', [
				'plugin'        => 'double-opt-in',
				'class'         => __CLASS__,
				'method'        => __METHOD__,
				'formParameter' => $formParameter,
			] );

			if ( ! isset( $postParameter['data'][ $formParameter['recipient'] ] ) ) {
				$this->get_logger()->warning( 'Recipient not found in postParameter', [
					'plugin' => 'double-opt-in',
				] );
				return '';
			}

			$value = $postParameter['data'][ $formParameter['recipient'] ];

			$this->get_logger()->debug( 'Recipient resolved', [
				'plugin'    => 'double-opt-in',
				'recipient' => $value,
			] );

			return $value;
		}
	}
}