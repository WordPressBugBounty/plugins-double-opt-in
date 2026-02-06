<?php

namespace forge12\contactform7\CF7DoubleOptIn {

	use Forge12\Shared\Logger;
	use Forge12\Shared\LoggerInterface;
	use Forge12\DoubleOptIn\EmailTemplates\EmailTemplateIntegration;

	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	/**
	 * Class AvadaFormOptions
	 * Add the OptIn Options to the Avada Form
	 *
	 * @package forge12\contactform7\CF7OptIn
	 *
	 * @deprecated 4.0.0 Use \Forge12\DoubleOptIn\Integration\AvadaIntegration instead.
	 *             This class is maintained for backward compatibility only.
	 * @see \Forge12\DoubleOptIn\Integration\AvadaIntegration
	 * @see \Forge12\DoubleOptIn\Integration\AdminPanelInterface
	 */
	class AvadaFormOptions {
		private LoggerInterface $logger;

		/**
		 * Admin constructor.
		 */
		public function __construct( Logger $logger ) {
			$this->logger = $logger;

			add_action( 'admin_init', array( $this, 'addHooks' ) );
			$this->get_logger()->debug( 'Hook admin_init registered', [
				'plugin' => 'double-opt-in',
			] );

			add_action( 'admin_enqueue_scripts', array( $this, 'addStyles' ) );
			$this->get_logger()->debug( 'Hook admin_enqueue_scripts registered', [
				'plugin' => 'double-opt-in',
			] );

			add_action( 'save_post', array( $this, 'save' ), 20, 1 );
			$this->get_logger()->debug( 'Hook save_post registered', [
				'plugin' => 'double-opt-in',
			] );

			add_filter( 'awb_po_get_value', array( $this, 'get_value' ), 10, 2 );
			$this->get_logger()->debug( 'Filter awb_po_get_value registered', [
				'plugin' => 'double-opt-in',
			] );
		}

		public function get_logger() {
			return $this->logger;
		}

		public function get_value( $ret, $field_id ) {
			global $post;

			$this->get_logger()->debug( 'get_value called', [
				'plugin'   => 'double-opt-in',
				'class'    => __CLASS__,
				'method'   => __METHOD__,
				'field_id' => $field_id,
				'post_id'  => $post->ID ?? null,
			] );

			if ( ! $post || ! isset( $post->ID ) ) {
				$this->get_logger()->debug( 'No post context available, returning default', [
					'plugin' => 'double-opt-in',
				] );
				return $ret;
			}

			$metadata = CF7DoubleOptIn::getInstance()->getParameter( $post->ID );

			foreach ( $metadata as $key => $value ) {
				$metadata[ 'doubleoptin[' . $key . ']' ] = $value;
			}

			if ( isset( $metadata[ $field_id ] ) ) {
				$this->get_logger()->debug( 'Value found for field_id', [
					'plugin'   => 'double-opt-in',
					'field_id' => $field_id,
					'value'    => $metadata[ $field_id ],
				] );
				$ret = $metadata[ $field_id ];
			} else {
				$this->get_logger()->debug( 'No value found for field_id, returning default', [
					'plugin'   => 'double-opt-in',
					'field_id' => $field_id,
				] );
			}

			return $ret;
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

			wp_enqueue_style(
				'f12-avada-doubleoptin-admin-avada',
				plugins_url( 'assets/admin-avada-style.css', __FILE__ )
			);
			$this->get_logger()->debug( 'Admin stylesheet enqueued', [
				'plugin' => 'double-opt-in',
			] );

			wp_enqueue_script(
				'f12-avada-doubleoptin-templateloader',
				plugins_url( 'assets/f12-avada-templateloader.js', __FILE__ ),
				array( 'jquery' )
			);
			$this->get_logger()->debug( 'Template loader script enqueued', [
				'plugin' => 'double-opt-in',
			] );

			wp_localize_script(
				'f12-avada-doubleoptin-templateloader',
				'templateloader',
				array(
					'ajax_url'          => admin_url( 'admin-ajax.php' ),
					'nonce'             => wp_create_nonce( 'f12_doi_templateloader' ),
					'label_placeholder' => __( 'Please wait while we load the template...', 'double-opt-in' )
				)
			);
			$this->get_logger()->debug( 'Template loader script localized', [
				'plugin' => 'double-opt-in',
			] );
		}


