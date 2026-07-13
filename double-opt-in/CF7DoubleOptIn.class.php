<?php

namespace forge12\contactform7\CF7DoubleOptIn {

	use Forge12\Shared\Logger;
	use Forge12\Shared\LoggerInterface;

	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	/**
	 * Plugin Name: Double Opt-In (Contact Form 7, Avada) - GDPR Ready
	 * Plugin URI: https://www.forge12.com/blog/so-verwendest-du-das-double-opt-in-fuer-contact-form-7/
	 * Description: This plugin allows you to add a double OptIn System to your Contact Form 7 & Avada Forms.
	 * Text Domain: double-opt-in
	 * Domain Path: /languages
	 * Version: 5.1.2
	 * Author: Forge12 Interactive GmbH
	 * Author URI: https://www.forge12.com
	 */
	if ( ! defined( 'FORGE12_OPTIN_VERSION' ) ) {
		define( 'FORGE12_OPTIN_VERSION', '5.1.2' );
	}

	// Addon API version — semver-independent from the plugin's marketing
	// version. Bumped only on breaking changes to the Addon API surface
	// (AddonInterface, AddonRegistry, AddonLicenseRegistry, FormIntegrationInterface,
	// event payloads). Addons declare their requirement against this constant,
	// not FORGE12_OPTIN_VERSION.
	if ( ! defined( 'F12_DOI_CORE_API_VERSION' ) ) {
		define( 'F12_DOI_CORE_API_VERSION', '4.3.0' );
	}
	if ( ! defined( 'FORGE12_OPTIN_SLUG' ) ) {
		define( 'FORGE12_OPTIN_SLUG', 'f12-cf7-doubleoptin' );
	}
	if ( ! defined( 'FORGE12_OPTIN_BASENAME' ) ) {
		define( 'FORGE12_OPTIN_BASENAME', plugin_basename( __FILE__ ) );
	}
	if ( ! defined( 'F12_DOUBLEOPTIN_PLUGIN_FILE' ) ) {
		define( 'F12_DOUBLEOPTIN_PLUGIN_FILE', __FILE__ );
	}


	/**
	 * Dependencies
	 */
	require_once 'logger/logger.php';
	require_once 'core/helpers/uuid.php';
	require_once 'core/telemetry.php';
	require_once 'core/review.php';
	require_once 'core/cron.php';
	require_once 'core/BaseController.class.php';

	require_once 'OnActivation.php';
	require_once 'OnDeactivation.php';
	require_once 'OnUpdate.php';
	require_once 'compatibility/OptInFrontend.class.php';
	require_once 'core/SpamMechanics.class.php';

	require_once 'core/Messages.class.php';
	require_once 'core/TemplateHandler.class.php';
	require_once 'core/IPHelper.class.php';
	require_once 'core/SanitizeHelper.class.php';
	require_once 'core/Ajax.class.php';
	require_once 'core/Compatibility.class.php';
	require_once 'core/CleanUp.class.php';
	require_once 'core/HTMLSelect.class.php';
	require_once 'core/OptIn.class.php';
	require_once 'core/OptInLimitFilter.class.php';
	require_once 'core/OptInSearchFilter.class.php';
	require_once 'core/Category.class.php';
	require_once 'core/CategoryOptions.class.php';
	require_once 'core/Pagination.class.php';
	if ( file_exists( __DIR__ . '/core/TestEmailBlocker.class.php' ) ) {
		require_once 'core/TestEmailBlocker.class.php';
	}

	/**
	 * PSR-4 Autoloader for new Enterprise Architecture (v4.0+)
	 */
	require_once 'autoload.php';

	/**
	 * Class CF7DoubleOptIn
	 * Controller for the Custom Links.
	 *
	 * @package forge12\contactform7
	 */
	class CF7DoubleOptIn {
		private LoggerInterface $logger;
		/**
		 * @var CF7DoubleOptIn|Null
		 */
		private static $_instance = null;

		/**
		 * @var TemplateHandler|null
		 */
		private $TemplateHandler = null;

		/**
		 * Get the singleton instance of CF7DoubleOptIn.
		 *
		 * @return CF7DoubleOptIn The singleton instance.
		 */
		public static function getInstance() {
			if ( self::$_instance == null ) {
				self::$_instance = new self();
			}

			return self::$_instance;
		}

