<?php

namespace forge12\contactform7\CF7DoubleOptIn {

	use Forge12\Shared\LoggerInterface;

	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	/**
	 * Class UICategories
	 * Show the Category and Assigned DOIs
	 *
	 * @package forge12\contactform7\CF7DoubleOptIn
	 */
	class UICategoriesView extends UIPage {
		/**
		 * Class constructor.
		 *
		 * @param TemplateHandler $templateHandler The template handler object.
		 * @param string          $domain          The domain string.
		 */
		public function __construct(LoggerInterface $logger, TemplateHandler $templateHandler, string $domain ) {

			parent::__construct($logger, $templateHandler, $domain, 'categories_view', __('Category View', 'double-opt-in') );

			add_action( 'admin_init', array( $this, 'maybeUpdateCategory' ) );
			$this->get_logger()->debug( 'Added "maybeUpdateCategory" method to "admin_init" action.', [
				'plugin' => $domain,
			] );
		}

		public function maybeUpdateCategory() {
			$this->get_logger()->info( 'Attempting to update a category.', [
				'plugin' => $this->domain,
			] );

			// Check for nonce and verify it for security
			if ( isset( $_POST['doi_settings_nonce'] ) && ! wp_verify_nonce( wp_unslash( $_POST['doi_settings_nonce'] ), 'doi_settings_action' ) ) {
				$this->get_logger()->error( 'Nonce verification failed. Aborting category update.', [
					'plugin' => $this->domain,
				] );
				return;
			}

			// Check if the form was submitted by checking for the specific submit button's name
			if ( ! isset( $_POST['doubleoptin-settings-edit-submit'] ) ) {
				$this->get_logger()->debug( 'Form not submitted via the edit button. Aborting.', [
					'plugin' => $this->domain,
				] );
				return;
			}

			// Ensure the category ID is present
			if ( ! isset( $_POST['category_id'] ) ) {
				$this->get_logger()->warning( 'Category ID not found in the POST data. Aborting.', [
					'plugin' => $this->domain,
				] );
				return;
			}

			$category_id = (int) $_POST['category_id'];
			$this->get_logger()->debug( 'Processing request to update category with ID: ' . $category_id, [
				'plugin' => $this->domain,
			] );

			// Retrieve the existing category object from the database
			$Category = Category::get_by_id( $category_id );

			if ( null === $Category ) {
				$this->get_logger()->error( 'Category with ID ' . $category_id . ' not found in the database. Aborting update.', [
					'plugin' => $this->domain,
				] );
				return;
			}

			// Sanitize and validate the new category name
			$name = sanitize_text_field( $_POST['categorie_name'] );

			if ( empty( $name ) ) {
				$this->get_logger()->error( 'New category name is empty after sanitization. Aborting update.', [
					'plugin' => $this->domain,
				] );
				Messages::getInstance()->add( __( 'Category name cannot be empty.', 'double-opt-in' ), 'error' );
				return;
			}

			// Set the new name and attempt to save the changes
			$this->get_logger()->debug( 'Updating category name from "' . $Category->get_name() . '" to "' . $name . '".', [
				'plugin' => $this->domain,
			] );
			$Category->set_name( $name );

			if ( $Category->save() ) {
				Messages::getInstance()->add( __( 'Category updated', 'double-opt-in' ), 'success' );
				$this->get_logger()->notice( 'Category with ID ' . $category_id . ' updated successfully.', [
					'plugin' => $this->domain,
				] );
			} else {
				Messages::getInstance()->add( __( 'Category update failed', 'double-opt-in' ), 'error' );
				$this->get_logger()->error( 'Failed to save the updated category to the database.', [
					'plugin' => $this->domain,
					'category_id' => $category_id,
				] );
			}
		}

		/**
		 * Hide this page in the menu.
		 *
		 * @return bool
		 */
		public function hideInMenu() {
			return true;
		}

