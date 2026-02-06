<?php
/**
 * Avada Forms Integration
 *
 * @package Forge12\DoubleOptIn\Integration
 * @since   4.0.0
 */

namespace Forge12\DoubleOptIn\Integration;

use Forge12\DoubleOptIn\Container\Container;
use Forge12\DoubleOptIn\EmailTemplates\EmailTemplateIntegration;
use forge12\contactform7\CF7DoubleOptIn\AvadaFormSubmit;
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
 * Class AvadaIntegration
 *
 * Integration for Avada Forms.
 * Handles opt-in creation, confirmation mail sending, and admin panel.
 */
class AvadaIntegration extends AbstractFormIntegration implements AdminPanelInterface {

	/**
	 * {@inheritdoc}
	 */
	public function getIdentifier(): string {
		return 'avada';
	}

	/**
	 * {@inheritdoc}
	 */
	public function getName(): string {
		return __( 'Avada Forms', 'double-opt-in' );
	}

	/**
	 * {@inheritdoc}
	 */
	public function isAvailable(): bool {
		return class_exists( '\Fusion_Form_Builder' ) || defined( 'FUSION_BUILDER_VERSION' );
	}

	/**
	 * {@inheritdoc}
	 */
	protected function getPostType(): string {
		return 'fusion_form';
	}

	/**
	 * {@inheritdoc}
	 */
	public function getFormEditUrl( $formId ): string {
		return admin_url( 'post.php?post=' . (int) $formId . '&action=edit' );
	}

	/**
	 * {@inheritdoc}
	 */
	public function registerHooks(): void {
		// Only register submission hook if not confirming opt-in
		if ( ! isset( $_GET['optin'] ) ) {
			add_filter( 'fusion_form_send_mail_args', [ $this, 'onSubmit' ], 10, 3 );
		}

		// Handle opt-in confirmation
		add_action( 'init', [ $this, 'handleOptInConfirmation' ] );

		// Register recipient filter
		add_filter( 'f12_cf7_doubleoptin_get_recipient_avada', [ $this, 'getRecipientFilter' ], 10, 3 );

		// Confirmation mail hooks
		add_action( 'f12_cf7_doubleoptin_trigger_default_mail', [ $this, 'sendConfirmationMail' ] );

		// Cleanup
		add_action( 'shutdown', [ $this, 'cleanupFiles' ] );

		// Admin hooks
		$this->registerAdminHooks();

		$this->getLogger()->debug( 'Avada integration hooks registered', [
			'plugin' => 'double-opt-in',
		] );
	}