		/**
		 * Add the hooks responsible to handle wordpress functions
		 */
		public function addHooks() {
			$this->get_logger()->debug( 'Registering admin hooks', [
				'plugin' => 'double-opt-in',
				'class'  => __CLASS__,
				'method' => __METHOD__,
			] );

			add_filter( 'avada_metabox_tabs', array( $this, 'addPanel' ), 10, 2 );
			$this->get_logger()->debug( 'Filter avada_metabox_tabs registered', [
				'plugin' => 'double-opt-in',
			] );

			add_filter( 'f12_cf7_doubleoptin_avada_form_panel', array( $this, 'render' ), 10, 1 );
			$this->get_logger()->debug( 'Filter f12_cf7_doubleoptin_avada_form_panel registered', [
				'plugin' => 'double-opt-in',
			] );

			add_action( 'wpcf7_save_contact_form', array( $this, 'save' ), 10, 3 );
			$this->get_logger()->debug( 'Action wpcf7_save_contact_form registered', [
				'plugin' => 'double-opt-in',
			] );
		}


		/**
		 * Add a custom panel to the contact form 7 options
		 */
		public function addPanel( array $panels, $post_type ) {
			global $post;

			$this->get_logger()->debug( 'addPanel called', [
				'plugin'    => 'double-opt-in',
				'class'     => __CLASS__,
				'method'    => __METHOD__,
				'post_type' => $post_type,
				'post_id'   => $post->ID ?? null,
			] );

			if ( $post && $post->post_type === 'fusion_form' ) {
				if ( $post_type === 'default' ) {
					$panels['tabs_names']['f12cf7doubleoptin'] = __( 'Double-Opt-in', 'double-opt-in' );
					$panels['requested_tabs'][]                = 'f12cf7doubleoptin';

					$this->get_logger()->debug( 'Double-Opt-in panel added to Avada metabox', [
						'plugin'  => 'double-opt-in',
						'post_id' => $post->ID,
					] );
				}
			}

			return $panels;
		}


