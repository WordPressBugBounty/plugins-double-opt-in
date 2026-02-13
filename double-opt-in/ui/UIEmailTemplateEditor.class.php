<?php

namespace forge12\contactform7\CF7DoubleOptIn {

	use Forge12\Shared\LoggerInterface;
	use Forge12\DoubleOptIn\EmailTemplates\EmailTemplateRepository;
	use Forge12\DoubleOptIn\EmailTemplates\PlaceholderMapper;
	use Forge12\DoubleOptIn\EmailTemplates\BlockRegistry;

	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	/**
	 * Class UIEmailTemplateEditor
	 *
	 * Admin page for the drag-and-drop email template editor.
	 *
	 * @package forge12\contactform7\CF7DoubleOptIn
	 */
	class UIEmailTemplateEditor extends UIPage {

		/**
		 * @var EmailTemplateRepository
		 */
		private EmailTemplateRepository $repository;

		/**
		 * Constructor.
		 *
		 * @param LoggerInterface   $logger          Logger instance.
		 * @param TemplateHandler   $templateHandler Template handler instance.
		 * @param string            $domain          Text domain.
		 */
		public function __construct( LoggerInterface $logger, TemplateHandler $templateHandler, string $domain ) {
			parent::__construct(
				$logger,
				$templateHandler,
				$domain,
				'f12-doi-email-editor',
				__( 'Email Editor', 'double-opt-in' ),
				51
			);

			$this->repository = new EmailTemplateRepository();

			add_action( 'admin_enqueue_scripts', [ $this, 'enqueueAssets' ] );
		}

		/**
		 * Hide this page from the menu (accessible via direct link only).
		 *
		 * @return bool
		 */
		public function hideInMenu(): bool {
			return true;
		}

		/**
		 * Enqueue assets for the editor page.
		 *
		 * @param string $hook Current admin page hook.
		 * @return void
		 */
		public function enqueueAssets( string $hook ): void {
			if ( strpos( $hook, 'f12-doi-email-editor' ) === false ) {
				return;
			}

			// Get template data if editing
			$templateId = isset( $_GET['template_id'] ) ? (int) $_GET['template_id'] : 0;
			$template = $templateId ? $this->repository->findById( $templateId ) : null;

			// Check if the React app build exists
			$buildDir = plugin_dir_path( dirname( __FILE__ ) ) . 'email-editor/build/';
			$assetFile = $buildDir . 'index.asset.php';

			if ( file_exists( $assetFile ) ) {
				$asset = include $assetFile;

				wp_enqueue_style(
					'doi-email-editor',
					plugins_url( 'email-editor/build/index.css', dirname( __FILE__ ) ),
					[],
					$asset['version']
				);

				wp_enqueue_script(
					'doi-email-editor',
					plugins_url( 'email-editor/build/index.js', dirname( __FILE__ ) ),
					$asset['dependencies'],
					$asset['version'],
					true
				);

				$blockRegistry = new BlockRegistry();

				wp_localize_script( 'doi-email-editor', 'doiEmailEditor', [
					'restUrl'           => esc_url_raw( rest_url( 'f12-doi/v1/email-templates' ) ),
					'nonce'             => wp_create_nonce( 'wp_rest' ),
					'templateId'        => $templateId,
					'template'          => $template,
					'listUrl'           => admin_url( 'admin.php?page=f12-cf7-doubleoptin_f12-doi-email-templates' ),
					'i18n'              => $this->getTranslations(),
					'placeholders'      => class_exists( PlaceholderMapper::class ) ? PlaceholderMapper::getAvailablePlaceholdersForEditor() : [],
					'blockAvailability' => $blockRegistry->getBlockAvailability(),
					'isProActive'       => $blockRegistry->isProActive(),
					'upgradeUrl'        => apply_filters( 'f12_doi_upgrade_url', 'https://www.forge12.com/product/contact-form-7-double-opt-in/' ),
					'templateLimit'     => $blockRegistry->getTemplateLimit(),
					'templateCount'     => $this->repository->countPublished(),
				] );
			} else {
				// Fallback: show a message that the editor needs to be built
				wp_enqueue_style(
					'doi-email-editor-fallback',
					plugins_url( 'assets/css/email-editor-fallback.css', dirname( __FILE__ ) ),
					[],
					FORGE12_OPTIN_VERSION
				);
			}
		}

