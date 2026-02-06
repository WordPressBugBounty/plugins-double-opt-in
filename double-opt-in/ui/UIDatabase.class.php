<?php

namespace forge12\contactform7\CF7DoubleOptIn {

	use Forge12\Shared\LoggerInterface;

	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	/**
	 * Class UIDatabase
	 *
	 * @package forge12\contactform7\CF7DoubleOptIn
	 */
	class UIDatabase extends UIPageForm {
		/**
		 * Constructor method for the class.
		 *
		 * @param TemplateHandler $templateHandler The template handler object to be used.
		 * @param string          $domain          The domain parameter for the constructor.
		 *
		 * @return void
		 */
		public function __construct(LoggerInterface $logger, TemplateHandler $templateHandler, string $domain ) {

			parent::__construct($logger, $templateHandler, $domain, 'database', __( 'Database', 'double-opt-in' ), 50 );
			$this->get_logger()->debug( 'Parent constructor called with slug "database" and position 50.', [
				'plugin' => $domain,
			] );

			$this->hideSubmitButton( true );
			$this->get_logger()->debug( 'Submit button is hidden for this page.', [
				'plugin' => $domain,
			] );

			// Suppress "Settings updated" message on database page (we show specific messages instead)
			add_filter( 'f12_cf7_doubleoptin_show_settings_updated_message', [ $this, 'suppressSettingsMessage' ], 10, 2 );
		}

		/**
		 * Suppress the generic "Settings updated" message on the database page.
		 *
		 * @param bool   $show Whether to show the message.
		 * @param string $slug The page slug.
		 *
		 * @return bool
		 */
		public function suppressSettingsMessage( $show, $slug ) {
			if ( $slug === 'database' ) {
				return false;
			}
			return $show;
		}

		/**
		 * Save on form submit
		 */
		private function maybeCleanDatabase() {
			$this->get_logger()->info('Attempting to clean the database based on form submission.', [
				'plugin' => $this->domain,
			]);

			// Check if any of the cleaning form buttons have been submitted
			if ( ( isset( $_POST['doi-clean-submit'] ) || isset( $_POST['doi-clean-confirmed-submit'] ) || isset( $_POST['doi-clean-unconfirmed-submit'] ) ) ) {
				if ( ! isset( $_POST['doi_settings_nonce'] )
				     || ! wp_verify_nonce( wp_unslash( $_POST['doi_settings_nonce'] ), 'doi_settings_action' )
				     || ! current_user_can( 'manage_options' )
				) {
					wp_die(
						__( 'Security check failed. Please try again.', 'double-opt-in' ),
						__( 'Security Error', 'double-opt-in' ),
						[ 'response' => 403 ]
					);
				}

				$this->get_logger()->notice('Database cleaning request detected.', [
					'plugin' => $this->domain,
				]);

				$CleanUp = new CleanUp($this->get_logger());
				$cleaned_something = false;

				if ( isset( $_POST['doi-clean-submit'] ) || isset( $_POST['doi-clean-confirmed-submit'] ) ) {
					$this->get_logger()->info('Initiating removal of confirmed OptIns.', [
						'plugin' => $this->domain,
					]);
					$CleanUp->removeConfirmedOptins( true );
					$cleaned_something = true;
				}

				if ( isset( $_POST['doi-clean-submit'] ) || isset( $_POST['doi-clean-unconfirmed-submit'] ) ) {
					$this->get_logger()->info('Initiating removal of unconfirmed OptIns.', [
						'plugin' => $this->domain,
					]);
					$CleanUp->removeUnconfirmedOptins( true );
					$cleaned_something = true;
				}

				if ($cleaned_something) {
					Messages::getInstance()->add( __( 'Database cleaned.', 'double-opt-in' ), 'success' );
					$this->get_logger()->notice('Database cleaning operation completed and success message added.', [
						'plugin' => $this->domain,
					]);
				} else {
					$this->get_logger()->warning('Form submitted but no cleaning action was triggered. This might indicate an unexpected state.', [
						'plugin' => $this->domain,
					]);
				}
			} else {
				$this->get_logger()->debug('No database cleaning form submission detected.', [
					'plugin' => $this->domain,
				]);
			}
		}

