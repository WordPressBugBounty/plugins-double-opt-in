<?php

namespace forge12\contactform7\CF7DoubleOptIn {

	use Forge12\Shared\LoggerInterface;

	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	/**
	 * Class UICategories
	 *
	 * @package forge12\contactform7\CF7DoubleOptIn
	 */
	class UICategories extends UIPageForm {
		/**
		 * Constructor for the class.
		 *
		 * @param TemplateHandler $templateHandler The template handler object.
		 * @param string          $domain          The domain.
		 *
		 * @return void
		 */
		public function __construct(LoggerInterface $logger, TemplateHandler $templateHandler, string $domain ) {
			parent::__construct($logger, $templateHandler, $domain, 'categories', __( 'Categories', 'double-opt-in' ) );

			$this->hideSubmitButton( true );
			$this->get_logger()->debug( 'Submit button is hidden for this page.', [
				'plugin' => $domain,
			] );

			// Check for status messages in the URL
			if ( isset( $_GET['status'] ) ) {
				$status = sanitize_text_field( $_GET['status'] );

				$this->get_logger()->info( 'URL status parameter found: ' . $status, [
					'plugin' => $domain,
				] );

				switch ( $status ) {
					case 'category-deleted':
						Messages::getInstance()->add( __( 'Category deleted', 'double-opt-in' ), 'success' );
						$this->get_logger()->notice( 'Added "Category deleted" success message.', [
							'plugin' => $domain,
						] );
						break;
					case 'category-not-deleted':
						Messages::getInstance()->add( __( 'Category not-deleted', 'double-opt-in' ), 'error' );
						$this->get_logger()->error( 'Added "Category not-deleted" error message.', [
							'plugin' => $domain,
						] );
						break;
					default:
						$this->get_logger()->warning( 'Unknown status parameter value: ' . $status, [
							'plugin' => $domain,
						] );
						break;
				}
			}
		}

		/**
		 * Check if the category should be deleted
		 */
		private function maybeDelete() {
			$this->get_logger()->info( 'Attempting to delete a category based on URL parameters.', [
				'plugin' => $this->domain,
			] );

			// Check for required parameters
			if ( ! isset( $_GET['id'], $_GET['nonce'], $_GET['option'] ) ) {
				$this->get_logger()->warning( 'Missing required URL parameters (id, nonce, or option). Aborting delete attempt.', [
					'plugin' => $this->domain,
				] );
				return;
			}

			$option = sanitize_text_field( $_GET['option'] );
			$id     = (int) $_GET['id'];
			$nonce  = sanitize_text_field( $_GET['nonce'] );

			$this->get_logger()->debug( 'Parameters received: ' . json_encode( ['option' => $option, 'id' => $id] ), [
				'plugin' => $this->domain,
			] );

			if ( 'delete' !== $option ) {
				$this->get_logger()->warning( 'Option parameter is not "delete". Aborting.', [
					'plugin' => $this->domain,
					'option_value' => $option,
				] );
				return;
			}

			$page = (int) ($_GET['pageNum'] ?? 1); // Use a default page number if not set

			// Nonce verification
			if ( ! wp_verify_nonce( $nonce, 'doi-delete-category-' . $id ) ) {
				$this->get_logger()->error( 'Nonce verification failed for category deletion.', [
					'plugin' => $this->domain,
					'category_id' => $id,
					'provided_nonce' => $nonce,
				] );
				wp_redirect( admin_url( 'admin.php' ) . '?page=f12-cf7-doubleoptin_categories&pageNum=' . esc_attr( $page ) . '&status=category-not-deleted' );
				wp_die();
			}

			$this->get_logger()->notice( 'Nonce verification successful. Attempting to delete category with ID: ' . $id, [
				'plugin' => $this->domain,
				'category_id' => $id,
			] );

			// Attempt to delete the category
			if ( Category::delete_by_id( $id ) ) {
				$this->get_logger()->info( 'Category deleted successfully. Redirecting.', [
					'plugin' => $this->domain,
					'category_id' => $id,
				] );
				wp_redirect( admin_url( 'admin.php' ) . '?page=f12-cf7-doubleoptin_categories&pageNum=' . esc_attr( $page ) . '&status=category-deleted' );
			} else {
				$this->get_logger()->error( 'Failed to delete category.', [
					'plugin' => $this->domain,
					'category_id' => $id,
				] );
				wp_redirect( admin_url( 'admin.php' ) . '?page=f12-cf7-doubleoptin_categories&pageNum=' . esc_attr( $page ) . '&status=category-not-deleted' );
			}

			// Terminate script execution after redirect
			wp_die();
		}

