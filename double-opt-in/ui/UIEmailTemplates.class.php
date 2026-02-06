<?php

namespace forge12\contactform7\CF7DoubleOptIn {

	use Forge12\Shared\LoggerInterface;
	use Forge12\DoubleOptIn\EmailTemplates\EmailTemplateRepository;

	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	/**
	 * Class UIEmailTemplates
	 *
	 * Admin page for listing and managing email templates.
	 *
	 * @package forge12\contactform7\CF7DoubleOptIn
	 */
	class UIEmailTemplates extends UIPage {

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
				'f12-doi-email-templates',
				__( 'Email Templates', 'double-opt-in' ),
				50
			);

			$this->repository = new EmailTemplateRepository();

			add_action( 'admin_enqueue_scripts', [ $this, 'enqueueAssets' ] );
		}

		/**
		 * Enqueue assets for the template list page.
		 *
		 * @param string $hook Current admin page hook.
		 * @return void
		 */
		public function enqueueAssets( string $hook ): void {
			if ( strpos( $hook, 'f12-doi-email-templates' ) === false ) {
				return;
			}

			wp_enqueue_style(
				'doi-email-templates-list',
				plugins_url( 'assets/css/email-templates-list.css', dirname( __FILE__ ) ),
				[],
				FORGE12_OPTIN_VERSION
			);
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
		 * Handle delete and other actions.
		 *
		 * @return void
		 */
		private function handleActions(): void {
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}

			// Handle delete
			if ( isset( $_GET['action'] ) && $_GET['action'] === 'delete' && isset( $_GET['template_id'] ) ) {
				$templateId = (int) $_GET['template_id'];

				// Verify nonce
				if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( wp_unslash( $_GET['_wpnonce'] ), 'delete-email-template-' . $templateId ) ) {
					wp_die( __( 'Security check failed.', 'double-opt-in' ) );
				}

				if ( $this->repository->delete( $templateId, true ) ) {
					Messages::getInstance()->add( __( 'Template deleted successfully.', 'double-opt-in' ), 'success' );
				} else {
					Messages::getInstance()->add( __( 'Failed to delete template.', 'double-opt-in' ), 'error' );
				}

				wp_redirect( admin_url( 'admin.php?page=f12-cf7-doubleoptin_f12-doi-email-templates' ) );
				exit;
			}

			// Handle duplicate
			if ( isset( $_GET['action'] ) && $_GET['action'] === 'duplicate' && isset( $_GET['template_id'] ) ) {
				$templateId = (int) $_GET['template_id'];

				// Verify nonce
				if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( wp_unslash( $_GET['_wpnonce'] ), 'duplicate-email-template-' . $templateId ) ) {
					wp_die( __( 'Security check failed.', 'double-opt-in' ) );
				}

				$newId = $this->repository->duplicate( $templateId );
				if ( $newId ) {
					Messages::getInstance()->add( __( 'Template duplicated successfully.', 'double-opt-in' ), 'success' );
					wp_redirect( admin_url( 'admin.php?page=f12-cf7-doubleoptin_f12-doi-email-editor&template_id=' . $newId ) );
					exit;
				} else {
					Messages::getInstance()->add( __( 'Failed to duplicate template.', 'double-opt-in' ), 'error' );
				}

				wp_redirect( admin_url( 'admin.php?page=f12-cf7-doubleoptin_f12-doi-email-templates' ) );
				exit;
			}
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
			$this->handleActions();

			$templates = $this->repository->findAll();
			?>
			<div class="doi-email-templates-header">
				<h1><?php _e( 'Email Templates', 'double-opt-in' ); ?></h1>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=f12-cf7-doubleoptin_f12-doi-email-editor' ) ); ?>" class="button button-primary">
					<?php _e( 'Add New Template', 'double-opt-in' ); ?>
				</a>
			</div>

			<?php if ( empty( $templates ) ) : ?>
				<div class="doi-no-templates">
					<p><?php _e( 'No email templates found. Create your first template to get started.', 'double-opt-in' ); ?></p>
				</div>
			<?php else : ?>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th scope="col" class="manage-column column-title"><?php _e( 'Title', 'double-opt-in' ); ?></th>
							<th scope="col" class="manage-column column-status"><?php _e( 'Status', 'double-opt-in' ); ?></th>
							<th scope="col" class="manage-column column-date"><?php _e( 'Date', 'double-opt-in' ); ?></th>
							<th scope="col" class="manage-column column-actions"><?php _e( 'Actions', 'double-opt-in' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $templates as $template ) : ?>
							<tr>
								<td class="column-title">
									<strong>
										<a href="<?php echo esc_url( admin_url( 'admin.php?page=f12-cf7-doubleoptin_f12-doi-email-editor&template_id=' . $template['id'] ) ); ?>">
											<?php echo esc_html( $template['title'] ); ?>
										</a>
									</strong>
								</td>
								<td class="column-status">
									<?php if ( $template['status'] === 'publish' ) : ?>
										<span class="doi-status doi-status-published"><?php _e( 'Published', 'double-opt-in' ); ?></span>
									<?php else : ?>
										<span class="doi-status doi-status-draft"><?php _e( 'Draft', 'double-opt-in' ); ?></span>
									<?php endif; ?>
								</td>
								<td class="column-date">
									<?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $template['created_at'] ) ) ); ?>
								</td>
								<td class="column-actions">
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=f12-cf7-doubleoptin_f12-doi-email-editor&template_id=' . $template['id'] ) ); ?>" class="button button-small">
										<?php _e( 'Edit', 'double-opt-in' ); ?>
									</a>
									<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=f12-cf7-doubleoptin_f12-doi-email-templates&action=duplicate&template_id=' . $template['id'] ), 'duplicate-email-template-' . $template['id'] ) ); ?>" class="button button-small">
										<?php _e( 'Duplicate', 'double-opt-in' ); ?>
									</a>
									<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=f12-cf7-doubleoptin_f12-doi-email-templates&action=delete&template_id=' . $template['id'] ), 'delete-email-template-' . $template['id'] ) ); ?>" class="button button-small button-link-delete" onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to delete this template?', 'double-opt-in' ); ?>');">
										<?php _e( 'Delete', 'double-opt-in' ); ?>
									</a>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
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
			?>
			<div class="box">
				<h2><?php _e( 'About Email Templates', 'double-opt-in' ); ?></h2>
				<p><?php _e( 'Create custom email templates using our drag-and-drop editor. Templates can be used for Double Opt-In confirmation emails in Contact Form 7 and Avada Forms.', 'double-opt-in' ); ?></p>
			</div>
			<div class="box">
				<h2><?php _e( 'Available Placeholders', 'double-opt-in' ); ?></h2>
				<ul>
					<li><code>[doubleoptinlink]</code> - <?php _e( 'Confirmation link', 'double-opt-in' ); ?></li>
					<li><code>[doubleoptoutlink]</code> - <?php _e( 'Opt-out link', 'double-opt-in' ); ?></li>
					<li><code>[doubleoptin_form_date]</code> - <?php _e( 'Submission date', 'double-opt-in' ); ?></li>
					<li><code>[doubleoptin_form_time]</code> - <?php _e( 'Submission time', 'double-opt-in' ); ?></li>
					<li><code>[field_name]</code> - <?php _e( 'Custom form fields', 'double-opt-in' ); ?></li>
				</ul>
			</div>
			<?php
		}
	}
}