		/**
		 * Reset the database deleting all entries.
		 */
		private function maybeResetDatabase() {
			$this->get_logger()->info('Attempting to reset the database based on form submission.', [
				'plugin' => $this->domain,
			]);

			// Check if the reset form button has been submitted
			if ( isset( $_POST['doi-reset-submit'] ) ) {
				if ( ! isset( $_POST['doi_settings_nonce'] )
				     || ! wp_verify_nonce( wp_unslash( $_POST['doi_settings_nonce'] ), 'doi_settings_action' )
				     || ! current_user_can( 'manage_options' )
				) {
					wp_die(
						__( 'Security check failed. Please try again.', 'double-opt-in' ),
						__( 'Security Error', 'double-opt-in' ),
						[ 'response' => 403 ]
					);
				}

				$this->get_logger()->notice('Database reset request detected.', [
					'plugin' => $this->domain,
				]);

				$CleanUp = new CleanUp($this->get_logger());
				$CleanUp->reset();

				Messages::getInstance()->add( __( 'Database reset finished.', 'double-opt-in' ), 'success' );
				$this->get_logger()->notice('Database reset operation completed and success message added.', [
					'plugin' => $this->domain,
				]);
			} else {
				$this->get_logger()->debug('No database reset form submission detected.', [
					'plugin' => $this->domain,
				]);
			}
		}

		/**
		 * Render the license subpage content
		 */
		protected function theContent( $slug, $page, $settings ) {
			$this->get_logger()->info('Rendering the main content for the "Database" UI page.', [
				'plugin'    => $this->domain,
				'page_slug' => $page,
			]);

			// Note: Database cleaning and reset are handled in onSave() to avoid duplicate processing

			echo wp_kses_post( Messages::getInstance()->getAll() );
			?>
            <form action="" method="post">
                <h2>
					<?php _e( 'Database', 'double-opt-in' ); ?>
                </h2>
				<?php
				$this->get_logger()->debug('Rendering nonce field for database actions.', [
					'plugin' => $this->domain,
				]);
				wp_nonce_field( 'doi_settings_action', 'doi_settings_nonce' );
				?>
                <div class="option">
                    <div class="label">
                        <label for="delete"><?php _e( 'Clean Database', 'double-opt-in' ); ?></label>
                    </div>
                    <div class="input">
                        <input type="submit" name="doi-clean-submit" class="button"
                               value="<?php _e( 'Clean All', 'double-opt-in' ); ?>"/>
                        <input type="submit" name="doi-clean-confirmed-submit" class="button"
                               value="<?php _e( 'Clean Confirmed', 'double-opt-in' ); ?>"/>
                        <input type="submit" name="doi-clean-unconfirmed-submit" class="button"
                               value="<?php _e( 'Clean Unconfirmed', 'double-opt-in' ); ?>"/>
                        <p>
							<?php _e( 'Make sure you backup your database before clicking one of these buttons.', 'double-opt-in' ); ?>
                        </p>
                    </div>
                </div>
                <div class="option">
                    <div class="label">
                        <label for="delete"><?php _e( 'Reset Database', 'double-opt-in' ); ?></label>
                    </div>
                    <div class="input">
                        <input type="submit" name="doi-reset-submit" class="button button-delete"
                               value="<?php _e( 'Reset', 'double-opt-in' ); ?>"/>
                        <p>
							<?php _e( 'Make sure you backup your database before clicking one of these buttons. If you click this button, all Opt-Ins will be deleted.', 'double-opt-in' ); ?>
                        </p>
                    </div>
                </div>
            </form>

			<?php if ( apply_filters( 'f12_doi_is_pro_active', false ) ) : ?>
            <hr>
            <h2><?php _e( 'Export Consent Records (GDPR)', 'double-opt-in' ); ?></h2>
            <p><?php _e( 'Export consent records for GDPR compliance documentation.', 'double-opt-in' ); ?></p>
            <form action="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" method="post" target="_blank">
                <input type="hidden" name="action" value="doi_export_consent">
				<?php wp_nonce_field( 'doi_consent_export' ); ?>

                <div class="option">
                    <div class="label">
                        <label for="doi-export-format"><?php _e( 'Format', 'double-opt-in' ); ?></label>
                    </div>
                    <div class="input">
                        <select name="format" id="doi-export-format">
                            <option value="csv">CSV</option>
                            <option value="json">JSON</option>
                        </select>
                    </div>
                </div>

                <div class="option">
                    <div class="label">
                        <label for="doi-export-scope"><?php _e( 'Scope', 'double-opt-in' ); ?></label>
                    </div>
                    <div class="input">
                        <select name="scope" id="doi-export-scope">
                            <option value="all"><?php _e( 'All Records', 'double-opt-in' ); ?></option>
                            <option value="email"><?php _e( 'By Email', 'double-opt-in' ); ?></option>
                        </select>
                    </div>
                </div>

                <div class="option" id="doi-export-email-field" style="display:none;">
                    <div class="label">
                        <label for="doi-export-email"><?php _e( 'Email', 'double-opt-in' ); ?></label>
                    </div>
                    <div class="input">
                        <input type="email" name="email" id="doi-export-email" class="regular-text" placeholder="user@example.com">
                    </div>
                </div>

                <div class="option">
                    <div class="label"></div>
                    <div class="input">
                        <input type="submit" class="button button-primary" value="<?php esc_attr_e( 'Export', 'double-opt-in' ); ?>">
                    </div>
                </div>
            </form>
            <script>
                (function() {
                    var scopeSelect = document.getElementById('doi-export-scope');
                    var emailField = document.getElementById('doi-export-email-field');
                    if (scopeSelect && emailField) {
                        scopeSelect.addEventListener('change', function() {
                            emailField.style.display = this.value === 'email' ? '' : 'none';
                        });
                    }
                })();
            </script>
			<?php endif; ?>
			<?php
			$this->get_logger()->notice('Database management page content rendered successfully.', [
				'plugin' => $this->domain,
			]);
		}