		/**
		 * Get translations for the JavaScript editor.
		 *
		 * @return array
		 */
		private function getTranslations(): array {
			return [
				'save'                => __( 'Save', 'double-opt-in' ),
				'saving'              => __( 'Saving...', 'double-opt-in' ),
				'saved'               => __( 'Saved', 'double-opt-in' ),
				'publish'             => __( 'Publish', 'double-opt-in' ),
				'preview'             => __( 'Preview', 'double-opt-in' ),
				'undo'                => __( 'Undo', 'double-opt-in' ),
				'redo'                => __( 'Redo', 'double-opt-in' ),
				'blocks'              => __( 'Blocks', 'double-opt-in' ),
				'settings'            => __( 'Settings', 'double-opt-in' ),
				'structure'           => __( 'Structure', 'double-opt-in' ),
				'content'             => __( 'Content', 'double-opt-in' ),
				'placeholders'        => __( 'Placeholders', 'double-opt-in' ),
				'globalStyles'        => __( 'Global Styles', 'double-opt-in' ),
				'templateName'        => __( 'Template Name', 'double-opt-in' ),
				'untitledTemplate'    => __( 'Untitled Template', 'double-opt-in' ),
				'dragBlockHere'       => __( 'Drag a block here', 'double-opt-in' ),
				'deleteBlock'         => __( 'Delete Block', 'double-opt-in' ),
				'duplicateBlock'      => __( 'Duplicate Block', 'double-opt-in' ),
				'moveUp'              => __( 'Move Up', 'double-opt-in' ),
				'moveDown'            => __( 'Move Down', 'double-opt-in' ),
				'confirmLink'         => __( 'Confirmation Link', 'double-opt-in' ),
				'optoutLink'          => __( 'Opt-out Link', 'double-opt-in' ),
				'formDate'            => __( 'Form Date', 'double-opt-in' ),
				'formTime'            => __( 'Form Time', 'double-opt-in' ),
				'formUrl'             => __( 'Form URL', 'double-opt-in' ),
				'customField'         => __( 'Custom Field', 'double-opt-in' ),
				'header'              => __( 'Header', 'double-opt-in' ),
				'heading'             => __( 'Heading', 'double-opt-in' ),
				'text'                => __( 'Text', 'double-opt-in' ),
				'button'              => __( 'Button', 'double-opt-in' ),
				'image'               => __( 'Image', 'double-opt-in' ),
				'divider'             => __( 'Divider', 'double-opt-in' ),
				'spacer'              => __( 'Spacer', 'double-opt-in' ),
				'socialIcons'         => __( 'Social Icons', 'double-opt-in' ),
				'footer'              => __( 'Footer', 'double-opt-in' ),
				'columns1'            => __( '1 Column', 'double-opt-in' ),
				'columns2'            => __( '2 Columns', 'double-opt-in' ),
				'columns2Sidebar'     => __( '2 Columns (Sidebar)', 'double-opt-in' ),
				'columns3'            => __( '3 Columns', 'double-opt-in' ),
				'row'                 => __( 'Row', 'double-opt-in' ),
				'wrapper'             => __( 'Wrapper', 'double-opt-in' ),
				'errorSaving'         => __( 'Error saving template. Please try again.', 'double-opt-in' ),
				'templateSaved'       => __( 'Template saved successfully.', 'double-opt-in' ),
				'backToList'              => __( 'Back to Templates', 'double-opt-in' ),
				'placeholders'            => __( 'Placeholders', 'double-opt-in' ),
				'placeholdersIntro'       => __( 'Click on a placeholder to copy it. Then paste it into any text field.', 'double-opt-in' ),
				'standardPlaceholders'    => __( 'Standard Placeholders', 'double-opt-in' ),
				'standardPlaceholdersDesc' => __( 'These work across all forms. Configure mapping in form settings.', 'double-opt-in' ),
				'systemPlaceholders'      => __( 'System Placeholders', 'double-opt-in' ),
				'howToUsePlaceholders'    => __( 'How to use', 'double-opt-in' ),
				'placeholderStep1'        => __( 'Add a Text or Button block to your template', 'double-opt-in' ),
				'placeholderStep2'        => __( 'Click a placeholder above to copy it', 'double-opt-in' ),
				'placeholderStep3'        => __( 'Paste it in the Content field in Settings', 'double-opt-in' ),
				// Preset selector translations
				'loading'                 => __( 'Loading...', 'double-opt-in' ),
				'chooseTemplate'          => __( 'Choose a Template', 'double-opt-in' ),
				'chooseTemplateDesc'      => __( 'Select a starting point for your email template or start from scratch.', 'double-opt-in' ),
				'startBlank'              => __( 'Start Blank', 'double-opt-in' ),
				'createTemplate'          => __( 'Create Template', 'double-opt-in' ),
				'creating'                => __( 'Creating...', 'double-opt-in' ),
				// Template limit
				'templateLimitReached'    => __( 'You have reached the maximum number of published templates. Upgrade to Pro for unlimited templates.', 'double-opt-in' ),
				// Send test email
				'sendTest'                => __( 'Send Test', 'double-opt-in' ),
				'sendTestTitle'           => __( 'Send Test Email', 'double-opt-in' ),
				'sendTestDesc'            => __( 'Send a test email to preview how your template looks in real email clients.', 'double-opt-in' ),
				'sendTestSuccess'         => __( 'Sent!', 'double-opt-in' ),
				'sendTestError'           => __( 'Error sending test email.', 'double-opt-in' ),
				'enterEmail'              => __( 'Enter email address', 'double-opt-in' ),
				'sending'                 => __( 'Sending...', 'double-opt-in' ),
				'cancel'                  => __( 'Cancel', 'double-opt-in' ),
				// Brand Kit
				'brandKit'                => __( 'Brand Kit', 'double-opt-in' ),
				'brandLogo'               => __( 'Logo URL', 'double-opt-in' ),
				'brandLogoWidth'          => __( 'Logo Width', 'double-opt-in' ),
				'brandColorPrimary'       => __( 'Brand Primary Color', 'double-opt-in' ),
				'brandColorSecondary'     => __( 'Brand Secondary Color', 'double-opt-in' ),
				'brandColorAccent'        => __( 'Brand Accent Color', 'double-opt-in' ),
				'applyToTemplate'         => __( 'Apply to Template', 'double-opt-in' ),
				// Preview
				'desktop'                 => __( 'Desktop', 'double-opt-in' ),
				'mobile'                  => __( 'Mobile', 'double-opt-in' ),
				// Conditional Content
				'conditionalContent'      => __( 'Conditional Content', 'double-opt-in' ),
				// Custom CSS
				'advanced'                => __( 'Advanced', 'double-opt-in' ),
				'customCss'               => __( 'Custom CSS', 'double-opt-in' ),
				'customCssHint'           => __( 'Enter inline CSS properties (e.g., background: #fff; border: 1px solid #ccc;)', 'double-opt-in' ),
				// Pro upgrade prompt
				'proFeature'              => __( 'Pro Feature', 'double-opt-in' ),
				'proBlockMessage'         => __( 'The "{block}" block requires the Pro version.', 'double-opt-in' ),
				'proUpgradeMessage'       => __( 'This feature requires the Pro version.', 'double-opt-in' ),
				'upgradeToPro'            => __( 'Upgrade to Pro', 'double-opt-in' ),
				'maybeLater'              => __( 'Maybe Later', 'double-opt-in' ),
			];
		}

