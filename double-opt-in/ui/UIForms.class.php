<?php
/**
 * Forms Management UI Page
 *
 * @package forge12\contactform7\CF7DoubleOptIn
 * @since   4.1.0
 */

namespace forge12\contactform7\CF7DoubleOptIn {

	use Forge12\DoubleOptIn\Container\Container;
	use Forge12\DoubleOptIn\FormSettings\FormSettingsService;
	use Forge12\DoubleOptIn\Integration\FormIntegrationRegistry;
	use Forge12\Shared\LoggerInterface;

	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	/**
	 * Class UIForms
	 *
	 * Admin page for central form management.
	 * Displays all forms from all integrations with their DOI status.
	 */
	class UIForms extends UIPage {

		/**
		 * Form settings service.
		 *
		 * @var FormSettingsService|null
		 */
		private ?FormSettingsService $settingsService = null;

		/**
		 * Constructor.
		 *
		 * @param LoggerInterface $logger          The logger instance.
		 * @param TemplateHandler $templateHandler The template handler.
		 * @param string          $domain          The text domain.
		 */
		public function __construct( LoggerInterface $logger, TemplateHandler $templateHandler, string $domain ) {
			parent::__construct(
				$logger,
				$templateHandler,
				$domain,
				'forms',
				__( 'Forms', 'double-opt-in' ),
				5 // Position after Dashboard (0)
			);

			add_action( 'admin_enqueue_scripts', [ $this, 'enqueueAssets' ] );

			$this->get_logger()->debug( 'UIForms initialized', [
				'plugin' => $domain,
			] );
		}

		/**
		 * Get the form settings service.
		 *
		 * @return FormSettingsService
		 */
		private function getSettingsService(): FormSettingsService {
			if ( $this->settingsService === null ) {
				$container = Container::getInstance();
				$this->settingsService = $container->get( FormSettingsService::class );
			}
			return $this->settingsService;
		}

		/**
		 * Enqueue admin assets for the forms page.
		 *
		 * @param string $hook The current admin page hook.
		 *
		 * @return void
		 */
		public function enqueueAssets( string $hook ): void {
			// Only load on our page
			if ( strpos( $hook, 'f12-cf7-doubleoptin_forms' ) === false ) {
				return;
			}

			wp_enqueue_style(
				'doi-forms-management',
				plugins_url( 'assets/css/forms-management.css', dirname( __FILE__ ) ),
				[],
				FORGE12_OPTIN_VERSION
			);

			wp_enqueue_script(
				'doi-form-settings-panel',
				plugins_url( 'assets/js/form-settings-panel.js', dirname( __FILE__ ) ),
				[ 'jquery' ],
				FORGE12_OPTIN_VERSION,
				true
			);

			wp_localize_script( 'doi-form-settings-panel', 'doiFormSettings', [
				'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
				'nonce'      => wp_create_nonce( 'doi_form_settings' ),
				'formsUrl'   => admin_url( 'admin.php?page=f12-cf7-doubleoptin_forms' ),
				'editorUrl'  => admin_url( 'admin.php?page=f12-cf7-doubleoptin_f12-doi-email-editor' ),
				'isProActive' => apply_filters( 'f12_doi_is_pro_active', false ),
				'upgradeUrl' => apply_filters( 'f12_doi_upgrade_url', 'https://www.forge12.com/product/contact-form-7-double-opt-in/' ),
				'standardPlaceholders' => [
					'doi_email'      => __( 'E-Mail', 'double-opt-in' ),
					'doi_name'       => __( 'Name (Full)', 'double-opt-in' ),
					'doi_first_name' => __( 'First Name', 'double-opt-in' ),
					'doi_last_name'  => __( 'Last Name', 'double-opt-in' ),
					'doi_phone'      => __( 'Phone', 'double-opt-in' ),
					'doi_company'    => __( 'Company', 'double-opt-in' ),
					'doi_message'    => __( 'Message', 'double-opt-in' ),
					'doi_subject'    => __( 'Subject', 'double-opt-in' ),
					'doi_address'    => __( 'Address', 'double-opt-in' ),
					'doi_city'       => __( 'City', 'double-opt-in' ),
					'doi_zip'        => __( 'ZIP/Postal Code', 'double-opt-in' ),
					'doi_country'    => __( 'Country', 'double-opt-in' ),
				],
				'i18n'       => [
					'loading'        => __( 'Loading...', 'double-opt-in' ),
					'saving'         => __( 'Saving...', 'double-opt-in' ),
					'saved'          => __( 'Saved!', 'double-opt-in' ),
					'error'          => __( 'Error', 'double-opt-in' ),
					'confirm'        => __( 'Are you sure?', 'double-opt-in' ),
					'enabled'        => __( 'Enabled', 'double-opt-in' ),
					'disabled'       => __( 'Disabled', 'double-opt-in' ),
					'configure'      => __( 'Configure', 'double-opt-in' ),
					'close'          => __( 'Close', 'double-opt-in' ),
					'saveSettings'   => __( 'Save Settings', 'double-opt-in' ),
					'noForms'        => __( 'No forms found.', 'double-opt-in' ),
					'autoDetected'   => __( 'Auto-detected', 'double-opt-in' ),
					'notMapped'      => __( '-- Not mapped --', 'double-opt-in' ),
					'selectField'    => __( 'Select field...', 'double-opt-in' ),
				],
			] );

			$this->get_logger()->debug( 'Forms page assets enqueued', [
				'plugin' => $this->domain,
			] );
		}

