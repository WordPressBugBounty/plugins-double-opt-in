<?php

namespace forge12\contactform7\CF7DoubleOptIn {

	use Forge12\Shared\Logger;
	use Forge12\Shared\LoggerInterface;

	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	/**
	 * Class OptInLimitFilter
	 *
	 * @package forge12\contactform7\CF7DoubleOptIn
	 */
	class OptInLimitFilter {
		private static $_instance = null;
		private LoggerInterface $logger;

		public static function getInstance() {
			if ( null === self::$_instance ) {
				self::$_instance = new OptInLimitFilter( Logger::getInstance() );
			}

			return self::$_instance;
		}

		private function __construct( LoggerInterface $logger ) {
			$this->logger = $logger;
			$this->get_logger()->info( 'OptIn class instance created.', [
				'plugin' => 'double-opt-in',
			] );

			// Add additional actions
			add_action( 'f12_cf7_doubleoptin_ui_table_filter', array( $this, 'addFilter' ) );
			$this->get_logger()->debug( 'Added "f12_cf7_doubleoptin_ui_table_filter" action to "addFilter" method.', [
				'plugin' => 'double-opt-in',
			] );
		}

		public function get_logger() {
			return $this->logger;
		}

		/**
		 * Return an Select Field for Categories
		 *
		 * @return string
		 */
		private function select_limit( $selected = 10 ) {
			$this->get_logger()->info( 'Generating HTML for the pagination limit dropdown.', [
				'plugin' => 'double-opt-in',
				'selected_value' => $selected,
			] );

			$list = array(
				10  => '10',
				25  => '25',
				50  => '50',
				100 => '100'
			);

			$response = '';

			foreach ( $list as $key => $value ) {
				$selected_html = '';

				if ( (int)$key === (int)$selected ) {
					$selected_html = 'selected="selected"';
					$this->get_logger()->debug( 'Option ' . $value . ' is selected.', [
						'plugin' => 'double-opt-in',
						'selected_key' => $key,
					] );
				}

				$response .= '<option value="' . esc_attr( $key ) . '" ' . $selected_html . '>' . esc_attr( $value ) . '</option>';
			}

			$final_html = '<select name="perPage">' . $response . '</select>';

			$this->get_logger()->notice( 'Successfully generated the select limit dropdown HTML.', [
				'plugin' => 'double-opt-in',
				'html_output' => $final_html,
			] );

			return $final_html;
		}

		/**
		 * Add a Option to update the given Categories
		 *
		 * @return string
		 */
		public function addFilter() {
			$this->get_logger()->info( 'Adding filter for UI table view.', [
				'plugin' => 'double-opt-in',
			] );

			$selected = 10;

			// Check if perPage is set in the GET request.
			if ( isset( $_GET['perPage'] ) ) {
				$selected = (int) $_GET['perPage'];
				$this->get_logger()->debug( 'perPage parameter found in URL. Setting selected value to ' . $selected, [
					'plugin'   => 'double-opt-in',
					'selected' => $selected,
				] );
			}

			// Start of the HTML output.
			?>
            <li>
				<?php _e( 'Show: ' ); ?>
				<?php
				$select_html = $this->select_limit( $selected );
				$this->get_logger()->info( 'Generated select_limit HTML.', [
					'plugin' => 'double-opt-in',
					'html'   => $select_html,
				] );
				echo wp_kses( $select_html, array(
					'select' => array( 'name' => array() ),
					'option' => array(
						'value'    => array(),
						'selected' => array()
					)
				) );
				?>
                <input type="submit" class="button" name="update-limit"
                       value="<?php _e( 'Show', 'double-opt-in' ); ?>"/>
            </li>
			<?php
			$this->get_logger()->notice( 'Filter HTML successfully output to the page.', [
				'plugin' => 'double-opt-in',
			] );
		}
	}
}