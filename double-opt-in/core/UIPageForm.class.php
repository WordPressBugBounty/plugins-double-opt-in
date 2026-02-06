<?php

namespace forge12\contactform7\CF7DoubleOptIn {
	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	abstract class UIPageForm extends UIPage {
		/**
		 * define if the button for the submit should be displayed or not.
		 * if hidden, the wp_nonce will also be removed. Ensure you handle
		 * the save process on your own. The onSave function will still be called
		 *
		 * @var bool
		 */
		private $hide_submit_button = false;

		/**
		 * @return mixed
		 */
		protected function maybeSave() {
			$this->get_logger()->info( 'Attempting to save settings.', [
				'plugin' => $this->domain,
				'page_slug' => $this->slug,
			] );

			// Check for the nonce and verify it for security
			if ( isset( $_POST['doi_settings_nonce'] ) && wp_verify_nonce( wp_unslash( $_POST['doi_settings_nonce'] ), 'doi_settings_action' ) ) {
				$this->get_logger()->notice( 'Nonce verification successful. Proceeding with save.', [
					'plugin' => $this->domain,
				] );

				$settings = CF7DoubleOptIn::getInstance()->getSettings();
				$this->get_logger()->debug( 'Current settings retrieved.', [
					'plugin' => $this->domain,
					'settings' => $settings,
				] );

				// Apply a filter before the save operation
				$this->get_logger()->info( 'Applying "f12_cf7_doubleoptin_ui_' . $this->slug . '_before_on_save" filter.', [
					'plugin' => $this->domain,
				] );
				$settings = apply_filters( 'f12_cf7_doubleoptin_ui_' . $this->slug . '_before_on_save', $settings );

				// Call the main save method
				$this->get_logger()->info( 'Calling the onSave method to process and update settings.', [
					'plugin' => $this->domain,
				] );
				$settings = $this->onSave( $settings );

				// Apply a filter after the save operation
				$this->get_logger()->info( 'Applying "f12_cf7_doubleoptin_ui_' . $this->slug . '_after_on_save" filter.', [
					'plugin' => $this->domain,
				] );
				$settings = apply_filters( 'f12_cf7_doubleoptin_ui_' . $this->slug . '_after_on_save', $settings );

				// Save the settings to the database
				$this->get_logger()->notice( 'Calling the save method to persist the changes.', [
					'plugin' => $this->domain,
				] );
				$this->save( $settings );

				// Add a success message (can be suppressed by filter for specific pages)
				if ( apply_filters( 'f12_cf7_doubleoptin_show_settings_updated_message', true, $this->slug ) ) {
					Messages::getInstance()->add( __( 'Settings updated', 'double-opt-in' ), 'success' );
					$this->get_logger()->info( 'Success message added to the queue.', [
						'plugin' => $this->domain,
					] );
				}

			} else {
				$this->get_logger()->error( 'Nonce verification failed. Settings not saved.', [
					'plugin' => $this->domain,
					'post_data_present' => isset($_POST['doi_settings_nonce']),
					'nonce_value' => $_POST['doi_settings_nonce'] ?? 'not set',
				] );
			}
		}

		/**
		 * Save the settings.
		 *
		 * @param array $settings The settings to be saved.
		 *
		 * @return void
		 * @private WordPress HOOK
		 */
		protected function save( array $settings ) {
			$this->get_logger()->info( 'Saving settings to the database.', [
				'plugin' => $this->domain,
				'page_slug' => $this->slug,
			] );

			// Update the 'f12-doi-settings' option with the new settings array
			update_option( 'f12-doi-settings', $settings );
			$this->get_logger()->notice( 'Settings successfully updated in the database.', [
				'plugin' => $this->domain,
				'option_name' => 'f12-doi-settings',
			] );

			// Trigger a custom action after the save operation is complete
			$this->get_logger()->info( 'Triggering "f12_cf7_doubleoptin_ui_' . $this->slug . '_after_save" action.', [
				'plugin' => $this->domain,
			] );
			do_action( 'f12_cf7_doubleoptin_ui_' . $this->slug . '_after_save', $settings );
		}

		/**
		 * Option to hide the submit button
		 *
		 * @param bool $hide
		 *
		 * @return void
		 */
		protected function hideSubmitButton( $hide ) {
			$this->hide_submit_button = $hide;
		}

		/**
		 * Returns true if the button should be hidden.
		 *
		 * @return bool
		 */
		protected function isSubmitButtonHidden() {
			return $this->hide_submit_button;
		}

		/**
		 * Update the settings and return them
		 *
		 * @param $settings
		 *
		 * @return array
		 */
		protected abstract function onSave( $settings );

		/**
		 * @return void
		 * @private WordPress HOOK
		 */
		public function renderContent( $slug, $page ) {
			$this->get_logger()->info( 'Attempting to render content for page: ' . $page, [
				'plugin' => $this->domain,
				'handler_slug' => $this->slug,
			] );

			if ( $this->slug != $page ) {
				$this->get_logger()->debug( 'Skipping content rendering because the provided page slug does not match the handler\'s slug.', [
					'plugin' => $this->domain,
					'provided_slug' => $page,
					'handler_slug' => $this->slug,
				] );
				return;
			}

			$this->maybeSave();
			$settings = CF7DoubleOptIn::getInstance()->getSettings();

			$messages_html = Messages::getInstance()->getAll();
			if (!empty($messages_html)) {
				$this->get_logger()->notice( 'Rendering message box with content.', [
					'plugin' => $this->domain,
				] );
			}
			echo wp_kses_post( $messages_html );
			?>
            <div class="box">
                <form action="" method="post">
					<?php
					$this->get_logger()->info( 'Triggering "f12_cf7_doubleoptin_ui_' . $page . '_before_content" action.', [
						'plugin' => $this->domain,
					] );
					do_action( 'f12_cf7_doubleoptin_ui_' . $page . '_before_content', $settings );

					$this->get_logger()->info( 'Calling theContent() method to render the page\'s main content.', [
						'plugin' => $this->domain,
					] );
					$this->theContent( $slug, $page, $settings );

					$this->get_logger()->info( 'Triggering "f12_cf7_doubleoptin_ui_' . $page . '_after_content" action.', [
						'plugin' => $this->domain,
					] );
					do_action( 'f12_cf7_doubleoptin_ui_' . $page . '_after_content', $settings );

					if ( ! $this->isSubmitButtonHidden() ):
						$this->get_logger()->debug( 'Submit button is not hidden. Rendering nonce field and submit button.', [
							'plugin' => $this->domain,
						] );
						wp_nonce_field( 'doi_settings_action', 'doi_settings_nonce' );
						?>
                        <input type="submit" name="doubleoptin-settings-submit" class="button button-primary"
                               value=" <?php _e( 'Save', 'double-opt-in' ); ?>"/>
					<?php
					else:
						$this->get_logger()->debug( 'Submit button is hidden. Not rendering.', [
							'plugin' => $this->domain,
						] );
					endif;
					?>
                </form>
            </div>
			<?php
			$this->get_logger()->notice( 'Content rendering for page ' . $page . ' completed successfully.', [
				'plugin' => $this->domain,
			] );
		}
	}
}