		/**
		 * Render the license subpage content
		 */
		protected function theContent( $slug, $page, $settings ) {
			$this->get_logger()->info( 'Rendering the main content for the "Categories" UI page.', [
				'plugin' => $this->domain,
				'page_slug' => $page,
			] );

			// Check for and handle a category deletion request
			$this->maybeDelete();

			?>
            <h2>
				<?php _e( 'Categories', 'double-opt-in' ); ?>
            </h2>

			<?php
			$atts = array(
				'perPage' => 10,
				'page'    => 1,
				'order'   => 'DESC'
			);

			// Get the current page number from the URL
			if ( isset( $_GET['pageNum'] ) ) {
				$atts['page'] = (int) $_GET['pageNum'];
				$this->get_logger()->debug( 'Page number set from URL: ' . $atts['page'], [
					'plugin' => $this->domain,
				] );
			}

			$numberOfPages    = 0;
			$this->get_logger()->info( 'Fetching a list of categories from the database.', [
				'plugin' => $this->domain,
				'query_atts' => $atts,
			] );
			$listOfCategories = Category::get_list( $atts, $numberOfPages );

			$this->get_logger()->notice( 'Found ' . count($listOfCategories) . ' categories across ' . $numberOfPages . ' pages.', [
				'plugin' => $this->domain,
			] );
			?>

            <table>
                <tr>
                    <th>
						<?php _e( 'ID', 'double-opt-in' ); ?>
                    </th>
                    <th>
						<?php _e( 'Name', 'double-opt-in' ); ?>
                    </th>
                    <th>
						<?php _e( 'Registration Date', 'double-opt-in' ); ?>
                    </th>
                    <th>
						<?php _e( 'Update Date', 'double-opt-in' ); ?>
                    </th>
                    <th></th>
                </tr>
				<?php foreach ( $listOfCategories as $Category /** @var Category $Category */ ) : ?>
					<?php
					$this->get_logger()->debug( 'Rendering row for category ID: ' . $Category->get_id(), [
						'plugin' => $this->domain,
					] );
					?>
                    <tr>
                        <td>
                            <div>
								<?php echo esc_html( $Category->get_id() ); ?>
                            </div>
                        </td>
                        <td>
                            <a href="<?php echo admin_url( 'admin.php' ); ?>?page=f12-cf7-doubleoptin_categories_view&id=<?php esc_attr_e( $Category->get_id() ); ?>"
                               title="<?php _e( 'Show details', 'double-opt-in' ); ?>"><?php echo esc_attr( $Category->get_name() ); ?></a>
                        </td>
                        <td>
							<?php
							echo esc_html( $Category->get_createtime( 'formatted' ) );
							?>
                        </td>
                        <td>
							<?php
							echo esc_html( $Category->get_updatetime( 'formatted' ) );
							?>
                        </td>
                        <td>
                            <a href="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>?page=<?php esc_attr_e( $slug . '_' . $page ); ?>&pageNum=<?php esc_attr_e( $atts['page'] ); ?>&option=delete&id=<?php esc_attr_e( $Category->get_id() ); ?>&nonce=<?php echo wp_create_nonce( 'doi-delete-category-' . $Category->get_id() ); ?>">
								<?php _e( 'Delete', 'double-opt-in' ); ?>
                            </a>
                        </td>
                    </tr>
				<?php endforeach; ?>
            </table>

            <div class="tablenav-pages">
				<?php
				$this->get_logger()->info( 'Rendering pagination links.', [
					'plugin' => $this->domain,
					'number_of_pages' => $numberOfPages,
					'current_page' => $atts['page'],
				] );
				?>
				<?php for ( $i = 1; $i <= $numberOfPages; $i ++ ): ?>
                    <a href="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>?page=<?php echo esc_attr( $slug . '_' . $page ); ?>&pageNum=<?php echo esc_attr( $i ); ?>"
                       class="button <?php echo ( $atts['page'] ) == $i ? 'active' : ''; ?>">
						<?php echo esc_html( $i ); ?>
                    </a>
				<?php endfor; ?>
            </div>
			<?php
			$this->get_logger()->notice( 'Finished rendering the "Categories" page content.', [
				'plugin' => $this->domain,
			] );
		}

