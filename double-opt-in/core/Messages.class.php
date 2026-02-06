<?php

namespace forge12\contactform7\CF7DoubleOptIn {

	use Forge12\Shared\Logger;
	use Forge12\Shared\LoggerInterface;

	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	/**
	 * Class Messages
	 *
	 * @package forge12\contactform7\CF7DoubleOptIn
	 */
	class Messages {
		/**
		 * @var Messages|null
		 */
		private static $instance;

		/**
		 * @var array
		 */
		private $messages = [];
		private LoggerInterface $logger;

		/**
		 * @return Messages|null
		 */
		public static function getInstance() {
			if ( null === self::$instance ) {
				self::$instance = new Messages( Logger::getInstance() );
			}

			return self::$instance;
		}

		private function __clone() {

		}

		public function __wakeup() {

		}

		private function __construct( LoggerInterface $logger ) {
			$this->logger = $logger;
		}

		public function get_logger() {
			return $this->logger;
		}

		/**
		 * getAll function.
		 *
		 * @access public
		 * @return string
		 */
		public function getAll() {
			$this->get_logger()->info( 'Retrieving all messages and combining them into a single string.', [
				'plugin' => 'double-opt-in',
			] );

			if ( empty( $this->messages ) ) {
				$this->get_logger()->debug( 'No messages found to combine.', [
					'plugin' => 'double-opt-in',
				] );
				return '';
			}

			$combined_messages = implode( "\n", $this->messages );

			$this->get_logger()->debug( 'Combined ' . count($this->messages) . ' messages.', [
				'plugin'       => 'double-opt-in',
				'message_count' => count( $this->messages ),
			] );

			// Clear messages after retrieval to prevent duplicates
			$this->messages = [];

			return $combined_messages;
		}

		/**
		 * add function.
		 *
		 * @access public
		 *
		 * @param mixed $message
		 * @param mixed $type
		 *
		 * @return void
		 */
		public function add( $message, $type ) {
			$this->get_logger()->info( 'Adding a new UI message.', [
				'plugin' => 'double-opt-in',
				'message' => $message,
				'original_type' => $type,
			] );

			// Convert the given message type to a specific CSS class.
			switch ($type) {
				case 'error':
					$type = 'alert-danger';
					$this->get_logger()->error( 'An error message has been added to the UI.', [
						'plugin' => 'double-opt-in',
						'message_content' => $message,
					] );
					break;
				case 'success':
					$type = 'alert-success';
					$this->get_logger()->info( 'A success message has been added to the UI.', [
						'plugin' => 'double-opt-in',
						'message_content' => $message,
					] );
					break;
				case 'info':
					$type = 'alert-info';
					$this->get_logger()->info( 'An info message has been added to the UI.', [
						'plugin' => 'double-opt-in',
						'message_content' => $message,
					] );
					break;
				case 'warning':
					$type = 'alert-warning';
					$this->get_logger()->warning( 'A warning message has been added to the UI.', [
						'plugin' => 'double-opt-in',
						'message_content' => $message,
					] );
					break;
				case 'offer':
					$type = 'alert-offer';
					$this->get_logger()->notice( 'An offer message has been added to the UI.', [
						'plugin' => 'double-opt-in',
						'message_content' => $message,
					] );
					break;
				case 'critical':
					$type = 'alert-critical';
					$this->get_logger()->critical( 'A critical message has been added to the UI.', [
						'plugin' => 'double-opt-in',
						'message_content' => $message,
					] );
					break;
				default:
					$this->get_logger()->debug( 'An unknown message type was provided. Using default type.', [
						'plugin' => 'double-opt-in',
						'unknown_type' => $type,
					] );
					// Fallback for unknown types
					$type = 'alert-info';
					break;
			}

			$this->messages[] = '<div class="box ' . \esc_attr( $type ) . '" role="alert">' . esc_html( $message ) . '</div>';

			$this->get_logger()->debug( 'Message added to messages array.', [
				'plugin'       => 'double-opt-in',
				'final_html'   => end($this->messages),
				'message_count' => count($this->messages),
			] );
		}
	}
}