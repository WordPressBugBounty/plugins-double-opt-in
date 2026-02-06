<?php

namespace forge12\contactform7\CF7DoubleOptIn {

	use Forge12\Shared\Logger;
	use Forge12\Shared\LoggerInterface;

	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	/**
	 * Class UICategories
	 *
	 * @package forge12\contactform7\CF7DoubleOptIn
	 */
	class CategoryOptions {
		private static $_instance = null;
		private LoggerInterface $logger;

		public static function getInstance() {
			if ( null === self::$_instance ) {
				self::$_instance = new CategoryOptions( Logger::getInstance() );
			}

			return self::$_instance;
		}

		private function __construct( LoggerInterface $logger ) {
			$this->logger = $logger;

			$this->get_logger()->info( 'Plugin class initialized.', [
				'plugin' => 'double-opt-in',
				'method' => __METHOD__,
			] );

			// Add additional actions
			$this->get_logger()->info( 'Adding action for UI table options.', [
				'plugin' => 'double-opt-in',
				'hook'   => 'f12_cf7_doubleoptin_ui_table_options',
				'callback' => 'addOption',
			] );
			add_action( 'f12_cf7_doubleoptin_ui_table_options', array( $this, 'addOption' ) );

			$this->get_logger()->info( 'Adding action for admin initialization.', [
				'plugin' => 'double-opt-in',
				'hook'   => 'admin_init',
				'callback' => 'init',
			] );
			add_action( 'admin_init', array( $this, 'init' ) );

			$this->get_logger()->info( 'All actions added successfully during initialization.', [
				'plugin' => 'double-opt-in',
			] );
		}

		public function get_logger() {
			return $this->logger;
		}

		/**
		 * After initializing the WordPress Admi
		 *
		 * @return void
		 */
		public function init() {
			$this->get_logger()->info( 'Admin init hook triggered.', [
				'plugin' => 'double-opt-in',
				'method' => __METHOD__,
			] );

			if ( isset( $_POST['update-category'] ) ) {
				$this->get_logger()->info( 'Update categories action detected via POST.', [
					'plugin' => 'double-opt-in',
					'post_action' => 'update-category',
				] );
				$this->update_categories();
			} else {
				$this->get_logger()->debug( 'No category update action detected.', [
					'plugin' => 'double-opt-in',
					'post_action_status' => 'not set',
				] );
			}
		}

		/**
		 * Assign the new categories to the selected opt ins.
		 *
		 * @return void
		 */
		private function update_categories() {
			$this->get_logger()->info( 'Starting update categories process.', [
				'plugin' => 'double-opt-in',
			] );

			// Check for nonce verification
			if ( ! \wp_verify_nonce( wp_unslash( $_POST['f12_cf7_doubleoptin_ui_table_options_nonce'] ), 'f12_cf7_doubleoptin_ui_table_options_action' ) ) {
				$this->get_logger()->warning( 'Nonce verification failed during category update.', [
					'plugin' => 'double-opt-in',
					'nonce'  => $_POST['f12_cf7_doubleoptin_ui_table_options_nonce'] ?? 'not set',
				] );
				return;
			}

			// Check if opt-in IDs are set and an array
			if ( ! isset( $_POST['optin-id'] ) || ! is_array( $_POST['optin-id'] ) ) {
				$this->get_logger()->warning( 'Invalid or missing opt-in IDs for category update.', [
					'plugin' => 'double-opt-in',
					'optin-id-status' => isset($_POST['optin-id']) ? gettype($_POST['optin-id']) : 'not set',
				] );
				return;
			}

			// Check if category ID is set
			if ( ! isset( $_POST['category'] ) ) {
				$this->get_logger()->warning( 'Category ID is missing for the update process.', [
					'plugin' => 'double-opt-in',
				] );
				return;
			}

			$category_id = (int) $_POST['category'];

			$this->get_logger()->info( 'Attempting to retrieve category with ID.', [
				'plugin' => 'double-opt-in',
				'category_id' => $category_id,
			] );

			$Category = Category::get_by_id( $category_id );

			// Check if category exists unless ID is 0
			if ( null == $Category && 0 !== $category_id ) {
				$this->get_logger()->error( 'Category not found for the given ID. Aborting update.', [
					'plugin' => 'double-opt-in',
					'category_id' => $category_id,
				] );
				return;
			}

			$optinIds = array_map( 'intval', $_POST['optin-id'] );
			$this->get_logger()->debug( 'Found a total of ' . count($optinIds) . ' opt-ins to update.', [
				'plugin' => 'double-opt-in',
				'optin_ids' => $optinIds,
			] );

			foreach ( $optinIds as $id ) {
				$this->get_logger()->info( 'Updating opt-in ID ' . $id . ' to category ID ' . $category_id . '.', [
					'plugin' => 'double-opt-in',
					'optin_id' => $id,
					'new_category_id' => $category_id,
				] );
				OptIn::update_category_by_id( $id, $category_id );
			}

			$this->get_logger()->notice( 'Finished updating all specified opt-ins.', [
				'plugin' => 'double-opt-in',
				'total_updated' => count($optinIds),
			] );
		}

