<?php

namespace forge12\contactform7\CF7DoubleOptIn {

	use Forge12\Shared\LoggerInterface;

	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	/**
	 * Class UIOptinView
	 * Show a specific OptIn
	 *
	 * @package forge12\contactform7\CF7DoubleOptIn
	 */
	class UIOptinView extends UIPage {
		/**
		 * Class constructor.
		 *
		 * @param TemplateHandler $templateHandler The template handler object.
		 * @param string          $domain          The domain of the class.
		 *
		 * @return void
		 */
		public function __construct(LoggerInterface $logger, TemplateHandler $templateHandler, string $domain ) {
			parent::__construct($logger, $templateHandler, $domain, 'optin_view', __( 'OptIn View', 'double-opt-in' ) );
			$this->get_logger()->debug( 'Parent constructor called with slug "optin_view".', [
				'plugin' => $domain,
			] );

			// Check for status messages in the URL and add corresponding messages.
			if ( isset( $_GET['status'] ) ) {
				$status = sanitize_text_field( $_GET['status'] );
				$this->get_logger()->info( 'URL status parameter found: ' . $status, [
					'plugin' => $domain,
				] );

				switch ( $status ) {
					case 'deleted':
						Messages::getInstance()->add( __( 'Opt-In deleted', 'double-opt-in' ), 'success' );
						$this->get_logger()->notice( 'Added "Opt-In deleted" success message.', [
							'plugin' => $domain,
						] );
						break;
					case 'not-deleted':
						Messages::getInstance()->add( __( 'Opt-In not-deleted', 'double-opt-in' ), 'error' );
						$this->get_logger()->error( 'Added "Opt-In not-deleted" error message.', [
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
			$this->get_logger()->info( 'Rendering the main content for the "OptIn View" UI page.', [
				'plugin'    => $this->domain,
				'page_slug' => $page,
			] );

			/*
			 * Ensure that the hash is given
			 */
			if ( ! isset( $_GET['hash'] ) ) {
				$this->get_logger()->error( 'OptIn hash not provided in URL. Redirecting to the dashboard.', [
					'plugin' => $this->domain,
				] );
				wp_redirect( admin_url( 'admin.php' ) . '?page=f12-cf7-doubleoptin' );
				return;
			}

			// Check if there is any POST data to save.
			$this->maybeSave();

			$hash = sanitize_text_field( $_GET['hash'] );
			$this->get_logger()->debug( 'OptIn hash from URL: ' . $hash, [
				'plugin' => $this->domain,
			] );

			/*
			 * Load the assigned Category
			 */
			$Optin = Optin::get_by_hash( $hash );

			if ( null === $Optin ) {
				$this->get_logger()->error( 'OptIn with hash ' . $hash . ' not found. Redirecting to the dashboard.', [
					'plugin' => $this->domain,
				] );
				wp_redirect( admin_url( 'admin.php' ) . '?page=f12-cf7-doubleoptin' );
				return;
			}

			$this->get_logger()->notice( 'OptIn object loaded successfully for hash: ' . $Optin->get_hash(), [
				'plugin' => $this->domain,
			] );

			/*
			 * Render the Template
			 */
			?>
            <h2><?php _e( 'Opt-In: ', 'double-opt-in' ) . esc_html( $Optin->get_hash() ); ?></h2>
			<?php
			$this->get_logger()->info( 'Rendering the "view-optin" template.', [
				'plugin' => $this->domain,
			] );

			$this->TemplateHandler->renderTemplate( 'view-optin', array(
				'domain' => $this->domain,
				'optin'  => $Optin
			) );

			$this->get_logger()->notice( 'Content rendering for "OptIn View" completed successfully.', [
				'plugin' => $this->domain,
			] );
		}

		/**
		 * Update the Optin if the category has been submitted.
		 *
		 * @return void
		 */
		private function maybeSave() {
			$this->get_logger()->info( 'Attempting to save OptIn data based on form submission.', [
				'plugin' => $this->domain,
			] );

			// Check for required POST data and nonce verification.
			if ( ! isset( $_POST['hash'] ) || ! wp_verify_nonce( wp_unslash( $_POST['doi_settings_nonce'] ), 'doi_settings_action' ) ) {
				$this->get_logger()->error( 'Missing hash or nonce verification failed. Aborting save.', [
					'plugin' => $this->domain,
				] );
				return;
			}

			$category = (int) $_POST['category'];
			$hash     = sanitize_text_field( $_POST['hash'] );

			$this->get_logger()->debug( 'Form data received: ' . json_encode(['category' => $category, 'hash' => $hash]), [
				'plugin' => $this->domain,
			] );

			// Validate the category ID.
			if ( ! $category ) {
				$this->get_logger()->warning( 'Category ID is not a valid number. Aborting save.', [
					'plugin' => $this->domain,
				] );
				return;
			}

			$Optin = OptIn::get_by_hash( $hash );

			if (null === $Optin) {
				$this->get_logger()->error('OptIn with hash ' . $hash . ' not found. Aborting save.', [
					'plugin' => $this->domain,
				]);
				return;
			}

			$this->get_logger()->notice( 'Updating OptIn with hash "' . $hash . '" to category ID "' . $category . '".', [
				'plugin' => $this->domain,
			] );

			$Optin->set_category( $category );

			if ($Optin->save()) {
				$this->get_logger()->info('OptIn successfully saved to the database.', [
					'plugin' => $this->domain,
				]);
				Messages::getInstance()->add(__( 'Opt-In updated successfully.', 'double-opt-in' ), 'success' );
			} else {
				$this->get_logger()->error('Failed to save OptIn to the database.', [
					'plugin' => $this->domain,
				]);
				Messages::getInstance()->add(__( 'Opt-In update failed.', 'double-opt-in' ), 'error' );
			}
		}

		protected function theSidebar( $slug, $page ) {
			$this->get_logger()->info( 'Rendering the sidebar content for the "OptIn View" UI page.', [
				'plugin'    => $this->domain,
				'page_slug' => $page,
			] );

			/*
			 * Ensure that the hash is set in the URL
			 */
			if ( ! isset( $_GET['hash'] ) ) {
				$this->get_logger()->error( 'OptIn hash not provided in URL for sidebar rendering. Redirecting.', [
					'plugin' => $this->domain,
				] );
				wp_redirect( admin_url( 'admin.php' ) . '?page=f12-cf7-doubleoptin' );
				return;
			}

			$hash = sanitize_text_field( $_GET['hash'] );
			$this->get_logger()->debug( 'OptIn hash from URL for sidebar: ' . $hash, [
				'plugin' => $this->domain,
			] );

			/*
			 * Load the OptIn object
			 */
			$Optin = OptIn::get_by_hash( $hash );

			if ( ! $Optin ) {
				$this->get_logger()->error( 'OptIn with hash ' . $hash . ' not found for sidebar. Redirecting.', [
					'plugin' => $this->domain,
				] );
				wp_redirect( admin_url( 'admin.php' ) . '?page=f12-cf7-doubleoptin' );
				return;
			}

			$this->get_logger()->notice( 'OptIn object loaded for sidebar rendering.', [
				'plugin' => $this->domain,
				'optin_id' => $Optin->get_id(),
			] );
			?>
            <div class="box">
                <h2>
					<?php _e( 'Edit Opt-In:', 'double-opt-in' ); ?>
                </h2>

                <form action="" method="post">
                    <div class="option">
                        <div class="label">
                            <label for="category"><?php _e( 'Category:', 'double-opt-in' ); ?></label>
                        </div>
                        <input type="hidden" value="<?php esc_attr_e( $Optin->get_hash() ); ?>" name="hash"/>
                        <div class="input">
							<?php
							$this->get_logger()->debug( 'Rendering category selection dropdown.', [
								'plugin' => $this->domain,
							] );
							// Using wp_kses for sanitization since the output contains a select tag and its options.
							echo wp_kses( CategoryOptions::getInstance()->select_category( $Optin->get_category() ), array(
								'select' => array( 'name' => array() ),
								'option' => array(
									'value'    => array(),
									'selected' => array()
								)
							) );
							?>
                            <p>
								<?php _e( 'Select the category to assign the Opt-In.', 'double-opt-in' ); ?>
                            </p>
                        </div>
                    </div>
					<?php
					$this->get_logger()->debug( 'Rendering nonce field and update button.', [
						'plugin' => $this->domain,
					] );
					wp_nonce_field( 'doi_settings_action', 'doi_settings_nonce' );
					?>

                    <input type="submit" name="doubleoptin-settings-edit-submit" class="button button-primary"
                           value=" <?php _e( 'Update', 'double-opt-in' ); ?>"/>
                </form>
            </div>

            <div class="box">
                <h2>
					<?php _e( 'Opt-In:', 'double-opt-in' ); ?>
                </h2>

                <div class="option">
                    <div class="label">
                        <label for="category"><?php _e( 'Link:', 'double-opt-in' ); ?></label>
                    </div>
                    <div class="input copy-to-clipboard">
						<?php
						echo esc_url( $Optin->get_link_optin() );
						?>
                    </div>
                </div>
            </div>
			<?php
			$this->get_logger()->info( 'Triggering "f12_cf7_doubleoptin_ui_view_optin_sidebar" action.', [
				'plugin' => $this->domain,
			] );
			/**
			 * Sidebar
			 *
			 * This Hook allows us to extend the sidebar for the pro version.
			 *
			 * @param OptIn $Optin
			 *
			 * @since 3.0.0
			 */
			do_action( 'f12_cf7_doubleoptin_ui_view_optin_sidebar', $Optin );

			$this->get_logger()->notice( 'Sidebar content for "OptIn View" page rendered successfully.', [
				'plugin' => $this->domain,
			] );
		}

		public function getSettings( $settings ) {
			return $settings;
		}
	}
}