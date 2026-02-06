<?php

namespace forge12\contactform7\CF7DoubleOptIn {

	use forge12\plugins\ContactForm7;
	use Forge12\Shared\LoggerInterface;
	use Forge12\DoubleOptIn\EmailTemplates\PlaceholderMapper;

	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	/**
	 * Class Backend
	 * Responsible to handle the admin settings for the double opt-in field
	 *
	 * @package forge12\contactform7\CF7OptIn
	 *
	 * @deprecated 4.0.0 Use \Forge12\DoubleOptIn\Integration\CF7Integration instead.
	 *             This class is maintained for backward compatibility only.
	 * @see \Forge12\DoubleOptIn\Integration\CF7Integration
	 * @see \Forge12\DoubleOptIn\Integration\AdminPanelInterface
	 */
	class CF7Backend {
		private LoggerInterface $logger;

		/**
		 * Admin constructor.
		 */
		public function __construct( LoggerInterface $logger ) {
			$this->logger = $logger;

			$this->get_logger()->debug( 'Admin constructor called', [
				'plugin' => 'double-opt-in',
				'class'  => __CLASS__,
				'method' => __METHOD__,
			] );

			add_action( 'admin_init', [ $this, 'addHooks' ] );
			$this->get_logger()->debug( 'Hook admin_init registered', [
				'plugin' => 'double-opt-in',
			] );

			add_action( 'admin_enqueue_scripts', [ $this, 'addStyles' ] );
			$this->get_logger()->debug( 'Hook admin_enqueue_scripts registered', [
				'plugin' => 'double-opt-in',
			] );
		}


		public function get_logger() {
			return $this->logger;
		}

		/**
		 * Add the styles for the form
		 */
		public function addStyles( $hook ) {
			$this->get_logger()->debug( 'Enqueuing admin styles and scripts', [
				'plugin' => 'double-opt-in',
				'class'  => __CLASS__,
				'method' => __METHOD__,
				'hook'   => $hook,
			] );

			wp_enqueue_script(
				'f12-cf7-doubleoptin-admin',
				plugins_url( 'assets/f12-cf7-popup.js', __FILE__ ),
				[ 'jquery' ]
			);
			$this->get_logger()->debug( 'Admin popup script enqueued', [
				'plugin' => 'double-opt-in',
			] );

			wp_localize_script(
				'f12-cf7-doubleoptin-admin',
				'doi',
				[
					'ajax_url' => admin_url( 'admin-ajax.php' ),
					'nonce'    => wp_create_nonce( 'f12_doi_details' ),
				]
			);
			$this->get_logger()->debug( 'Admin popup script localized', [
				'plugin' => 'double-opt-in',
			] );

			wp_enqueue_script(
				'f12-cf7-doubleoptin-templateloader',
				plugins_url( 'assets/f12-cf7-templateloader.js', __FILE__ ),
				[ 'jquery' ]
			);
			$this->get_logger()->debug( 'Template loader script enqueued', [
				'plugin' => 'double-opt-in',
			] );

			wp_localize_script(
				'f12-cf7-doubleoptin-templateloader',
				'templateloader',
				[
					'ajax_url'          => admin_url( 'admin-ajax.php' ),
					'nonce'             => wp_create_nonce( 'f12_doi_templateloader' ),
					'label_placeholder' => __( 'Please wait while we load the template...', 'double-opt-in' ),
				]
			);
			$this->get_logger()->debug( 'Template loader script localized', [
				'plugin' => 'double-opt-in',
			] );
		}