		protected function theSidebar( $slug, $page ) {
			$this->get_logger()->info( 'Rendering the sidebar content for the "Categories" UI page.', [
				'plugin'    => $this->domain,
				'page_slug' => $page,
			] );
			?>
            <div class="box">
                <h2>
					<?php _e( 'New Category:', 'double-opt-in' ); ?>
                </h2>

                <form action="" method="post">
                    <div class="option">
                        <div class="input">
                            <input type="text" name="categorie_name" value=""
                                   placeholder="<?php _e( 'Please enter the Name of the Categorie', 'double-opt-in' ); ?>"/>
                            <p>
								<?php _e( 'Enter the name of the Categorie and submit.', 'double-opt-in' ); ?>
                            </p>
                        </div>
                    </div>
					<?php
					$this->get_logger()->debug( 'Rendering nonce field for category creation form.', [
						'plugin' => $this->domain,
					] );
					wp_nonce_field( 'doi_settings_action', 'doi_settings_nonce' );
					?>

                    <input type="submit" name="doubleoptin-settings-submit" class="button button-primary"
                           value=" <?php _e( 'Add', 'double-opt-in' ); ?>"/>
                </form>
            </div>
			<?php
			$this->get_logger()->notice( 'Sidebar content for "Categories" page rendered successfully.', [
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

		/**
		 * Create a new Category
		 *
		 * @return void
		 */
		private function maybeAddCategory() {
			$this->get_logger()->info( 'Attempting to add a new category.', [
				'plugin' => $this->domain,
			] );

			$name = sanitize_text_field( $_POST['categorie_name'] ?? '' );

			// Validate the category name
			if ( empty( $name ) ) {
				Messages::getInstance()->add( __( 'Please enter a valid name for the categorie.', 'double-opt-in' ), 'error' );
				$this->get_logger()->warning( 'Category name is empty. Cannot add category.', [
					'plugin' => $this->domain,
				] );
				return;
			}

			$this->get_logger()->debug( 'Category name sanitized: ' . $name, [
				'plugin' => $this->domain,
			] );

			$Category = new Category($this->get_logger());
			$Category->set_name( $name );

			// Attempt to save the new category
			if ( $Category->save() ) {
				Messages::getInstance()->add( __( 'New Category added', 'double-opt-in' ), 'success' );
				$this->get_logger()->notice( 'New category successfully added to the database.', [
					'plugin' => $this->domain,
					'category_name' => $name,
					'category_id' => $Category->get_id(),
				] );
			} else {
				Messages::getInstance()->add( __( 'Something went wrong', 'double-opt-in' ), 'error' );
				$this->get_logger()->error( 'Failed to save the new category to the database.', [
					'plugin' => $this->domain,
					'category_name' => $name,
				] );
			}
		}

		protected function onSave( $settings ) {
			$this->get_logger()->info( 'Executing onSave method for the "Categories" UI page.', [
				'plugin' => $this->domain,
			] );

			// Call the method to handle adding a new category, if applicable.
			// This function will handle its own validation and messaging.
			$this->maybeAddCategory();

			$this->get_logger()->notice( 'onSave method completed. Returning the original settings array.', [
				'plugin' => $this->domain,
			] );

			// This method does not modify the $settings array. It's used for
			// handling form submissions that create new data rather than updating settings.
			return $settings;
		}
	}
}