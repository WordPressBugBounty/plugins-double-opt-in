<?php

namespace forge12\contactform7\CF7DoubleOptIn {

	use Forge12\Shared\Logger;
	use Forge12\Shared\LoggerInterface;

	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	/**
	 * Class OptInSearchFilter
	 *
	 * @package forge12\contactform7\CF7DoubleOptIn
	 */
	class OptInSearchFilter {
		private static $_instance = null;
		private LoggerInterface $logger;

		public static function getInstance() {
			if ( null === self::$_instance ) {
				self::$_instance = new OptInSearchFilter( Logger::getInstance() );
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
		 * Add a Option to update the given Categories
		 *
		 * @return void
		 */
		public function addFilter() {
			$this->get_logger()->info( 'Adding search filter to the UI table view.', [
				'plugin' => 'double-opt-in',
			] );

			$keyword = '';
			// Check if a keyword is already set in the GET request to pre-fill the search box.
			if ( isset( $_GET['keyword'] ) ) {
				$keyword = sanitize_text_field( wp_unslash( $_GET['keyword'] ) );
				$this->get_logger()->debug( 'Pre-filling search keyword from URL.', [
					'plugin' => 'double-opt-in',
					'keyword' => $keyword,
				] );
			}

			?>
            <li>
				<?php _e( 'Search:', 'double-opt-in' ); ?>
                <input type="text" name="keyword" value="<?php echo esc_attr( $keyword ); ?>" />
                <input type="submit" class="button" name="search"
                       value="<?php _e( 'Search', 'double-opt-in' ); ?>" />
            </li>
			<?php
			$this->get_logger()->notice( 'Search filter HTML successfully output to the page.', [
				'plugin' => 'double-opt-in',
			] );
		}
	}
}