		/**
		 * Get settings for this page.
		 *
		 * @param array $settings Current settings.
		 * @return array Modified settings.
		 */
		public function getSettings( $settings ) {
			return $settings;
		}

		/**
		 * Handle save operations.
		 *
		 * @param array $settings Current settings.
		 * @return array Modified settings.
		 */
		protected function onSave( $settings ) {
			return $settings;
		}

		/**
		 * Render the main content.
		 *
		 * @param string $slug    Page slug.
		 * @param string $page    Page name.
		 * @param array  $settings Current settings.
		 * @return void
		 */
		public function theContent( $slug, $page, $settings ): void {
			$templateId = isset( $_GET['template_id'] ) ? (int) $_GET['template_id'] : 0;
			$template = $templateId ? $this->repository->findById( $templateId ) : null;

			// Check if React app is built
			$buildDir = plugin_dir_path( dirname( __FILE__ ) ) . 'email-editor/build/';
			$hasReactApp = file_exists( $buildDir . 'index.asset.php' );
			?>
			<div class="doi-email-editor-header">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=f12-cf7-doubleoptin_f12-doi-email-templates' ) ); ?>" class="doi-back-link">
					&larr; <?php _e( 'Back to Templates', 'double-opt-in' ); ?>
				</a>
				<h1>
					<?php
					if ( $template ) {
						echo esc_html( sprintf( __( 'Edit: %s', 'double-opt-in' ), $template['title'] ) );
					} else {
						_e( 'Create New Email Template', 'double-opt-in' );
					}
					?>
				</h1>
			</div>

			<?php if ( $hasReactApp ) : ?>
				<div id="doi-email-editor-root" class="doi-email-editor-container">
					<!-- React app will mount here -->
					<div class="doi-editor-loading">
						<span class="spinner is-active"></span>
						<p><?php _e( 'Loading editor...', 'double-opt-in' ); ?></p>
					</div>
				</div>
			<?php else : ?>
				<div class="doi-email-editor-fallback">
					<div class="notice notice-warning">
						<p>
							<strong><?php _e( 'Email Editor Not Built', 'double-opt-in' ); ?></strong>
						</p>
						<p>
							<?php _e( 'The email template editor needs to be built before it can be used. Please run the following commands in your terminal:', 'double-opt-in' ); ?>
						</p>
						<pre>cd wp-content/plugins/double-opt-in/email-editor
npm install
npm run build</pre>
						<p>
							<?php _e( 'After building, refresh this page to use the editor.', 'double-opt-in' ); ?>
						</p>
					</div>
				</div>
			<?php endif; ?>
			<?php
		}

		/**
		 * Render the sidebar.
		 *
		 * @param string $slug Page slug.
		 * @param string $page Page name.
		 * @return void
		 */
		public function theSidebar( $slug, $page ): void {
			// Editor has its own sidebar, so we render minimal content here
			?>
			<div class="box">
				<h2><?php _e( 'Keyboard Shortcuts', 'double-opt-in' ); ?></h2>
				<ul>
					<li><code>Ctrl/Cmd + S</code> - <?php _e( 'Save template', 'double-opt-in' ); ?></li>
					<li><code>Ctrl/Cmd + Z</code> - <?php _e( 'Undo', 'double-opt-in' ); ?></li>
					<li><code>Ctrl/Cmd + Shift + Z</code> - <?php _e( 'Redo', 'double-opt-in' ); ?></li>
					<li><code>Delete</code> - <?php _e( 'Delete selected block', 'double-opt-in' ); ?></li>
				</ul>
			</div>
			<?php
		}
	}
}
