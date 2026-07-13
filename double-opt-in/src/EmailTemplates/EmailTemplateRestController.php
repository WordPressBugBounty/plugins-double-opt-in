<?php
/**
 * Email Template REST Controller
 *
 * @package Forge12\DoubleOptIn\EmailTemplates
 * @since   4.0.0
 */

namespace Forge12\DoubleOptIn\EmailTemplates;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class EmailTemplateRestController
 *
 * REST API endpoints for email templates.
 */
class EmailTemplateRestController {

	/**
	 * REST API namespace.
	 */
	const NAMESPACE = 'f12-doi/v1';

	/**
	 * REST API base.
	 */
	const BASE = 'email-templates';

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
	 * Block Registry instance.
	 *
	 * @var BlockRegistry
	 */
	private BlockRegistry $blockRegistry;

	/**
	 * Constructor.
	 *
	 * @param EmailTemplateRepository $repository    Repository instance.
	 * @param EmailHtmlGenerator      $htmlGenerator HTML Generator instance.
	 */
	public function __construct( EmailTemplateRepository $repository, EmailHtmlGenerator $htmlGenerator ) {
		$this->repository    = $repository;
		$this->htmlGenerator = $htmlGenerator;
		$this->blockRegistry = new BlockRegistry();
	}

	/**
	 * Initialize REST routes.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'rest_api_init', array( $this, 'registerRoutes' ) );
	}

	/**
	 * Register REST API routes.
	 *
	 * @return void
	 */
	public function registerRoutes(): void {
		// GET /email-templates - List all templates
		register_rest_route(
			self::NAMESPACE,
			'/' . self::BASE,
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'getItems' ),
				'permission_callback' => array( $this, 'checkPermission' ),
			)
		);

		// GET /email-templates/{id} - Get single template
		register_rest_route(
			self::NAMESPACE,
			'/' . self::BASE . '/(?P<id>[\d]+)',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'getItem' ),
				'permission_callback' => array( $this, 'checkPermission' ),
				'args'                => array(
					'id' => array(
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
					),
				),
			)
		);

		// POST /email-templates - Create template
		register_rest_route(
			self::NAMESPACE,
			'/' . self::BASE,
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'createItem' ),
				'permission_callback' => array( $this, 'checkPermission' ),
			)
		);

		// PUT /email-templates/{id} - Update template
		register_rest_route(
			self::NAMESPACE,
			'/' . self::BASE . '/(?P<id>[\d]+)',
			array(
				'methods'             => \WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'updateItem' ),
				'permission_callback' => array( $this, 'checkPermission' ),
				'args'                => array(
					'id' => array(
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
					),
				),
			)
		);

		// DELETE /email-templates/{id} - Delete template
		register_rest_route(
			self::NAMESPACE,
			'/' . self::BASE . '/(?P<id>[\d]+)',
			array(
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'deleteItem' ),
				'permission_callback' => array( $this, 'checkPermission' ),
				'args'                => array(
					'id' => array(
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
					),
				),
			)
		);

		// POST /email-templates/{id}/render - Render template HTML
		register_rest_route(
			self::NAMESPACE,
			'/' . self::BASE . '/(?P<id>[\d]+)/render',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'renderTemplate' ),
				'permission_callback' => array( $this, 'checkPermission' ),
				'args'                => array(
					'id' => array(
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
					),
				),
			)
		);

		// POST /email-templates/preview - Generate live preview
		register_rest_route(
			self::NAMESPACE,
			'/' . self::BASE . '/preview',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'previewTemplate' ),
				'permission_callback' => array( $this, 'checkPermission' ),
			)
		);

		// POST /email-templates/{id}/duplicate - Duplicate template
		register_rest_route(
			self::NAMESPACE,
			'/' . self::BASE . '/(?P<id>[\d]+)/duplicate',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'duplicateTemplate' ),
				'permission_callback' => array( $this, 'checkPermission' ),
				'args'                => array(
					'id' => array(
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
					),
				),
			)
		);

		// GET /email-templates/placeholders - Get available placeholders
		register_rest_route(
			self::NAMESPACE,
			'/' . self::BASE . '/placeholders',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'getPlaceholders' ),
				'permission_callback' => array( $this, 'checkPermission' ),
			)
		);

		// GET /email-templates/presets - Get available template presets
		register_rest_route(
			self::NAMESPACE,
			'/' . self::BASE . '/presets',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'getPresets' ),
				'permission_callback' => array( $this, 'checkPermission' ),
			)
		);

		// GET /email-templates/presets/{id} - Get single preset
		register_rest_route(
			self::NAMESPACE,
			'/' . self::BASE . '/presets/(?P<preset_id>[a-z0-9-]+)',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'getPreset' ),
				'permission_callback' => array( $this, 'checkPermission' ),
				'args'                => array(
					'preset_id' => array(
						'validate_callback' => function ( $param ) {
							return is_string( $param ) && preg_match( '/^[a-z0-9-]+$/', $param );
						},
					),
				),
			)
		);

		// POST /email-templates/{id}/send-test - Send test email
		register_rest_route(
			self::NAMESPACE,
			'/' . self::BASE . '/(?P<id>[\d]+)/send-test',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'sendTestEmail' ),
				'permission_callback' => array( $this, 'checkPermission' ),
				'args'                => array(
					'id' => array(
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
					),
				),
			)
		);

		// POST /email-templates/from-preset - Create template from preset
		register_rest_route(
			self::NAMESPACE,
			'/' . self::BASE . '/from-preset',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'createFromPreset' ),
				'permission_callback' => array( $this, 'checkPermission' ),
			)
		);
	}

	/**
	 * Check if current user has permission.
	 *
	 * @return bool
	 */
	public function checkPermission(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Get all templates.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function getItems( \WP_REST_Request $request ): \WP_REST_Response {
		$templates = $this->repository->findAll();

		return new \WP_REST_Response(
			array(
				'success' => true,
				'data'    => $templates,
			),
			200
		);
	}

	/**
	 * Get single template.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function getItem( \WP_REST_Request $request ): \WP_REST_Response {
		$id       = (int) $request->get_param( 'id' );
		$template = $this->repository->findById( $id );

		if ( ! $template ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Template not found.', 'double-opt-in' ),
				),
				404
			);
		}

		return new \WP_REST_Response(
			array(
				'success' => true,
				'data'    => $template,
			),
			200
		);
	}

	/**
	 * Create new template.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function createItem( \WP_REST_Request $request ): \WP_REST_Response {
		$data = $request->get_json_params();

		// Check template limit for published templates
		$status = sanitize_text_field( $data['status'] ?? 'draft' );
		if ( $status === 'publish' ) {
			$published = $this->repository->countPublished();
			$limit     = $this->blockRegistry->getTemplateLimit();
			if ( $published >= $limit ) {
				return new \WP_REST_Response(
					array(
						'success' => false,
						'message' => __( 'You have reached the maximum number of published templates. Upgrade to Pro for unlimited templates.', 'double-opt-in' ),
					),
					403
				);
			}
		}

		// Validate blocks for Pro gating
		$invalidBlocks = $this->validateBlocksFromData( $data );
		if ( ! empty( $invalidBlocks ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => sprintf(
						__( 'Template contains Pro blocks that require a license: %s', 'double-opt-in' ),
						implode( ', ', array_unique( $invalidBlocks ) )
					),
				),
				403
			);
		}

		$id = $this->repository->create( $data );

		if ( ! $id ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Failed to create template.', 'double-opt-in' ),
				),
				500
			);
		}

		$template = $this->repository->findById( $id );

		return new \WP_REST_Response(
			array(
				'success' => true,
				'data'    => $template,
			),
			201
		);
	}

	/**
	 * Update template.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function updateItem( \WP_REST_Request $request ): \WP_REST_Response {
		$id   = (int) $request->get_param( 'id' );
		$data = $request->get_json_params();

		// Check template limit when changing status to publish
		$newStatus = isset( $data['status'] ) ? sanitize_text_field( $data['status'] ) : null;
		if ( $newStatus === 'publish' ) {
			$currentTemplate = $this->repository->findById( $id );
			// Only check limit if the template is not already published
			if ( $currentTemplate && $currentTemplate['status'] !== 'publish' ) {
				$published = $this->repository->countPublished();
				$limit     = $this->blockRegistry->getTemplateLimit();
				if ( $published >= $limit ) {
					return new \WP_REST_Response(
						array(
							'success' => false,
							'message' => __( 'You have reached the maximum number of published templates. Upgrade to Pro for unlimited templates.', 'double-opt-in' ),
						),
						403
					);
				}
			}
		}

		// Validate blocks for Pro gating
		$invalidBlocks = $this->validateBlocksFromData( $data );
		if ( ! empty( $invalidBlocks ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => sprintf(
						__( 'Template contains Pro blocks that require a license: %s', 'double-opt-in' ),
						implode( ', ', array_unique( $invalidBlocks ) )
					),
				),
				403
			);
		}

		$success = $this->repository->update( $id, $data );

		if ( ! $success ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Failed to update template.', 'double-opt-in' ),
				),
				500
			);
		}

		$template = $this->repository->findById( $id );

		return new \WP_REST_Response(
			array(
				'success' => true,
				'data'    => $template,
			),
			200
		);
	}

	/**
	 * Delete template.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function deleteItem( \WP_REST_Request $request ): \WP_REST_Response {
		$id    = (int) $request->get_param( 'id' );
		$force = (bool) $request->get_param( 'force' );

		$success = $this->repository->delete( $id, $force );

		if ( ! $success ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Failed to delete template.', 'double-opt-in' ),
				),
				500
			);
		}

		return new \WP_REST_Response(
			array(
				'success' => true,
				'message' => __( 'Template deleted successfully.', 'double-opt-in' ),
			),
			200
		);
	}

	/**
	 * Render template HTML with placeholders.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function renderTemplate( \WP_REST_Request $request ): \WP_REST_Response {
		$id       = (int) $request->get_param( 'id' );
		$template = $this->repository->findById( $id );

		if ( ! $template ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Template not found.', 'double-opt-in' ),
				),
				404
			);
		}

		$blocks       = json_decode( $template['blocks_json'], true ) ?: array();
		$globalStyles = json_decode( $template['global_styles'], true ) ?: array();

		$html = $this->htmlGenerator->generate( $blocks, $globalStyles );

		return new \WP_REST_Response(
			array(
				'success' => true,
				'data'    => array(
					'html' => $html,
				),
			),
			200
		);
	}

	/**
	 * Generate live preview.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function previewTemplate( \WP_REST_Request $request ): \WP_REST_Response {
		$data = $request->get_json_params();

		$blocks       = $data['blocks'] ?? array();
		$globalStyles = $data['global_styles'] ?? array();

		$html = $this->htmlGenerator->generate( $blocks, $globalStyles );

		return new \WP_REST_Response(
			array(
				'success' => true,
				'data'    => array(
					'html' => $html,
				),
			),
			200
		);
	}

	/**
	 * Duplicate template.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function duplicateTemplate( \WP_REST_Request $request ): \WP_REST_Response {
		$id = (int) $request->get_param( 'id' );

		$newId = $this->repository->duplicate( $id );

		if ( ! $newId ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Failed to duplicate template.', 'double-opt-in' ),
				),
				500
			);
		}

		$template = $this->repository->findById( $newId );

		return new \WP_REST_Response(
			array(
				'success' => true,
				'data'    => $template,
			),
			201
		);
	}

	/**
	 * Send a test email with the template.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function sendTestEmail( \WP_REST_Request $request ): \WP_REST_Response {
		// Requires Pro
		if ( ! $this->blockRegistry->isProActive() ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Sending test emails requires the Pro version.', 'double-opt-in' ),
				),
				403
			);
		}

		$id    = (int) $request->get_param( 'id' );
		$data  = $request->get_json_params();
		$email = sanitize_email( $data['email'] ?? '' );

		if ( ! is_email( $email ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Please enter a valid email address.', 'double-opt-in' ),
				),
				400
			);
		}

		$template = $this->repository->findById( $id );

		if ( ! $template ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Template not found.', 'double-opt-in' ),
				),
				404
			);
		}

		// Generate HTML from blocks
		$blocks       = json_decode( $template['blocks_json'], true ) ?: array();
		$globalStyles = json_decode( $template['global_styles'], true ) ?: array();
		$html         = $this->htmlGenerator->generate( $blocks, $globalStyles );

		// Replace placeholder tags with dummy values for test
		$html = str_replace( '[doubleoptinlink]', '#', $html );
		$html = str_replace( '[doubleoptoutlink]', '#', $html );
		$html = str_replace( '[doubleoptin_form_date]', date_i18n( get_option( 'date_format' ) ), $html );
		$html = str_replace( '[doubleoptin_form_time]', date_i18n( get_option( 'time_format' ) ), $html );
		$html = str_replace( '[doubleoptin_form_url]', home_url(), $html );

		$subject = sprintf( '[Test] %s', $template['title'] );
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		$sent = wp_mail( $email, $subject, $html, $headers );

		if ( ! $sent ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Failed to send test email. Please check your email configuration.', 'double-opt-in' ),
				),
				500
			);
		}

		return new \WP_REST_Response(
			array(
				'success' => true,
				'message' => sprintf(
					__( 'Test email sent to %s.', 'double-opt-in' ),
					$email
				),
			),
			200
		);
	}

	/**
	 * Get available placeholders.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function getPlaceholders( \WP_REST_Request $request ): \WP_REST_Response {
		$placeholders = PlaceholderMapper::getAvailablePlaceholdersForEditor();

		return new \WP_REST_Response(
			array(
				'success' => true,
				'data'    => $placeholders,
			),
			200
		);
	}

	/**
	 * Get all available template presets.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function getPresets( \WP_REST_Request $request ): \WP_REST_Response {
		$presets = EmailTemplatePresets::getAll();

		// Return only metadata, not full blocks (to keep response small)
		$presetsMetadata = array_map(
			function ( $preset ) {
				return array(
					'id'          => $preset['id'],
					'name'        => $preset['name'],
					'description' => $preset['description'],
					'thumbnail'   => $preset['thumbnail'],
					'category'    => $preset['category'],
				);
			},
			$presets
		);

		return new \WP_REST_Response(
			array(
				'success' => true,
				'data'    => $presetsMetadata,
			),
			200
		);
	}

	/**
	 * Get a single preset by ID.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function getPreset( \WP_REST_Request $request ): \WP_REST_Response {
		$presetId = $request->get_param( 'preset_id' );
		$preset   = EmailTemplatePresets::getById( $presetId );

		if ( ! $preset ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Preset not found.', 'double-opt-in' ),
				),
				404
			);
		}

		return new \WP_REST_Response(
			array(
				'success' => true,
				'data'    => $preset,
			),
			200
		);
	}

	/**
	 * Create a new template from a preset.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function createFromPreset( \WP_REST_Request $request ): \WP_REST_Response {
		$data     = $request->get_json_params();
		$presetId = $data['preset_id'] ?? '';
		$title    = $data['title'] ?? '';

		// Check template limit (presets are created as drafts, but check limit proactively)
		$published = $this->repository->countPublished();
		$limit     = $this->blockRegistry->getTemplateLimit();
		if ( $published >= $limit ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'You have reached the maximum number of published templates. Upgrade to Pro for unlimited templates.', 'double-opt-in' ),
				),
				403
			);
		}

		if ( empty( $presetId ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Preset ID is required.', 'double-opt-in' ),
				),
				400
			);
		}

		$preset = EmailTemplatePresets::getById( $presetId );

		if ( ! $preset ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Preset not found.', 'double-opt-in' ),
				),
				404
			);
		}

		// Get blocks from preset
		$blocks       = $preset['blocks'] ?? array();
		$globalStyles = $preset['globalStyles'] ?? array();

		// Debug: Check if blocks exist
		if ( empty( $blocks ) && $presetId !== 'blank' ) {
			// Something is wrong - preset should have blocks
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => 'Preset blocks are empty - this should not happen!',
					'_debug'  => array(
						'preset_id'    => $presetId,
						'preset_keys'  => array_keys( $preset ),
						'blocks_type'  => gettype( $preset['blocks'] ?? null ),
						'blocks_count' => count( $blocks ),
						'has_children' => isset( $preset['blocks'][0]['children'] ),
					),
				),
				500
			);
		}

		// Encode to JSON
		$blocksJson       = wp_json_encode( $blocks, JSON_UNESCAPED_UNICODE );
		$globalStylesJson = wp_json_encode( $globalStyles, JSON_UNESCAPED_UNICODE );

		$templateData = array(
			'title'         => ! empty( $title ) ? $title : $preset['name'],
			'blocks_json'   => $blocksJson,
			'global_styles' => $globalStylesJson,
			'status'        => 'draft',
		);

		$id = $this->repository->create( $templateData );

		if ( ! $id ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Failed to create template from preset.', 'double-opt-in' ),
					'_debug'  => array_merge(
						array(
							'preset_id'          => $presetId,
							'blocks_count'       => count( $blocks ),
							'blocks_json_length' => strlen( $blocksJson ),
						),
						$this->repository->lastCreateDebug
					),
				),
				500
			);
		}

		$template = $this->repository->findById( $id );

		// Debug: Return additional info
		$template['_debug'] = array_merge(
			array(
				'preset_id'          => $presetId,
				'blocks_count'       => count( $blocks ),
				'blocks_json_length' => strlen( $blocksJson ),
			),
			$this->repository->lastCreateDebug
		);

		return new \WP_REST_Response(
			array(
				'success' => true,
				'data'    => $template,
			),
			201
		);
	}

	/**
	 * Validate blocks from request data for Pro gating.
	 *
	 * @param array $data The request data containing blocks_json.
	 *
	 * @return array Array of invalid block types. Empty if valid.
	 */
	private function validateBlocksFromData( array $data ): array {
		if ( empty( $data['blocks_json'] ) ) {
			return array();
		}

		$blocks = is_string( $data['blocks_json'] )
			? json_decode( $data['blocks_json'], true )
			: $data['blocks_json'];

		if ( ! is_array( $blocks ) ) {
			return array();
		}

		return $this->blockRegistry->validateBlocks( $blocks );
	}
}
