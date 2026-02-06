<?php
/**
 * Email Template Integration
 *
 * Provides integration with CF7 and Avada Forms for custom email templates.
 *
 * @package Forge12\DoubleOptIn\EmailTemplates
 * @since   4.0.0
 */

namespace Forge12\DoubleOptIn\EmailTemplates;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class EmailTemplateIntegration
 *
 * Helper class for integrating custom email templates with form plugins.
 */
class EmailTemplateIntegration {

	/**
	 * Repository instance.
	 *
	 * @var EmailTemplateRepository
	 */
	private EmailTemplateRepository $repository;

	/**
	 * HTML Generator instance.
	 *
	 * @var EmailHtmlGenerator
	 */
	private EmailHtmlGenerator $htmlGenerator;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->repository = new EmailTemplateRepository();
		$this->htmlGenerator = new EmailHtmlGenerator();
	}

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		// Hook into template loading Ajax
		add_action( 'wp_ajax_f12_doi_templateloader', [ $this, 'handleTemplateLoaderAjax' ], 5 );

		// Hook into template rendering for email sending
		add_filter( 'f12_cf7_doubleoptin_template_body', [ $this, 'renderCustomTemplateBody' ], 10, 4 );

		// Add custom templates to CF7 panel
		add_action( 'f12_cf7_doubleoptin_admin_panel_templates', [ $this, 'renderCustomTemplateOptions' ], 10, 2 );
	}

	/**
	 * Get all available templates (built-in + custom).
	 *
	 * @return array Array of template options with 'value' => 'label' format.
	 */
	public function getAvailableTemplates(): array {
		$templates = [
			'blank'           => __( 'Blank', 'double-opt-in' ),
			'newsletter_en'   => __( 'Newsletter EN', 'double-opt-in' ),
			'newsletter_en_2' => __( 'Newsletter EN 2', 'double-opt-in' ),
			'newsletter_en_3' => __( 'Newsletter EN 3', 'double-opt-in' ),
		];

		// Add custom templates
		$customTemplates = $this->repository->findAll( [
			'post_status' => 'publish',
		] );

		foreach ( $customTemplates as $template ) {
			$templates[ 'custom_' . $template['id'] ] = sprintf(
				__( '%s (Custom)', 'double-opt-in' ),
				$template['title']
			);
		}

		return $templates;
	}

	/**
	 * Get custom templates for display.
	 *
	 * @return array Array of custom template data.
	 */
	public function getCustomTemplates(): array {
		return $this->repository->findAll( [
			'post_status' => 'publish',
		] );
	}

	/**
	 * Check if a template is a custom template.
	 *
	 * @param string $templateKey Template key.
	 * @return bool True if custom template.
	 */
	public function isCustomTemplate( string $templateKey ): bool {
		return strpos( $templateKey, 'custom_' ) === 0;
	}

	/**
	 * Get custom template ID from template key.
	 *
	 * @param string $templateKey Template key (e.g., 'custom_123').
	 * @return int|null Template ID or null.
	 */
	public function getCustomTemplateId( string $templateKey ): ?int {
		if ( ! $this->isCustomTemplate( $templateKey ) ) {
			return null;
		}

		return (int) str_replace( 'custom_', '', $templateKey );
	}

	/**
	 * Handle template loader Ajax request for custom templates.
	 *
	 * @return void
	 */
	public function handleTemplateLoaderAjax(): void {
		// Check nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['nonce'] ), 'f12_doi_templateloader' ) ) {
			return; // Let default handler run
		}

		$templateKey = isset( $_POST['template'] ) ? sanitize_text_field( $_POST['template'] ) : '';

		if ( ! $this->isCustomTemplate( $templateKey ) ) {
			return; // Let default handler run
		}

		$templateId = $this->getCustomTemplateId( $templateKey );
		if ( ! $templateId ) {
			// Match the expected response format for the template loader JS
			// Don't set Content-Type header - jQuery would auto-parse and the JS does JSON.parse manually
			echo wp_json_encode( [
				'status'  => 400,
				'content' => '',
				'message' => __( 'Invalid template.', 'double-opt-in' ),
			] );
			wp_die( '', '', [ 'response' => null ] );
		}

		$template = $this->repository->findById( $templateId );
		if ( ! $template ) {
			// Match the expected response format for the template loader JS
			echo wp_json_encode( [
				'status'  => 404,
				'content' => '',
				'message' => __( 'Template not found.', 'double-opt-in' ),
			] );
			wp_die( '', '', [ 'response' => null ] );
		}

		// Generate HTML from blocks
		$blocks = json_decode( $template['blocks_json'], true ) ?: [];
		$globalStyles = json_decode( $template['global_styles'], true ) ?: [];

		$html = $this->htmlGenerator->generate( $blocks, $globalStyles );

		// Return in the expected format for the template loader JS
		// The JS expects: { status: 200, content: "..." } as a string (not auto-parsed JSON)
		echo wp_json_encode( [
			'status'  => 200,
			'content' => $html,
		] );
		wp_die( '', '', [ 'response' => null ] );
	}

	/**
	 * Render custom template body for email sending.
	 *
	 * @param string $body        Current email body.
	 * @param string $templateKey Template key (e.g., 'blank', 'custom_123').
	 * @param array  $parameter   Form parameters.
	 * @param mixed  $optIn       OptIn object.
	 * @return string Modified email body.
	 */
	public function renderCustomTemplateBody( string $body, string $templateKey, array $parameter, $optIn ): string {
		if ( ! $this->isCustomTemplate( $templateKey ) ) {
			return $body;
		}

		$templateId = $this->getCustomTemplateId( $templateKey );
		if ( ! $templateId ) {
			return $body;
		}

		$template = $this->repository->findById( $templateId );
		if ( ! $template ) {
			return $body;
		}

		// Generate HTML from blocks
		$blocks = json_decode( $template['blocks_json'], true ) ?: [];
		$globalStyles = json_decode( $template['global_styles'], true ) ?: [];

		return $this->htmlGenerator->generate( $blocks, $globalStyles );
	}

	/**
	 * Render custom template options in the CF7 admin panel.
	 *
	 * @param array  $metadata Current form metadata.
	 * @param string $id       Form element ID prefix.
	 * @return void
	 */
	public function renderCustomTemplateOptions( array $metadata, string $id ): void {
		$customTemplates = $this->getCustomTemplates();

		if ( empty( $customTemplates ) ) {
			return;
		}

		$selectedTemplate = $metadata['template'] ?? '';

		// Add inline script via WordPress hook to avoid sanitization issues
		add_action( 'admin_footer', function() {
			?>
			<script type="text/javascript">
				jQuery(document).ready(function($) {
					$('.custom-template-preview').on('click', function() {
						var template = $(this).data('template');
						$('.f12-cf7-templateloader').val(template).trigger('change');
						$('.preview-item-inner').removeClass('active');
						$('.f12-cf7-templateloader-preview').parent().removeClass('active');
						$(this).closest('.preview-item-inner').addClass('active');
					});
				});
			</script>
			<style type="text/css">
				.custom-templates-section {
					margin-top: 20px;
					padding-top: 20px;
					border-top: 1px solid #ddd;
				}
				.custom-templates-section h4 {
					margin-bottom: 15px;
				}
				.custom-templates-section .preview {
					display: flex;
					flex-wrap: wrap;
					gap: 10px;
				}
				.custom-template-box {
					width: 120px;
					height: 90px;
					background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
					border-radius: 4px;
					display: flex;
					flex-direction: column;
					align-items: center;
					justify-content: center;
					color: #fff;
					cursor: pointer;
					transition: transform 0.2s, box-shadow 0.2s;
				}
				.custom-template-box:hover {
					transform: translateY(-2px);
					box-shadow: 0 4px 12px rgba(0,0,0,0.15);
				}
				.custom-template-box .template-icon {
					font-size: 24px;
					margin-bottom: 5px;
				}
				.custom-template-box .template-name {
					font-size: 11px;
					font-weight: 600;
					text-align: center;
					padding: 0 5px;
					white-space: nowrap;
					overflow: hidden;
					text-overflow: ellipsis;
					max-width: 100%;
				}
				.preview-item-inner.active .custom-template-box {
					box-shadow: 0 0 0 3px #0073aa;
				}
			</style>
			<?php
		} );
		?>
		<div class="custom-templates-section">
			<h4><?php _e( 'Custom Templates', 'double-opt-in' ); ?></h4>
			<p style="margin-bottom: 15px; color: #666; font-size: 12px;">
				<?php _e( 'Or select a custom template from the Email Editor:', 'double-opt-in' ); ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=f12-cf7-doubleoptin_f12-doi-email-templates' ) ); ?>" target="_blank">
					<?php _e( 'Manage Templates', 'double-opt-in' ); ?> →
				</a>
			</p>
			<div class="preview">
				<?php foreach ( $customTemplates as $template ) :
					$templateKey = 'custom_' . $template['id'];
					$isActive = $selectedTemplate === $templateKey;
					?>
					<div class="preview-item">
						<div class="preview-item-inner <?php echo $isActive ? 'active' : ''; ?>">
							<div class="f12-cf7-templateloader-preview custom-template-preview custom-template-box"
								 data-template="<?php echo esc_attr( $templateKey ); ?>"
								 title="<?php echo esc_attr( $template['title'] ); ?>">
								<span class="template-icon">✉</span>
								<span class="template-name"><?php echo esc_html( $template['title'] ); ?></span>
							</div>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}
}
