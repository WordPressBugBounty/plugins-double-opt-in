<?php

namespace forge12\contactform7\CF7DoubleOptIn;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class BaseController {
	/**
	 * @var CF7DoubleOptIn $Controller
	 */
	protected $Controller;

	public function __construct( $Controller ) {
		$this->Controller = $Controller;

		$this->on_init();
	}

	public function get_logger() {
		return $this->Controller->get_logger();
	}

	public abstract function on_init();
}