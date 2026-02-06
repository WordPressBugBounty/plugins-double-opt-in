<?php

namespace forge12\contactform7\CF7DoubleOptIn {

	use Forge12\Shared\LoggerInterface;

	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}


	/**
	 * Class ConditionalFields Support
	 * Add support for conditional fields if available.
	 *
	 * @package forge12\contactform7\CF7DoubleOptIn
	 */
	class CF7ConditionalFields {
		private $conditionalFieldsParameter = array(
			'_wpcf7cf_hidden_group_fields' => '',
			'_wpcf7cf_hidden_groups'       => '',
			'_wpcf7cf_visible_groups'      => '',
			'_wpcf7cf_repeaters'           => '',
			'_wpcf7cf_steps'               => '',
			'_wpcf7cf_options'             => ''
		);
		private LoggerInterface $logger;

		/**
		 * Admin constructor.
		 */
		public function __construct( LoggerInterface $logger ) {
			$this->logger = $logger;

			$this->get_logger()->debug( 'Conditional fields constructor called', [
				'plugin' => 'double-opt-in',
				'class'  => __CLASS__,
				'method' => __METHOD__,
			] );

			add_filter(
				'f12_cf7_doubleoptin_add_request_parameter',
				[ $this, '_initConditionalFieldParameter' ],
				10,
				1
			);
			$this->get_logger()->debug( 'Filter f12_cf7_doubleoptin_add_request_parameter registered', [
				'plugin' => 'double-opt-in',
			] );

			add_filter(
				'f12_cf7_doubleoptin_body',
				[ $this, '_getOptinBody' ],
				10,
				1
			);
			$this->get_logger()->debug( 'Filter f12_cf7_doubleoptin_body registered', [
				'plugin' => 'double-opt-in',
			] );
		}


		public function get_logger() {
			return $this->logger;
		}

		/**
		 * Add the option to add conditional fields also to the optin mail.
		 *
		 * @return string
		 */
		public function _getOptinBody( $body ) {
			$this->get_logger()->debug( '_getOptinBody called', [
				'plugin' => 'double-opt-in',
				'class'  => __CLASS__,
				'method' => __METHOD__,
			] );

			if ( ! defined( 'WPCF7CF_PLUGIN' ) || ! class_exists( '\Wpcf7cfMailParser' ) ) {
				$this->get_logger()->debug( 'Conditional fields plugin not available, returning original body', [
					'plugin' => 'double-opt-in',
				] );
				return $body;
			}

			$CFMP = new \Wpcf7cfMailParser(
				$body,
				$this->conditionalFieldsParameter['_wpcf7cf_visible_groups'],
				$this->conditionalFieldsParameter['_wpcf7cf_hidden_groups'],
				$this->conditionalFieldsParameter['_wpcf7cf_repeaters'],
				[]
			);

			$parsedBody = $CFMP->getParsedMail();

			$this->get_logger()->debug( 'Optin body parsed with conditional fields', [
				'plugin' => 'double-opt-in',
			] );

			return $parsedBody;
		}


		/**
		 * Check if conditional field plugin is available and if yes, check for the parameter
		 * which has to be stored within the content.
		 *
		 * @param $parameter
		 */
		public function _initConditionalFieldParameter( $parameter ) {
			$this->get_logger()->debug( '_initConditionalFieldParameter called', [
				'plugin' => 'double-opt-in',
				'class'  => __CLASS__,
				'method' => __METHOD__,
			] );

			if ( ! defined( 'WPCF7CF_PLUGIN' ) ) {
				$this->get_logger()->debug( 'Conditional fields plugin not available, returning parameter unchanged', [
					'plugin' => 'double-opt-in',
				] );
				return $parameter;
			}

			foreach ( $this->conditionalFieldsParameter as $key => $value ) {
				if ( isset( $_POST[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
					$parameter[ $key ] = sanitize_text_field( $_POST[ $key ] );
					$this->conditionalFieldsParameter[ $key ] = sanitize_text_field(
						json_decode( wp_unslash( $_POST[ $key ] ) )
					);

					$this->get_logger()->debug( 'Conditional field parameter set', [
						'plugin' => 'double-opt-in',
						'key'    => $key,
					] );
				}
			}

			return $parameter;
		}
	}
}