		/**
		 * Return a list containing the array with all data stored within the form
		 *
		 * @param int $postID
		 *
		 * @formatter:off
		 *
		 * @return {
		 *      @type int       $enable     The Status of the OptIn, either 1 for enabled or 0 for disabled. Default: 0
		 *      @type string    $sender     The E-Mail of the sender of the optIn mail
		 *      @type string    $subject    The Subject of the OptIn Mail
		 *      @type string    $body       The Content of the OptIn Mail
		 *      @type string    $recipient  The Field that contains the E-Mail of the Recipient.
		 *      @type int       $page       The Post ID of the confirmation page. Default: -1
		 *      @type string    $conditions Additional condition to dynamically enable / disable the optin.
		 *                                  Default: disabled
		 *      @type string    $template   The Template used for the OptIn Mail
		 *      @type int       $category   The Category the OptIns will be assigned to.
		 * }
		 * @formatter:on
		 */
		public function getParameter( $postID ) {
			$this->get_logger()->debug(
				'Fetching parameters',
				array(
					'plugin'  => 'double-opt-in',
					'class'   => __CLASS__,
					'method'  => __METHOD__,
					'post_id' => $postID,
				)
			);

			$data = array(
				'enable'      => 0,
				'sender'      => get_bloginfo( 'admin_email' ),
				'sender_name' => '',
				'subject'     => '',
				'body'        => '',
				'recipient'   => '',
				'page'        => - 1,
				'conditions'  => 'disabled',
				'template'    => '',
				'category'    => 0,
			);

			$data = apply_filters( 'f12_cf7_doubleoptin_get_parameter', $data );

			if ( ! $postID ) {
				$this->get_logger()->debug(
					'No postID provided, returning defaults',
					array(
						'plugin' => 'double-opt-in',
					)
				);

				return $data;
			}

			$options = get_post_meta( $postID, 'f12-cf7-doubleoptin', true );

			if ( ! $options ) {
				$this->get_logger()->debug(
					'No options found for postID, returning defaults',
					array(
						'plugin'  => 'double-opt-in',
						'post_id' => $postID,
					)
				);

				return $data;
			}

			$this->get_logger()->debug(
				'Options merged with defaults',
				array(
					'plugin'  => 'double-opt-in',
					'post_id' => $postID,
				)
			);

			return array_merge( $data, $options );
		}