		/**
		 * Return an Select Field for Categories
		 *
		 * @return string
		 */
		public function select_category( $selected = 0 ) {
			$this->get_logger()->info( 'Generating category selection dropdown.', [
				'plugin' => 'double-opt-in',
				'selected_id' => $selected,
			] );

			$atts = array(
				'perPage' => - 1,
				'orderBy' => 'name',
				'order'   => 'ASC'
			);

			// Log the attributes used for the category list retrieval.
			$this->get_logger()->debug( 'Fetching full list of categories for dropdown.', [
				'plugin' => 'double-opt-in',
				'attributes' => $atts,
			] );

			$list = Category::get_list( $atts, $numberOfPages );

			if ( empty( $list ) ) {
				$this->get_logger()->warning( 'No categories found to populate the dropdown.', [
					'plugin' => 'double-opt-in',
				] );
			}

			$response = '<option value="0">' . __( 'Please select', 'double-opt-in' ) . '</option>';

			foreach ( $list as $Category /** @var Category $Category */ ) {
				$selected_html = $selected == $Category->get_id() ? 'selected="selected"' : '';
				$response      .= '<option value="' . esc_attr( $Category->get_id() ) . '" ' . $selected_html . '>' . esc_attr( $Category->get_name() ) . '</option>';

				// Log each option added to the dropdown.
				$this->get_logger()->debug( 'Added category option to dropdown.', [
					'plugin' => 'double-opt-in',
					'category_id' => $Category->get_id(),
					'category_name' => $Category->get_name(),
					'is_selected' => ($selected == $Category->get_id()),
				] );
			}

			$final_html = '<select name="category">' . $response . '</select>';

			// Log the successful generation of the dropdown.
			$this->get_logger()->info( 'Category selection dropdown successfully generated.', [
				'plugin' => 'double-opt-in',
				'html_length' => strlen($final_html),
			] );

			return $final_html;
		}

		/**
		 * Add a Option to update the given Categories
		 *
		 * @return string
		 */
		public function addOption() {
			$this->get_logger()->info( 'Adding category selection option to UI.', [
				'plugin' => 'double-opt-in',
			] );
			?>
            <li>
				<?php _e( 'Change Category to: ' ); ?>
				<?php
				// Log the generation of the select input.
				$this->get_logger()->info( 'Rendering select input for category change.', [
					'plugin' => 'double-opt-in',
					'function' => 'select_category',
				] );

				echo wp_kses( $this->select_category(), array(
					'select' => array( 'name' => array() ),
					'option' => array(
						'value'    => array(),
						'selected' => array()
					)
				) );
				?>
                <input type="submit" class="button" name="update-category"
                       value="<?php _e( 'Update', 'double-opt-in' ); ?>"/>
            </li>
			<?php
			$this->get_logger()->notice( 'Category selection option successfully added to the UI.', [
				'plugin' => 'double-opt-in',
			] );
		}
	}
}