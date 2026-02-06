<?php

namespace forge12\contactform7\CF7DoubleOptIn {
	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	/**
	 * Class ControllerCF7
	 *
	 * @package forge12\contactform7\CF7OptIn
	 *
	 * @deprecated 4.0.0 This controller is part of the legacy system.
	 *             Use the filter 'f12_cf7_doubleoptin_use_new_integration_system' to switch
	 *             to the new event-based integration system.
	 */
	class ControllerCF7 extends BaseController {
		/**
		 * Admin constructor.
		 */
		public function on_init() {
			/**
			 * Check if the new integration system is active.
			 * If so, skip loading the legacy classes to avoid duplicate processing.
			 *
			 * @since 4.0.0
			 */
			if ( apply_filters( 'f12_cf7_doubleoptin_use_new_integration_system', true ) ) {
				$this->get_logger()->info( 'Legacy CF7 controller skipped - new integration system is active', [
					'plugin' => 'double-opt-in',
					'class'  => __CLASS__,
				] );
				return;
			}

			$this->get_logger()->debug( 'CF7 integration on_init started', [
				'plugin' => 'double-opt-in',
				'class'  => __CLASS__,
				'method' => __METHOD__,
			] );

			require_once( 'CF7Frontend.class.php' );
			$Frontend = new CF7Frontend( $this->get_logger() );
			$this->get_logger()->debug( 'CF7Frontend initialized', [
				'plugin' => 'double-opt-in',
			] );

			require_once( 'CF7Backend.class.php' );
			$Backend = new CF7Backend( $this->get_logger() );
			$this->get_logger()->debug( 'CF7Backend initialized', [
				'plugin' => 'double-opt-in',
			] );

			require_once( 'CF7ConditionalFields.class.php' );
			$CF = new CF7ConditionalFields( $this->get_logger() );
			$this->get_logger()->debug( 'CF7ConditionalFields initialized', [
				'plugin' => 'double-opt-in',
			] );
		}

	}
}