		/**
		 * Private constructor to prevent direct instantiation.
		 */
		private function __construct() {
			$this->logger = Logger::getInstance();

			// Initialize test email blocker (blocks @example.com during E2E tests)
			if ( class_exists( __NAMESPACE__ . '\\TestEmailBlocker' ) ) {
				TestEmailBlocker::init();
			}

			// Initialize the DI Container and Service Providers (v4.0+ Enterprise Architecture)
			$this->initializeContainer();

			// Register the Avada deprecation notice + grandfather-license claim flow.
			// Covers the migration of Avada support out of Core into the paid
			// addon-avada plugin planned for 5.0. The notice only renders on
			// sites that actually use DOI with an Avada form.
			\Forge12\DoubleOptIn\Migration\AvadaDeprecationNotice::register();

			if ( ! get_option( 'f12_cf7_doubleoptin_installed_at' ) ) {
				update_option( 'f12_cf7_doubleoptin_installed_at', time() );
			}

			// Handle Spam Mechanics
			$SpamMechanics = new SpamMechanics( $this->logger );

			// Resend Confirmation Mail (Admin AJAX)
			new \Forge12\DoubleOptIn\Admin\ResendController( $this->logger );

			$this->get_logger()->info(
				'Initialization of Forge12 Double Opt-In started',
				array(
					'plugin' => 'double-opt-in',
					'class'  => __CLASS__,
					'method' => __METHOD__,
				)
			);

			add_action(
				'init',
				function () {
					load_plugin_textdomain(
						'double-opt-in',
						false,
						dirname( plugin_basename( __FILE__ ) ) . '/languages'
					);
					$this->get_logger()->debug(
						'Textdomain loaded',
						array(
							'plugin' => 'double-opt-in',
							'domain' => 'double-opt-in',
						)
					);
				}
			);

			do_action( 'f12_cf7_doubleoptin_init', $this );
			$this->get_logger()->debug(
				'Action f12_cf7_doubleoptin_init executed',
				array(
					'plugin' => 'double-opt-in',
				)
			);

			$this->TemplateHandler = TemplateHandler::getInstance();
			$this->get_logger()->debug(
				'TemplateHandler initialized',
				array(
					'plugin' => 'double-opt-in',
				)
			);

			// Settings-defaults filter — historically registered by the legacy
			// admin UI (UISettings::getSettings). Registered here at runtime so
			// getSettings() keeps its default key set (and the whitelist it builds
			// from it) even without the legacy admin. The test-override mu-plugin
			// and any addon still layer on top of the filter chain.
			add_filter( 'f12_cf7_doubleoptin_settings', array( $this, 'injectDefaultSettings' ) );

			// Legacy admin UI (the `f12-cf7-doubleoptin` menu + its list-table
			// screens) removed 2026-07-02 — the React SPA (`f12-doi-admin`,
			// AdminPageController) is the sole admin UI. Runtime opt-in processing
			// (OptIn, CleanUp, OptInFrontend, the CF7 flow) is unaffected.

			add_action( 'after_setup_theme', array( $this, 'init' ) );
			$this->get_logger()->debug(
				'Hook after_setup_theme registered',
				array(
					'plugin' => 'double-opt-in',
				)
			);

			$Compatibility = new Compatibility( $this );
			$this->get_logger()->debug(
				'Compatibility initialized',
				array(
					'plugin' => 'double-opt-in',
				)
			);

			$CleanUp = new CleanUp( $this->get_logger() );
			$this->get_logger()->debug(
				'CleanUp initialized',
				array(
					'plugin' => 'double-opt-in',
				)
			);

			// Pagination
			Pagination::getInstance();
			$this->get_logger()->debug(
				'Pagination initialized',
				array(
					'plugin' => 'double-opt-in',
				)
			);

			// initialize filter
			CategoryOptions::getInstance();
			$this->get_logger()->debug(
				'CategoryOptions initialized',
				array(
					'plugin' => 'double-opt-in',
				)
			);

			OptInLimitFilter::getInstance();
			$this->get_logger()->debug(
				'OptInLimitFilter initialized',
				array(
					'plugin' => 'double-opt-in',
				)
			);

			OptInSearchFilter::getInstance();
			$this->get_logger()->debug(
				'OptInSearchFilter initialized',
				array(
					'plugin' => 'double-opt-in',
				)
			);

			$this->get_logger()->info(
				'Initialization of Forge12 Double Opt-In completed',
				array(
					'plugin' => 'double-opt-in',
					'class'  => __CLASS__,
					'method' => __METHOD__,
				)
			);
		}

		public function get_logger() {
			return $this->logger;
		}

		/**
		 * Initialize the DI Container and register Service Providers.
		 *
		 * @since 4.0.0
		 * @return void
		 */
		private function initializeContainer(): void {
			$container = \Forge12\DoubleOptIn\Container\Container::getInstance();

			// Register core services
			$container->addProvider( new \Forge12\DoubleOptIn\Providers\CoreServiceProvider() );

			// Register event system
			$container->addProvider( new \Forge12\DoubleOptIn\Providers\EventServiceProvider() );

			// Register repositories and services
			$container->addProvider( new \Forge12\DoubleOptIn\Providers\RepositoryServiceProvider() );

			// Register email template services
			$container->addProvider( new \Forge12\DoubleOptIn\Providers\EmailTemplateServiceProvider() );

			// Register form integration system (v4.0+ Event-based Architecture)
			$container->addProvider( new \Forge12\DoubleOptIn\Providers\IntegrationServiceProvider() );

			// Register form settings services (v4.1+ Central Form Management)
			$container->addProvider( new \Forge12\DoubleOptIn\Providers\FormSettingsServiceProvider() );

			// Register GDPR compliance services (v3.2.0+)
			$container->addProvider( new \Forge12\DoubleOptIn\Providers\GdprServiceProvider() );

			// Register admin REST API and audit services (v4.2.0+)
			$container->addProvider( new \Forge12\DoubleOptIn\Providers\AdminServiceProvider() );

			// Register licensing registry (v4.3.0+ — entitlement state for paid addons)
			$container->addProvider( new \Forge12\DoubleOptIn\Providers\LicensingServiceProvider() );

			// Register migration registry (v4.3.0+ — runs pending DB migrations on admin_init)
			$container->addProvider( new \Forge12\DoubleOptIn\Providers\MigrationServiceProvider() );

			// Register addon system (v4.3.0+ — public Addon API)
			$container->addProvider( new \Forge12\DoubleOptIn\Providers\AddonServiceProvider() );

			// Register RateLimiter as singleton
			$container->singleton(
				\Forge12\DoubleOptIn\Service\RateLimiter::class,
				function () {
					return new \Forge12\DoubleOptIn\Service\RateLimiter();
				}
			);

			// Boot all providers
			$container->boot();

			$this->get_logger()->info(
				'DI Container initialized with Service Providers',
				array(
					'plugin'    => 'double-opt-in',
					'component' => 'container',
				)
			);
		}

