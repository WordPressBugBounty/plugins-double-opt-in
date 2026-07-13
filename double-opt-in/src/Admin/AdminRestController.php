<?php
/**
 * Admin REST Controller
 *
 * Consolidated REST API endpoints for the React admin SPA.
 *
 * @package Forge12\DoubleOptIn\Admin
 * @since   4.2.0
 */

namespace Forge12\DoubleOptIn\Admin;

use Forge12\DoubleOptIn\Addon\AddonRegistry;
use Forge12\DoubleOptIn\Audit\AuditLogger;
use Forge12\DoubleOptIn\FormSettings\FormSettingsDTO;
use Forge12\DoubleOptIn\FormSettings\FormSettingsService;
use Forge12\DoubleOptIn\FormSettings\FormSettingsValidator;
use Forge12\Shared\LoggerInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AdminRestController
 *
 * REST API endpoints for the admin SPA.
 */
class AdminRestController {

	const API_NAMESPACE = 'f12-doi/v1';

	private LoggerInterface $logger;
	private FormSettingsService $formService;
	private FormSettingsValidator $formValidator;

	public function __construct(
		LoggerInterface $logger,
		FormSettingsService $formService,
		FormSettingsValidator $formValidator
	) {
		$this->logger        = $logger;
		$this->formService   = $formService;
		$this->formValidator = $formValidator;
	}

	/**
	 * Initialize REST routes.
	 */
	public function init(): void {
		add_action( 'rest_api_init', array( $this, 'registerRoutes' ) );
	}

	/**
	 * Permission callback.
	 */
	public function checkPermission(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Permission callback for dev-only endpoints (currently the
	 * reset-confirmation route).
	 *
	 * Hard-locked behind WP_DEBUG so a production site running this
	 * codebase cannot reset confirmation status — that would silently
	 * erase legal Art-7 consent proof. Filterable for cases like CI
	 * test environments that want it gated by a different signal.
	 */
	public function checkDevResetPermission(): bool {
		$wpDebugOn = defined( 'WP_DEBUG' ) && WP_DEBUG === true;
		/**
		 * Whether dev-only reset endpoints are reachable.
		 *
		 * @param bool $allowed Default: WP_DEBUG === true.
		 * @since 4.3.0
		 */
		$allowed = (bool) apply_filters( 'f12_doi_dev_reset_allowed', $wpDebugOn );

		return $allowed && current_user_can( 'manage_options' );
	}

	/**
	 * Register all REST API routes.
	 */
	public function registerRoutes(): void {
		// ── Dashboard ──────────────────────────────────────────────
		register_rest_route(
			self::API_NAMESPACE,
			'/dashboard/stats',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'getDashboardStats' ),
				'permission_callback' => array( $this, 'checkPermission' ),
			)
		);

