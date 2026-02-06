<?php

namespace forge12\contactform7\CF7DoubleOptIn {

	use Forge12\Shared\Logger;
	use Fusion_Builder_Form_Helper;

	if (!defined('ABSPATH')) {
        exit;
    }

    if (class_exists('\Fusion_Form_Submit')) {
        /**
         * Class Frontend
         * Responsible to handle the frontend of the Double OptIn
         *
         * @package forge12\contactform7\CF7DoubleOptIn
         */
        class AvadaFormSubmit extends \Fusion_Form_Submit
        {
            /**
             * Flag to skip security checks during opt-in confirmation.
             *
             * @var bool
             */
            private $skip_security_checks = false;

            /**
             * Set whether to skip security checks.
             *
             * @param bool $skip Whether to skip.
             *
             * @return void
             */
            public function set_skip_security_checks( $skip ) {
                $this->skip_security_checks = (bool) $skip;
            }

            /**
             * Proces nonce, recaptcha and similar checks.
             * Dies if checks fail.
             *
             * @access protected
             * @since 3.1.1
             * @return void
             */
	        protected function pre_process_form_submit() {
		        $logger = Logger::getInstance();

		        $logger->debug( 'Pre process form submit started', [
			        'plugin' => 'double-opt-in',
			        'class'  => __CLASS__,
			        'method' => __METHOD__,
			        'skip_security' => $this->skip_security_checks,
		        ] );

		        // Skip security checks during opt-in confirmation
		        // (form was already validated during initial submission)
		        if ( $this->skip_security_checks ) {
			        $logger->debug( 'Skipping security checks for opt-in confirmation', [
				        'plugin' => 'double-opt-in',
			        ] );
			        return;
		        }

		        // Verify the form submission nonce.
		        // check_ajax_referer( 'fusion_form_nonce', 'fusion_form_nonce' );

		        // If we are in demo mode, just pretend it has sent.
		        if ( apply_filters( 'fusion_form_demo_mode', false ) ) {
			        $logger->info( 'Demo mode active, returning demo success response', [
				        'plugin' => 'double-opt-in',
			        ] );
			        die( wp_json_encode( $this->get_results_from_message( 'success', 'demo' ) ) );
		        }

		        // Check reCAPTCHA response and die if error.
		        $logger->debug( 'Checking reCAPTCHA response', [
			        'plugin' => 'double-opt-in',
		        ] );

		        $this->check_recaptcha_response();

		        $logger->debug( 'Pre process form submit finished', [
			        'plugin' => 'double-opt-in',
		        ] );
	        }


            /**
             * Ajax callback for 'send to email' submission type.
             *
             * @access public
             * @return void
             * @since 3.1
             */
	        public function submit( $formID, $sendmail_args ) {
		        $logger = Logger::getInstance();

		        $logger->debug( 'Form submission started', [
			        'plugin'   => 'double-opt-in',
			        'class'    => __CLASS__,
			        'method'   => __METHOD__,
			        'form_id'  => $formID,
			        'args'     => $sendmail_args,
		        ] );

		        $this->pre_process_form_submit();

		        // Checks nonce, recaptcha and similar. Dies if checks fail.
		        $data = $this->get_submit_data();
		        $logger->debug( 'Form data retrieved', [
			        'plugin'  => 'double-opt-in',
			        'form_id' => $formID,
		        ] );

		        $form_meta = Fusion_Builder_Form_Helper::fusion_form_get_form_meta( $formID );
		        $actions   = $form_meta['form_actions'] ?? [];

		        if ( ! empty( $actions ) ) {
			        foreach ( $actions as $action ) {
				        if ( $action === 'email' ) {
					        $logger->debug( 'Processing deprecated email action', [
						        'plugin'  => 'double-opt-in',
						        'form_id' => $formID,
					        ] );

					        $sendmail = $this->submit_form_to_email( $data );

					        if ( $sendmail ) {
						        $logger->info( 'Email action executed successfully', [
							        'plugin'  => 'double-opt-in',
							        'form_id' => $formID,
						        ] );
						        $fusion_forms = new \Fusion_Form_DB_Forms();
						        $fusion_forms->increment_submissions_count( $formID );
					        } else {
						        $logger->warning( 'Email action failed', [
							        'plugin'  => 'double-opt-in',
							        'form_id' => $formID,
						        ] );
					        }
				        }
			        }
		        }

		        $sendmail = $this->handle_form_notifications( $data, $formID );

		        if ( $sendmail ) {
			        $logger->info( 'Form notifications handled successfully', [
				        'plugin'  => 'double-opt-in',
				        'form_id' => $formID,
			        ] );

			        $fusion_forms = new \Fusion_Form_DB_Forms();
			        $fusion_forms->increment_submissions_count( $formID );

			        return true;
		        }

		        $logger->warning( 'Form submission failed', [
			        'plugin'  => 'double-opt-in',
			        'form_id' => $formID,
		        ] );

		        return false;
	        }

        }
    }
}