		/**
		 * Add the hooks responsible to handle wordpress functions
		 */
		public function addHooks() {
			$this->get_logger()->debug( 'Registering CF7 admin hooks', [
				'plugin' => 'double-opt-in',
				'class'  => __CLASS__,
				'method' => __METHOD__,
			] );

			add_filter( 'wpcf7_editor_panels', [ $this, 'addPanel' ], 10, 1 );
			$this->get_logger()->debug( 'Filter wpcf7_editor_panels registered', [
				'plugin' => 'double-opt-in',
			] );

			add_action( 'wpcf7_save_contact_form', [ $this, 'save' ], 10, 3 );
			$this->get_logger()->debug( 'Action wpcf7_save_contact_form registered', [
				'plugin' => 'double-opt-in',
			] );
		}


		/**
		 * Add a custom panel to the contact form 7 options
		 */
		public function addPanel( array $panels ) {
			$this->get_logger()->debug( 'Adding CF7 editor panel', [
				'plugin' => 'double-opt-in',
				'class'  => __CLASS__,
				'method' => __METHOD__,
			] );

			$panels['optin'] = [
				'title'    => __( 'Double-Opt-in', 'double-opt-in' ),
				'callback' => [ $this, 'render' ],
			];

			$this->get_logger()->debug( 'CF7 editor panel added', [
				'plugin' => 'double-opt-in',
			] );

			return $panels;
		}