		register_rest_route(
			self::API_NAMESPACE,
			'/dashboard/quick-info',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'getDashboardQuickInfo' ),
				'permission_callback' => array( $this, 'checkPermission' ),
			)
		);

		// ── Opt-Ins ────────────────────────────────────────────────
		register_rest_route(
			self::API_NAMESPACE,
			'/optins',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'getOptins' ),
				'permission_callback' => array( $this, 'checkPermission' ),
			)
		);

		register_rest_route(
			self::API_NAMESPACE,
			'/optins/(?P<id>[\d]+)',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'getOptin' ),
				'permission_callback' => array( $this, 'checkPermission' ),
				'args'                => array(
					'id' => array(
						'validate_callback' => function ( $p ) {
								return is_numeric( $p ); },
					),
				),
			)
		);

		register_rest_route(
			self::API_NAMESPACE,
			'/optins/(?P<id>[\d]+)',
			array(
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'deleteOptin' ),
				'permission_callback' => array( $this, 'checkPermission' ),
				'args'                => array(
					'id' => array(
						'validate_callback' => function ( $p ) {
								return is_numeric( $p ); },
					),
				),
			)
		);

		register_rest_route(
			self::API_NAMESPACE,
			'/optins/(?P<id>[\d]+)/resend',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'resendOptinEmail' ),
				'permission_callback' => array( $this, 'checkPermission' ),
				'args'                => array(
					'id' => array(
						'validate_callback' => function ( $p ) {
								return is_numeric( $p ); },
					),
				),
			)
		);

		// Dev-only: revert a confirmed opt-in to "pending" so the same
		// confirmation link can be tested again without re-filling the
		// form. Gated by WP_DEBUG — see checkDevResetPermission().
		register_rest_route(
			self::API_NAMESPACE,
			'/optins/(?P<id>[\d]+)/reset-confirmation',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'resetOptinConfirmation' ),
				'permission_callback' => array( $this, 'checkDevResetPermission' ),
				'args'                => array(
					'id' => array(
						'validate_callback' => function ( $p ) {
								return is_numeric( $p ); },
					),
				),
			)
		);

		// ── Forms ──────────────────────────────────────────────────
		register_rest_route(
			self::API_NAMESPACE,
			'/forms',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'getForms' ),
				'permission_callback' => array( $this, 'checkPermission' ),
			)
		);

		register_rest_route(
			self::API_NAMESPACE,
			'/forms/(?P<integration>[a-z0-9_-]+)/(?P<form_id>[a-zA-Z0-9_-]+)/settings',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'getFormSettings' ),
				'permission_callback' => array( $this, 'checkPermission' ),
			)
		);

		register_rest_route(
			self::API_NAMESPACE,
			'/forms/(?P<integration>[a-z0-9_-]+)/(?P<form_id>[a-zA-Z0-9_-]+)/settings',
			array(
				'methods'             => \WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'saveFormSettings' ),
				'permission_callback' => array( $this, 'checkPermission' ),
			)
		);

		register_rest_route(
			self::API_NAMESPACE,
			'/forms/(?P<integration>[a-z0-9_-]+)/(?P<form_id>[a-zA-Z0-9_-]+)/toggle',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'toggleForm' ),
				'permission_callback' => array( $this, 'checkPermission' ),
			)
		);

		register_rest_route(
			self::API_NAMESPACE,
			'/forms/(?P<integration>[a-z0-9_-]+)/(?P<form_id>[a-zA-Z0-9_-]+)/fields',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'getFormFields' ),
				'permission_callback' => array( $this, 'checkPermission' ),
			)
		);

		// ── Settings ───────────────────────────────────────────────
		register_rest_route(
			self::API_NAMESPACE,
			'/settings',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'getSettings' ),
				'permission_callback' => array( $this, 'checkPermission' ),
			)
		);

		register_rest_route(
			self::API_NAMESPACE,
			'/settings',
			array(
				'methods'             => \WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'updateSettings' ),
				'permission_callback' => array( $this, 'checkPermission' ),
			)
		);

		register_rest_route(
			self::API_NAMESPACE,
			'/settings/pages',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'getPages' ),
				'permission_callback' => array( $this, 'checkPermission' ),
			)
		);

		register_rest_route(
			self::API_NAMESPACE,
			'/settings/email-templates-list',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'getEmailTemplatesList' ),
				'permission_callback' => array( $this, 'checkPermission' ),
			)
		);

		// ── Categories ─────────────────────────────────────────────
		register_rest_route(
			self::API_NAMESPACE,
			'/categories',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'getCategories' ),
				'permission_callback' => array( $this, 'checkPermission' ),
			)
		);

		register_rest_route(
			self::API_NAMESPACE,
			'/categories',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'createCategory' ),
				'permission_callback' => array( $this, 'checkPermission' ),
			)
		);

		register_rest_route(
			self::API_NAMESPACE,
			'/categories/(?P<id>[\d]+)',
			array(
				'methods'             => \WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'updateCategory' ),
				'permission_callback' => array( $this, 'checkPermission' ),
				'args'                => array(
					'id' => array(
						'validate_callback' => function ( $p ) {
								return is_numeric( $p ); },
					),
				),
			)
		);

		register_rest_route(
			self::API_NAMESPACE,
			'/categories/(?P<id>[\d]+)',
			array(
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'deleteCategory' ),
				'permission_callback' => array( $this, 'checkPermission' ),
				'args'                => array(
					'id' => array(
						'validate_callback' => function ( $p ) {
								return is_numeric( $p ); },
					),
				),
			)
		);

		// ── Database ───────────────────────────────────────────────
		register_rest_route(
			self::API_NAMESPACE,
			'/database/stats',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'getDatabaseStats' ),
				'permission_callback' => array( $this, 'checkPermission' ),
			)
		);

		register_rest_route(
			self::API_NAMESPACE,
			'/database/clean',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'cleanDatabase' ),
				'permission_callback' => array( $this, 'checkPermission' ),
			)
		);

		register_rest_route(
			self::API_NAMESPACE,
			'/database/reset',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'resetDatabase' ),
				'permission_callback' => array( $this, 'checkPermission' ),
			)
		);

		// ── Audit Log ──────────────────────────────────────────────
		register_rest_route(
			self::API_NAMESPACE,
			'/audit/events',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'getAuditEvents' ),
				'permission_callback' => array( $this, 'checkPermission' ),
			)
		);

		register_rest_route(
			self::API_NAMESPACE,
			'/audit/summary',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'getAuditSummary' ),
				'permission_callback' => array( $this, 'checkPermission' ),
			)
		);

		// ── Analytics (Pro-extensible) ─────────────────────────────
		register_rest_route(
			self::API_NAMESPACE,
			'/analytics/overview',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'getAnalyticsOverview' ),
				'permission_callback' => array( $this, 'checkPermission' ),
			)
		);

		register_rest_route(
			self::API_NAMESPACE,
			'/analytics/form/(?P<form_id>[\d]+)',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'getAnalyticsForm' ),
				'permission_callback' => array( $this, 'checkPermission' ),
				'args'                => array(
					'form_id' => array(
						'validate_callback' => function ( $p ) {
								return is_numeric( $p ); },
					),
				),
			)
		);

		// ── Opt-Out Settings (Pro-extensible) ──────────────────────
		register_rest_route(
			self::API_NAMESPACE,
			'/optout/settings',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'getOptoutSettings' ),
					'permission_callback' => array( $this, 'checkPermission' ),
				),
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'updateOptoutSettings' ),
					'permission_callback' => array( $this, 'checkPermission' ),
				),
			)
		);

		// One-click opt-out page generator. Creates a WP page with the
		// required list+form shortcodes so the admin doesn't have to
		// hop over to Pages → New manually. Idempotent: a page that
		// already contains `[f12-cf7-doubleoptin-optout-list]` is
		// returned instead of duplicated.
		register_rest_route(
			self::API_NAMESPACE,
			'/optout/page/generate',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'generateOptoutPage' ),
					'permission_callback' => array( $this, 'checkPermission' ),
				),
			)
		);

		// ── User Creation Settings (Pro-extensible) ────────────────
		register_rest_route(
			self::API_NAMESPACE,
			'/user-creation/settings',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'getUserCreationSettings' ),
					'permission_callback' => array( $this, 'checkPermission' ),
				),
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'updateUserCreationSettings' ),
					'permission_callback' => array( $this, 'checkPermission' ),
				),
			)
		);

		// ── API Settings (Pro-extensible) ──────────────────────────
		register_rest_route(
			self::API_NAMESPACE,
			'/api/settings',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'getApiSettings' ),
					'permission_callback' => array( $this, 'checkPermission' ),
				),
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'updateApiSettings' ),
					'permission_callback' => array( $this, 'checkPermission' ),
				),
			)
		);

		// ── License (Pro-extensible) ───────────────────────────────
		register_rest_route(
			self::API_NAMESPACE,
			'/license',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'getLicense' ),
				'permission_callback' => array( $this, 'checkPermission' ),
			)
		);

		register_rest_route(
			self::API_NAMESPACE,
			'/license/activate',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'activateLicense' ),
				'permission_callback' => array( $this, 'checkPermission' ),
			)
		);

		register_rest_route(
			self::API_NAMESPACE,
			'/license/deactivate',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'deactivateLicense' ),
				'permission_callback' => array( $this, 'checkPermission' ),
			)
		);

		// ── Database Export (Pro-extensible) ────────────────────────
		register_rest_route(
			self::API_NAMESPACE,
			'/database/export',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'exportDatabase' ),
				'permission_callback' => array( $this, 'checkPermission' ),
			)
		);

		// ── Addons manifest (UI mount-point system, plan §9) ────────
		register_rest_route(
			self::API_NAMESPACE,
			'/addons',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'getAddonsManifest' ),
				'permission_callback' => array( $this, 'checkPermission' ),
			)
		);

		// ── Addons catalog (marketplace view + state) ───────────────
		register_rest_route(
			self::API_NAMESPACE,
			'/addons/catalog',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'getAddonCatalog' ),
				'permission_callback' => array( $this, 'checkPermission' ),
			)
		);

		// ── Per-addon activate (calls activate_plugin in Core) ──────
		register_rest_route(
			self::API_NAMESPACE,
			'/addons/(?P<id>[a-z0-9-]+)/activate',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'activateAddon' ),
				'permission_callback' => function () {
					return current_user_can( 'activate_plugins' );
				},
				'args'                => array(
					'id' => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_key',
					),
				),
			)
		);

		// ── Per-addon deactivate (mirror of /activate) ──────────────
		register_rest_route(
			self::API_NAMESPACE,
			'/addons/(?P<id>[a-z0-9-]+)/deactivate',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'deactivateAddon' ),
				'permission_callback' => function () {
					return current_user_can( 'activate_plugins' );
				},
				'args'                => array(
					'id' => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_key',
					),
				),
			)
		);

		// ── Per-addon settings GET/POST (feature-level toggle + addon-specific settings) ──
		// Distinct from plugin activation: the addon plugin file can be
		// active in WP while the user temporarily turns the feature off
		// here. Each addon stores its settings under a dedicated WP
		// option (`f12_doi_addon_{id}_settings`); the addon's own hooks
		// read from that option to gate their behaviour.
		register_rest_route(
			self::API_NAMESPACE,
			'/addons/(?P<id>[a-z0-9-]+)/settings',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'getAddonSettings' ),
					'permission_callback' => array( $this, 'checkPermission' ),
					'args'                => array(
						'id' => array(
							'required'          => true,
							'sanitize_callback' => 'sanitize_key',
						),
					),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'updateAddonSettings' ),
					'permission_callback' => array( $this, 'checkPermission' ),
					'args'                => array(
						'id' => array(
							'required'          => true,
							'sanitize_callback' => 'sanitize_key',
						),
					),
				),
			)
		);
	}

	// ═══════════════════════════════════════════════════════════════
	// DASHBOARD
	// ═══════════════════════════════════════════════════════════════

	public function getDashboardStats( \WP_REST_Request $request ): \WP_REST_Response {
		global $wpdb;
		$table = $wpdb->prefix . 'f12_cf7_doubleoptin';

		$total     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		$confirmed = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE doubleoptin = 1" );
		$pending   = $total - $confirmed;
		$rate      = $total > 0 ? round( ( $confirmed / $total ) * 100, 1 ) : 0;

		// Recent opt-ins (raw activity feed — not analytics).
		// Time-bucketed activity, top-forms breakdown and the big
		// conversion-rate card moved into addon-analytics, which
		// renders them at the `dashboard.widget` mount point.
		$recent = $wpdb->get_results(
			"SELECT id, email, cf_form_id, doubleoptin, createtime FROM {$table} ORDER BY id DESC LIMIT 5",
			ARRAY_A
		);

		foreach ( $recent as &$row ) {
			$post             = get_post( (int) $row['cf_form_id'] );
			$row['formName']  = $post ? $post->post_title : sprintf( '#%d', $row['cf_form_id'] );
			$row['confirmed'] = (int) $row['doubleoptin'] === 1;
		}

		$data = array(
			'totalOptins'    => $total,
			'confirmed'      => $confirmed,
			'pending'        => $pending,
			'conversionRate' => $rate,
			'recentOptins'   => $recent ?: array(),
		);

		/**
		 * Filter dashboard stats so Pro can add data.
		 *
		 * @param array $data The dashboard data.
		 * @since 4.2.0
		 */
		$data = apply_filters( 'f12_doi_rest_dashboard_stats', $data );

		return new \WP_REST_Response(
			array(
				'success' => true,
				'data'    => $data,
			),
			200
		);
	}

	public function getDashboardQuickInfo( \WP_REST_Request $request ): \WP_REST_Response {
		$settings = get_option( 'f12-doi-settings', array() );

		$info = array(
			'version'       => defined( 'FORGE12_OPTIN_VERSION' ) ? FORGE12_OPTIN_VERSION : '0.0.0',
			'tokenExpiry'   => (int) ( $settings['token_expiry_hours'] ?? 48 ),
			'retention'     => ( $settings['delete'] ?? 12 ) . ' ' . ( $settings['delete_period'] ?? 'months' ),
			'rateLimit'     => (int) ( $settings['rate_limit_ip'] ?? 5 ),
			'licenseStatus' => apply_filters( 'f12_doi_is_pro_active', false ) ? 'Pro Active' : 'Free',
		);

		/**
		 * Filter quick info so Pro can add license data.
		 *
		 * @param array $info The quick info data.
		 * @since 4.2.0
		 */
		$info = apply_filters( 'f12_doi_rest_dashboard_info', $info );

		return new \WP_REST_Response(
			array(
				'success' => true,
				'data'    => $info,
			),
			200
		);
	}

	// ═══════════════════════════════════════════════════════════════
	// OPT-INS
	// ═══════════════════════════════════════════════════════════════

	public function getOptins( \WP_REST_Request $request ): \WP_REST_Response {
		$page     = max( 1, (int) $request->get_param( 'page' ) ?: 1 );
		$perPage  = max( 1, min( 100, (int) $request->get_param( 'per_page' ) ?: 20 ) );
		$search   = sanitize_text_field( $request->get_param( 'search' ) ?? '' );
		$category = $request->get_param( 'category' );
		$status   = sanitize_text_field( $request->get_param( 'status' ) ?? '' );
		$formId   = $request->get_param( 'form_id' );

		global $wpdb;
		$table  = $wpdb->prefix . 'f12_cf7_doubleoptin';
		$where  = array( '1=1' );
		$params = array();

		if ( ! empty( $search ) ) {
			$where[]  = '(email LIKE %s OR hash LIKE %s)';
			$like     = '%' . $wpdb->esc_like( $search ) . '%';
			$params[] = $like;
			$params[] = $like;
		}

		if ( $category !== null && $category !== '' ) {
			$where[]  = 'category = %d';
			$params[] = (int) $category;
		}

		if ( $status === 'confirmed' ) {
			$where[] = 'doubleoptin = 1';
		} elseif ( $status === 'pending' ) {
			$where[] = '(doubleoptin = 0 OR doubleoptin IS NULL)';
		}

		if ( $formId !== null && $formId !== '' ) {
			$where[]  = 'cf_form_id = %d';
			$params[] = (int) $formId;
		}

		$whereClause = implode( ' AND ', $where );

		// Count
		$countQuery = "SELECT COUNT(*) FROM {$table} WHERE {$whereClause}";
		if ( ! empty( $params ) ) {
			$countQuery = $wpdb->prepare( $countQuery, $params );
		}
		$total = (int) $wpdb->get_var( $countQuery );

		// Fetch
		$offset    = ( $page - 1 ) * $perPage;
		$query     = "SELECT * FROM {$table} WHERE {$whereClause} ORDER BY id DESC LIMIT %d OFFSET %d";
		$allParams = array_merge( $params, array( $perPage, $offset ) );
		$rows      = $wpdb->get_results( $wpdb->prepare( $query, $allParams ), ARRAY_A );

		$optins = array();
		foreach ( $rows as $row ) {
			$optins[] = $this->formatOptinRow( $row );
		}

		return new \WP_REST_Response(
			array(
				'success' => true,
				'data'    => array(
					'items'   => $optins,
					'total'   => $total,
					'pages'   => (int) ceil( $total / $perPage ),
					'page'    => $page,
					'perPage' => $perPage,
				),
			),
			200
		);
	}

	public function getOptin( \WP_REST_Request $request ): \WP_REST_Response {
		global $wpdb;
		$id    = (int) $request->get_param( 'id' );
		$table = $wpdb->prefix . 'f12_cf7_doubleoptin';

		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );

		if ( ! $row ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Opt-In not found.', 'double-opt-in' ),
				),
				404
			);
		}

		$data = $this->formatOptinRow( $row, true );

		// Dev-mode UI hint: surface whether the reset-confirmation
		// endpoint is reachable for this request, so the React detail
		// page can show/hide the "Reset to pending" button without
		// having to probe the endpoint and handle a 403. Mirrors
		// the same WP_DEBUG + capability gate as the endpoint itself.
		$data['_devResetAvailable'] = $this->checkDevResetPermission();

		/**
		 * Filter single optin response so Pro can add reminder/optout data.
		 *
		 * @param array $data    The optin data.
		 * @param int   $optinId The optin ID.
		 * @since 4.2.0
		 */
		$data = apply_filters( 'f12_doi_rest_optin_response', $data, $id );

		return new \WP_REST_Response(
			array(
				'success' => true,
				'data'    => $data,
			),
			200
		);
	}

	public function deleteOptin( \WP_REST_Request $request ): \WP_REST_Response {
		global $wpdb;
		$id    = (int) $request->get_param( 'id' );
		$table = $wpdb->prefix . 'f12_cf7_doubleoptin';

		// Pre-fetch the row so post-delete listeners (file-storage
		// cleanup, addon cleanup hooks) get the data they need to
		// locate per-OptIn artifacts. Pre-fix the REST endpoint
		// silently bypassed the deletion-event pipeline that the cron
		// + manual-hash paths use — addons hooking
		// f12_cf7_doubleoptin_deleted got coverage gaps for any opt-in
		// the admin removed via the React Trash button.
		//
		// Full row (id, hash, content, files, cf_form_id) so the
		// pre-delete cascade hook from pre-doi-data-retention Step 1
		// can fire with a payload that lets listeners reach into
		// integration storage. ARRAY_A — listener-friendly.
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT id, hash, content, files, cf_form_id FROM {$table} WHERE id = %d", $id ),
			ARRAY_A
		);
		$hash = is_array( $row ) ? ( $row['hash'] ?? null ) : null;

		// Pre-delete cascade — fires before the DELETE so listeners
		// read the row's payload to cascade form-system cleanup. Mirror
		// of CleanUp::removeOlderThan + delete_optin_by_hash. See
		// plan/pre-doi-data-retention.md.
		if ( is_array( $row ) ) {
			do_action( 'f12_doi_optin_pre_delete', $row );
		}

		$result = $wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );

		if ( $result === false ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Failed to delete opt-in.', 'double-opt-in' ),
				),
				500
			);
		}

		// Fire the deletion event ONLY if a row actually existed and
		// was removed. Idempotent retries (DELETE on a non-existent
		// id) silently succeed at the wpdb layer with $result=0 — but
		// dispatching an event for a no-op deletion would mislead any
		// listener doing aggregate counting / file cleanup.
		if ( $hash && (int) $result > 0 ) {
			$cleanup = new \forge12\contactform7\CF7DoubleOptIn\CleanUp(
				\Forge12\Shared\Logger::getInstance()
			);
			$cleanup->dispatchOptInDeletedEvent(
				(string) $hash,
				'manual_rest',
				get_current_user_id() ?: null
			);
		}

		AuditLogger::log(
			AuditLogger::TYPE_SETTINGS,
			AuditLogger::SEVERITY_INFO,
			sprintf(
				__( 'Opt-in #%d deleted.', 'double-opt-in' ),
				$id
			)
		);

		return new \WP_REST_Response(
			array(
				'success' => true,
				'message' => __( 'Opt-In deleted.', 'double-opt-in' ),
			),
			200
		);
	}

	public function resendOptinEmail( \WP_REST_Request $request ): \WP_REST_Response {
		global $wpdb;
		$id    = (int) $request->get_param( 'id' );
		$table = $wpdb->prefix . 'f12_cf7_doubleoptin';

		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );

		if ( ! $row ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Opt-In not found.', 'double-opt-in' ),
				),
				404
			);
		}

		if ( (int) $row['doubleoptin'] === 1 ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Opt-In is already confirmed.', 'double-opt-in' ),
				),
				400
			);
		}

		// Try to get the OptIn object via hash for filter compatibility
		$optin = \forge12\contactform7\CF7DoubleOptIn\OptIn::get_by_hash( $row['hash'] );

		/**
		 * Allow Pro or other extensions to handle the actual resend.
		 *
		 * @param bool|null $result  Null = not handled, true = sent, false = failed.
		 * @param object    $optin   The OptIn instance (or null).
		 * @param array     $row     The raw database row.
		 * @since 4.2.0
		 */
		$result = apply_filters( 'f12_doi_rest_resend_optin_email', null, $optin, $row );

		if ( $result === null ) {
			// Default resend logic: use stored mail data.
			//
			// `mail_optin` is shipped by every integration via
			// {@see \forge12\contactform7\CF7DoubleOptIn\OptIn::set_mail_optin()}.
			// That method takes a STRING (the rendered HTML body) — the
			// admin opt-in-detail UI reads it as-is for the body
			// preview. Earlier versions of this handler expected a
			// serialized `['to' => ..., 'subject' => ..., 'body' => ...]`
			// array and bailed with "Email data is incomplete" whenever
			// the stored value was the (correct) plain body string —
			// which is the production case for every free-version
			// integration (CF7 / Avada / WPForms / Gravity / Elementor).
			// User-reported 2026-05-13: clicking Resend yielded that
			// error 100 % of the time.
			//
			// Both shapes are accepted now: the array form for Pro and
			// any future caller that stores structured payloads, the
			// plain string for the free-version integrations whose
			// contract is documented in
			// {@see \Forge12\DoubleOptIn\Wpforms\Tests\Unit\Integration\WPFormsSettingsApplyTest}.
			$mailOptin = $row['mail_optin'] ?? '';
			if ( empty( $mailOptin ) ) {
				return new \WP_REST_Response(
					array(
						'success' => false,
						'message' => __( 'No email data available for resend.', 'double-opt-in' ),
					),
					400
				);
			}

			$unserialized = maybe_unserialize( $mailOptin );

			if ( is_array( $unserialized ) ) {
				// Structured payload (Pro / future writers).
				$to      = $unserialized['to']      ?? '';
				$subject = $unserialized['subject'] ?? '';
				$body    = $unserialized['body']    ?? '';
				$from    = $unserialized['from']    ?? '';
			} else {
				// Plain body string — the production case. Reconstruct
				// `to` from the OptIn record's own `email` column and
				// `subject` from the form's central settings.
				$to      = $row['email'] ?? '';
				$body    = is_string( $unserialized ) ? $unserialized : (string) $mailOptin;
				$subject = '';
				$from    = '';

				$formId = isset( $row['cf_form_id'] ) ? (int) $row['cf_form_id'] : 0;
				if ( $formId > 0 && class_exists( '\\forge12\\contactform7\\CF7DoubleOptIn\\CF7DoubleOptIn' ) ) {
					$formParam = \forge12\contactform7\CF7DoubleOptIn\CF7DoubleOptIn::getInstance()->getParameter( $formId );
					$subject   = (string) ( $formParam['subject']     ?? '' );
					$senderEmail = (string) ( $formParam['sender']      ?? '' );
					$senderName  = (string) ( $formParam['sender_name'] ?? '' );
					if ( $senderEmail !== '' ) {
						$from = $senderName !== ''
							? $senderName . ' <' . $senderEmail . '>'
							: $senderEmail;
					}
				}
			}

			if ( empty( $to ) || empty( $body ) ) {
				return new \WP_REST_Response(
					array(
						'success' => false,
						'message' => __( 'Email data is incomplete.', 'double-opt-in' ),
					),
					400
				);
			}

			$headers = array( 'Content-Type: text/html; charset=UTF-8' );
			if ( ! empty( $from ) ) {
				$headers[] = 'From: ' . $from;
			}

			$result = wp_mail( $to, $subject !== '' ? $subject : __( 'Confirmation Email (resent)', 'double-opt-in' ), $body, $headers );
		}

		if ( ! $result ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Failed to send email.', 'double-opt-in' ),
				),
				500
			);
		}

		AuditLogger::log(
			AuditLogger::TYPE_EMAIL,
			AuditLogger::SEVERITY_INFO,
			sprintf(
				__( 'Confirmation email resent for opt-in #%d.', 'double-opt-in' ),
				$id
			)
		);

		return new \WP_REST_Response(
			array(
				'success' => true,
				'message' => __( 'Confirmation email resent.', 'double-opt-in' ),
			),
			200
		);
	}

	/**
	 * Dev-only: revert a confirmed opt-in to "pending".
	 *
	 * Lets developers click the same confirmation link multiple times
	 * during integration testing without re-filling the source form.
	 * Permission is locked behind WP_DEBUG via checkDevResetPermission()
	 * — this MUST NOT be reachable in production: resetting an opt-in's
	 * doubleoptin flag drops legal Art.7 consent state.
	 *
	 * Side-effect intentional: any submission/entry rows that the
	 * Avada (or future) replay wrote on the previous confirmation are
	 * left alone. They become orphan dev-noise, deletable via Avada's
	 * own Form Entries UI. Cleaning them up here would require knowing
	 * the integration-specific cleanup path for every addon, which is
	 * scope-creep for a debugging convenience.
	 */
	public function resetOptinConfirmation( \WP_REST_Request $request ): \WP_REST_Response {
		global $wpdb;
		$id    = (int) $request->get_param( 'id' );
		$table = $wpdb->prefix . 'f12_cf7_doubleoptin';

		$row = $wpdb->get_row( $wpdb->prepare( "SELECT id, hash, doubleoptin FROM {$table} WHERE id = %d", $id ), ARRAY_A );

		if ( ! $row ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Opt-In not found.', 'double-opt-in' ),
				),
				404
			);
		}

		if ( (int) $row['doubleoptin'] !== 1 ) {
			// Already pending — nothing to do, return success so the
			// React UI's idempotent retry behaves cleanly.
			return new \WP_REST_Response(
				array(
					'success' => true,
					'message' => __( 'Opt-In is already pending.', 'double-opt-in' ),
				),
				200
			);
		}

		$result = $wpdb->update(
			$table,
			array( 'doubleoptin' => 0 ),
			array( 'id' => $id ),
			array( '%d' ),
			array( '%d' )
		);

		if ( $result === false ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Failed to reset confirmation status.', 'double-opt-in' ),
				),
				500
			);
		}

		AuditLogger::log(
			AuditLogger::TYPE_SETTINGS,
			AuditLogger::SEVERITY_WARNING,
			sprintf(
				/* translators: %d: opt-in id */
				__( 'DEV: Opt-in #%d confirmation reset (WP_DEBUG mode).', 'double-opt-in' ),
				$id
			)
		);

		/**
		 * Fires after a dev-mode reset. Addons can use this to clear
		 * any side-effect rows they wrote on confirmation (e.g. the
		 * Avada submission/entries replay) so a re-confirmation starts
		 * from a clean slate.
		 *
		 * @param int    $id   The opt-in id whose confirmation was reset.
		 * @param string $hash The opt-in's confirmation hash.
		 * @since 4.3.0
		 */
		do_action( 'f12_doi_optin_confirmation_reset', $id, $row['hash'] );

		return new \WP_REST_Response(
			array(
				'success' => true,
				'message' => __( 'Confirmation reset to pending. Click the confirmation link again to re-test.', 'double-opt-in' ),
			),
			200
		);
	}

	// ═══════════════════════════════════════════════════════════════
	// FORMS
	// ═══════════════════════════════════════════════════════════════

	public function getForms( \WP_REST_Request $request ): \WP_REST_Response {
		$forms = $this->formService->getAllForms();

		// Enrich each form with completeness data so FormsPage can
		// render the "Konfiguration unvollständig"-Badge and disable
		// the toggle for forms whose config blocks activation
		// (plan/doi-completeness-gate.md §2.5).
		foreach ( $forms as $integrationKey => &$integrationData ) {
			if ( ! isset( $integrationData['forms'] ) || ! is_array( $integrationData['forms'] ) ) {
				continue;
			}
			foreach ( $integrationData['forms'] as &$form ) {
				$formId    = $form['id'] ?? null;
				$storageId = is_string( $formId ) && strpos( $formId, '_' ) !== false
					? (int) explode( '_', $formId )[0]
					: (int) $formId;
				if ( $storageId <= 0 ) {
					$form['isComplete']    = false;
					$form['missingFields'] = array();
					$form['enabled']       = false;
					continue;
				}

				$dto                   = $this->formService->getSettings( $storageId );
				$missing               = $dto->getMissingRequiredFields();
				$form['isComplete']    = empty( $missing );
				$form['missingFields'] = array_values( $missing );

				// Completeness-gate override (user-reported 2026-05-13):
				// the Integration's getForms() reads the raw `enable=1`
				// from post-meta. A form whose recipient field was
				// removed AFTER it was originally enabled stays
				// `enable=1` in storage, so the list view used to
				// render it as active green-checkmark while the detail
				// view showed it as disabled (the latter applies the
				// same gate that REST save + the completeness-sweep
				// migration apply). Both surfaces now agree: an
				// incomplete form is effectively inactive, period.
				// Storage stays as-is so the user's intent survives —
				// once they fix the missing field, the gate clears and
				// the form goes back to its stored enabled state.
				if ( ! empty( $missing ) ) {
					$form['enabled'] = false;
				}
			}
			unset( $form );
		}
		unset( $integrationData );

		$data = apply_filters( 'f12_doi_rest_forms_response', $forms );

		return new \WP_REST_Response(
			array(
				'success' => true,
				'data'    => $data,
			),
			200
		);
	}

	public function getFormSettings( \WP_REST_Request $request ): \WP_REST_Response {
		$formId      = sanitize_text_field( $request->get_param( 'form_id' ) );
		$integration = sanitize_text_field( $request->get_param( 'integration' ) );

		$formData = $this->formService->getFormData( $formId, $integration );

		if ( ! $formData ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Form not found.', 'double-opt-in' ),
				),
				404
			);
		}

		// Add dropdown data
		$formData['templates']       = $this->formService->getAvailableTemplates( $formId );
		$formData['categories']      = $this->formService->getAvailableCategories();
		$formData['pages']           = $this->formService->getAvailablePages();
		$formData['templateDetails'] = $this->formService->getTemplateDetails();

		/**
		 * Filter form settings response so Pro can add data.
		 *
		 * @param array      $formData    The form data.
		 * @param string|int $formId      The form ID.
		 * @param string     $integration The integration identifier.
		 * @since 4.2.0
		 */
		$formData = apply_filters( 'f12_doi_rest_form_settings_response', $formData, $formId, $integration );

		return new \WP_REST_Response(
			array(
				'success' => true,
				'data'    => $formData,
			),
			200
		);
	}

	public function saveFormSettings( \WP_REST_Request $request ): \WP_REST_Response {
		$formId      = sanitize_text_field( $request->get_param( 'form_id' ) );
		$integration = sanitize_text_field( $request->get_param( 'integration' ) );
		$input       = $request->get_json_params();

		if ( empty( $formId ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Invalid form ID.', 'double-opt-in' ),
				),
				400
			);
		}

		// For composite IDs (Elementor), extract post ID for storage
		$storageId = strpos( $formId, '_' ) !== false ? (int) explode( '_', $formId )[0] : (int) $formId;

		// Capture enabled-state BEFORE sanitize for the completeness-gate
		// (plan §2.2). Same shape as FormSettingsController; both
		// endpoints must enforce the same gate so the React UI sees
		// uniform behaviour whichever path it happens to use.
		$oldSettings = $this->formService->getSettings( $storageId );
		$wasEnabled  = $oldSettings->enabled;

		// Sanitize and create DTO
		$settingsData = $input['settings'] ?? $input;
		$settings     = $this->formValidator->sanitize( $settingsData );

		// Completeness-gate
		$missingRequired = $settings->getMissingRequiredFields();
		$autoDisabled    = false;

		if ( $settings->enabled && ! empty( $missingRequired ) ) {
			if ( ! $wasEnabled ) {
				return $this->incompleteConfigResponse( $missingRequired );
			}
			// Was enabled, save makes it incomplete — auto-disable so
			// the user's other edits land but the form stops misfiring.
			$settings->enabled = false;
			$autoDisabled      = true;

			do_action( 'f12_doi_form_auto_disabled_incomplete', $formId, $missingRequired );

			$this->logger->warning(
				'Form auto-disabled — REST save would have left it enabled with incomplete config',
				array(
					'plugin'  => 'double-opt-in',
					'form_id' => $formId,
					'missing' => $missingRequired,
				)
			);
		}

		// Format-only validation (sender email format, page/category existence)
		$errors = $this->formValidator->validate( $settings );
		if ( ! empty( $errors ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Validation failed.', 'double-opt-in' ),
					'errors'  => $errors,
				),
				400
			);
		}

		/**
		 * Filter to allow Pro to modify settings before saving.
		 *
		 * @param FormSettingsDTO $settings     The settings DTO.
		 * @param int             $storageId    The storage ID.
		 * @param array           $settingsData The raw input data.
		 * @since 4.2.0
		 */
		$settings = apply_filters( 'f12_doi_rest_form_settings_save', $settings, $storageId, $settingsData );

		$result = $this->formService->saveSettings( $storageId, $settings );

		if ( ! $result ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Failed to save settings.', 'double-opt-in' ),
				),
				500
			);
		}

		do_action( 'f12_doi_form_settings_saved', $formId, $settings, $settingsData, $integration );

		return new \WP_REST_Response(
			array(
				'success' => true,
				'message' => $autoDisabled
					? __( 'Settings saved. Double Opt-In was auto-disabled because the configuration is incomplete.', 'double-opt-in' )
					: __( 'Settings saved successfully.', 'double-opt-in' ),
				'data'    => array(
					'enabled'      => $settings->enabled,
					'autoDisabled' => $autoDisabled,
					'missing'      => array_values( $missingRequired ),
				),
			),
			200
		);
	}

	public function toggleForm( \WP_REST_Request $request ): \WP_REST_Response {
		$formId      = sanitize_text_field( $request->get_param( 'form_id' ) );
		$integration = sanitize_text_field( $request->get_param( 'integration' ) );

		$storageId = strpos( $formId, '_' ) !== false ? (int) explode( '_', $formId )[0] : (int) $formId;

		// Completeness-gate before toggle-to-enabled (plan §2.3).
		// Toggling-to-disabled is always allowed.
		$currentSettings = $this->formService->getSettings( $storageId );
		if ( ! $currentSettings->enabled ) {
			$missing = $currentSettings->getMissingRequiredFields();
			if ( ! empty( $missing ) ) {
				return $this->incompleteConfigResponse( $missing );
			}
		}

		$newState = $this->formService->toggleEnabled( $storageId );

		do_action( 'f12_doi_form_toggled', $formId, $newState, $integration );

		return new \WP_REST_Response(
			array(
				'success' => true,
				'data'    => array(
					'enabled' => $newState,
					'message' => $newState
						? __( 'Double Opt-In enabled.', 'double-opt-in' )
						: __( 'Double Opt-In disabled.', 'double-opt-in' ),
				),
			),
			200
		);
	}

	/**
	 * Build the structured 422 INCOMPLETE_CONFIG response shared by
	 * {@see saveFormSettings()} and {@see toggleForm()}.
	 *
	 * Per plan/doi-completeness-gate.md §2.2 + §2.3. Distinct code so
	 * the React UI can pattern-match on `code === 'INCOMPLETE_CONFIG'`
	 * for the Toast affordance (§2.7) instead of falling through to
	 * generic field-level error rendering.
	 *
	 * @param array<int,string> $missing Stable required-field IDs.
	 */
	private function incompleteConfigResponse( array $missing ): \WP_REST_Response {
		return new \WP_REST_Response(
			array(
				'success' => false,
				'code'    => 'INCOMPLETE_CONFIG',
				'message' => __(
					'Cannot enable Double Opt-In: configuration is incomplete. Please fill in all required fields first.',
					'double-opt-in'
				),
				'missing' => array_values( $missing ),
			),
			422
		);
	}

	public function getFormFields( \WP_REST_Request $request ): \WP_REST_Response {
		$formId      = sanitize_text_field( $request->get_param( 'form_id' ) );
		$integration = sanitize_text_field( $request->get_param( 'integration' ) );

		$formData = $this->formService->getFormData( $formId, $integration );

		if ( ! $formData ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Form not found.', 'double-opt-in' ),
				),
				404
			);
		}

		return new \WP_REST_Response(
			array(
				'success' => true,
				'data'    => $formData['fields'] ?? array(),
			),
			200
		);
	}

	// ═══════════════════════════════════════════════════════════════
	// SETTINGS
	// ═══════════════════════════════════════════════════════════════

	public function getSettings( \WP_REST_Request $request ): \WP_REST_Response {
		$defaults = array(
			'telemetry'                 => 1,
			'delete'                    => 12,
			'delete_unconfirmed'        => 7,
			'delete_period'             => 'months',
			'delete_unconfirmed_period' => 'months',
			'privacy_policy_page'       => 0,
			'token_expiry_hours'        => 48,
			'rate_limit_ip'             => 5,
			'rate_limit_email'          => 3,
			'rate_limit_window'         => 60,
			// Preserve opt-in data when the plugin is deleted. Defaults to 1
			// (keep) so deleting the plugin never silently destroys GDPR
			// consent records; admins can opt into a full cleanup. Read by
			// uninstall.php.
			'keep_data_on_uninstall'    => 1,
			// Pro defaults (will be overridden by f12_doi_rest_settings_response filter if Pro is active)
			'reminder_enabled'          => 0,
			'reminder_delay'            => 24,
			'reminder_subject'          => '',
			'reminder_template'         => '',
			'mx_validation_enabled'     => 0,
			'mx_validation_behavior'    => 'silent',
			'mx_validation_message'     => '',
			'domain_blocklist_enabled'  => 0,
			'domain_blocklist'          => '',
			'domain_blocklist_behavior' => 'silent',
			'domain_blocklist_message'  => '',
		);

		$settings = array_merge( $defaults, (array) get_option( 'f12-doi-settings', array() ) );

		/**
		 * Filter settings response so Pro can add its settings.
		 *
		 * @param array $settings The settings array.
		 * @since 4.2.0
		 */
		$settings = apply_filters( 'f12_doi_rest_settings_response', $settings );

		return new \WP_REST_Response(
			array(
				'success' => true,
				'data'    => $settings,
			),
			200
		);
	}

	public function updateSettings( \WP_REST_Request $request ): \WP_REST_Response {
		$input    = $request->get_json_params();
		$settings = (array) get_option( 'f12-doi-settings', array() );

		// Free settings validation & save
		$freeFields = array(
			'delete'                    => array(
				'type' => 'int',
				'min'  => 0,
				'max'  => 30,
			),
			'delete_period'             => array(
				'type'   => 'enum',
				'values' => array( 'months', 'days', 'years' ),
			),
			'delete_unconfirmed'        => array(
				'type' => 'int',
				'min'  => 0,
				'max'  => 30,
			),
			'delete_unconfirmed_period' => array(
				'type'   => 'enum',
				'values' => array( 'months', 'days', 'years' ),
			),
			'telemetry'                 => array(
				'type' => 'int',
				'min'  => 0,
				'max'  => 1,
			),
			'privacy_policy_page'       => array(
				'type' => 'int',
				'min'  => 0,
			),
			'token_expiry_hours'        => array(
				'type' => 'int',
				'min'  => 0,
				'max'  => 720,
			),
			'rate_limit_ip'             => array(
				'type' => 'int',
				'min'  => 0,
				'max'  => 100,
			),
			'rate_limit_email'          => array(
				'type' => 'int',
				'min'  => 0,
				'max'  => 100,
			),
			'rate_limit_window'         => array(
				'type' => 'int',
				'min'  => 1,
				'max'  => 1440,
			),
			'keep_data_on_uninstall'    => array(
				'type' => 'int',
				'min'  => 0,
				'max'  => 1,
			),
		);

		foreach ( $freeFields as $key => $rules ) {
			if ( ! array_key_exists( $key, $input ) ) {
				continue;
			}

			$value = $input[ $key ];

			switch ( $rules['type'] ) {
				case 'int':
					$value = (int) $value;
					if ( isset( $rules['min'] ) ) {
						$value = max( $rules['min'], $value ); }
					if ( isset( $rules['max'] ) ) {
						$value = min( $rules['max'], $value ); }
					break;
				case 'enum':
					$value = sanitize_text_field( $value );
					if ( ! in_array( $value, $rules['values'], true ) ) {
						$value = $rules['values'][0];
					}
					break;
				default:
					$value = sanitize_text_field( $value );
			}

			$settings[ $key ] = $value;
		}

		/**
		 * Filter to allow Pro to process its settings before saving.
		 *
		 * @param array $settings The settings to save.
		 * @param array $input    The raw input from the request.
		 * @since 4.2.0
		 */
		$settings = apply_filters( 'f12_doi_rest_settings_save', $settings, $input );

		update_option( 'f12-doi-settings', $settings );

		AuditLogger::log( AuditLogger::TYPE_SETTINGS, AuditLogger::SEVERITY_INFO, __( 'Global settings updated via REST API.', 'double-opt-in' ) );

		// Return updated settings
		$settings = apply_filters( 'f12_doi_rest_settings_response', $settings );

		return new \WP_REST_Response(
			array(
				'success' => true,
				'data'    => $settings,
			),
			200
		);
	}

	public function getPages( \WP_REST_Request $request ): \WP_REST_Response {
		$pages = $this->formService->getAvailablePages();

		$list = array();
		foreach ( $pages as $id => $title ) {
			$list[] = array(
				'id'    => $id,
				'title' => $title,
			);
		}

		return new \WP_REST_Response(
			array(
				'success' => true,
				'data'    => $list,
			),
			200
		);
	}

	public function getEmailTemplatesList( \WP_REST_Request $request ): \WP_REST_Response {
		$presets = $this->formService->getAvailableTemplates( 0 );
		$details = $this->formService->getTemplateDetails();

		// Build a flat list for dropdown selectors
		$list = array();
		foreach ( $presets as $key => $label ) {
			$list[] = array(
				'id'    => $key,
				'title' => $label,
			);
		}
		foreach ( $details as $key => $detail ) {
			$list[] = array(
				'id'    => $key,
				'title' => $detail['title'] ?? $key,
			);
		}

		return new \WP_REST_Response(
			array(
				'success' => true,
				'data'    => $list,
			),
			200
		);
	}

	// ═══════════════════════════════════════════════════════════════
	// CATEGORIES
	// ═══════════════════════════════════════════════════════════════

	public function getCategories( \WP_REST_Request $request ): \WP_REST_Response {
		global $wpdb;

		$catTable   = $wpdb->prefix . 'f12_cf7_doubleoptin_categories';
		$optinTable = $wpdb->prefix . 'f12_cf7_doubleoptin';

		$categories = $wpdb->get_results(
			"SELECT c.*, COALESCE(o.cnt, 0) as optin_count
			 FROM {$catTable} c
			 LEFT JOIN (SELECT category, COUNT(*) as cnt FROM {$optinTable} GROUP BY category) o ON o.category = c.id
			 ORDER BY c.name ASC",
			ARRAY_A
		);

		return new \WP_REST_Response(
			array(
				'success' => true,
				'data'    => $categories ?: array(),
			),
			200
		);
	}

	public function createCategory( \WP_REST_Request $request ): \WP_REST_Response {
		$data = $request->get_json_params();
		$name = sanitize_text_field( $data['name'] ?? '' );

		if ( empty( $name ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Category name is required.', 'double-opt-in' ),
				),
				400
			);
		}

		$category = new \forge12\contactform7\CF7DoubleOptIn\Category( \Forge12\Shared\Logger::getInstance() );
		$category->set_name( $name );
		$category->set_createtime( current_time( 'mysql' ) );
		$category->set_updatetime( current_time( 'mysql' ) );
		$id = $category->save();

		if ( ! $id ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Failed to create category.', 'double-opt-in' ),
				),
				500
			);
		}

		return new \WP_REST_Response(
			array(
				'success' => true,
				'data'    => array(
					'id'         => $id,
					'name'       => $name,
					'createtime' => $category->get_createtime(),
					'updatetime' => $category->get_updatetime(),
				),
			),
			201
		);
	}

	public function updateCategory( \WP_REST_Request $request ): \WP_REST_Response {
		$id   = (int) $request->get_param( 'id' );
		$data = $request->get_json_params();
		$name = sanitize_text_field( $data['name'] ?? '' );

		if ( empty( $name ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Category name is required.', 'double-opt-in' ),
				),
				400
			);
		}

		$category = \forge12\contactform7\CF7DoubleOptIn\Category::get_by_id( $id );
		if ( ! $category ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Category not found.', 'double-opt-in' ),
				),
				404
			);
		}

		$category->set_name( $name );
		$category->set_updatetime( current_time( 'mysql' ) );
		$category->save();

		return new \WP_REST_Response(
			array(
				'success' => true,
				'data'    => array(
					'id'         => $id,
					'name'       => $name,
					'updatetime' => $category->get_updatetime(),
				),
			),
			200
		);
	}

	public function deleteCategory( \WP_REST_Request $request ): \WP_REST_Response {
		$id = (int) $request->get_param( 'id' );

		$result = \forge12\contactform7\CF7DoubleOptIn\Category::delete_by_id( $id );

		// `false` = real DB error (query failed, legacy OptIn class missing).
		// `0`     = no row matched the ID — typically a stale UI re-click on
		//           an already-deleted category. Not an error: the desired
		//           end-state (category not present) is reached.
		// `>= 1`  = success.
		if ( $result === false ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Failed to delete category.', 'double-opt-in' ),
				),
				500
			);
		}

		return new \WP_REST_Response(
			array(
				'success' => true,
				'message' => __( 'Category deleted.', 'double-opt-in' ),
			),
			200
		);
	}

	// ═══════════════════════════════════════════════════════════════
	// DATABASE
	// ═══════════════════════════════════════════════════════════════

	public function getDatabaseStats( \WP_REST_Request $request ): \WP_REST_Response {
		global $wpdb;
		$table = $wpdb->prefix . 'f12_cf7_doubleoptin';

		$total       = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		$confirmed   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE doubleoptin = 1" );
		$unconfirmed = $total - $confirmed;

		return new \WP_REST_Response(
			array(
				'success' => true,
				'data'    => array(
					'total'       => $total,
					'confirmed'   => $confirmed,
					'unconfirmed' => $unconfirmed,
				),
			),
			200
		);
	}

	public function cleanDatabase( \WP_REST_Request $request ): \WP_REST_Response {
		$data  = $request->get_json_params();
		$scope = sanitize_text_field( $data['scope'] ?? '' );

		if ( ! in_array( $scope, array( 'all', 'confirmed', 'unconfirmed' ), true ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Invalid scope.', 'double-opt-in' ),
				),
				400
			);
		}

		$cleanUp = new \forge12\contactform7\CF7DoubleOptIn\CleanUp( $this->logger );

		if ( $scope === 'all' || $scope === 'confirmed' ) {
			$cleanUp->removeConfirmedOptins( true );
		}
		if ( $scope === 'all' || $scope === 'unconfirmed' ) {
			$cleanUp->removeUnconfirmedOptins( true );
		}

		AuditLogger::log(
			AuditLogger::TYPE_SETTINGS,
			AuditLogger::SEVERITY_WARNING,
			sprintf(
				__( 'Database cleaned (scope: %s).', 'double-opt-in' ),
				$scope
			)
		);

		return new \WP_REST_Response(
			array(
				'success' => true,
				'message' => __( 'Database cleaned.', 'double-opt-in' ),
			),
			200
		);
	}

	public function resetDatabase( \WP_REST_Request $request ): \WP_REST_Response {
		$cleanUp = new \forge12\contactform7\CF7DoubleOptIn\CleanUp( $this->logger );
		$cleanUp->reset();

		AuditLogger::log( AuditLogger::TYPE_SETTINGS, AuditLogger::SEVERITY_CRITICAL, __( 'Database reset performed.', 'double-opt-in' ) );

		return new \WP_REST_Response(
			array(
				'success' => true,
				'message' => __( 'Database reset finished.', 'double-opt-in' ),
			),
			200
		);
	}

	// ═══════════════════════════════════════════════════════════════
	// AUDIT LOG
	// ═══════════════════════════════════════════════════════════════

	public function getAuditEvents( \WP_REST_Request $request ): \WP_REST_Response {
		$result = AuditLogger::getEvents(
			array(
				'period'   => (int) ( $request->get_param( 'period' ) ?: 30 ),
				'type'     => $request->get_param( 'type' ) ?? '',
				'severity' => $request->get_param( 'severity' ) ?? '',
				'page'     => (int) ( $request->get_param( 'page' ) ?: 1 ),
				'per_page' => (int) ( $request->get_param( 'per_page' ) ?: 15 ),
			)
		);

		return new \WP_REST_Response(
			array(
				'success' => true,
				'data'    => $result,
			),
			200
		);
	}

	public function getAuditSummary( \WP_REST_Request $request ): \WP_REST_Response {
		$period  = (int) ( $request->get_param( 'period' ) ?: 30 );
		$summary = AuditLogger::getSummary( $period );

		return new \WP_REST_Response(
			array(
				'success' => true,
				'data'    => $summary,
			),
			200
		);
	}

	// ═══════════════════════════════════════════════════════════════
	// PRO-EXTENSIBLE STUBS
	// These return minimal responses; Pro overrides via filters or
	// registers its own REST routes that take precedence.
	// ═══════════════════════════════════════════════════════════════

	public function getAnalyticsOverview( \WP_REST_Request $request ): \WP_REST_Response {
		if ( ! apply_filters( 'f12_doi_is_pro_active', false ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Pro version required.', 'double-opt-in' ),
				),
				403
			);
		}

		$data = apply_filters( 'f12_doi_rest_analytics_overview', array(), $request );

		return new \WP_REST_Response(
			array(
				'success' => true,
				'data'    => $data,
			),
			200
		);
	}

	public function getAnalyticsForm( \WP_REST_Request $request ): \WP_REST_Response {
		if ( ! apply_filters( 'f12_doi_is_pro_active', false ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Pro version required.', 'double-opt-in' ),
				),
				403
			);
		}

		$formId = (int) $request->get_param( 'form_id' );
		$data   = apply_filters( 'f12_doi_rest_analytics_form', array(), $formId, $request );

		return new \WP_REST_Response(
			array(
				'success' => true,
				'data'    => $data,
			),
			200
		);
	}

	public function getOptoutSettings( \WP_REST_Request $request ): \WP_REST_Response {
		if ( ! apply_filters( 'f12_doi_is_pro_active', false ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Pro version required.', 'double-opt-in' ),
				),
				403
			);
		}

		$data = apply_filters( 'f12_doi_rest_optout_settings', array(), $request );

		return new \WP_REST_Response(
			array(
				'success' => true,
				'data'    => $data,
			),
			200
		);
	}

	public function updateOptoutSettings( \WP_REST_Request $request ): \WP_REST_Response {
		if ( ! apply_filters( 'f12_doi_is_pro_active', false ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Pro version required.', 'double-opt-in' ),
				),
				403
			);
		}

		$data = apply_filters( 'f12_doi_rest_optout_settings_save', array(), $request );

		return new \WP_REST_Response(
			array(
				'success' => true,
				'data'    => $data,
			),
			200
		);
	}

	/**
	 * POST /f12-doi/v1/optout/page/generate
	 *
	 * One-click generator for the opt-out landing page. Eliminates the
	 * onboarding-friction loop where the user has to manually create a
	 * page and paste the shortcodes before opt-out works at all.
	 *
	 * Algorithm:
	 *   1. Idempotent fast-path — scan `published` pages for the list
	 *      shortcode. If one already exists, return its ID untouched
	 *      (no duplicate creation, no content overwrite).
	 *   2. Title-collision safety — if a page named "Opt-Out" exists
	 *      but WITHOUT the list shortcode, refuse to auto-modify. The
	 *      user might have intentionally repurposed that title; we'd
	 *      rather show a 409 with a clear message than clobber.
	 *   3. Insert a fresh page with both shortcodes (form + list) so
	 *      the page is functional end-to-end out of the box.
	 *
	 * Response shape (always 200 unless error):
	 *   { page_id, page_title, edit_url, view_url, created: bool }
	 *
	 * @return \WP_REST_Response
	 */
	public function generateOptoutPage( \WP_REST_Request $request ): \WP_REST_Response {
		if ( ! apply_filters( 'f12_doi_is_pro_active', false ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Pro version required.', 'double-opt-in' ),
				),
				403
			);
		}

		if ( ! current_user_can( 'publish_pages' ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'You do not have permission to create pages.', 'double-opt-in' ),
				),
				403
			);
		}

		$listShortcode = '[f12-cf7-doubleoptin-optout-list]';
		$formShortcode = '[f12-cf7-doubleoptin-optout-form]';

		// 1. Idempotent fast-path — first page with the list shortcode wins.
		$existing = get_posts(
			array(
				'post_type'      => 'page',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				's'              => $listShortcode,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);
		if ( ! empty( $existing ) ) {
			$pageId = (int) $existing[0];
			return new \WP_REST_Response(
				array(
					'success'    => true,
					'created'    => false,
					'page_id'    => $pageId,
					'page_title' => get_the_title( $pageId ),
					'edit_url'   => get_edit_post_link( $pageId, 'raw' ),
					'view_url'   => get_permalink( $pageId ),
					'message'    => __( 'An existing opt-out page was selected.', 'double-opt-in' ),
				),
				200
			);
		}

		// 2. Title collision — a page literally titled "Opt-Out" but
		//    without the shortcode is the user's own content. Refuse
		//    to silently modify it.
		$desiredTitle = __( 'Opt-Out', 'double-opt-in-opt-out' );
		$collisionId  = (int) get_page_by_path( sanitize_title( $desiredTitle ), OBJECT, 'page' )?->ID;
		if ( $collisionId > 0 ) {
			return new \WP_REST_Response(
				array(
					'success'      => false,
					'code'         => 'TITLE_COLLISION',
					'page_id'      => $collisionId,
					'edit_url'     => get_edit_post_link( $collisionId, 'raw' ),
					'message'      => sprintf(
						/* translators: %s = page title */
						__( 'A page titled "%s" already exists but doesn\'t contain the opt-out shortcode. Add the shortcode manually, or rename the page, then try again.', 'double-opt-in' ),
						$desiredTitle
					),
				),
				409
			);
		}

		// 3. Insert.
		$pageId = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => $desiredTitle,
				'post_content' => $formShortcode . "\n\n" . $listShortcode,
				'post_author'  => get_current_user_id(),
				'comment_status' => 'closed',
				'ping_status'    => 'closed',
			),
			true
		);

		if ( is_wp_error( $pageId ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => $pageId->get_error_message(),
				),
				500
			);
		}

		return new \WP_REST_Response(
			array(
				'success'    => true,
				'created'    => true,
				'page_id'    => (int) $pageId,
				'page_title' => $desiredTitle,
				'edit_url'   => get_edit_post_link( (int) $pageId, 'raw' ),
				'view_url'   => get_permalink( (int) $pageId ),
				'message'    => __( 'Opt-out page created and selected.', 'double-opt-in' ),
			),
			200
		);
	}

	/**
	 * License gate for the User Creation endpoints.
	 *
	 * Under bundle-only licensing the entitlement is expressed through the
	 * addon's own registry lookup: the bundle grants `user-registration`
	 * into AddonLicenseRegistry, and the addon hooks
	 * `f12_doi_user_creation_authorized` to return `isLicensed('user-registration')`.
	 * That is the precise, tier-safe gate — we do NOT fall back to the raw
	 * `f12_doi_is_pro_active` bundle flag, which would over-authorize a
	 * future bundle tier that does not cover this addon, or a site where the
	 * addon plugin isn't even booted. (Per-module standalone licensing was
	 * removed 2026-07-11 — see plan/bundle-only-licensing-migration.md.)
	 */
	private function userCreationAuthorized(): bool {
		return (bool) apply_filters( 'f12_doi_user_creation_authorized', false );
	}

	public function getUserCreationSettings( \WP_REST_Request $request ): \WP_REST_Response {
		if ( ! $this->userCreationAuthorized() ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'User Registration addon is not licensed for this site.', 'double-opt-in' ),
				),
				403
			);
		}

		$data = apply_filters( 'f12_doi_rest_user_creation_settings', array(), $request );

		return new \WP_REST_Response(
			array(
				'success' => true,
				'data'    => $data,
			),
			200
		);
	}

	public function updateUserCreationSettings( \WP_REST_Request $request ): \WP_REST_Response {
		if ( ! $this->userCreationAuthorized() ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'User Registration addon is not licensed for this site.', 'double-opt-in' ),
				),
				403
			);
		}

		$data = apply_filters( 'f12_doi_rest_user_creation_settings_save', array(), $request );

		return new \WP_REST_Response(
			array(
				'success' => true,
				'data'    => $data,
			),
			200
		);
	}

	public function getApiSettings( \WP_REST_Request $request ): \WP_REST_Response {
		if ( ! apply_filters( 'f12_doi_is_pro_active', false ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Pro version required.', 'double-opt-in' ),
				),
				403
			);
		}

		$data = apply_filters( 'f12_doi_rest_api_settings', array(), $request );

		return new \WP_REST_Response(
			array(
				'success' => true,
				'data'    => $data,
			),
			200
		);
	}

	public function updateApiSettings( \WP_REST_Request $request ): \WP_REST_Response {
		if ( ! apply_filters( 'f12_doi_is_pro_active', false ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Pro version required.', 'double-opt-in' ),
				),
				403
			);
		}

		$data = apply_filters( 'f12_doi_rest_api_settings_save', array(), $request );

		return new \WP_REST_Response(
			array(
				'success' => true,
				'data'    => $data,
			),
			200
		);
	}

	public function getLicense( \WP_REST_Request $request ): \WP_REST_Response {
		$data = array(
			'isActive'    => apply_filters( 'f12_doi_is_pro_active', false ),
			'isInstalled' => defined( 'F12_DOI_PRO_VERSION' ),
			'licenseType' => null,
			'expiresAt'   => null,
			'key'         => null,
			'features'    => $this->getFeaturesList(),
		);

		/**
		 * Filter license data so Pro can add real license info.
		 *
		 * @param array $data License data.
		 * @since 4.2.0
		 */
		$data = apply_filters( 'f12_doi_rest_license_response', $data );

		return new \WP_REST_Response(
			array(
				'success' => true,
				'data'    => $data,
			),
			200
		);
	}

	public function activateLicense( \WP_REST_Request $request ): \WP_REST_Response {
		$input = $request->get_json_params();
		$key   = sanitize_text_field( $input['key'] ?? '' );

		if ( empty( $key ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'License key is required.', 'double-opt-in' ),
				),
				400
			);
		}

		/**
		 * Filter to let Pro handle license activation.
		 *
		 * @param array  $result Result array.
		 * @param string $key    The license key.
		 * @since 4.2.0
		 */
		$result = apply_filters(
			'f12_doi_rest_license_activate',
			array(
				'success' => false,
				'message' => __( 'Pro plugin not installed.', 'double-opt-in' ),
			),
			$key
		);

		$status = ( $result['success'] ?? false ) ? 200 : 400;

		return new \WP_REST_Response( $result, $status );
	}

	public function deactivateLicense( \WP_REST_Request $request ): \WP_REST_Response {
		/**
		 * Filter to let Pro handle license deactivation.
		 *
		 * @param array $result Result array.
		 * @since 4.2.0
		 */
		$result = apply_filters(
			'f12_doi_rest_license_deactivate',
			array(
				'success' => false,
				'message' => __( 'Pro plugin not installed.', 'double-opt-in' ),
			)
		);

		$status = ( $result['success'] ?? false ) ? 200 : 400;

		return new \WP_REST_Response( $result, $status );
	}

	public function exportDatabase( \WP_REST_Request $request ): \WP_REST_Response {
		if ( ! apply_filters( 'f12_doi_is_pro_active', false ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Pro version required.', 'double-opt-in' ),
				),
				403
			);
		}

		$input = $request->get_json_params();

		/**
		 * Filter to let Pro handle database export.
		 *
		 * @param array $result Result.
		 * @param array $input  Export parameters.
		 * @since 4.2.0
		 */
		$result = apply_filters(
			'f12_doi_rest_database_export',
			array(
				'success' => false,
				'message' => __( 'Export not available.', 'double-opt-in' ),
			),
			$input
		);

		return new \WP_REST_Response( $result, ( $result['success'] ?? false ) ? 200 : 400 );
	}

	// ═══════════════════════════════════════════════════════════════
	// HELPERS
	// ═══════════════════════════════════════════════════════════════

	/**
	 * Format an opt-in database row for the API response.
	 *
	 * @param array $row     The database row.
	 * @param bool  $detailed Whether to include full detail (content, mail data).
	 *
	 * @return array Formatted data.
	 */
	private function formatOptinRow( array $row, bool $detailed = false ): array {
		$post = get_post( (int) $row['cf_form_id'] );

		$data = array(
			'id'         => (int) $row['id'],
			'hash'       => $row['hash'],
			'email'      => $row['email'],
			'formId'     => (int) $row['cf_form_id'],
			'formName'   => $post ? $post->post_title : sprintf( '#%d', $row['cf_form_id'] ),
			'category'   => (int) $row['category'],
			'confirmed'  => (int) $row['doubleoptin'] === 1,
			'createtime' => $this->toSiteLocalTime( $row['createtime'] ),
			'updatetime' => $this->toSiteLocalTime( $row['updatetime'] ),
		);

		if ( $detailed ) {
			$data['ipRegister']     = $row['ipaddr_register'];
			$data['ipConfirmation'] = $row['ipaddr_confirmation'];
			$data['ipOptout']       = $row['ipaddr_optout'];
			$data['optouttime']     = $this->toSiteLocalTime( $row['optouttime'] );
			$data['consentText']    = $row['consent_text'];
			$data['consentField']   = $row['consent_field'] ?? '';
			$data['reminderSentAt'] = $this->toSiteLocalTime( $row['reminder_sent_at'] );

			// Category name
			$cat                  = \forge12\contactform7\CF7DoubleOptIn\Category::get_by_id( (int) $row['category'] );
			$data['categoryName'] = $cat ? $cat->get_name() : null;

			// Parse content (form submission data)
			$content          = maybe_unserialize( $row['content'] );
			$data['formData'] = is_array( $content ) ? $content : array();

			// Consent acknowledgment proof: when a consent_field was
			// configured, look up the value the user actually submitted.
			// Truthy = explicit acknowledgment captured. Falsy = either
			// gate wasn't enforced or this is a legacy record.
			//
			// Storage shape varies per integration:
			//   - CF7 / WPForms / GF (default path) store fields flat
			//     at the top level: $content[fieldName] = value.
			//   - Avada wraps fields under a `data` sub-key alongside
			//     metadata (field_labels, field_types, form_parameter)
			//     — its OnSubmit overrides the flat content set by
			//     createOptIn(). For Avada records, $content[fieldName]
			//     is undefined; the value lives at $content['data'][fieldName].
			//
			// Pre-2026-05-01 we only checked the flat shape, so every
			// Avada opt-in showed "User acknowledged: ✗ No" even when
			// the user explicitly checked the GDPR box. The fallback
			// below recognises the Avada shape too — adding a third
			// shape would be the next addition.
			$data['consentAcknowledged'] = ! empty( $data['consentField'] )
				&& is_array( $content )
				&& (
					! empty( $content[ $data['consentField'] ] )
					|| ! empty( $content['data'][ $data['consentField'] ] ?? null )
				);

			// Parse mail_optin
			$mailOptin         = maybe_unserialize( $row['mail_optin'] );
			$data['mailOptin'] = is_array( $mailOptin ) ? $mailOptin : array();

			// Raw form HTML and mail HTML for detail view
			$data['formHtml']      = $row['form'] ?? '';
			$data['mailOptinHtml'] = is_string( $row['mail_optin'] ?? '' ) ? $row['mail_optin'] : '';
		}

		return $data;
	}

	/**
	 * Convert a UTC datetime string from the DB to the site's
	 * configured timezone (Settings → General → Timezone).
	 *
	 * The OptIn entity persists timestamps via gmdate(), so DB rows
	 * always carry GMT/UTC. The admin React UI then displays whatever
	 * the REST endpoint returns verbatim — so the conversion has to
	 * happen here, server-side, against WP's site timezone (not the
	 * browser locale: a German admin checking the panel from a NYC
	 * hotel still wants to see Berlin time, because that's where the
	 * site lives).
	 *
	 * Empty / null values pass through as ''. Malformed strings
	 * (impossible in practice — the entity always emits Y-m-d H:i:s)
	 * get returned unchanged via get_date_from_gmt's fallback.
	 *
	 * @param mixed $utcString Raw value from $row[...] — usually
	 *                         'YYYY-MM-DD HH:MM:SS' UTC, or empty.
	 */
	private function toSiteLocalTime( $utcString ): string {
		if ( empty( $utcString ) ) {
			return '';
		}
		return get_date_from_gmt( (string) $utcString );
	}

	/**
	 * Get the features list for the license page.
	 *
	 * Built from three sources (in priority order):
	 *
	 *  1. Live addons in {@see AddonRegistry}. Each registered addon
	 *     contributes one entry using its own getId()/getName()/isAvailable().
	 *     This is the source of truth — Avada, Elementor, etc. show up
	 *     here as soon as their addon plugin is registered, with no
	 *     hardcoded names.
	 *
	 *  2. The `f12_doi_license_features` filter. Used by bundle-pro to
	 *     surface bundle-covered addons that are NOT yet installed (so
	 *     the user can see them as locked entries before running the
	 *     installer), and by other plugins that want to advertise an
	 *     unlock under the same license card. Filter contributions with
	 *     a slug that already came from the registry are ignored — the
	 *     live addon wins.
	 *
	 *     Filter signature: array<int, array{name:string,slug:string,available:bool}>
	 *
	 *  3. Core-side non-addon perks (hardcoded below). These are
	 *     license-bound features that don't have their own AddonInterface
	 *     implementation — Priority Support, Multi-Column Email Layouts,
	 *     Social Icons, Conditional Content Blocks. Same dedup-by-slug
	 *     rule applies.
	 *
	 * @return array<int, array{name:string,slug:string,available:bool}>
	 */
	private function getFeaturesList(): array {
		$isPro = (bool) apply_filters( 'f12_doi_is_pro_active', false );

		$features = array();

		// Tier 1 — live addons from the registry.
		if ( class_exists( '\\Forge12\\DoubleOptIn\\Addon\\AddonRegistry' ) ) {
			foreach ( AddonRegistry::getInstance()->all() as $id => $addon ) {
				$features[ $id ] = array(
					'name'      => (string) $addon->getName(),
					'slug'      => (string) $id,
					'available' => (bool) $addon->isAvailable(),
				);
			}
		}

		// Tier 2 — third-party / bundle contributions.
		$contributions = apply_filters( 'f12_doi_license_features', array(), $isPro );
		if ( is_array( $contributions ) ) {
			foreach ( $contributions as $entry ) {
				if ( ! is_array( $entry ) ) {
					continue;
				}
				$slug = isset( $entry['slug'] ) ? (string) $entry['slug'] : '';
				if ( $slug === '' || isset( $features[ $slug ] ) ) {
					continue;
				}
				$features[ $slug ] = array(
					'name'      => isset( $entry['name'] ) ? (string) $entry['name'] : $slug,
					'slug'      => $slug,
					'available' => isset( $entry['available'] ) ? (bool) $entry['available'] : $isPro,
				);
			}
		}

		// Tier 3 — Core-side non-addon Pro perks.
		$coreExtras = array(
			array(
				'name' => __( 'Multi-Column Email Layouts', 'double-opt-in' ),
				'slug' => 'multi-column',
			),
			array(
				'name' => __( 'Social Icons in Emails', 'double-opt-in' ),
				'slug' => 'social-icons',
			),
			array(
				'name' => __( 'Conditional Content Blocks', 'double-opt-in' ),
				'slug' => 'conditional-content',
			),
			array(
				'name' => __( 'Priority Support', 'double-opt-in' ),
				'slug' => 'priority-support',
			),
		);
		foreach ( $coreExtras as $entry ) {
			if ( isset( $features[ $entry['slug'] ] ) ) {
				continue;
			}
			$features[ $entry['slug'] ] = array(
				'name'      => $entry['name'],
				'slug'      => $entry['slug'],
				'available' => $isPro,
			);
		}

		return array_values( $features );
	}

	// ═══════════════════════════════════════════════════════════════
	// ADDONS MANIFEST (plan §9 — admin UI mount-point system)
	// ═══════════════════════════════════════════════════════════════

	/**
	 * GET /f12-doi/v1/addons
	 *
	 * Returns a manifest of every registered addon with:
	 *   - id, name, version, capabilities, available (from AddonInterface)
	 *   - ui.bundles[]: { handle, url } pairs of JS bundles Core should
	 *     dynamic-import() to unlock component registration
	 *   - ui.mountPoints: { mountPointId: [componentName, …] } — which
	 *     components each addon wants rendered at each mount point
	 *   - ui.sidebar[]: { title, url, icon } sidebar nav entries the
	 *     addon contributes. Pure data — Core renders. The entry
	 *     vanishes when the addon's WP plugin is deactivated because
	 *     the filter contribution disappears with it.
	 *
	 * Addons contribute their ui fragment via the filter
	 * `f12_doi_admin_manifest_fragments`. Core merges the fragments
	 * with auto-derived fields from AddonRegistry. An addon that
	 * doesn't contribute anything still appears in the manifest (with
	 * an empty ui section) so clients can display its status.
	 *
	 * Valid mount-point IDs (plan §9.2):
	 *   dashboard.widget, dashboard.alert, forms.integration-settings,
	 *   optins.row-action, optin.detail-panel, settings.tab,
	 *   license.section, addons.list
	 *
	 * @return \WP_REST_Response
	 */
	public function getAddonsManifest( \WP_REST_Request $request ): \WP_REST_Response {
		$fragments = apply_filters( 'f12_doi_admin_manifest_fragments', array() );
		if ( ! is_array( $fragments ) ) {
			$fragments = array();
		}

		$registered = array();
		if ( class_exists( '\\Forge12\\DoubleOptIn\\Addon\\AddonRegistry' ) ) {
			$registered = AddonRegistry::getInstance()->all();
		}

		$addons = array();

		// First pass: every registered addon gets an entry, even if
		// it contributes no UI. That lets the client show per-addon
		// licensing/boot state without a second round-trip.
		foreach ( $registered as $id => $addon ) {
			$fragment        = is_array( $fragments[ $id ] ?? null ) ? $fragments[ $id ] : array();
			$addons[ $id ]   = $this->buildAddonEntry( $id, $addon, $fragment );
			unset( $fragments[ $id ] );
		}

		// Second pass: fragments for addons NOT in the registry
		// (rare — would be a plugin that hooks the filter without
		// using AddonInterface). Include them with minimal metadata
		// so the client still loads their bundle.
		foreach ( $fragments as $id => $fragment ) {
			if ( ! is_string( $id ) || ! is_array( $fragment ) ) {
				continue;
			}
			$addons[ $id ] = $this->buildAddonEntry( $id, null, $fragment );
		}

		return new \WP_REST_Response(
			array(
				'addons' => array_values( $addons ),
			)
		);
	}

	/**
	 * Build one manifest entry from (optionally) the AddonInterface
	 * instance plus the filter-contributed fragment.
	 *
	 * @param string  $id
	 * @param mixed   $addon    AddonInterface|null
	 * @param array   $fragment
	 * @return array
	 */
	private function buildAddonEntry( string $id, $addon, array $fragment ): array {
		$entry = array(
			'id'           => $id,
			'name'         => '',
			'version'      => '',
			'capabilities' => array(),
			'available'    => false,
			'ui'           => array(
				'bundles'     => array(),
				'mountPoints' => new \stdClass(),
				'sidebar'     => array(),
			),
		);

		if ( $addon !== null && is_object( $addon ) ) {
			if ( method_exists( $addon, 'getName' ) ) {
				$entry['name'] = (string) $addon->getName();
			}
			if ( method_exists( $addon, 'getVersion' ) ) {
				$entry['version'] = (string) $addon->getVersion();
			}
			if ( method_exists( $addon, 'getCapabilities' ) ) {
				$caps = $addon->getCapabilities();
				if ( is_array( $caps ) ) {
					$entry['capabilities'] = array_values( array_map( 'strval', $caps ) );
				}
			}
			if ( method_exists( $addon, 'isAvailable' ) ) {
				try {
					$entry['available'] = (bool) $addon->isAvailable();
				} catch ( \Throwable $e ) {
					// Defensive — an addon throwing from isAvailable() is a bug
					// but shouldn't sink the whole manifest endpoint.
					$entry['available'] = false;
				}
			}
		}

		// Fragment fields override the auto-derived values. Use this
		// sparingly — mostly to surface a nicer user-facing name or
		// to flag an addon "available" even when AddonInterface isn't
		// implemented.
		if ( isset( $fragment['name'] ) && is_string( $fragment['name'] ) ) {
			$entry['name'] = $fragment['name'];
		}
		if ( isset( $fragment['version'] ) && is_string( $fragment['version'] ) ) {
			$entry['version'] = $fragment['version'];
		}
		if ( isset( $fragment['capabilities'] ) && is_array( $fragment['capabilities'] ) ) {
			$entry['capabilities'] = array_values( array_map( 'strval', $fragment['capabilities'] ) );
		}
		if ( isset( $fragment['available'] ) ) {
			$entry['available'] = (bool) $fragment['available'];
		}

		// UI section — sanitise bundles and mountPoints.
		if ( isset( $fragment['ui'] ) && is_array( $fragment['ui'] ) ) {
			$ui = $fragment['ui'];

			if ( isset( $ui['bundles'] ) && is_array( $ui['bundles'] ) ) {
				$bundles = array();
				foreach ( $ui['bundles'] as $bundle ) {
					if ( ! is_array( $bundle ) ) {
						continue;
					}
					$handle = isset( $bundle['handle'] ) ? (string) $bundle['handle'] : '';
					$url    = isset( $bundle['url'] ) ? (string) $bundle['url'] : '';
					if ( $handle === '' || $url === '' ) {
						continue;
					}
					$bundles[] = array(
						'handle' => $handle,
						'url'    => esc_url_raw( $url ),
					);
				}
				$entry['ui']['bundles'] = $bundles;
			}

			if ( isset( $ui['mountPoints'] ) && is_array( $ui['mountPoints'] ) ) {
				$mountPoints = array();
				foreach ( $ui['mountPoints'] as $mountId => $componentNames ) {
					if ( ! is_string( $mountId ) || ! is_array( $componentNames ) ) {
						continue;
					}
					$names = array();
					foreach ( $componentNames as $n ) {
						if ( is_string( $n ) && $n !== '' ) {
							$names[] = $n;
						}
					}
					if ( $names ) {
						$mountPoints[ $mountId ] = $names;
					}
				}
				$entry['ui']['mountPoints'] = $mountPoints ?: new \stdClass();
			}

			// Sidebar nav contributions — pure data, no React component
			// involvement. Each entry: { title, url, icon }. The icon is
			// a lucide-react icon name (string); Core's sidebar maps it
			// to a component via an allowlist (unknown names fall back
			// to a generic icon). Lets addons add their own nav items
			// without owning any of Core's UI primitives, and lets
			// items disappear automatically when the addon's WP plugin
			// is deactivated (no fragment → no entry).
			if ( isset( $ui['sidebar'] ) && is_array( $ui['sidebar'] ) ) {
				$sidebar = array();
				foreach ( $ui['sidebar'] as $item ) {
					if ( ! is_array( $item ) ) {
						continue;
					}
					$title = isset( $item['title'] ) ? (string) $item['title'] : '';
					$url   = isset( $item['url'] ) ? (string) $item['url'] : '';
					$icon  = isset( $item['icon'] ) ? (string) $item['icon'] : '';
					if ( $title === '' || $url === '' ) {
						continue;
					}
					$sidebar[] = array(
						'title' => $title,
						'url'   => $url,
						'icon'  => $icon,
					);
				}
				$entry['ui']['sidebar'] = $sidebar;
			}
		}

		return $entry;
	}

	/**
	 * GET /f12-doi/v1/addons/catalog
	 *
	 * Returns the canonical addon catalog with each entry's live state
	 * merged in. Powers the marketplace-style Addons admin page:
	 *
	 *   - For each catalog entry: is the plugin file present on disk
	 *     (`pluginFile` exists), is it active (`is_plugin_active`), and
	 *     does the registered AddonInterface report `isAvailable`?
	 *   - `status` collapses those three signals into one of
	 *     `active` / `inactive` / `not_installed` for easy CTA dispatch.
	 *   - `activateUrl` is a pre-signed wp-admin link for the plugin
	 *     activation flow when the plugin is on disk but inactive.
	 *
	 * Top-level fields:
	 *   `hasBundleLicense` — Pro license active. The page uses this to
	 *      decide between an "Install" CTA (for licensed users) and a
	 *      "Buy" CTA (for unlicensed users).
	 *
	 * @return \WP_REST_Response
	 */
	public function getAddonCatalog( \WP_REST_Request $request ): \WP_REST_Response {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$registered = array();
		if ( class_exists( '\\Forge12\\DoubleOptIn\\Addon\\AddonRegistry' ) ) {
			$registered = AddonRegistry::getInstance()->all();
		}

		// License registry is optional — Core-only sites without bundle-pro
		// or any standalone-license addon may not have it bound. Resolved
		// once per request via the same Container the addons themselves use.
		$licenseRegistry = null;
		if (
			class_exists( '\\Forge12\\DoubleOptIn\\Container\\Container' )
			&& interface_exists( '\\Forge12\\DoubleOptIn\\Licensing\\AddonLicenseRegistryInterface' )
		) {
			try {
				$container = \Forge12\DoubleOptIn\Container\Container::getInstance();
				if ( $container->has( \Forge12\DoubleOptIn\Licensing\AddonLicenseRegistryInterface::class ) ) {
					$licenseRegistry = $container->get( \Forge12\DoubleOptIn\Licensing\AddonLicenseRegistryInterface::class );
				}
			} catch ( \Throwable $e ) {
				$licenseRegistry = null;
			}
		}

		// Form integration registry — distinguishes "addon booted" (which
		// just means AvadaAddon::boot() ran) from "form integration is
		// actually wired" (which is what the Forms page consumes). The two
		// can diverge: AvadaAddon::boot() does its OWN second isAvailable()
		// check on the AvadaIntegration before calling registry->register().
		$formRegistry = null;
		if ( class_exists( '\\Forge12\\DoubleOptIn\\Integration\\FormIntegrationRegistry' ) ) {
			try {
				$formRegistry = \Forge12\DoubleOptIn\Integration\FormIntegrationRegistry::getInstance();
			} catch ( \Throwable $e ) {
				$formRegistry = null;
			}
		}

		$entries = array();
		foreach ( \Forge12\DoubleOptIn\Addon\AddonCatalog::entries() as $id => $catalog ) {
			$pluginFile = $catalog['pluginFile'];
			$installed  = file_exists( WP_PLUGIN_DIR . '/' . $pluginFile );
			$active     = $installed && is_plugin_active( $pluginFile );

			if ( $active ) {
				$status = 'active';
			} elseif ( $installed ) {
				$status = 'inactive';
			} else {
				$status = 'not_installed';
			}

			$activateUrl = null;
			if ( $installed && ! $active ) {
				$activateUrl = wp_nonce_url(
					self_admin_url( 'plugins.php?action=activate&plugin=' . rawurlencode( $pluginFile ) ),
					'activate-plugin_' . $pluginFile
				);
			}

			$registeredAddon = $registered[ $id ] ?? null;
			$capabilities    = array();
			if ( $registeredAddon !== null && method_exists( $registeredAddon, 'getCapabilities' ) ) {
				$caps = $registeredAddon->getCapabilities();
				if ( is_array( $caps ) ) {
					$capabilities = array_values( array_map( 'strval', $caps ) );
				}
			}

			// ── Operational diagnostic ─────────────────────────────────
			// Distinguishes "WP plugin is active" from "addon is fully
			// booted and serving its features". The two diverge any time
			// the addon's isAvailable() returns false — usually because
			// of a missing license or a missing third-party prerequisite
			// (e.g. Avada is active in WP but Fusion Builder isn't).
			$registered_b   = ( $registeredAddon !== null );
			$operational    = false;
			$inactiveReason = null;

			if ( $active ) {
				if ( ! $registered_b ) {
					// Plugin file activated but addon never reached the
					// registry — unusual; usually a fatal during boot.
					$inactiveReason = 'not_registered';
				} else {
					try {
						$operational = (bool) $registeredAddon->isAvailable();
					} catch ( \Throwable $e ) {
						$operational = false;
					}

					if ( ! $operational ) {
						$isLicensed = false;
						if ( $licenseRegistry !== null ) {
							try {
								$isLicensed = (bool) $licenseRegistry->isLicensed( $id );
							} catch ( \Throwable $e ) {
								$isLicensed = false;
							}
						}
						// Bundle-only licensing: whether a covered module is
						// *unlocked* is a bundle-level fact, reported once via
						// `hasBundleLicense` below — never a per-addon reason.
						// The only genuinely per-addon reason a covered addon
						// stays non-operational is a missing third-party
						// prerequisite (e.g. Avada active but Fusion Builder
						// not). When it isn't licensed the bundle simply isn't
						// active; the UI surfaces that globally, not per card.
						$inactiveReason = $isLicensed ? 'prerequisite' : null;
					}
				}
			}

			// ── Form integration diagnostic ───────────────────────────
			// Convention: form-providing addons use the same id for both
			// AddonInterface::getId() and FormIntegrationInterface::getIdentifier().
			// Non-form addons (analytics, reminder, …) won't have an entry
			// here; that's expected and we report null.
			$integrationRegistered = null;
			$integrationAvailable  = null;
			$formCount             = null;

			if ( $formRegistry !== null && $formRegistry->has( $id ) ) {
				$integrationRegistered = true;
				$integration           = $formRegistry->get( $id );
				if ( $integration !== null ) {
					try {
						$integrationAvailable = (bool) $integration->isAvailable();
					} catch ( \Throwable $e ) {
						$integrationAvailable = false;
					}
					if ( $integrationAvailable ) {
						try {
							$forms     = $integration->getForms();
							$formCount = is_array( $forms ) ? count( $forms ) : 0;
						} catch ( \Throwable $e ) {
							$formCount = 0;
						}
					} else {
						$formCount = 0;
					}
				}
			} elseif ( $formRegistry !== null && $operational ) {
				// Addon booted but didn't register a form integration —
				// either it's a non-form addon, or AvadaAddon::boot() hit
				// its second isAvailable() guard and silently skipped
				// registration. We can't tell which from out here; the
				// UI can hint based on whether the addon's id is in a
				// known list of form integrations.
				$integrationRegistered = false;
			}

			$entries[] = array(
				'id'                    => $id,
				'name'                  => (string) $catalog['name'],
				'description'           => (string) $catalog['description'],
				'pluginFile'            => $pluginFile,
				'bundleMember'          => (bool) $catalog['bundleMember'],
				'status'                => $status,
				'activateUrl'           => $activateUrl,
				'capabilities'          => $capabilities,
				'registered'            => $registered_b,
				'operational'           => $operational,
				'inactiveReason'        => $inactiveReason,
				'integrationRegistered' => $integrationRegistered,
				'integrationAvailable'  => $integrationAvailable,
				'formCount'             => $formCount,
			);
		}

		return new \WP_REST_Response(
			array(
				'entries'          => $entries,
				'hasBundleLicense' => (bool) apply_filters( 'f12_doi_is_pro_active', false ),
			)
		);
	}

	/**
	 * POST /f12-doi/v1/addons/{id}/activate
	 *
	 * Activates the addon plugin file derived from `AddonCatalog`. The
	 * standard REST X-WP-Nonce already authenticates the request — no
	 * pre-signed wp-admin nonce URL needed.
	 *
	 * Returns 404 when the ID is unknown, 409 when the plugin file is
	 * not on disk (caller must run the bundle installer first), or a
	 * 500 with the WP_Error message if `activate_plugin` fails.
	 *
	 * @return \WP_REST_Response
	 */
	public function activateAddon( \WP_REST_Request $request ): \WP_REST_Response {
		$id      = (string) $request->get_param( 'id' );
		$catalog = \Forge12\DoubleOptIn\Addon\AddonCatalog::get( $id );
		if ( $catalog === null ) {
			return new \WP_REST_Response(
				array( 'message' => __( 'Unknown addon.', 'double-opt-in' ) ),
				404
			);
		}

		if ( ! function_exists( 'activate_plugin' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$pluginFile = $catalog['pluginFile'];

		if ( ! file_exists( WP_PLUGIN_DIR . '/' . $pluginFile ) ) {
			return new \WP_REST_Response(
				array(
					'message' => __( 'Addon is not installed. Install it first via the Pro bundle installer.', 'double-opt-in' ),
				),
				409
			);
		}

		$result = activate_plugin( $pluginFile );
		if ( is_wp_error( $result ) ) {
			return new \WP_REST_Response(
				array( 'message' => $result->get_error_message() ),
				500
			);
		}

		return new \WP_REST_Response(
			array(
				'success' => true,
				'id'      => $id,
				'status'  => 'active',
			)
		);
	}

	/**
	 * GET /f12-doi/v1/addons/{id}/settings
	 *
	 * Returns the user-controlled settings for an addon (the feature
	 * toggle and any addon-specific preferences). Distinct from the
	 * WP-plugin activation state: a plugin can be active while its
	 * feature is paused via this toggle.
	 *
	 * Default shape `{ enabled: true }` so addons that haven't been
	 * configured yet behave like they're on — matches WP convention
	 * where activating a plugin opts you in to its default behaviour.
	 *
	 * @return \WP_REST_Response
	 */
	public function getAddonSettings( \WP_REST_Request $request ): \WP_REST_Response {
		$id = (string) $request->get_param( 'id' );
		if ( \Forge12\DoubleOptIn\Addon\AddonCatalog::get( $id ) === null ) {
			return new \WP_REST_Response(
				array( 'message' => __( 'Unknown addon.', 'double-opt-in' ) ),
				404
			);
		}

		$option   = 'f12_doi_addon_' . $id . '_settings';
		$stored   = get_option( $option, array() );
		$settings = is_array( $stored ) ? $stored : array();

		return new \WP_REST_Response(
			array_merge( array( 'enabled' => true ), $settings )
		);
	}

	/**
	 * POST /f12-doi/v1/addons/{id}/settings
	 *
	 * Stores per-addon settings. Body must be a JSON object; only known
	 * keys (currently `enabled`) are accepted. Future-proof: this is the
	 * single endpoint addons grow into when they have more knobs than
	 * just on/off.
	 *
	 * @return \WP_REST_Response
	 */
	public function updateAddonSettings( \WP_REST_Request $request ): \WP_REST_Response {
		$id = (string) $request->get_param( 'id' );
		if ( \Forge12\DoubleOptIn\Addon\AddonCatalog::get( $id ) === null ) {
			return new \WP_REST_Response(
				array( 'message' => __( 'Unknown addon.', 'double-opt-in' ) ),
				404
			);
		}

		$body = $request->get_json_params();
		if ( ! is_array( $body ) ) {
			$body = array();
		}

		$option = 'f12_doi_addon_' . $id . '_settings';
		$stored = get_option( $option, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		// Whitelist of keys an addon settings page may write. Each addon
		// can extend this via the `f12_doi_addon_settings_keys` filter as
		// it grows beyond a simple toggle.
		$allowedKeys = apply_filters(
			'f12_doi_addon_settings_keys',
			array( 'enabled' ),
			$id
		);

		$next = $stored;
		foreach ( $body as $key => $value ) {
			if ( ! is_string( $key ) || ! in_array( $key, $allowedKeys, true ) ) {
				continue;
			}
			if ( $key === 'enabled' ) {
				$next['enabled'] = (bool) $value;
				continue;
			}
			$next[ $key ] = is_scalar( $value ) ? $value : null;
		}

		/**
		 * Final sanitize pass for addons whose settings carry nested
		 * arrays (lists, objects). The scalar-only loop above can't
		 * persist those — addons that need it hook this filter to
		 * receive the raw body alongside the partially-built `$next`
		 * and merge their structured fields back in. Reference impl:
		 * see UniqueEmailAddon::sanitizeSettings (2026-05-13).
		 *
		 * @since 4.5.0
		 *
		 * @param array<string,mixed> $next    Already-sanitised settings
		 *                                     so far (scalar fields).
		 * @param array<string,mixed> $stored  Previously-saved option.
		 * @param string              $addonId Internal addon ID.
		 * @param array<string,mixed> $body    Raw request body.
		 */
		$next = apply_filters( 'f12_doi_addon_settings_sanitize', $next, $stored, $id, $body );

		update_option( $option, $next, false );

		// Also let addons hook a post-save signal to refresh caches etc.
		do_action( 'f12_doi_addon_settings_updated', $id, $next, $stored );

		return new \WP_REST_Response(
			array_merge( array( 'enabled' => true ), $next )
		);
	}

	/**
	 * POST /f12-doi/v1/addons/{id}/deactivate
	 *
	 * Mirror of {@see activateAddon()}. Used by the Addons page to let
	 * the user toggle an active addon off without uninstalling it.
	 */
	public function deactivateAddon( \WP_REST_Request $request ): \WP_REST_Response {
		$id      = (string) $request->get_param( 'id' );
		$catalog = \Forge12\DoubleOptIn\Addon\AddonCatalog::get( $id );
		if ( $catalog === null ) {
			return new \WP_REST_Response(
				array( 'message' => __( 'Unknown addon.', 'double-opt-in' ) ),
				404
			);
		}

		if ( ! function_exists( 'deactivate_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		deactivate_plugins( array( $catalog['pluginFile'] ) );

		return new \WP_REST_Response(
			array(
				'success' => true,
				'id'      => $id,
				'status'  => 'inactive',
			)
		);
	}
}