		/**
		 * Get the DI Container instance.
		 *
		 * @since 4.0.0
		 * @return \Forge12\DoubleOptIn\Container\Container
		 */
		public function getContainer(): \Forge12\DoubleOptIn\Container\Container {
			return \Forge12\DoubleOptIn\Container\Container::getInstance();
		}

		/**
		 * Retrieve the template handler instance.
		 *
		 * @return TemplateHandler The template handler instance.
		 */
		public function get_template_handler() {
			$this->get_logger()->debug(
				'TemplateHandler retrieved',
				array(
					'plugin' => 'double-opt-in',
					'class'  => __CLASS__,
					'method' => __METHOD__,
				)
			);

			return $this->TemplateHandler;
		}

		/**
		 * @private WordPress Hook
		 */
		public function init() {
			$this->get_logger()->debug(
				'Init started',
				array(
					'plugin' => 'double-opt-in',
					'class'  => __CLASS__,
					'method' => __METHOD__,
				)
			);

			do_action( 'f12_cf7_doubleoptin_register_implementations' );

			$this->get_logger()->debug(
				'Action f12_cf7_doubleoptin_register_implementations executed',
				array(
					'plugin' => 'double-opt-in',
				)
			);
		}


		/**
		 * Return the settings for the optin.
		 *
		 * @param string $single                     The Key of the setting to return only the required setting
		 *
		 * @formatter:off
		 * @return {
		 *     // Returns the Settings for the DOI
		 *
		 *      @type string    $optout_subject             The Subject for the OptOut Mail
		 *      @type string    $optout_body                The Content for the OptOut Mail
		 *      @type int       $optout_page                The Post ID for the OptOut Page
		 *      @type int       $support                    Defines if the Support link will be added to the footer
		 *      @type int       $delete                     An integer from 1 to 30
		 *      @type int       $delete_unconfirmed         An integer from 1 to 30
		 *      @type string    $delete_period              The time period, either months, days, years
		 *      @type string    $delete_unconfirmed_period  The time period, either months, days, years
		 * }
		 * @formatter:on
		 */

		/**
		 * Inject the core settings defaults onto the f12_cf7_doubleoptin_settings
		 * filter. Relocated from the legacy admin UI (UISettings::getSettings) so
		 * the default key set survives without the legacy admin. Defaults are the
		 * base; any value already on the filter (saved settings, test overrides,
		 * addon contributions) wins via array_merge.
		 *
		 * @param array $settings Settings collected so far on the filter.
		 * @return array
		 */
		public function injectDefaultSettings( $settings ) {
			$default_settings = array(
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
				'reminder_enabled'          => 0,
				'reminder_delay'            => 24,
				'reminder_template'         => '',
				'reminder_subject'          => '',
				'mx_validation_enabled'     => 0,
				'mx_validation_behavior'    => 'silent',
				'mx_validation_message'     => '',
				'domain_blocklist_enabled'  => 0,
				'domain_blocklist'          => '',
				'domain_blocklist_behavior' => 'silent',
				'domain_blocklist_message'  => '',
			);

			return array_merge( $default_settings, is_array( $settings ) ? $settings : array() );
		}