		/**
		 * Render the license subpage content
		 */
		protected function theContent( $slug, $page, $settings ) {
			$this->get_logger()->info( 'Rendering the main content for the "Category View" UI page.', [
				'plugin' => $this->domain,
				'page_slug' => $page,
			] );

			/*
			 * Ensure that the ID for the Categorie is set
			 */
			if ( ! isset( $_GET['id'] ) ) {
				$this->get_logger()->error( 'Category ID not provided in URL. Redirecting to main page.', [
					'plugin' => $this->domain,
				] );
				wp_redirect( admin_url( 'admin.php' ) . '?page=f12-cf7-doubleoptin' );
				return;
			}

			$id = (int) $_GET['id'];
			$this->get_logger()->debug( 'Category ID from URL: ' . $id, [
				'plugin' => $this->domain,
			] );

			/*
			 * Load the assigned Category
			 */
			$Category = Category::get_by_id( $id );

			if ( null === $Category ) {
				$this->get_logger()->error( 'Category with ID ' . $id . ' not found. Redirecting to main page.', [
					'plugin' => $this->domain,
				] );
				wp_redirect( admin_url( 'admin.php' ) . '?page=f12-cf7-doubleoptin' );
				return;
			}

			$this->get_logger()->notice( 'Category found: ' . $Category->get_name(), [
				'plugin' => $this->domain,
				'category_id' => $id,
			] );

			$atts = array(
				'perPage' => 10,
				'page'    => 1,
				'order'   => 'DESC',
			);

			// Set pagination and search attributes from URL parameters
			if ( isset( $_GET['pageNum'] ) ) {
				$atts['page'] = (int) $_GET['pageNum'];
			}

			if ( isset( $_GET['perPage'] ) ) {
				$atts['perPage'] = (int) $_GET['perPage'];
			}

			if ( isset( $_GET['keyword'] ) ) {
				$atts['keyword'] = sanitize_text_field( $_GET['keyword'] );
			}

			$this->get_logger()->debug( 'Query attributes for OptIns: ' . json_encode($atts), [
				'plugin' => $this->domain,
			] );

			$numberOfPages = 0;
			$listOfOptIns  = OptIn::get_list_by_category_id( $Category->get_id(), $atts, $numberOfPages );

			$this->get_logger()->info( 'Found ' . count($listOfOptIns) . ' OptIns across ' . $numberOfPages . ' pages for this category.', [
				'plugin' => $this->domain,
			] );

			/*
			 * Render the Template
			 */
			?>
            <h1><?php _e( 'Category: ', 'double-opt-in' ) . esc_html( $Category->get_name() ); ?></h1>
			<?php
			$this->get_logger()->info( 'Rendering the "list-optins" template.', [
				'plugin' => $this->domain,
			] );

			$this->TemplateHandler->renderTemplate( 'list-optins', array(
				'domain'        => $this->domain,
				'listOfOptIns'  => $listOfOptIns,
				'numberOfPages' => $numberOfPages,
				'currentPage'   => $atts['page'],
				'slug'          => $slug
			) );

			$this->get_logger()->notice( 'Content rendering for "Category View" completed successfully.', [
				'plugin' => $this->domain,
			] );
		}

		protected function theSidebar( $slug, $page ) {
			$this->get_logger()->info( 'Rendering the sidebar content for the "Category View" UI page.', [
				'plugin'    => $this->domain,
				'page_slug' => $page,
			] );

			/*
			 * Ensure that the ID for the Categorie is set
			 */
			if ( ! isset( $_GET['id'] ) ) {
				$this->get_logger()->error( 'Category ID not provided in URL for sidebar rendering. Redirecting.', [
					'plugin' => $this->domain,
				] );
				wp_redirect( admin_url( 'admin.php' ) . '?page=f12-cf7-doubleoptin' );
				return;
			}

			$id = (int) $_GET['id'];
			$this->get_logger()->debug( 'Category ID from URL for sidebar: ' . $id, [
				'plugin' => $this->domain,
			] );

			/*
			 * Load the assigned Category
			 */
			$Category = Category::get_by_id( $id );

			if ( null === $Category ) {
				$this->get_logger()->error( 'Category with ID ' . $id . ' not found for sidebar. Redirecting.', [
					'plugin' => $this->domain,
				] );
				wp_redirect( admin_url( 'admin.php' ) . '?page=f12-cf7-doubleoptin' );
				return;
			}

			$this->get_logger()->notice( 'Category object loaded for sidebar rendering.', [
				'plugin'      => $this->domain,
				'category_id' => $id,
			] );
			?>
            <div class="box">
                <h2>
					<?php _e( 'Edit Category:', 'double-opt-in' ); ?>
                </h2>

                <form action="" method="post">
                    <div class="option">
                        <input type="hidden" value="<?php esc_attr_e( $_GET['id'] ); ?>" name="category_id"/>
                        <div class="input">
                            <input type="text" name="categorie_name"
                                   value="<?php esc_attr_e( $Category->get_name() ); ?>"
                                   placeholder="<?php _e( 'Please enter the Name of the Categorie', 'double-opt-in' ); ?>"/>
                            <p>
								<?php _e( 'Enter the name of the Categorie and submit.', 'double-opt-in' ); ?>
                            </p>
                        </div>
                    </div>
					<?php
					$this->get_logger()->debug( 'Rendering nonce field and submit button for category edit form.', [
						'plugin' => $this->domain,
					] );
					wp_nonce_field( 'doi_settings_action', 'doi_settings_nonce' );
					?>

                    <input type="submit" name="doubleoptin-settings-edit-submit" class="button button-primary"
                           value=" <?php _e( 'Update', 'double-opt-in' ); ?>"/>
                </form>
            </div>
			<?php
			$this->get_logger()->notice( 'Sidebar content for "Category View" page rendered successfully.', [
				'plugin' => $this->domain,
			] );
		}

		public function getSettings( $settings ) {
			$this->get_logger()->info( 'getSettings method called. Returning the provided settings array without modification.', [
				'plugin' => $this->domain,
				'page_slug' => $this->slug,
				'settings_keys' => is_array($settings) ? array_keys($settings) : 'not an array',
			] );

			// This method is intentionally designed to be a pass-through
			// It's part of a filter chain and simply returns the value it receives.
			// Future subclasses can override this to inject or modify settings.
			return $settings;
		}
	}
}