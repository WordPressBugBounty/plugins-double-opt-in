<?php

namespace forge12\contactform7\CF7DoubleOptIn {

	use Forge12\Shared\Logger;
	use Forge12\Shared\LoggerInterface;

	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	/**
	 * Class Pagination
	 *
	 * @package forge12\contactform7\CF7DoubleOptIn
	 */
	class Pagination {
		private static $_instance = null;
		private LoggerInterface $logger;

		public static function getInstance() {
			if ( null === self::$_instance ) {
				self::$_instance = new Pagination( Logger::getInstance() );
			}

			return self::$_instance;
		}

		private function __construct( LoggerInterface $logger ) {
			$this->logger = $logger;
			$this->get_logger()->info( 'OptIn class instance created.', [
				'plugin' => 'double-opt-in',
			] );

			// Add additional actions
			add_filter( 'f12_cf7_doubleoptin_pagination_link', array( $this, 'buildLink' ), 10, 2 );
			$this->get_logger()->debug( 'Added "f12_cf7_doubleoptin_pagination_link" filter to "buildLink" method.', [
				'plugin' => 'double-opt-in',
			] );
		}

		public function get_logger() {
			return $this->logger;
		}

		public function buildLink( $link, $parameter = array() ) {
			$this->get_logger()->info( 'Building a pagination link with existing and new parameters.', [
				'plugin' => 'double-opt-in',
				'base_link' => $link,
				'additional_parameters' => $parameter,
			] );

			/*
			 * Default Parameter
			 */
			$default = array(
				'perPage' => 10,
				'pageNum' => 1,
			);

			// Get existing parameters from the URL
			if ( isset( $_GET['perPage'] ) ) {
				$default['perPage'] = (int) $_GET['perPage'];
				$this->get_logger()->debug( 'Found perPage in URL: ' . $default['perPage'], [
					'plugin' => 'double-opt-in',
				] );
			}

			if ( isset( $_GET['pageNum'] ) ) {
				$default['pageNum'] = (int) $_GET['pageNum'];
				$this->get_logger()->debug( 'Found pageNum in URL: ' . $default['pageNum'], [
					'plugin' => 'double-opt-in',
				] );
			}

			if ( isset( $_GET['keyword'] ) ) {
				$default['keyword'] = sanitize_text_field( $_GET['keyword'] );
				$this->get_logger()->debug( 'Found keyword in URL: ' . $default['keyword'], [
					'plugin' => 'double-opt-in',
				] );
			}

			if ( isset( $_GET['cf_form_id'] ) ) {
				$default['cf_form_id'] = (int) $_GET['cf_form_id'];
				$this->get_logger()->debug( 'Found cf_form_id in URL: ' . $default['cf_form_id'], [
					'plugin' => 'double-opt-in',
				] );
			}

			// Merge default and provided parameters
			$merged_params = array_merge( $default, $parameter );
			$this->get_logger()->debug( 'Merged all parameters for the link.', [
				'plugin' => 'double-opt-in',
				'merged_params' => $merged_params,
			] );

			$default_keypair = array();

			// Create a key=value pair for each parameter
			foreach ( $merged_params as $key => $value ) {
				$default_keypair[] = urlencode( $key ) . '=' . urlencode( $value );
			}

			$final_link = $link . '?' . implode( "&", $default_keypair );

			$this->get_logger()->notice( 'Successfully built the pagination link.', [
				'plugin' => 'double-opt-in',
				'final_link' => $final_link,
			] );

			return $final_link;
		}
	}
}