		protected function theSidebar( $slug, $page ) {
			$this->get_logger()->info('Attempting to render the sidebar for the "settings" page.', [
				'plugin'    => $this->domain,
				'page_slug' => $page,
			]);

			// Check if the current page is the 'settings' page, and return if it's not.
			if ( $page != 'settings' ) {
				$this->get_logger()->debug('Skipping sidebar rendering because the page is not "settings".', [
					'plugin'    => $this->domain,
					'page_slug' => $page,
				]);
				return;
			}

			$this->get_logger()->notice('Rendering sidebar content for the "settings" page.', [
				'plugin' => $this->domain,
			]);
			?>
            <div class="box">
                <h2>
					<?php _e( 'Hint:', 'double-opt-in' ); ?>
                </h2>
                <p>
					<?php _e( "In the table on the left side, you'll find an list containing all Opt-Ins and the required information from your customers. Click on the hash to open aditional informations about the form and the user data. All unconfirmed opt-ins will be deleted after 7 days.", 'double-opt-in' ); ?>
                </p>
            </div>
			<?php
			$this->get_logger()->notice('Sidebar for the "settings" page rendered successfully.', [
				'plugin' => $this->domain,
			]);
		}

		public function getSettings( $settings ) {
			return $settings;
		}

		protected function onSave( $settings ) {
			$this->get_logger()->info( 'Executing onSave method for the "Database" UI page.', [
				'plugin' => $this->domain,
			] );

			// Check for and handle database cleaning requests from the form.
			// The maybeCleanDatabase() method contains the logic for processing the 'doi-clean-submit',
			// 'doi-clean-confirmed-submit', and 'doi-clean-unconfirmed-submit' POST parameters.
			$this->maybeCleanDatabase();

			// Check for and handle database reset requests from the form.
			// The maybeResetDatabase() method processes the 'doi-reset-submit' POST parameter.
			$this->maybeResetDatabase();

			$this->get_logger()->notice( 'onSave method completed. Returning the original settings array.', [
				'plugin' => $this->domain,
			] );

			// This method is designed to perform actions (cleaning/resetting the database)
			// rather than modifying the settings array itself. Therefore, it returns the
			// settings array unchanged.
			return $settings;
		}
	}
}