		public function getSettings( $single = '', $container = null ) {
			$this->get_logger()->debug(
				'Fetching settings',
				array(
					'plugin'    => 'double-opt-in',
					'class'     => __CLASS__,
					'method'    => __METHOD__,
					'single'    => $single,
					'container' => $container,
				)
			);

			$default = array();

			$default = apply_filters( 'f12_cf7_doubleoptin_settings', $default );

			$settings = get_option( 'f12-doi-settings' );

			if ( ! is_array( $settings ) ) {
				$this->get_logger()->debug(
					'No settings found in options, using empty array',
					array(
						'plugin' => 'double-opt-in',
					)
				);
				$settings = array();
			}

			foreach ( $default as $key => $data ) {
				if ( isset( $settings[ $key ] ) ) {
					if ( is_array( $default[ $key ] ) ) {
						$default[ $key ] = array_merge( $default[ $key ], $settings[ $key ] );
					} else {
						$default[ $key ] = $settings[ $key ];
					}
					$this->get_logger()->debug(
						'Merged settings for key',
						array(
							'plugin' => 'double-opt-in',
							'key'    => $key,
						)
					);
				}
			}

			$settings = $default;

			if ( ! empty( $single ) ) {
				if ( $container != null ) {
					if ( isset( $settings[ $container ] ) && isset( $settings[ $container ][ $single ] ) ) {
						$this->get_logger()->debug(
							'Returning single setting from container',
							array(
								'plugin'    => 'double-opt-in',
								'container' => $container,
								'single'    => $single,
							)
						);
						$settings = $settings[ $container ][ $single ];
					}
				}
			} elseif ( isset( $settings[ $single ] ) ) {
					$this->get_logger()->debug(
						'Returning single setting',
						array(
							'plugin' => 'double-opt-in',
							'single' => $single,
						)
					);
					$settings = $settings[ $single ];
			}

			return $settings;
		}
	}


	add_action(
		'plugins_loaded',
		function () {
			add_cron_jobs();
			CF7DoubleOptIn::getInstance();
		}
	);

	/**
	 * Display upgrade notice in plugin list when updating to major versions.
	 *
	 * @param array  $data     Plugin update data.
	 * @param object $response Response object from WordPress.org API.
	 */
	add_action(
		'in_plugin_update_message-' . FORGE12_OPTIN_BASENAME,
		function ( $data, $response ) {
			$upgrade_notice = '';

			// Check if this is a major update (e.g., 3.1.x -> 3.2.x)
			$current_version = FORGE12_OPTIN_VERSION;
			$new_version     = $response->new_version ?? '';

			if ( empty( $new_version ) ) {
				return;
			}

			// Extract major.minor from versions
			$current_parts = explode( '.', $current_version );
			$new_parts     = explode( '.', $new_version );

			$current_minor = ( $current_parts[0] ?? '0' ) . '.' . ( $current_parts[1] ?? '0' );
			$new_minor     = ( $new_parts[0] ?? '0' ) . '.' . ( $new_parts[1] ?? '0' );

			// Show warning for major/minor version changes
			if ( version_compare( $new_minor, $current_minor, '>' ) ) {
				$upgrade_notice = sprintf(
					'</p><div class="notice inline notice-warning notice-alt" style="margin: 10px 0; padding: 10px; border-left-color: #ffb900;"><p><strong>%s</strong></p><p>%s</p></div><p style="display:none;">',
					esc_html__( '⚠️ Important: Major Update – Please backup before updating!', 'double-opt-in' ),
					esc_html__( 'This version includes significant changes to the form management system, email templates, and database structure. We strongly recommend creating a full site backup before updating.', 'double-opt-in' )
				);

				echo wp_kses_post( $upgrade_notice );
			}

			// Avada deprecation notice is handled by
			// Forge12\DoubleOptIn\Migration\AvadaDeprecationNotice (registered in
			// __construct). That class renders a proper admin notice on every
			// admin page with a grandfather-license claim button, rather than
			// a one-shot message at update time.
		},
		10,
		2
	);

}