		/**
		 * On Contact Form save store the information in the database
		 *
		 * @param $postID
		 */
		public function save( $postID ) {
			$this->get_logger()->debug( 'Save called', [
				'plugin'  => 'double-opt-in',
				'class'   => __CLASS__,
				'method'  => __METHOD__,
				'post_id' => $postID,
			] );

			if ( ! class_exists( '\Fusion_Data_PostMeta' ) ) {
				$this->get_logger()->debug( 'Fusion_Data_PostMeta not available, aborting save', [
					'plugin' => 'double-opt-in',
				] );
				return;
			}

			if ( ! isset( $_POST[ \Fusion_Data_PostMeta::ROOT ] ) || ! isset( $_POST[ \Fusion_Data_PostMeta::ROOT ]['doubleoptin'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
				$this->get_logger()->debug( 'No doubleoptin data found in POST, aborting save', [
					'plugin' => 'double-opt-in',
				] );
				return;
			}

			$parameter = SanitizeHelper::sanitize_array( $_POST[ \Fusion_Data_PostMeta::ROOT ]['doubleoptin'] );
			$parameter = array_merge(
				$parameter,
				SanitizeHelper::sanitize_array( $_POST[ \Fusion_Data_PostMeta::ROOT ]['_doubleoptin'] )
			);

			$metadata = CF7DoubleOptIn::getInstance()->getParameter( $postID );

			foreach ( $metadata as $key => $value ) {
				if ( isset( $parameter[ $key ] ) ) {
					if ( $key === 'body' ) {
						$metadata[ $key ] = $parameter[ $key ];
					} elseif ( $key === 'sender' ) {
						$metadata[ $key ] = $parameter[ $key ];
					} elseif ( $key === 'page' || $key === 'enable' ) {
						$metadata[ $key ] = (int) $parameter[ $key ];
					} else {
						$metadata[ $key ] = sanitize_text_field( $parameter[ $key ] );
					}
				} elseif ( $key === 'enable' ) {
					$metadata[ $key ] = 0;
				}
			}

			$metadata = apply_filters( 'f12_cf7_doubleoptin_metadata_avada', $metadata );
			$metadata = apply_filters( 'f12_cf7_doubleoptin_save_form', $metadata );

			update_post_meta( $postID, 'f12-cf7-doubleoptin', $metadata );

			$this->get_logger()->debug( 'Metadata saved for post', [
				'plugin'  => 'double-opt-in',
				'post_id' => $postID,
				'data'    => $metadata,
			] );
		}


		/**
		 * @return array
		 */
		private function getPages() {
			$this->get_logger()->debug( 'Fetching pages', [
				'plugin' => 'double-opt-in',
				'class'  => __CLASS__,
				'method' => __METHOD__,
			] );

			$value = array();
			$pages = get_pages();

			foreach ( $pages as $page ) {
				$value[ $page->ID ] = $page->post_title;
			}

			$this->get_logger()->debug( 'Pages fetched', [
				'plugin' => 'double-opt-in',
				'count'  => count( $value ),
			] );

			return $value;
		}


		/**
		 * Render custom template options HTML.
		 *
		 * @param array $metadata Current form metadata.
		 * @return string HTML for custom template selection.
		 */
		private function renderCustomTemplateOptions( array $metadata ): string {
			$html = '';

			try {
				if ( ! class_exists( EmailTemplateIntegration::class ) ) {
					return $html;
				}

				$integration = new EmailTemplateIntegration();
				$customTemplates = $integration->getCustomTemplates();

				if ( empty( $customTemplates ) ) {
					return $html;
				}

				$selectedTemplate = $metadata['template'] ?? '';

				// Enqueue styles and scripts via admin_footer
				add_action( 'admin_footer', function() {
					?>
					<script type="text/javascript">
						jQuery(document).ready(function($) {
							$('.avada-custom-template-preview').on('click', function() {
								var template = $(this).data('template');
								$('.f12-cf7-templateloader').val(template).trigger('change');
								$('.avada-custom-template-preview').removeClass('active');
								$(this).addClass('active');
							});
						});
					</script>
					<style type="text/css">
						.avada-custom-templates-section {
							margin-top: 20px;
							padding-top: 20px;
							border-top: 1px solid #ddd;
						}
						.avada-custom-templates-section h4 {
							margin-bottom: 10px;
							font-size: 14px;
						}
						.avada-custom-templates-list {
							display: flex;
							flex-wrap: wrap;
							gap: 10px;
							margin-top: 10px;
						}
						.avada-custom-template-preview {
							width: 120px;
							height: 80px;
							background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
							border-radius: 4px;
							display: flex;
							flex-direction: column;
							align-items: center;
							justify-content: center;
							color: #fff;
							cursor: pointer;
							transition: transform 0.2s, box-shadow 0.2s;
							border: 3px solid transparent;
						}
						.avada-custom-template-preview:hover {
							transform: translateY(-2px);
							box-shadow: 0 4px 12px rgba(0,0,0,0.15);
						}
						.avada-custom-template-preview.active {
							border-color: #0073aa;
							box-shadow: 0 0 0 2px #0073aa;
						}
						.avada-custom-template-preview .template-icon {
							font-size: 20px;
							margin-bottom: 5px;
						}
						.avada-custom-template-preview .template-name {
							font-size: 10px;
							font-weight: 600;
							text-align: center;
							padding: 0 5px;
							white-space: nowrap;
							overflow: hidden;
							text-overflow: ellipsis;
							max-width: 100%;
						}
					</style>
					<?php
				} );

				$html .= '<div class="avada-custom-templates-section">';
				$html .= '<h4>' . __( 'Custom Templates', 'double-opt-in' ) . '</h4>';
				$html .= '<p style="color: #666; font-size: 12px; margin-bottom: 10px;">';
				$html .= __( 'Or select a custom template:', 'double-opt-in' );
				$html .= ' <a href="' . esc_url( admin_url( 'admin.php?page=f12-cf7-doubleoptin_f12-doi-email-templates' ) ) . '" target="_blank">';
				$html .= __( 'Manage', 'double-opt-in' ) . ' →';
				$html .= '</a></p>';
				$html .= '<div class="avada-custom-templates-list">';

				foreach ( $customTemplates as $template ) {
					$templateKey = 'custom_' . $template['id'];
					$isActive = $selectedTemplate === $templateKey;

					$html .= '<div class="avada-custom-template-preview ' . ( $isActive ? 'active' : '' ) . '" ';
					$html .= 'data-template="' . esc_attr( $templateKey ) . '" ';
					$html .= 'title="' . esc_attr( $template['title'] ) . '">';
					$html .= '<span class="template-icon">✉</span>';
					$html .= '<span class="template-name">' . esc_html( $template['title'] ) . '</span>';
					$html .= '</div>';
				}

				$html .= '</div>'; // .avada-custom-templates-list
				$html .= '</div>'; // .avada-custom-templates-section

			} catch ( \Exception $e ) {
				$this->get_logger()->error( 'Failed to render custom template options', [
					'plugin' => 'double-opt-in',
					'error'  => $e->getMessage(),
				] );
			}

			return $html;
		}

		/**
		 * Get available email templates including custom ones.
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
				if ( class_exists( EmailTemplateIntegration::class ) ) {
					$integration = new EmailTemplateIntegration();
					$customTemplates = $integration->getCustomTemplates();

					foreach ( $customTemplates as $template ) {
						$templates[ 'custom_' . $template['id'] ] = $template['title'] . ' (' . __( 'Custom', 'double-opt-in' ) . ')';
					}
				}
			} catch ( \Exception $e ) {
				$this->get_logger()->error( 'Failed to load custom templates', [
					'plugin' => 'double-opt-in',
					'error'  => $e->getMessage(),
				] );
			}

			return $templates;
		}

		/**
		 * @return array
		 */
		private function getCategories() {
			$this->get_logger()->debug( 'Fetching categories', [
				'plugin' => 'double-opt-in',
				'class'  => __CLASS__,
				'method' => __METHOD__,
			] );

			$atts = array(
				'perPage' => -1,
				'orderBy' => 'name',
				'order'   => 'ASC'
			);

			$listOfCategories = Category::get_list( $atts, $numberOfPages );

			$value = array(
				0 => '---'
			);

			foreach ( $listOfCategories as $Category /** @var Category $Category */ ) {
				$value[ $Category->get_id() ] = $Category->get_name();
			}

			$this->get_logger()->debug( 'Categories fetched', [
				'plugin' => 'double-opt-in',
				'count'  => count( $value ),
			] );

			return $value;
		}


		/**
		 * Show the backend double opt in options
		 *
		 * Displays a notice with link to central form management.
		 * Full settings are now managed centrally in the Forms admin page.
		 *
		 * @param array $tab_data
		 *
		 * @return array
		 */
		public function render( $tab_data ) {
			global $post;

			$this->get_logger()->debug( 'Rendering Avada settings panel notice', [
				'plugin'  => 'double-opt-in',
				'class'   => __CLASS__,
				'method'  => __METHOD__,
				'post_id' => $post->ID ?? null,
			] );

			$metadata   = CF7DoubleOptIn::getInstance()->getParameter( $post->ID );
			$centralUrl = admin_url( 'admin.php?page=f12-cf7-doubleoptin_forms' );
			$isEnabled  = isset( $metadata['enable'] ) && $metadata['enable'] == 1;

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
					'type'        => 'custom'
				],
			];

			$tab_data['f12cf7doubleoptin']['fields'] = $fields;

			$this->get_logger()->debug( 'Avada settings panel notice rendered', [
				'plugin'  => 'double-opt-in',
				'post_id' => $post->ID ?? null,
			] );

			return $tab_data;
		}
	}
}

/**
 * Extra Function to use the Avada From Options
 */

namespace {
	function avada_page_options_tab_f12cf7doubleoptin() {
		return apply_filters( 'f12_cf7_doubleoptin_avada_form_panel', [] );
	}
}