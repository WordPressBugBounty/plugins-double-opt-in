<?php
/**
 * Contact Form 7 Integration
 *
 * @package Forge12\DoubleOptIn\Integration
 * @since   4.0.0
 */

namespace Forge12\DoubleOptIn\Integration;

use Forge12\DoubleOptIn\Container\Container;
use Forge12\DoubleOptIn\EmailTemplates\PlaceholderMapper;
use forge12\contactform7\CF7DoubleOptIn\Category;
use forge12\contactform7\CF7DoubleOptIn\CF7DoubleOptIn;
use forge12\contactform7\CF7DoubleOptIn\HTMLSelect;
use forge12\contactform7\CF7DoubleOptIn\OptIn;
use forge12\contactform7\CF7DoubleOptIn\SanitizeHelper;
use Forge12\Shared\LoggerInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CF7Integration
 *
 * Integration for Contact Form 7.
 * Handles opt-in creation, confirmation mail sending, and admin panel.
 */
class CF7Integration extends AbstractFormIntegration implements AdminPanelInterface {

	/**
	 * Current OptIn for mail attachment handling.
	 *
	 * @var OptIn|null
	 */
	private ?OptIn $currentOptIn = null;

	/**
	 * {@inheritdoc}
	 */
	public function getIdentifier(): string {
		return 'cf7';
	}

	/**
	 * {@inheritdoc}
	 */
	public function getName(): string {
		return __( 'Contact Form 7', 'double-opt-in' );
	}

	/**
	 * {@inheritdoc}
	 */
	public function isAvailable(): bool {
		return function_exists( 'wpcf7' ) || class_exists( '\WPCF7_ContactForm' );
	}

	/**
	 * {@inheritdoc}
	 */
	protected function getPostType(): string {
		return 'wpcf7_contact_form';
	}

	/**
	 * {@inheritdoc}
	 */
	public function getFormEditUrl( $formId ): string {
		return admin_url( 'admin.php?page=wpcf7&post=' . (int) $formId . '&action=edit' );
	}

	/**
	 * {@inheritdoc}
	 */
	public function registerHooks(): void {
		// Frontend hooks
		add_action( 'wpcf7_before_send_mail', [ $this, 'onSubmit' ], $this->getHookPriority(), 3 );
		add_action( 'init', [ $this, 'handleOptInConfirmation' ] );
		add_action( 'shutdown', [ $this, 'cleanupFiles' ] );

		// Register recipient filter
		add_filter( 'f12_cf7_doubleoptin_get_recipient_cf7', [ $this, 'getRecipientFilter' ], 10, 3 );

		// Confirmation mail hooks
		add_action( 'f12_cf7_doubleoptin_before_send_default_mail', [ $this, 'beforeSendDefaultMail' ] );
		add_action( 'f12_cf7_doubleoptin_after_send_default_mail', [ $this, 'afterSendDefaultMail' ] );
		add_action( 'f12_cf7_doubleoptin_trigger_default_mail', [ $this, 'sendConfirmationMail' ] );

		// Admin hooks
		$this->registerAdminHooks();

		$this->getLogger()->debug( 'CF7 integration hooks registered', [
			'plugin' => 'double-opt-in',
		] );
	}