		/**
		 * On Contact Form save store the information in the database
		 *
		 * @param \WPCF7_ContactForm $contact_form
		 * @param                    $args
		 * @param                    $context
		 */
		public function save( $contact_form, $args, $context ) {
			$postID = $contact_form->id();

			$this->get_logger()->debug( 'Saving CF7 Double Opt-In settings', [
				'plugin'  => 'double-opt-in',
				'class'   => __CLASS__,
				'method'  => __METHOD__,
				'post_id' => $postID,
			] );

			// Validate nonce
			if ( ! isset( $_POST['f12_cf7_doubleoptin_save_form_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['f12_cf7_doubleoptin_save_form_nonce'] ), 'f12_cf7_doubleoptin_save_form_action' ) ) {
				$this->get_logger()->warning( 'Nonce validation failed during save', [
					'plugin'  => 'double-opt-in',
					'post_id' => $postID,
				] );
				return;
			}

			// Check if the double opt-in settings is set
			if ( ! $postID || ! isset( $_POST['doubleoptin'] ) ) {
				update_post_meta( $postID, 'f12-cf7-doubleoptin', [] );
				$this->get_logger()->debug( 'No doubleoptin data found, cleared settings', [
					'plugin'  => 'double-opt-in',
					'post_id' => $postID,
				] );
				return;
			}

			$parameter = SanitizeHelper::sanitize_array( $_POST['doubleoptin'] );
			$metadata  = CF7DoubleOptIn::getInstance()->getParameter( $postID );

			foreach ( $metadata as $key => $value ) {
				if ( isset( $parameter[ $key ] ) ) {
					if ( $key === 'body' ) {
						$metadata[ $key ] = $parameter[ $key ];
					} elseif ( $key === 'enable' ) {
						$metadata[ $key ] = (int) $parameter[ $key ];
					} elseif ( $key === 'sender' ) {
						$metadata[ $key ] = $parameter[ $key ];
					} else {
						$metadata[ $key ] = $parameter[ $key ];
					}
				} elseif ( $key === 'enable' ) {
					$metadata[ $key ] = 0;
				}
			}

			$metadata = apply_filters( 'f12_cf7_doubleoptin_metadata_cf7', $metadata );
			$metadata = apply_filters( 'f12_cf7_doubleoptin_save_form', $metadata );

			update_post_meta( $postID, 'f12-cf7-doubleoptin', $metadata );

			// Save placeholder mapping
			if ( isset( $_POST['doubleoptin']['placeholder_mapping'] ) && is_array( $_POST['doubleoptin']['placeholder_mapping'] ) ) {
				$mapping = array_map( 'sanitize_text_field', $_POST['doubleoptin']['placeholder_mapping'] );
				PlaceholderMapper::saveCustomMapping( $postID, $mapping, 'cf7' );

				$this->get_logger()->debug( 'Placeholder mapping saved', [
					'plugin'  => 'double-opt-in',
					'post_id' => $postID,
					'mapping' => $mapping,
				] );
			}

			$this->get_logger()->info( 'Double Opt-In settings saved', [
				'plugin'  => 'double-opt-in',
				'post_id' => $postID,
				'data'    => $metadata,
			] );
		}


		/**
		 * Get all available templates including custom ones.
		 *
		 * @return array Array of template key => label.
		 */
		private function getAvailableTemplates(): array {
			$templates = [
				'blank'           => 'blank',
				'newsletter_en'   => 'newsletter_en',
				'newsletter_en_2' => 'newsletter_en_2',
				'newsletter_en_3' => 'newsletter_en_3',
			];

			// Add custom templates from the Email Template Editor
			try {
				$container = \Forge12\DoubleOptIn\Container\Container::getInstance();
				$integration = $container->get( \Forge12\DoubleOptIn\EmailTemplates\EmailTemplateIntegration::class );
				$customTemplates = $integration->getCustomTemplates();

				foreach ( $customTemplates as $template ) {
					$templates[ 'custom_' . $template['id'] ] = $template['title'] . ' (' . __( 'Custom', 'double-opt-in' ) . ')';
				}
			} catch ( \Exception $e ) {
				// Silently fail if integration is not available
				$this->get_logger()->warning( 'Failed to load custom templates: ' . $e->getMessage(), [
					'plugin' => 'double-opt-in',
				] );
			}

			return $templates;
		}

		/**
		 * Return an option list containing all tags for the condition and a default field to disable the condition.
		 *
		 * @param \WPCF7_ContactForm $post
		 *
		 * @return array
		 */
		private function getTags( $post ) {
			$this->get_logger()->debug( 'Fetching CF7 form tags', [
				'plugin'  => 'double-opt-in',
				'class'   => __CLASS__,
				'method'  => __METHOD__,
				'post_id' => $post->id() ?? null,
			] );

			$tags      = [];
			$arrayTags = $post->scan_form_tags();

			foreach ( $arrayTags as $formTag /** @var \WPCF7_FormTag $formTag */ ) {
				if ( ! empty( $formTag->name ) ) {
					$tags[ $formTag->name ] = $formTag->name;
				}
			}

			$this->get_logger()->debug( 'Form tags fetched', [
				'plugin' => 'double-opt-in',
				'count'  => count( $tags ),
			] );

			return $tags;
		}


		/**
		 * Show the backend double opt in options
		 *
		 * Displays a notice with link to central form management.
		 * Full settings are now managed centrally in the Forms admin page.
		 *
		 * @param \WPCF7_ContactForm $post
		 */
		public function render( $post ) {
			if ( ! $post ) {
				return;
			}

			$metadata   = CF7DoubleOptIn::getInstance()->getParameter( $post->id() );
			$centralUrl = admin_url( 'admin.php?page=f12-cf7-doubleoptin_forms' );
			$isEnabled  = isset( $metadata['enable'] ) && $metadata['enable'] == 1;

			$this->get_logger()->debug( 'Rendering CF7 panel notice', [
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

					<a href="<?php echo esc_url( $centralUrl ); ?>" class="button button-primary">
						<?php _e( 'Configure Double Opt-In', 'double-opt-in' ); ?>
					</a>
				</div>
			</div>
			<?php
		}

		/**
		 * Render the placeholder mapping UI.
		 *
		 * @param \WPCF7_ContactForm $post     The contact form.
		 * @param array              $metadata Form metadata.
		 * @param string             $id       Form element ID prefix.
		 * @return void
		 */
		private function renderPlaceholderMappingUI( $post, array $metadata, string $id ): void {
			$formFields = array_keys( $this->getTags( $post ) );
			$standardPlaceholders = PlaceholderMapper::getStandardPlaceholders();
			$autoMapping = PlaceholderMapper::autoDetectMapping( $formFields );
			$customMapping = PlaceholderMapper::getCustomMapping( $post->id(), 'cf7' );

			// Merge for display (custom takes precedence)
			$effectiveMapping = array_merge( $autoMapping, $customMapping );
			?>
			<div class="box" style="width:100%;">
				<h2><?php _e( 'Standard Placeholders Mapping', 'double-opt-in' ); ?></h2>
				<p class="description" style="margin-bottom: 15px;">
					<?php _e( 'Map your form fields to standard placeholders. This allows you to use the same email template across different forms. Fields are auto-detected based on common naming patterns, but you can override them.', 'double-opt-in' ); ?>
				</p>

				<table class="widefat" style="margin-bottom: 20px;">
					<thead>
						<tr>
							<th style="width: 200px;"><?php _e( 'Standard Placeholder', 'double-opt-in' ); ?></th>
							<th style="width: 150px;"><?php _e( 'Auto-Detected', 'double-opt-in' ); ?></th>
							<th><?php _e( 'Custom Mapping (Override)', 'double-opt-in' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $standardPlaceholders as $placeholder => $config ) :
							$autoDetected = isset( $autoMapping[ $placeholder ] ) ? $autoMapping[ $placeholder ] : '';
							$customValue = isset( $customMapping[ $placeholder ] ) ? $customMapping[ $placeholder ] : '';
							?>
							<tr>
								<td>
									<code>[<?php echo esc_html( $placeholder ); ?>]</code>
									<br>
									<small style="color: #666;"><?php echo esc_html( $config['label'] ); ?></small>
								</td>
								<td>
									<?php if ( $autoDetected ) : ?>
										<span style="color: #46b450;">
											<code>[<?php echo esc_html( $autoDetected ); ?>]</code>
										</span>
									<?php else : ?>
										<span style="color: #999;"><?php _e( 'Not detected', 'double-opt-in' ); ?></span>
									<?php endif; ?>
								</td>
								<td>
									<select name="doubleoptin[placeholder_mapping][<?php echo esc_attr( $placeholder ); ?>]"
									        style="width: 100%; max-width: 300px;">
										<option value=""><?php
											if ( $autoDetected ) {
												printf( __( 'Use auto-detected: [%s]', 'double-opt-in' ), $autoDetected );
											} else {
												_e( '-- Select field --', 'double-opt-in' );
											}
										?></option>
										<?php foreach ( $formFields as $field ) : ?>
											<option value="<?php echo esc_attr( $field ); ?>"
												<?php selected( $customValue, $field ); ?>>
												[<?php echo esc_html( $field ); ?>]
											</option>
										<?php endforeach; ?>
									</select>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<div style="background: #f9f9f9; padding: 15px; border-radius: 4px; border: 1px solid #ddd;">
					<h4 style="margin-top: 0;"><?php _e( 'How to use', 'double-opt-in' ); ?></h4>
					<p style="margin-bottom: 10px;">
						<?php _e( 'In your email template, use these standard placeholders instead of form-specific ones:', 'double-opt-in' ); ?>
					</p>
					<div style="display: flex; flex-wrap: wrap; gap: 10px;">
						<?php foreach ( $standardPlaceholders as $placeholder => $config ) : ?>
							<code style="background: #fff; padding: 3px 8px; border: 1px solid #ddd; border-radius: 3px;">
								[<?php echo esc_html( $placeholder ); ?>]
							</code>
						<?php endforeach; ?>
					</div>
					<p style="margin-top: 15px; margin-bottom: 0;">
						<strong><?php _e( 'System Placeholders (always available):', 'double-opt-in' ); ?></strong><br>
						<code>[doubleoptinlink]</code>,
						<code>[doubleoptoutlink]</code>,
						<code>[doubleoptin_form_date]</code>,
						<code>[doubleoptin_form_time]</code>,
						<code>[doubleoptin_form_url]</code>
					</p>
				</div>
			</div>
			<?php
		}
	}
}