	/**
	 * {@inheritdoc}
	 */
	public function registerAdminHooks(): void {
		add_action( 'admin_init', [ $this, 'setupAdminPanel' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueueAdminAssets' ] );
		add_action( 'save_post', [ $this, 'saveFormSettings' ], 20, 1 );
		add_filter( 'awb_po_get_value', [ $this, 'getMetaValue' ], 10, 2 );
	}

	/**
	 * Setup admin panel hooks.
	 *
	 * @return void
	 */
	public function setupAdminPanel(): void {
		add_filter( 'avada_metabox_tabs', [ $this, 'addMetaboxTab' ], 10, 2 );
		add_filter( 'f12_cf7_doubleoptin_avada_form_panel', [ $this, 'renderTabContent' ], 10, 1 );
	}

	/**
	 * {@inheritdoc}
	 */
	public function enqueueAdminAssets( string $hook ): void {
		wp_enqueue_style(
			'f12-avada-doubleoptin-admin-avada',
			plugins_url( 'compatibility/avada/assets/admin-avada-style.css', F12_DOUBLEOPTIN_PLUGIN_FILE )
		);

		wp_enqueue_script(
			'f12-avada-doubleoptin-templateloader',
			plugins_url( 'compatibility/avada/assets/f12-avada-templateloader.js', F12_DOUBLEOPTIN_PLUGIN_FILE ),
			[ 'jquery' ]
		);

		wp_localize_script( 'f12-avada-doubleoptin-templateloader', 'templateloader', [
			'ajax_url'          => admin_url( 'admin-ajax.php' ),
			'nonce'             => wp_create_nonce( 'f12_doi_templateloader' ),
			'label_placeholder' => __( 'Please wait while we load the template...', 'double-opt-in' ),
		] );
	}

	/**
	 * {@inheritdoc}
	 */
	public function processSubmission( $context ): ?FormDataInterface {
		if ( ! is_array( $context ) ) {
			return null;
		}

		$formId   = isset( $_POST['form_id'] ) ? absint( $_POST['form_id'] ) : 0;
		$formData = $context;

		if ( ! $formId ) {
			return null;
		}

		return FormData::fromAvada( $formId, $formData );
	}

	/**
	 * {@inheritdoc}
	 */
	public function resolveRecipient( FormDataInterface $formData, array $formParameter ): string {
		if ( ! isset( $formParameter['recipient'] ) ) {
			return '';
		}

		$recipientField = $formParameter['recipient'];
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
		if ( ! isset( $formParameter['recipient'] ) || ! isset( $postParameter['data'] ) ) {
			return '';
		}

		$recipientField = $formParameter['recipient'];

		if ( isset( $postParameter['data'][ $recipientField ] ) ) {
			return sanitize_email( $postParameter['data'][ $recipientField ] );
		}

		return '';
	}

	/**
	 * Handle form submission.
	 *
	 * @param array $formParameter The form mail parameters.
	 * @param int   $submissionId  The submission ID.
	 * @param array $formData      The form data.
	 *
	 * @return array The form parameters (may terminate script if opt-in sent).
	 */
	public function onSubmit( $formParameter, $submissionId, $formData ) {
		$formId = isset( $_POST['form_id'] ) ? absint( $_POST['form_id'] ) : 0;

		if ( ! $formId ) {
			return $formParameter;
		}

		$this->getLogger()->debug( 'Avada form submission received', [
			'plugin'  => 'double-opt-in',
			'form_id' => $formId,
		] );

		if ( ! $this->isOptInEnabled( $formId ) ) {
			return $formParameter;
		}

		$form = get_post( $formId );
		if ( ! $form ) {
			return $formParameter;
		}

		// Store form parameter in data for later use
		$formData['form_parameter'] = $formParameter;

		// Create normalized form data
		$normalizedData = FormData::fromAvada( $formId, $formData );
		$parameter      = $this->getFormParameter( $formId );

		// Check skip filter
		if ( apply_filters( 'f12_cf7_doubleoptin_skip_option', false, $formId, $formData, 'avada' ) ) {
			$this->getLogger()->info( 'OptIn skipped by filter', [
				'plugin'  => 'double-opt-in',
				'form_id' => $formId,
			] );
			die( wp_json_encode( [ 'status' => 'error', 'info' => 'opt-in skipped' ] ) );
		}

		// Resolve recipient
		$recipient      = $this->resolveRecipient( $normalizedData, $parameter );
		$normalizedData = $normalizedData->withRecipientEmail( $recipient );

		// Create OptIn
		$optIn = $this->createOptIn( $normalizedData, $parameter );

		if ( ! $optIn ) {
			$validationError = self::getLastRecipientValidationError();
			if ( ! empty( $validationError ) && apply_filters( 'f12_cf7_doubleoptin_show_validation_error', false ) ) {
				die( wp_json_encode( [
					'status' => 'error',
					'info'   => 'validation_failed',
				] ) );
			}
			return $formParameter;
		}

		// Store the complete Avada data structure for later notification processing
		// This includes data, field_labels, field_types, hidden_field_names needed by Avada's notification system
		$avadaContent = [
			'data'               => $formData['data'] ?? [],
			'field_labels'       => $formData['field_labels'] ?? [],
			'field_types'        => $formData['field_types'] ?? [],
			'hidden_field_names' => $formData['hidden_field_names'] ?? [],
			'form_parameter'     => $formData['form_parameter'] ?? [],
		];
		$avadaContent = apply_filters( 'f12_cf7_doubleoptin_add_request_parameter', $avadaContent );
		$optIn->set_content( maybe_serialize( $avadaContent ) );
		$optIn->save();

		$this->getLogger()->debug( 'Avada opt-in content updated with full data structure', [
			'plugin'   => 'double-opt-in',
			'optin_id' => $optIn->get_id(),
			'keys'     => array_keys( $avadaContent ),
		] );

		// Send opt-in mail
		$this->sendOptInMail( $optIn, $normalizedData, $parameter );

		do_action( 'f12_cf7_doubleoptin_sent', $form, $formId );

		die( wp_json_encode( [ 'status' => 'success', 'info' => 'opt-in send' ] ) );
	}

	/**
	 * {@inheritdoc}
	 */
	public function sendOptInMail( OptIn $optIn, FormDataInterface $formData, array $formParameter ): bool {
		$formParameter['formUrl'] = $formData->getMetaValue( 'source_url', '' );

		// Get template body
		$body = apply_filters(
			'f12_cf7_doubleoptin_template_body',
			$formParameter['body'] ?? '',
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

		// Prepare headers
		$headers  = "MIME-Version: 1.0\r\n";
		$headers .= "Content-type: text/html; charset=UTF-8\r\n";

		$args = apply_filters( 'f12-cf7-doubleoptin-cf7-args', [
			'subject'            => $formParameter['subject'] ?? '',
			'body'               => $body,
			'sender'             => $formParameter['sender'] ?? '',
			'sender_name'        => $formParameter['sender_name'] ?? '',
			'recipient'          => $optIn->get_email(),
			'use_html'           => true,
			'additional_headers' => $headers,
		] );

		$args['additional_headers'] .= sprintf( 'From: %s <%s>' . "\r\n", $args['sender_name'], $args['sender'] );

		// Send via wp_mail
		$result = wp_mail(
			$args['recipient'],
			$args['subject'],
			$args['body'],
			$args['additional_headers']
		);

		$this->getLogger()->info( 'OptIn mail sent via Avada', [
			'plugin'    => 'double-opt-in',
			'form_id'   => $formData->getFormId(),
			'recipient' => $args['recipient'],
			'result'    => $result,
		] );

		return $result;
	}

	/**
	 * {@inheritdoc}
	 */
	public function sendConfirmationMail( OptIn $optIn ): void {
		// Only handle if this is an Avada opt-in
		if ( ! $optIn->isType( $this->getIdentifier() ) ) {
			return;
		}

		$this->getLogger()->debug( 'Sending Avada confirmation mail', [
			'plugin'   => 'double-opt-in',
			'form_id'  => $optIn->get_cf_form_id(),
			'optin_id' => $optIn->get_id(),
		] );

		$formData = maybe_unserialize( $optIn->get_content() );
		$formData = SanitizeHelper::sanitize_array( $formData );

		if ( ! isset( $formData['data'] ) ) {
			$this->getLogger()->warning( 'No form data in OptIn for confirmation', [
				'plugin' => 'double-opt-in',
			] );
			return;
		}

		// Reconstruct POST data for Avada
		$data           = $formData['data'];
		$formDataString = [];

		foreach ( $data as $key => $value ) {
			if ( is_array( $value ) ) {
				$value = implode( ',', $value );
			}
			// URL-encode the values for proper parsing by Avada
			$formDataString[] = rawurlencode( $key ) . '=' . rawurlencode( $value );
		}

		$this->getLogger()->debug( 'Reconstructed Avada POST data', [
			'plugin'      => 'double-opt-in',
			'form_id'     => $optIn->get_cf_form_id(),
			'data_keys'   => array_keys( $data ),
			'labels_keys' => array_keys( $formData['field_labels'] ?? [] ),
		] );

		$_POST = [
			'formData'           => implode( '&', $formDataString ),
			'field_labels'       => wp_json_encode( $formData['field_labels'] ?? [] ),
			'field_types'        => wp_json_encode( $formData['field_types'] ?? [] ),
			'hidden_field_names' => wp_json_encode( $formData['hidden_field_names'] ?? [] ),
			'form_id'            => $optIn->get_cf_form_id(),
		];

		// Load Avada form submit handler
		$avadaSubmitFile = dirname( F12_DOUBLEOPTIN_PLUGIN_FILE ) . '/compatibility/avada/AvadaFormSubmit.class.php';
		if ( file_exists( $avadaSubmitFile ) ) {
			require_once $avadaSubmitFile;
		}

		$formSubmit = new AvadaFormSubmit();
		// Skip security checks since form was already validated during initial submission
		$formSubmit->set_skip_security_checks( true );
		$result = $formSubmit->submit( $optIn->get_cf_form_id(), [] );

		$this->getLogger()->info( 'Avada confirmation mail triggered', [
			'plugin'   => 'double-opt-in',
			'form_id'  => $optIn->get_cf_form_id(),
			'optin_id' => $optIn->get_id(),
			'result'   => $result,
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

		$hash  = sanitize_text_field( $_GET['optin'] );
		$optIn = OptIn::get_by_hash( $hash );

		// Only process if it's an Avada opt-in
		if ( $optIn && $optIn->isType( $this->getIdentifier() ) ) {
			$this->validateOptIn( $hash );
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
		if ( ! $post || $post->post_type !== 'fusion_form' ) {
			return [];
		}

		$fields  = [];
		$content = $post->post_content;

		// Parse Avada form shortcodes to extract field names
		// Avada uses shortcodes like [fusion_form_text name="email" /], [fusion_form_email name="email" /], etc.
		$pattern = '/\[fusion_form_(?:text|email|phone|textarea|select|checkbox|radio|range|rating|date|time|upload|image_select|hidden|password|number|recaptcha|turnstile|hcaptcha|notice|honeypot)[^\]]*\s+name=["\']([^"\']+)["\'][^\]]*\]/i';

		if ( preg_match_all( $pattern, $content, $matches ) ) {
			foreach ( $matches[1] as $fieldName ) {
				$fieldName = trim( $fieldName );
				if ( ! empty( $fieldName ) ) {
					$fields[ $fieldName ] = $fieldName;
				}
			}
		}

		// Also try to extract from label attribute for better display
		$patternWithLabel = '/\[fusion_form_(?:text|email|phone|textarea|select|checkbox|radio|range|rating|date|time|upload|image_select|hidden|password|number)[^\]]*\s+name=["\']([^"\']+)["\'][^\]]*\s+label=["\']([^"\']+)["\'][^\]]*\]/i';

		if ( preg_match_all( $patternWithLabel, $content, $matchesWithLabel ) ) {
			foreach ( $matchesWithLabel[1] as $index => $fieldName ) {
				$fieldName = trim( $fieldName );
				$label     = trim( $matchesWithLabel[2][ $index ] );
				if ( ! empty( $fieldName ) && ! empty( $label ) ) {
					$fields[ $fieldName ] = $label;
				}
			}
		}

		// Try alternative pattern where name comes after label
		$patternAlt = '/\[fusion_form_(?:text|email|phone|textarea|select|checkbox|radio|range|rating|date|time|upload|image_select|hidden|password|number)[^\]]*\s+label=["\']([^"\']+)["\'][^\]]*\s+name=["\']([^"\']+)["\'][^\]]*\]/i';

		if ( preg_match_all( $patternAlt, $content, $matchesAlt ) ) {
			foreach ( $matchesAlt[2] as $index => $fieldName ) {
				$fieldName = trim( $fieldName );
				$label     = trim( $matchesAlt[1][ $index ] );
				if ( ! empty( $fieldName ) && ! empty( $label ) ) {
					$fields[ $fieldName ] = $label;
				}
			}
		}

		$this->getLogger()->debug( 'Extracted Avada form fields', [
			'plugin'  => 'double-opt-in',
			'form_id' => $formId,
			'fields'  => array_keys( $fields ),
		] );

		return $fields;
	}

	/**
	 * Add metabox tab to Avada form editor.
	 *
	 * @param array  $panels    The panels array.
	 * @param string $postType  The post type.
	 *
	 * @return array Modified panels.
	 */
	public function addMetaboxTab( array $panels, $postType ): array {
		global $post;

		if ( $post && $post->post_type === 'fusion_form' && $postType === 'default' ) {
			$panels['tabs_names']['f12cf7doubleoptin'] = $this->getPanelTitle();
			$panels['requested_tabs'][]               = 'f12cf7doubleoptin';
		}

		return $panels;
	}

	/**
	 * Get metadata value for Avada metabox.
	 *
	 * @param mixed  $ret     Current value.
	 * @param string $fieldId Field ID.
	 *
	 * @return mixed The value.
	 */
	public function getMetaValue( $ret, $fieldId ) {
		global $post;

		if ( ! $post || ! isset( $post->ID ) ) {
			return $ret;
		}

		$metadata = $this->getFormParameter( $post->ID );

		// Create prefixed keys for Avada metabox
		foreach ( $metadata as $key => $value ) {
			$metadata[ 'doubleoptin[' . $key . ']' ] = $value;
		}

		return $metadata[ $fieldId ] ?? $ret;
	}

	/**
	 * {@inheritdoc}
	 */
	public function render( $form, array $metadata ): void {
		// Avada uses a different rendering approach
		$this->renderTabContent( [] );
	}

	/**
	 * Render the tab content for Avada metabox.
	 *
	 * Displays a notice with link to central form management.
	 * Full settings are now managed centrally in the Forms admin page.
	 *
	 * @param array $tabData The tab data.
	 *
	 * @return array Modified tab data.
	 */
	public function renderTabContent( $tabData ): array {
		global $post;

		$metadata   = $this->getFormParameter( $post->ID );
		$centralUrl = admin_url( 'admin.php?page=f12-cf7-doubleoptin_forms' );
		$isEnabled  = $this->isOptInEnabled( $post->ID );

		$this->getLogger()->debug( 'Rendering Avada panel notice', [
			'plugin'  => 'double-opt-in',
			'form_id' => $post->ID,
			'enabled' => $isEnabled,
		] );

		// Build the status HTML
		$statusHtml = $isEnabled
			? '<span style="display: inline-block; padding: 4px 12px; background: #d4edda; color: #155724; border-radius: 3px; font-weight: 500;">' . __( 'Enabled', 'double-opt-in' ) . '</span>'
			: '<span style="display: inline-block; padding: 4px 12px; background: #f8d7da; color: #721c24; border-radius: 3px; font-weight: 500;">' . __( 'Disabled', 'double-opt-in' ) . '</span>';

		// Build the notice HTML
		$noticeHtml = sprintf(
			'<div style="padding: 20px; background: #fff; border: 1px solid #c3c4c7; border-radius: 4px;">' .
			'<h3 style="margin-top: 0;">%s</h3>' .
			'<div style="display: flex; align-items: center; gap: 15px; margin-bottom: 20px;">' .
			'<span style="font-weight: 600;">%s</span> %s' .
			'</div>' .
			'<p style="color: #666; margin-bottom: 20px;">%s</p>' .
			'<a href="%s" class="button button-primary" target="_blank">%s</a>' .
			'</div>',
			__( 'Double Opt-In Settings', 'double-opt-in' ),
			__( 'Status:', 'double-opt-in' ),
			$statusHtml,
			__( 'Double Opt-In settings are now managed centrally. Use the button below to configure this form.', 'double-opt-in' ),
			esc_url( $centralUrl ),
			__( 'Configure Double Opt-In', 'double-opt-in' )
		);

		$fields = [
			[
				'id'          => 'doubleoptin_notice',
				'label'       => '',
				'description' => $noticeHtml,
				'default'     => '',
				'dependency'  => '',
				'type'        => 'custom',
			],
		];

		$tabData['f12cf7doubleoptin']['fields'] = $fields;

		return $tabData;
	}

	/**
	 * {@inheritdoc}
	 */
	public function save( int $formId, array $data ): bool {
		if ( ! class_exists( '\Fusion_Data_PostMeta' ) ) {
			return false;
		}

		if ( ! isset( $data[ \Fusion_Data_PostMeta::ROOT ]['doubleoptin'] ) ) {
			return false;
		}

		$parameter = SanitizeHelper::sanitize_array( $data[ \Fusion_Data_PostMeta::ROOT ]['doubleoptin'] );

		if ( isset( $data[ \Fusion_Data_PostMeta::ROOT ]['_doubleoptin'] ) ) {
			$parameter = array_merge(
				$parameter,
				SanitizeHelper::sanitize_array( $data[ \Fusion_Data_PostMeta::ROOT ]['_doubleoptin'] )
			);
		}

		$metadata = $this->getFormParameter( $formId );

		foreach ( $metadata as $key => $value ) {
			if ( isset( $parameter[ $key ] ) ) {
				if ( in_array( $key, [ 'page', 'enable' ], true ) ) {
					$metadata[ $key ] = (int) $parameter[ $key ];
				} elseif ( $key === 'body' || $key === 'sender' ) {
					$metadata[ $key ] = $parameter[ $key ];
				} else {
					$metadata[ $key ] = sanitize_text_field( $parameter[ $key ] );
				}
			} elseif ( $key === 'enable' ) {
				$metadata[ $key ] = 0;
			}
		}

		$metadata = apply_filters( 'f12_cf7_doubleoptin_metadata_avada', $metadata );
		$metadata = apply_filters( 'f12_cf7_doubleoptin_save_form', $metadata );

		update_post_meta( $formId, 'f12-cf7-doubleoptin', $metadata );

		return true;
	}

	/**
	 * Save form settings callback.
	 *
	 * @param int $postId The post ID.
	 *
	 * @return void
	 */
	public function saveFormSettings( int $postId ): void {
		$this->save( $postId, $_POST );
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

		try {
			if ( class_exists( EmailTemplateIntegration::class ) ) {
				$integration = new EmailTemplateIntegration();
				$custom      = $integration->getCustomTemplates();

				foreach ( $custom as $template ) {
					$templates[ 'custom_' . $template['id'] ] = $template['title'] . ' (' . __( 'Custom', 'double-opt-in' ) . ')';
				}
			}
		} catch ( \Exception $e ) {
			$this->getLogger()->warning( 'Failed to load custom templates', [
				'plugin' => 'double-opt-in',
				'error'  => $e->getMessage(),
			] );
		}

		return $templates;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getAvailableCategories(): array {
		$categories = [ 0 => '---' ];

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

	/**
	 * Get available pages for confirmation page dropdown.
	 *
	 * @return array<int, string>
	 */
	private function getPages(): array {
		$pages  = get_pages();
		$result = [];

		foreach ( $pages as $page ) {
			$result[ $page->ID ] = $page->post_title;
		}

		return $result;
	}

	/**
	 * Render custom template options HTML.
	 *
	 * @param array $metadata Current form metadata.
	 *
	 * @return string HTML for custom template selection.
	 */
	private function renderCustomTemplateOptions( array $metadata ): string {
		$html = '';

		try {
			if ( ! class_exists( EmailTemplateIntegration::class ) ) {
				return $html;
			}

			$integration     = new EmailTemplateIntegration();
			$customTemplates = $integration->getCustomTemplates();

			if ( empty( $customTemplates ) ) {
				return $html;
			}

			$selectedTemplate = $metadata['template'] ?? '';

			$html .= '<div class="avada-custom-templates-section">';
			$html .= '<h4>' . __( 'Custom Templates', 'double-opt-in' ) . '</h4>';
			$html .= '<div class="avada-custom-templates-list">';

			foreach ( $customTemplates as $template ) {
				$templateKey = 'custom_' . $template['id'];
				$isActive    = $selectedTemplate === $templateKey;

				$html .= '<div class="avada-custom-template-preview ' . ( $isActive ? 'active' : '' ) . '" ';
				$html .= 'data-template="' . esc_attr( $templateKey ) . '" ';
				$html .= 'title="' . esc_attr( $template['title'] ) . '">';
				$html .= '<span class="template-name">' . esc_html( $template['title'] ) . '</span>';
				$html .= '</div>';
			}

			$html .= '</div></div>';

		} catch ( \Exception $e ) {
			$this->getLogger()->warning( 'Failed to render custom template options', [
				'plugin' => 'double-opt-in',
				'error'  => $e->getMessage(),
			] );
		}

		return $html;
	}
}