	/**
	 * {@inheritdoc}
	 */
	public function registerAdminHooks(): void {
		add_action( 'admin_init', [ $this, 'setupAdminPanel' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueueAdminAssets' ] );
	}

	/**
	 * Setup admin panel hooks.
	 *
	 * @return void
	 */
	public function setupAdminPanel(): void {
		add_filter( 'wpcf7_editor_panels', [ $this, 'addEditorPanel' ], 10, 1 );
		add_action( 'wpcf7_save_contact_form', [ $this, 'saveFormSettings' ], 10, 3 );
	}

	/**
	 * {@inheritdoc}
	 */
	public function enqueueAdminAssets( string $hook ): void {
		wp_enqueue_script(
			'f12-cf7-doubleoptin-admin',
			plugins_url( 'compatibility/cf7/assets/f12-cf7-popup.js', F12_DOUBLEOPTIN_PLUGIN_FILE ),
			[ 'jquery' ]
		);

		wp_localize_script( 'f12-cf7-doubleoptin-admin', 'doi', [
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'f12_doi_details' ),
		] );

		wp_enqueue_script(
			'f12-cf7-doubleoptin-templateloader',
			plugins_url( 'compatibility/cf7/assets/f12-cf7-templateloader.js', F12_DOUBLEOPTIN_PLUGIN_FILE ),
			[ 'jquery' ]
		);

		wp_localize_script( 'f12-cf7-doubleoptin-templateloader', 'templateloader', [
			'ajax_url'          => admin_url( 'admin-ajax.php' ),
			'nonce'             => wp_create_nonce( 'f12_doi_templateloader' ),
			'label_placeholder' => __( 'Please wait while we load the template...', 'double-opt-in' ),
		] );
	}

	/**
	 * {@inheritdoc}
	 */
	public function getHookPriority(): int {
		return 5;
	}

	/**
	 * {@inheritdoc}
	 */
	public function processSubmission( $context ): ?FormDataInterface {
		if ( ! is_array( $context ) || ! isset( $context['form'] ) || ! isset( $context['submission'] ) ) {
			return null;
		}

		$form       = $context['form'];
		$submission = $context['submission'];

		return FormData::fromCF7( $form, $submission );
	}

	/**
	 * {@inheritdoc}
	 */
	public function resolveRecipient( FormDataInterface $formData, array $formParameter ): string {
		if ( ! isset( $formParameter['recipient'] ) ) {
			return '';
		}

		$recipientField = str_replace( [ '[', ']' ], '', $formParameter['recipient'] );
		$fields         = $formData->getFields();

		if ( isset( $fields[ $recipientField ] ) ) {
			return sanitize_email( $fields[ $recipientField ] );
		}

		return '';
	}

	/**
	 * Recipient filter callback for legacy compatibility.
	 *
	 * @param string $recipient     Current recipient.
	 * @param array  $formParameter Form parameters.
	 * @param array  $postParameter Post data.
	 *
	 * @return string The resolved recipient.
	 */
	public function getRecipientFilter( string $recipient, array $formParameter, array $postParameter ): string {
		if ( ! isset( $formParameter['recipient'] ) ) {
			return $recipient;
		}

		$recipientField = str_replace( [ '[', ']' ], '', $formParameter['recipient'] );

		if ( isset( $postParameter[ $recipientField ] ) ) {
			return sanitize_email( $postParameter[ $recipientField ] );
		}

		return $recipient;
	}

	/**
	 * Handle form submission.
	 *
	 * @param \WPCF7_ContactForm $form       The contact form.
	 * @param bool               $abort      Whether to abort submission.
	 * @param \WPCF7_Submission  $submission The submission.
	 *
	 * @return void
	 */
	public function onSubmit( $form, &$abort, $submission ): void {
		$formId = $form->id();

		$this->getLogger()->debug( 'CF7 form submission received', [
			'plugin'  => 'double-opt-in',
			'form_id' => $formId,
		] );

		if ( ! $this->isOptInEnabled( $formId ) ) {
			// Handle file attachments for confirmation
			if ( isset( $_GET['optin'] ) ) {
				$this->attachStoredFiles( $submission );
			}
			return;
		}

		// Remove CF7 DB integration
		remove_action( 'wpcf7_before_send_mail', 'cfdb7_before_send_mail' );

		// Create form data
		$formData = FormData::fromCF7( $form, $submission );
		$formParameter = $this->getFormParameter( $formId );

		// Check skip filter
		if ( apply_filters( 'f12_cf7_doubleoptin_skip_option', false, $formId, $formData->getFields(), 'cf7' ) ) {
			$this->getLogger()->info( 'OptIn skipped by filter', [
				'plugin'  => 'double-opt-in',
				'form_id' => $formId,
			] );
			return;
		}

		// Set recipient
		$recipient = $this->resolveRecipient( $formData, $formParameter );
		$formData  = $formData->withRecipientEmail( $recipient );

		// Create OptIn
		$optIn = $this->createOptIn( $formData, $formParameter );

		if ( ! $optIn ) {
			// Always prevent the original CF7 mail from being sent when opt-in creation fails
			add_filter( 'wpcf7_skip_mail', '__return_true' );

			$error = self::getLastError();
			if ( $error && apply_filters( 'f12_cf7_doubleoptin_show_validation_error', false ) ) {
				$message = apply_filters( 'f12_cf7_doubleoptin_error_message', $error->getMessage(), $error, $formId );
				if ( method_exists( $submission, 'set_response' ) ) {
					$submission->set_response( $message );
				}
				$abort = true;
			}
			return;
		}

		// Send opt-in mail
		$this->sendOptInMail( $optIn, $formData, $formParameter );

		// Skip original mail
		add_filter( 'wpcf7_skip_mail', '__return_true' );
		do_action( 'f12_cf7_doubleoptin_sent', $form, $formId );
	}

	/**
	 * {@inheritdoc}
	 */
	public function sendOptInMail( OptIn $optIn, FormDataInterface $formData, array $formParameter ): bool {
		$formParameter['formUrl'] = $formData->getMetaValue( 'source_url', '' );

		// Get template body
		$body = apply_filters(
			'f12_cf7_doubleoptin_template_body',
			$formParameter['body'],
			$formParameter['template'] ?? 'blank',
			$formParameter,
			$optIn
		);

		// Process placeholders
		$body = $this->prepareMailBody( $body, $optIn, $formParameter );
		$body = apply_filters( 'f12_cf7_doubleoptin_body', $body );

		// Store mail content in OptIn
		$optIn->set_mail_optin( $body );
		$optIn->save();

		// Prepare mail arguments
		$args = apply_filters( 'f12-cf7-doubleoptin-cf7-args', [
			'subject'            => $formParameter['subject'] ?? '',
			'body'               => $body,
			'sender'             => $formParameter['sender'] ?? '',
			'sender_name'        => $formParameter['sender_name'] ?? '',
			'recipient'          => $optIn->get_email(),
			'use_html'           => true,
			'additional_headers' => '',
		] );

		if ( ! empty( $args['sender_name'] ) ) {
			$args['additional_headers'] .= 'From: ' . $args['sender_name'] . ' <' . $args['sender'] . '>';
		}

		// Send via CF7 mail system
		\WPCF7_Mail::send( $args, 'mail' );

		$this->getLogger()->info( 'OptIn mail sent via CF7', [
			'plugin'    => 'double-opt-in',
			'form_id'   => $formData->getFormId(),
			'recipient' => $args['recipient'],
		] );

		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	public function sendConfirmationMail( OptIn $optIn ): void {
		if ( ! $this->isAvailable() ) {
			$this->getLogger()->warning( 'CF7 not available for confirmation mail', [
				'plugin' => 'double-opt-in',
			] );
			return;
		}

		$this->currentOptIn = $optIn;

		// Restore POST data
		$data  = maybe_unserialize( $optIn->get_content() );
		$_POST = SanitizeHelper::sanitize_array( $data );

		// Get CF7 form
		$contactForm = \WPCF7_ContactForm::get_instance( $optIn->get_cf_form_id() );
		if ( ! $contactForm ) {
			$this->getLogger()->warning( 'CF7 form not found for confirmation mail', [
				'plugin'  => 'double-opt-in',
				'form_id' => $optIn->get_cf_form_id(),
			] );
			return;
		}

		// Add attachment hook
		add_action( 'wpcf7_before_send_mail', [ $this, 'attachExtraAttachments' ], 10, 3 );

		// Create submission and send mail
		$submission = \WPCF7_Submission::get_instance( $contactForm );

		$this->getLogger()->info( 'Confirmation mail triggered via CF7', [
			'plugin'   => 'double-opt-in',
			'form_id'  => $optIn->get_cf_form_id(),
			'optin_id' => $optIn->get_id(),
		] );
	}

	/**
	 * Handle opt-in confirmation from URL.
	 *
	 * @return void
	 */
	public function handleOptInConfirmation(): void {
		if ( ! isset( $_GET['optin'] ) ) {
			return;
		}

		$hash = sanitize_text_field( $_GET['optin'] );
		$this->validateOptIn( $hash );
	}

	/**
	 * Before sending default mail callback.
	 *
	 * @return void
	 */
	public function beforeSendDefaultMail(): void {
		$this->beforeSendConfirmationMail();
	}

	/**
	 * After sending default mail callback.
	 *
	 * @return void
	 */
	public function afterSendDefaultMail(): void {
		$this->afterSendConfirmationMail();
	}

	/**
	 * Attach extra attachments to the mail.
	 *
	 * @param \WPCF7_ContactForm $form       The contact form.
	 * @param bool               $abort      Whether to abort.
	 * @param \WPCF7_Submission  $submission The submission.
	 *
	 * @return void
	 */
	public function attachExtraAttachments( $form, $abort, $submission ): void {
		if ( $this->currentOptIn && $this->currentOptIn->get_files() ) {
			$files = maybe_unserialize( $this->currentOptIn->get_files() );
			if ( is_array( $files ) ) {
				foreach ( $files as $file ) {
					$submission->add_extra_attachments( $file );
				}
			}
		}
	}

	/**
	 * Attach stored files to submission during confirmation.
	 *
	 * @param \WPCF7_Submission $submission The submission.
	 *
	 * @return void
	 */
	private function attachStoredFiles( $submission ): void {
		$hash  = sanitize_text_field( $_GET['optin'] );
		$optIn = OptIn::get_by_hash( $hash );

		if ( ! $optIn ) {
			return;
		}

		$files = maybe_unserialize( $optIn->get_files() );
		if ( empty( $files ) ) {
			return;
		}

		foreach ( $files as $file ) {
			if ( empty( $file ) ) {
				continue;
			}

			if ( apply_filters( 'f12_cf7_doubleoptin_files_mail_1', true, $optIn ) ) {
				$submission->add_extra_attachments( $file );
			}

			if ( apply_filters( 'f12_cf7_doubleoptin_files_mail_2', true, $optIn ) ) {
				$submission->add_extra_attachments( $file, 'mail_2' );
			}
		}
	}

	/**
	 * Cleanup stored files after request.
	 *
	 * @return void
	 */
	public function cleanupFiles(): void {
		if ( ! isset( $_GET['optin'] ) ) {
			return;
		}

		$hash  = sanitize_text_field( $_GET['optin'] );
		$optIn = OptIn::get_by_hash( $hash );

		if ( $optIn && $optIn->isType( $this->getIdentifier() ) ) {
			$this->removeStoredFiles( $optIn );
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function getFormFields( $formId ): array {
		$formId = (int) $formId;
		$post   = get_post( $formId );
		if ( ! $post || $post->post_type !== 'wpcf7_contact_form' ) {
			return [];
		}

		$contactForm = \WPCF7_ContactForm::get_instance( $formId );
		if ( ! $contactForm ) {
			return [];
		}

		$fields = [];
		$tags   = $contactForm->scan_form_tags();

		foreach ( $tags as $tag ) {
			if ( ! empty( $tag->name ) ) {
				$fields[ $tag->name ] = $tag->name;
			}
		}

		return $fields;
	}

	/**
	 * Add editor panel to CF7.
	 *
	 * @param array $panels The panels array.
	 *
	 * @return array Modified panels.
	 */
	public function addEditorPanel( array $panels ): array {
		$panels['optin'] = [
			'title'    => $this->getPanelTitle(),
			'callback' => [ $this, 'renderPanel' ],
		];
		return $panels;
	}

	/**
	 * {@inheritdoc}
	 */
	public function render( $form, array $metadata ): void {
		$this->renderPanel( $form );
	}

	/**
	 * Render the CF7 editor panel.
	 *
	 * Displays a notice with link to central form management.
	 * Full settings are now managed centrally in the Forms admin page.
	 *
	 * @param \WPCF7_ContactForm $post The contact form.
	 *
	 * @return void
	 */
	public function renderPanel( $post ): void {
		if ( ! $post || ! $post->id() ) {
			?>
			<div class="doi-cf7-notice" style="padding: 20px;">
				<div style="background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 20px;">
					<h2 style="margin-top: 0;"><?php _e( 'Double Opt-In Settings', 'double-opt-in' ); ?></h2>
					<p style="color: #666;">
						<?php _e( 'Please save the contact form first before configuring Double Opt-In.', 'double-opt-in' ); ?>
					</p>
				</div>
			</div>
			<?php
			return;
		}

		$metadata   = $this->getFormParameter( $post->id() );
		$centralUrl = admin_url( 'admin.php?page=f12-cf7-doubleoptin_forms' );
		$isEnabled  = $this->isOptInEnabled( $post->id() );

		$this->getLogger()->debug( 'Rendering CF7 panel notice', [
			'plugin'  => 'double-opt-in',
			'form_id' => $post->id(),
			'enabled' => $isEnabled,
		] );
		?>
		<div class="doi-cf7-notice" style="padding: 20px;">
			<div style="background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 20px;">
				<h2 style="margin-top: 0;"><?php _e( 'Double Opt-In Settings', 'double-opt-in' ); ?></h2>

				<div style="display: flex; align-items: center; gap: 15px; margin-bottom: 20px;">
					<span style="font-weight: 600;"><?php _e( 'Status:', 'double-opt-in' ); ?></span>
					<?php if ( $isEnabled ) : ?>
						<span style="display: inline-block; padding: 4px 12px; background: #d4edda; color: #155724; border-radius: 3px; font-weight: 500;">
							<?php _e( 'Enabled', 'double-opt-in' ); ?>
						</span>
					<?php else : ?>
						<span style="display: inline-block; padding: 4px 12px; background: #f8d7da; color: #721c24; border-radius: 3px; font-weight: 500;">
							<?php _e( 'Disabled', 'double-opt-in' ); ?>
						</span>
					<?php endif; ?>
				</div>

				<p style="color: #666; margin-bottom: 20px;">
					<?php _e( 'Double Opt-In settings are now managed centrally. Use the button below to configure this form.', 'double-opt-in' ); ?>
				</p>

				<a href="<?php echo esc_url( $centralUrl ); ?>" class="button button-primary" target="_blank">
					<?php _e( 'Configure Double Opt-In', 'double-opt-in' ); ?>
				</a>
			</div>
		</div>
		<?php
	}

	/**
	 * {@inheritdoc}
	 */
	public function save( int $formId, array $data ): bool {
		if ( ! isset( $data['doubleoptin'] ) ) {
			update_post_meta( $formId, 'f12-cf7-doubleoptin', [] );
			return true;
		}

		$parameter = SanitizeHelper::sanitize_array( $data['doubleoptin'] );
		$metadata  = $this->getFormParameter( $formId );

		foreach ( $metadata as $key => $value ) {
			if ( isset( $parameter[ $key ] ) ) {
				$metadata[ $key ] = $key === 'enable' ? (int) $parameter[ $key ] : $parameter[ $key ];
			} elseif ( $key === 'enable' ) {
				$metadata[ $key ] = 0;
			}
		}

		$metadata = apply_filters( 'f12_cf7_doubleoptin_metadata_cf7', $metadata );
		$metadata = apply_filters( 'f12_cf7_doubleoptin_save_form', $metadata );

		update_post_meta( $formId, 'f12-cf7-doubleoptin', $metadata );

		// Save placeholder mapping
		if ( isset( $data['doubleoptin']['placeholder_mapping'] ) ) {
			$mapping = array_map( 'sanitize_text_field', $data['doubleoptin']['placeholder_mapping'] );
			PlaceholderMapper::saveCustomMapping( $formId, $mapping, 'cf7' );
		}

		return true;
	}

	/**
	 * Save form settings callback.
	 *
	 * @param \WPCF7_ContactForm $contactForm The contact form.
	 * @param array              $args        The arguments.
	 * @param string             $context     The context.
	 *
	 * @return void
	 */
	public function saveFormSettings( $contactForm, $args, $context ): void {
		$formId = $contactForm->id();

		// Verify nonce
		if ( ! isset( $_POST['f12_cf7_doubleoptin_save_form_nonce'] ) ||
			! wp_verify_nonce( wp_unslash( $_POST['f12_cf7_doubleoptin_save_form_nonce'] ), 'f12_cf7_doubleoptin_save_form_action' ) ) {
			return;
		}

		$this->save( $formId, $_POST );
	}

	/**
	 * {@inheritdoc}
	 */
	public function getPanelTitle(): string {
		return __( 'Double-Opt-in', 'double-opt-in' );
	}

	/**
	 * {@inheritdoc}
	 */
	public function getAvailableTemplates(): array {
		$templates = [
			'blank'           => 'blank',
			'newsletter_en'   => 'newsletter_en',
			'newsletter_en_2' => 'newsletter_en_2',
			'newsletter_en_3' => 'newsletter_en_3',
		];

		// Add custom templates
		try {
			$container   = Container::getInstance();
			$integration = $container->get( \Forge12\DoubleOptIn\EmailTemplates\EmailTemplateIntegration::class );
			$custom      = $integration->getCustomTemplates();

			foreach ( $custom as $template ) {
				$templates[ 'custom_' . $template['id'] ] = $template['title'] . ' (' . __( 'Custom', 'double-opt-in' ) . ')';
			}
		} catch ( \Exception $e ) {
			// Ignore if custom templates not available
		}

		return $templates;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getAvailableCategories(): array {
		$categories = [ 0 => __( 'Please select', 'double-opt-in' ) ];

		$list = Category::get_list( [
			'perPage' => -1,
			'orderBy' => 'name',
			'order'   => 'ASC',
		], $numberOfPages );

		foreach ( $list as $category ) {
			$categories[ $category->get_id() ] = $category->get_name();
		}

		return $categories;
	}
}
