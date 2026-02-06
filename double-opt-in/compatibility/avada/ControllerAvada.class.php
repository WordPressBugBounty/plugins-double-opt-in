<?php

namespace forge12\contactform7\CF7DoubleOptIn {
	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	/**
	 * Class AvadaOptIn
	 *
	 * @package forge12\contactform7\CF7OptIn
	 *
	 * @deprecated 4.0.0 This controller is part of the legacy system.
	 *             Use the filter 'f12_cf7_doubleoptin_use_new_integration_system' to switch
	 *             to the new event-based integration system.
	 */
	class ControllerAvada extends BaseController {
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
				$this->get_logger()->info( 'Legacy Avada controller skipped - new integration system is active', [
					'plugin' => 'double-opt-in',
					'class'  => __CLASS__,
				] );
				return;
			}

			$this->get_logger()->debug( 'Avada integration on_init started', [
				'plugin' => 'double-opt-in',
				'class'  => __CLASS__,
				'method' => __METHOD__,
			] );

			require_once( 'AvadaFormOptions.class.php' );
			$AFO = new AvadaFormOptions( $this->get_logger() );
			$this->get_logger()->debug( 'AvadaFormOptions initialized', [
				'plugin' => 'double-opt-in',
			] );

			require_once( 'AvadaFrontend.class.php' );
			$AF = new AvadaFrontend( $this->get_logger() );
			$this->get_logger()->debug( 'AvadaFrontend initialized', [
				'plugin' => 'double-opt-in',
			] );
		}

	}
}