		/**
		 * Get settings for this page.
		 *
		 * @param mixed $settings The settings array.
		 *
		 * @return mixed
		 */
		public function getSettings( $settings ) {
			return $settings;
		}

		/**
		 * Render the sidebar.
		 *
		 * @param string $slug The WordPress slug.
		 * @param string $page The current page.
		 *
		 * @return void
		 */
		protected function theSidebar( $slug, $page ): void {
			?>
			<div class="box">
				<h2><?php _e( 'Central Form Management', 'double-opt-in' ); ?></h2>
				<p>
					<?php _e( 'Configure Double Opt-In settings for all your forms in one place. Click on "Configure" to edit the settings for each form.', 'double-opt-in' ); ?>
				</p>
			</div>
			<div class="box">
				<h2><?php _e( 'Quick Actions', 'double-opt-in' ); ?></h2>
				<p>
					<?php _e( 'Use the toggle switch to quickly enable or disable Double Opt-In for a form.', 'double-opt-in' ); ?>
				</p>
			</div>
			<?php
		}

		/**
		 * Render the main content.
		 *
		 * @param string $slug     The WordPress slug.
		 * @param string $page     The current page.
		 * @param mixed  $settings The settings array.
		 *
		 * @return void
		 */
		protected function theContent( $slug, $page, $settings ): void {
			$allForms = $this->getSettingsService()->getAllForms();

			$this->get_logger()->debug( 'Rendering forms list', [
				'plugin'            => $this->domain,
				'integration_count' => count( $allForms ),
			] );

			// Count total forms
			$totalForms   = 0;
			$enabledForms = 0;
			foreach ( $allForms as $data ) {
				foreach ( $data['forms'] as $form ) {
					$totalForms++;
					if ( $form['enabled'] ) {
						$enabledForms++;
					}
				}
			}
			?>
			<div class="doi-forms-container">
				<div class="doi-forms-header">
					<h1><?php _e( 'Form Management', 'double-opt-in' ); ?></h1>
					<p><?php _e( 'Manage Double Opt-In settings for all your forms across different integrations.', 'double-opt-in' ); ?></p>
				</div>

				<?php if ( ! empty( $allForms ) ) : ?>
				<!-- Stats -->
				<div class="doi-forms-stats">
					<div class="doi-forms-stat">
						<div class="doi-forms-stat-icon total">
							<span class="dashicons dashicons-forms"></span>
						</div>
						<div class="doi-forms-stat-content">
							<strong><?php echo esc_html( number_format_i18n( $totalForms ) ); ?></strong>
							<span><?php _e( 'Total Forms', 'double-opt-in' ); ?></span>
						</div>
					</div>
					<div class="doi-forms-stat">
						<div class="doi-forms-stat-icon active">
							<span class="dashicons dashicons-yes-alt"></span>
						</div>
						<div class="doi-forms-stat-content">
							<strong><?php echo esc_html( number_format_i18n( $enabledForms ) ); ?></strong>
							<span><?php _e( 'DOI Active', 'double-opt-in' ); ?></span>
						</div>
					</div>
					<div class="doi-forms-stat">
						<div class="doi-forms-stat-icon inactive">
							<span class="dashicons dashicons-minus"></span>
						</div>
						<div class="doi-forms-stat-content">
							<strong><?php echo esc_html( number_format_i18n( $totalForms - $enabledForms ) ); ?></strong>
							<span><?php _e( 'DOI Inactive', 'double-opt-in' ); ?></span>
						</div>
					</div>
				</div>

				<?php foreach ( $allForms as $integration => $data ) : ?>
					<div class="doi-integration-section" data-integration="<?php echo esc_attr( $integration ); ?>">
						<div class="doi-integration-header">
							<h2>
								<span class="dashicons dashicons-feedback"></span>
								<?php echo esc_html( $data['name'] ); ?>
								<span class="doi-integration-count"><?php echo esc_html( count( $data['forms'] ) ); ?></span>
							</h2>
						</div>

						<?php if ( $integration === 'elementor' ) : ?>
							<div class="doi-elementor-info">
								<p>
									<strong><?php _e( 'Elementor Forms:', 'double-opt-in' ); ?></strong>
									<?php _e( 'To enable Double Opt-In, add the action in Elementor. Configuration is done here.', 'double-opt-in' ); ?>
								</p>
								<ol>
									<li><?php _e( 'Edit the page in Elementor → Select Form widget → "Actions After Submit"', 'double-opt-in' ); ?></li>
									<li><?php _e( 'Add "Forge12 Double Opt-In" action and save', 'double-opt-in' ); ?></li>
									<li><?php _e( 'Return here and click "Configure" to set up the email settings', 'double-opt-in' ); ?></li>
								</ol>
							</div>
						<?php endif; ?>

						<div class="doi-forms-table-wrapper">
							<table class="doi-forms-table">
								<thead>
									<tr>
										<th class="column-status"><?php _e( 'Status', 'double-opt-in' ); ?></th>
										<th class="column-title"><?php _e( 'Form', 'double-opt-in' ); ?></th>
										<th class="column-id"><?php _e( 'ID', 'double-opt-in' ); ?></th>
										<th class="column-actions"><?php _e( 'Actions', 'double-opt-in' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ( $data['forms'] as $form ) : ?>
										<tr class="doi-form-row" data-form-id="<?php echo esc_attr( $form['id'] ); ?>">
											<td class="column-status">
												<?php if ( $integration === 'elementor' ) : ?>
													<?php if ( $form['enabled'] ) : ?>
														<span class="doi-status-badge doi-status-enabled" title="<?php esc_attr_e( 'Action is added in Elementor', 'double-opt-in' ); ?>">
															<span class="dashicons dashicons-yes-alt"></span>
															<?php _e( 'Active', 'double-opt-in' ); ?>
														</span>
													<?php else : ?>
														<span class="doi-status-badge doi-status-disabled" title="<?php esc_attr_e( 'Add action in Elementor to enable', 'double-opt-in' ); ?>">
															<span class="dashicons dashicons-minus"></span>
															<?php _e( 'Not Active', 'double-opt-in' ); ?>
														</span>
													<?php endif; ?>
												<?php else : ?>
													<label class="doi-toggle">
														<input type="checkbox"
															class="doi-toggle-input"
															data-form-id="<?php echo esc_attr( $form['id'] ); ?>"
															aria-label="<?php echo esc_attr( sprintf( __( 'Toggle Double Opt-In for %s', 'double-opt-in' ), $form['title'] ) ); ?>"
															<?php checked( $form['enabled'] ); ?>>
														<span class="doi-toggle-slider"></span>
														<span class="screen-reader-text">
															<?php echo esc_html( sprintf( __( 'Toggle Double Opt-In for %s', 'double-opt-in' ), $form['title'] ) ); ?>
														</span>
													</label>
												<?php endif; ?>
											</td>
											<td class="column-title">
												<strong>
													<a href="<?php echo esc_url( $form['edit_url'] ); ?>" target="_blank">
														<?php echo esc_html( $form['title'] ); ?>
													</a>
												</strong>
											</td>
											<td class="column-id">
												<span class="doi-form-id"><?php echo esc_html( $form['id'] ); ?></span>
											</td>
											<td class="column-actions">
												<div class="doi-actions">
													<button type="button"
														class="button doi-configure-btn"
														data-form-id="<?php echo esc_attr( $form['id'] ); ?>"
														data-form-title="<?php echo esc_attr( $form['title'] ); ?>"
														data-integration="<?php echo esc_attr( $integration ); ?>"
														<?php echo ( $integration === 'elementor' && ! $form['enabled'] ) ? 'disabled title="' . esc_attr__( 'Add DOI action in Elementor first', 'double-opt-in' ) . '"' : ''; ?>>
														<span class="dashicons dashicons-admin-generic"></span>
														<?php _e( 'Configure', 'double-opt-in' ); ?>
													</button>
													<a href="<?php echo esc_url( $form['edit_url'] ); ?>"
														class="button"
														target="_blank">
														<span class="dashicons dashicons-edit"></span>
														<?php echo ( $integration === 'elementor' ) ? __( 'Edit in Elementor', 'double-opt-in' ) : __( 'Edit Form', 'double-opt-in' ); ?>
													</a>
												</div>
											</td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					</div>
					<?php endforeach; ?>
				<?php else : ?>
					<div class="doi-empty-state">
						<span class="dashicons dashicons-forms"></span>
						<h3><?php _e( 'No Forms Found', 'double-opt-in' ); ?></h3>
						<p><?php _e( 'Please install a supported form plugin (Contact Form 7, Avada Forms, Elementor Pro).', 'double-opt-in' ); ?></p>
					</div>
				<?php endif; ?>
			</div>

			<!-- Slide-out Settings Panel -->
			<div id="doi-settings-panel" class="doi-panel">
				<div class="doi-panel-overlay"></div>
				<div class="doi-panel-content">
					<div class="doi-panel-header">
						<h2 class="doi-panel-title"><?php _e( 'Configure Double Opt-In', 'double-opt-in' ); ?></h2>
						<button type="button" class="doi-panel-close" aria-label="<?php esc_attr_e( 'Close', 'double-opt-in' ); ?>">
							<span class="dashicons dashicons-no-alt"></span>
						</button>
					</div>
					<div class="doi-panel-body">
						<div class="doi-panel-loading">
							<span class="spinner is-active"></span>
							<p><?php _e( 'Loading settings...', 'double-opt-in' ); ?></p>
						</div>
						<form id="doi-settings-form" class="doi-settings-form" style="display: none;">
							<input type="hidden" name="form_id" id="doi-form-id" value="">

							<!-- Enable/Disable (toggle for CF7/Avada, badge for Elementor) -->
							<div class="doi-field" id="doi-enable-field">
								<label class="doi-field-label">
									<input type="checkbox" name="enabled" id="doi-enabled" value="1">
									<?php _e( 'Enable Double Opt-In', 'double-opt-in' ); ?>
									<span class="doi-tooltip">
										<span class="dashicons dashicons-editor-help"></span>
										<span class="doi-tooltip-text"><?php esc_html_e( 'When enabled, users receive a confirmation email with a link they must click to verify their submission.', 'double-opt-in' ); ?></span>
									</span>
								</label>
								<p class="description"><?php _e( 'Activate Double Opt-In for this form.', 'double-opt-in' ); ?></p>
							</div>

							<!-- Status Badge (for Elementor) -->
							<div class="doi-field" id="doi-status-field" style="display: none;">
								<label><?php _e( 'Status', 'double-opt-in' ); ?></label>
								<div id="doi-status-badge-container">
									<span class="doi-status-badge doi-status-enabled" id="doi-status-active" style="display: none;">
										<span class="dashicons dashicons-yes-alt"></span>
										<?php _e( 'Active', 'double-opt-in' ); ?>
									</span>
									<span class="doi-status-badge doi-status-disabled" id="doi-status-inactive" style="display: none;">
										<span class="dashicons dashicons-minus"></span>
										<?php _e( 'Not Active', 'double-opt-in' ); ?>
									</span>
								</div>
								<p class="description"><?php _e( 'Elementor forms are enabled/disabled via "Actions After Submit" in Elementor.', 'double-opt-in' ); ?></p>
							</div>

							<!-- Consent Text (GDPR) -->
							<div class="doi-field">
								<label for="doi-consent-text">
								<?php _e( 'Consent Text (GDPR)', 'double-opt-in' ); ?>
								<span class="doi-tooltip">
									<span class="dashicons dashicons-editor-help"></span>
									<span class="doi-tooltip-text"><?php esc_html_e( 'The text entered here is stored as a snapshot with each opt-in record. In case of a dispute, it proves what the user agreed to.', 'double-opt-in' ); ?></span>
								</span>
							</label>
								<textarea name="consentText" id="doi-consent-text" rows="4" class="large-text" placeholder="<?php esc_attr_e( 'e.g. I agree to receive the newsletter and accept the privacy policy...', 'double-opt-in' ); ?>"></textarea>
								<p class="description"><?php _e( 'The consent text is saved as a snapshot with each opt-in record for GDPR Art. 7 compliance. This proves what the user agreed to at the time of submission.', 'double-opt-in' ); ?></p>
							</div>

							<!-- Category -->
							<div class="doi-field">
								<label for="doi-category">
								<?php _e( 'Category', 'double-opt-in' ); ?>
								<span class="doi-tooltip">
									<span class="dashicons dashicons-editor-help"></span>
									<span class="doi-tooltip-text"><?php esc_html_e( 'Categories help you organize and filter opt-ins — e.g., by newsletter, event, or contact form.', 'double-opt-in' ); ?></span>
								</span>
							</label>
								<select name="category" id="doi-category" class="regular-text"></select>
								<p class="description"><?php _e( 'Assign opt-ins to a category for easier management.', 'double-opt-in' ); ?></p>
							</div>

							<!-- Conditions -->
							<div class="doi-field">
								<label for="doi-conditions">
								<?php _e( 'Dynamic Condition', 'double-opt-in' ); ?>
								<span class="doi-tooltip">
									<span class="dashicons dashicons-editor-help"></span>
									<span class="doi-tooltip-text"><?php esc_html_e( 'Select a form field. The opt-in is only triggered when this field has been filled in or checked by the visitor.', 'double-opt-in' ); ?></span>
								</span>
							</label>
								<select name="conditions" id="doi-conditions" class="regular-text">
									<option value="disabled"><?php _e( 'Disabled', 'double-opt-in' ); ?></option>
								</select>
								<p class="description"><?php _e( 'Only enable opt-in when this field is filled/checked.', 'double-opt-in' ); ?></p>
							</div>

							<!-- Confirmation Page -->
							<div class="doi-field">
								<label for="doi-confirmation-page">
								<?php _e( 'Confirmation Page', 'double-opt-in' ); ?>
								<span class="doi-tooltip">
									<span class="dashicons dashicons-editor-help"></span>
									<span class="doi-tooltip-text"><?php esc_html_e( 'This page is displayed after the user clicks the confirmation link in the email. Leave empty for the default page.', 'double-opt-in' ); ?></span>
								</span>
							</label>
								<select name="confirmationPage" id="doi-confirmation-page" class="regular-text"></select>
								<p class="description"><?php _e( 'Page shown after confirmation link is clicked.', 'double-opt-in' ); ?></p>
							</div>

							<!-- Error Redirect Page -->
							<div class="doi-field">
								<label for="doi-error-redirect-page">
								<?php _e( 'Error Redirect Page', 'double-opt-in' ); ?>
								<span class="doi-tooltip">
									<span class="dashicons dashicons-editor-help"></span>
									<span class="doi-tooltip-text"><?php esc_html_e( 'If set, users are redirected to this page when an OptIn error occurs (e.g. rate limit, invalid email). The error code is appended as a query parameter. Leave on default to show a toast notification instead.', 'double-opt-in' ); ?></span>
								</span>
							</label>
								<select name="errorRedirectPage" id="doi-error-redirect-page" class="regular-text"></select>
								<p class="description"><?php _e( 'Page to redirect to when an error occurs. Leave on default for toast notification.', 'double-opt-in' ); ?></p>
							</div>

							<hr>
							<h3><?php _e( 'Email Settings', 'double-opt-in' ); ?></h3>

							<!-- Recipient -->
							<div class="doi-field">
								<label for="doi-recipient">
								<?php _e( 'Recipient Field', 'double-opt-in' ); ?>
								<span class="doi-tooltip">
									<span class="dashicons dashicons-editor-help"></span>
									<span class="doi-tooltip-text"><?php esc_html_e( 'Enter the CF7 field name in brackets that contains the recipient\'s email address, e.g. [your-email].', 'double-opt-in' ); ?></span>
								</span>
							</label>
								<div class="doi-recipient-wrapper">
									<select name="recipient_select" id="doi-recipient-select" class="regular-text"></select>
									<input type="text" name="recipient" id="doi-recipient" class="regular-text" placeholder="[email]" style="display: none;">
									<button type="button" class="button doi-toggle-manual-input" id="doi-toggle-recipient-input" title="<?php esc_attr_e( 'Toggle manual input', 'double-opt-in' ); ?>">
										<span class="dashicons dashicons-edit"></span>
									</button>
								</div>
								<p class="description"><?php _e( 'Form field containing the recipient email. Use field name in brackets, e.g., [email] or [your-email].', 'double-opt-in' ); ?></p>
							</div>

							<!-- Sender -->
							<div class="doi-field">
								<label for="doi-sender">
								<?php _e( 'From Email', 'double-opt-in' ); ?>
								<span class="doi-tooltip">
									<span class="dashicons dashicons-editor-help"></span>
									<span class="doi-tooltip-text"><?php esc_html_e( 'The email address used as the sender of the confirmation email. You can also use [_site_admin_email].', 'double-opt-in' ); ?></span>
								</span>
							</label>
								<input type="text" name="sender" id="doi-sender" class="regular-text">
								<p class="description"><?php _e( 'Sender email address (e.g., noreply@example.com or [_site_admin_email]).', 'double-opt-in' ); ?></p>
							</div>

							<!-- Sender Name -->
							<div class="doi-field">
								<label for="doi-sender-name">
								<?php _e( 'From Name', 'double-opt-in' ); ?>
								<span class="doi-tooltip">
									<span class="dashicons dashicons-editor-help"></span>
									<span class="doi-tooltip-text"><?php esc_html_e( 'The name displayed as the sender in the email, e.g. your company name or website name.', 'double-opt-in' ); ?></span>
								</span>
							</label>
								<input type="text" name="senderName" id="doi-sender-name" class="regular-text">
								<p class="description"><?php _e( 'Sender name displayed in the email.', 'double-opt-in' ); ?></p>
							</div>

							<!-- Subject -->
							<div class="doi-field">
								<label for="doi-subject">
								<?php _e( 'Subject', 'double-opt-in' ); ?>
								<span class="doi-tooltip">
									<span class="dashicons dashicons-editor-help"></span>
									<span class="doi-tooltip-text"><?php esc_html_e( 'The subject line of the confirmation email. You can also use placeholders like [doi_name].', 'double-opt-in' ); ?></span>
								</span>
							</label>
								<input type="text" name="subject" id="doi-subject" class="regular-text">
								<p class="description"><?php _e( 'Email subject line.', 'double-opt-in' ); ?></p>
							</div>

							<!-- Template -->
							<div class="doi-field">
								<label for="doi-template">
								<?php _e( 'Email Template', 'double-opt-in' ); ?>
								<span class="doi-tooltip">
									<span class="dashicons dashicons-editor-help"></span>
									<span class="doi-tooltip-text"><?php esc_html_e( 'Select a template for the confirmation email. Custom templates can be created in the Email Designer.', 'double-opt-in' ); ?></span>
								</span>
							</label>
								<select name="template" id="doi-template" class="regular-text"></select>
								<p class="description"><?php _e( 'Select an email template.', 'double-opt-in' ); ?></p>
							</div>

							<!-- Field Mapping -->
							<div class="doi-field" id="doi-field-mapping-section">
								<label>
									<?php _e( 'Field Mapping', 'double-opt-in' ); ?>
									<span class="doi-tooltip">
										<span class="dashicons dashicons-editor-help"></span>
										<span class="doi-tooltip-text"><?php esc_html_e( 'Map your form fields to standard placeholders to use them in email templates and the dashboard. Unmapped fields are auto-detected.', 'double-opt-in' ); ?></span>
									</span>
								</label>
								<p class="description" style="margin-bottom: 10px;">
									<?php _e( 'Map form fields to standard placeholders. Auto-detection is used if not configured.', 'double-opt-in' ); ?>
								</p>
								<div id="doi-field-mapping-grid" class="doi-field-mapping-grid">
									<!-- Populated by JS -->
								</div>
								<p class="description" style="margin-top: 10px; font-style: italic;">
									<?php _e( 'Tip: Standard placeholders like [doi_email] work across all forms.', 'double-opt-in' ); ?>
								</p>
							</div>

							<hr>

							<!-- Body -->
							<div class="doi-field" id="doi-body-field">
								<label for="doi-body">
								<?php _e( 'Message Body', 'double-opt-in' ); ?>
								<span class="doi-tooltip">
									<span class="dashicons dashicons-editor-help"></span>
									<span class="doi-tooltip-text"><?php esc_html_e( 'The content of the confirmation email. Use [doubleoptinlink] as a placeholder for the confirmation link.', 'double-opt-in' ); ?></span>
								</span>
							</label>

								<!-- Standard Template Body Editor -->
								<div id="doi-body-editor">
									<textarea name="body" id="doi-body" rows="10" class="large-text code"></textarea>
									<p class="description">
										<?php _e( 'Email content. Include [doubleoptinlink] for the confirmation link.', 'double-opt-in' ); ?>
									</p>
									<div class="doi-placeholders">
										<strong><?php _e( 'Available Placeholders:', 'double-opt-in' ); ?></strong>
										<code>[doubleoptinlink]</code>
										<code>[doubleoptoutlink]</code>
										<code>[doubleoptin_form_date]</code>
										<code>[doubleoptin_form_time]</code>
										<code>[doubleoptin_form_url]</code>
										<code>[doubleoptin_privacy_url]</code>
										<br><strong><?php _e( 'Standard Field Placeholders:', 'double-opt-in' ); ?></strong>
										<code>[doi_email]</code>
										<code>[doi_name]</code>
										<code>[doi_first_name]</code>
										<code>[doi_last_name]</code>
										<code>[doi_phone]</code>
										<code>[doi_company]</code>
										<code>[doi_message]</code>
										<br><strong><?php _e( 'Form Fields:', 'double-opt-in' ); ?></strong>
										<span class="doi-form-fields-placeholders"></span>
									</div>
								</div>

								<!-- Custom Template Notice -->
								<div id="doi-custom-template-notice" style="display: none;">
									<div class="doi-custom-template-box">
										<div class="doi-custom-template-icon">
											<span class="dashicons dashicons-email-alt"></span>
										</div>
										<div class="doi-custom-template-content">
											<h4><?php _e( 'Custom Template Selected', 'double-opt-in' ); ?></h4>
											<p><?php _e( 'This template was created with the Email Designer. The message body is managed within the template.', 'double-opt-in' ); ?></p>
											<a href="<?php echo esc_url( admin_url( 'admin.php?page=f12-cf7-doubleoptin_f12-doi-email-templates' ) ); ?>"
											   class="button button-secondary"
											   id="doi-edit-template-btn"
											   target="_blank">
												<span class="dashicons dashicons-edit"></span>
												<?php _e( 'Edit in Email Designer', 'double-opt-in' ); ?>
											</a>
										</div>
									</div>
								</div>
							</div>

							<?php
							// Pro-gating variables for Unique Email section
							$is_pro            = apply_filters( 'f12_doi_is_pro_active', false );
							$pro_disabled      = $is_pro ? '' : ' disabled';
							$pro_style_wrap    = $is_pro ? '' : 'opacity:.6;';
							$pro_style_toggle  = $is_pro ? '' : 'pointer-events:none;opacity:.6;';
							?>

							<hr>
							<h3><?php _e( 'Unique Email', 'double-opt-in' ); ?>
								<span class="doi-pro-label">PRO</span>
							</h3>

							<div class="doi-field" style="<?php echo esc_attr( $pro_style_toggle ); ?>">
								<label class="doi-field-label">
									<input type="checkbox" id="doi-unique-email-enabled"<?php echo $pro_disabled; ?>>
									<?php _e( 'Each email address may only be used once per form.', 'double-opt-in' ); ?>
									<span class="doi-tooltip">
										<span class="dashicons dashicons-editor-help"></span>
										<span class="doi-tooltip-text"><?php esc_html_e( 'Prevents the same email address from being used more than once for this form. Useful for one-time registrations.', 'double-opt-in' ); ?></span>
									</span>
								</label>
							</div>

							<div id="doi-unique-email-options" style="display:none;<?php echo esc_attr( $pro_style_wrap ); ?>">
								<div class="doi-field">
									<label for="doi-unique-email-behavior">
									<?php _e( 'Behavior', 'double-opt-in' ); ?>
									<span class="doi-tooltip">
										<span class="dashicons dashicons-editor-help"></span>
										<span class="doi-tooltip-text"><?php esc_html_e( 'Determines how the system reacts when a duplicate email address is detected.', 'double-opt-in' ); ?></span>
									</span>
								</label>
									<select id="doi-unique-email-behavior" class="regular-text"<?php echo $pro_disabled; ?>>
										<option value="block"><?php _e( 'Show error message', 'double-opt-in' ); ?></option>
										<option value="silent"><?php _e( 'Silent rejection', 'double-opt-in' ); ?></option>
										<option value="redirect"><?php _e( 'Redirect to page', 'double-opt-in' ); ?></option>
									</select>
									<p class="description"><?php _e( 'How to handle duplicate email submissions.', 'double-opt-in' ); ?></p>
								</div>

								<div class="doi-field" id="doi-unique-email-redirect-field" style="display:none;">
									<label for="doi-unique-email-redirect-page">
									<?php _e( 'Redirect Page', 'double-opt-in' ); ?>
									<span class="doi-tooltip">
										<span class="dashicons dashicons-editor-help"></span>
										<span class="doi-tooltip-text"><?php esc_html_e( 'The page the user is redirected to when a duplicate email is detected. The error code is appended as a query parameter.', 'double-opt-in' ); ?></span>
									</span>
								</label>
									<select id="doi-unique-email-redirect-page" class="regular-text"<?php echo $pro_disabled; ?>></select>
									<p class="description"><?php _e( 'Select the page to redirect to when a duplicate email is submitted.', 'double-opt-in' ); ?></p>
								</div>

								<div class="doi-field">
									<label for="doi-unique-email-scope">
									<?php _e( 'Scope', 'double-opt-in' ); ?>
									<span class="doi-tooltip">
										<span class="dashicons dashicons-editor-help"></span>
										<span class="doi-tooltip-text"><?php esc_html_e( 'Determines whether only confirmed or also unconfirmed opt-ins are checked for duplicates.', 'double-opt-in' ); ?></span>
									</span>
								</label>
									<select id="doi-unique-email-scope" class="regular-text"<?php echo $pro_disabled; ?>>
										<option value="confirmed"><?php _e( 'Only check confirmed opt-ins', 'double-opt-in' ); ?></option>
										<option value="all"><?php _e( 'Check all opt-ins (including unconfirmed)', 'double-opt-in' ); ?></option>
									</select>
									<p class="description"><?php _e( 'Whether to check only confirmed or all opt-in records.', 'double-opt-in' ); ?></p>
								</div>

								<div class="doi-field">
									<label for="doi-unique-email-message">
									<?php _e( 'Custom Error Message', 'double-opt-in' ); ?>
									<span class="doi-tooltip">
										<span class="dashicons dashicons-editor-help"></span>
										<span class="doi-tooltip-text"><?php esc_html_e( 'Custom error message shown to the user when a duplicate email is detected. Leave empty for the default message.', 'double-opt-in' ); ?></span>
									</span>
								</label>
									<input type="text" id="doi-unique-email-message" class="regular-text"<?php echo $pro_disabled; ?> placeholder="<?php esc_attr_e( 'This email address has already been used for this form.', 'double-opt-in' ); ?>">
									<p class="description"><?php _e( 'Leave empty to use the default message.', 'double-opt-in' ); ?></p>
								</div>
							</div>

							<?php
							/**
							 * Action to allow extensions (e.g., Pro version) to add additional fields to the settings panel.
							 *
							 * @since 4.1.0
							 */
							do_action( 'f12_doi_panel_fields' );
							?>
						</form>
					</div>
					<div class="doi-panel-footer">
						<button type="button" class="button" id="doi-panel-cancel">
							<?php _e( 'Cancel', 'double-opt-in' ); ?>
						</button>
						<button type="button" class="button button-primary" id="doi-panel-save">
							<?php _e( 'Save Settings', 'double-opt-in' ); ?>
						</button>
					</div>
				</div>
			</div>
			<?php
		}
	}
}
