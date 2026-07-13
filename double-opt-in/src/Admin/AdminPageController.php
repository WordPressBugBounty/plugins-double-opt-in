<?php
/**
 * Admin Page Controller
 *
 * Registers the React SPA as a WordPress admin page and enqueues assets.
 *
 * @package Forge12\DoubleOptIn\Admin
 * @since   4.2.0
 */

namespace Forge12\DoubleOptIn\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AdminPageController
 *
 * Handles the React admin SPA page registration and asset loading.
 */
class AdminPageController {

	/**
	 * The admin page slug.
	 */
	const PAGE_SLUG = 'f12-doi-admin';

	/**
	 * The admin page hook suffix (set after registration).
	 *
	 * @var string
	 */
	private string $hookSuffix = '';

	/**
	 * Initialize the controller.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_menu', array( $this, 'registerAdminPage' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueueAssets' ) );
	}

	/**
	 * Register the React SPA admin page.
	 *
	 * The page is added as a top-level menu item. The legacy PHP pages
	 * remain registered under their existing slugs for backward compatibility.
	 *
	 * @return void
	 */
	public function registerAdminPage(): void {
		$icon_url = plugins_url( 'assets/icon-double-opt-in-20x20.png', dirname( __DIR__ ) );

		$this->hookSuffix = add_menu_page(
			__( 'Double Opt-In', 'double-opt-in' ),
			__( 'Double Opt-In', 'double-opt-in' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'renderApp' ),
			$icon_url,
			31
		);
	}

	/**
	 * Enqueue React SPA assets only on our admin page.
	 *
	 * @param string $hook The current admin page hook suffix.
	 *
	 * @return void
	 */
	public function enqueueAssets( string $hook ): void {
		if ( $hook !== $this->hookSuffix ) {
			return;
		}

		$pluginDir = plugin_dir_path( dirname( __DIR__ ) );
		$pluginUrl = plugin_dir_url( dirname( __DIR__ ) );
		$buildDir  = $pluginDir . 'admin-ui/build/';

		// Enqueue the main JS bundle (single IIFE file with CSS injected).
		// Depends on `wp-i18n` so `window.wp.i18n` exists before the bundle
		// runs — the SPA's __() calls resolve against it (vite externalises
		// @wordpress/i18n to that global).
		$jsFile = $buildDir . 'index.js';
		if ( file_exists( $jsFile ) ) {
			wp_enqueue_script(
				'doi-admin-ui',
				$pluginUrl . 'admin-ui/build/index.js',
				array( 'wp-i18n' ),
				filemtime( $jsFile ),
				true
			);

			// Feed the active locale's translations to wp.i18n for the
			// "double-opt-in" text domain, so the React admin renders in the
			// WP-admin language instead of the hardcoded English source. We
			// reuse the .mo already loaded by load_plugin_textdomain() rather
			// than shipping a separate JS translation pipeline.
			$this->injectSpaTranslations( 'doi-admin-ui' );
		}

		// NOTE: the legacy flat `email-editor/build/` enqueue was removed
		// (2026-07). The editor bundle is enqueued solely by
		// EmailEditorAddon::enqueueEditorOnSpa() now. Enqueuing it here as
		// well caused the bundle to load twice on sites that still had the
		// pre-Phase-2 flat build, which double-mounted the editor App and
		// produced two "Save" buttons in the toolbar.

		// Enqueue CSS if it exists as a separate file (fallback for alternate builds)
		$assetsDir = $buildDir . 'assets/';
		if ( is_dir( $assetsDir ) ) {
			$cssFiles = glob( $assetsDir . 'index-*.css' );
			if ( ! empty( $cssFiles ) ) {
				$cssFile     = $cssFiles[0];
				$cssFilename = basename( $cssFile );
				wp_enqueue_style(
					'doi-admin-ui',
					$pluginUrl . 'admin-ui/build/assets/' . $cssFilename,
					array(),
					filemtime( $cssFile )
				);
			}
		}

		// Adjust WP admin layout for clean React SPA
		add_action(
			'admin_notices',
			function () {
				echo '<style>
				/* Hide WP clutter */
				.notice, .updated, .error, .is-dismissible { display: none !important; }
				#wpfooter { display: none; }

				/* Remove default WP content padding */
				#wpcontent { padding-left: 0; }
				#wpbody-content { padding-bottom: 0; }

				/* Offset React app below WP admin bar (32px) */
				#doi-admin-root {
					margin-left: 0;
					margin-top: 0;
					min-height: calc(100vh - 32px);
				}

				/*
				 * Sidebar offset for the WP admin bar. We target the
				 * sidebar by its data-attribute ONLY — the previous rule
				 * also targeted `.fixed`, which caught every Tailwind
				 * fixed-positioned element (Radix Dialog overlay + content
				 * primarily). The dialog content uses `top: 50%` +
				 * `translate-y(-50%)` centering, and `top: 32px !important`
				 * obliterated that math: the modal grew to nearly full
				 * viewport height with the body content squeezed off-
				 * screen above the buttons. Reported by user 2026-04-30.
				 */
				#doi-admin-root [data-sidebar="sidebar"] {
					top: 32px !important;
					height: calc(100vh - 32px) !important;
				}

				/* Fix sticky header to sit below WP admin bar */
				#doi-admin-root .sticky {
					top: 32px !important;
				}

				/* Mobile WP admin bar is 46px */
				@media screen and (max-width: 782px) {
					#doi-admin-root [data-sidebar="sidebar"] {
						top: 46px !important;
						height: calc(100vh - 46px) !important;
					}
					#doi-admin-root .sticky {
						top: 46px !important;
					}
					#doi-admin-root {
						min-height: calc(100vh - 46px);
					}
				}

				/* When admin bar is not shown (e.g. fullscreen) */
				.no-adminbar #doi-admin-root [data-sidebar="sidebar"] {
					top: 0 !important;
					height: 100vh !important;
				}
				.no-adminbar #doi-admin-root .sticky {
					top: 0 !important;
				}

				/* WP-admin\'s forms.css applies `button { border, background,
				 * box-shadow, cursor }` via tag selectors that bleed into
				 * shadcn components (most visibly the Tabs pill).
				 * Reset to neutral so Tailwind utilities can paint cleanly.
				 *
				 * Specificity: `#doi-admin-root button` is (1,0,1), beats
				 * WP\'s `.wp-core-ui .button` (0,2,0). Tailwind utilities
				 * are emitted as `#doi-admin-root .bg-muted` (1,1,0) via
				 * the `important: "#doi-admin-root"` config option, which
				 * beats this reset on class > tag. */
				#doi-admin-root button {
					/* `appearance: none` is the critical bit — without it
					 * browsers render the native button look (3D border,
					 * raised effect) on top of any Tailwind background, so
					 * the active tab gets a dark outline regardless of
					 * `bg-background`. WP-admin\'s forms.css drops this
					 * for native buttons but Tailwind\'s preflight is in
					 * the lower-priority @layer base; the unlayered WP CSS
					 * wins. We reset at #doi-admin-root scope to restore
					 * the neutral baseline.
					 *
					 * Border reset uses long-hand props (NOT shorthand
					 * `border: 0`) because the shorthand also sets
					 * `border-style: none`. Tailwind\'s `.border` utility
					 * only sets `border-width: 1px` — without `border-style:
					 * solid` from preflight (which we just clobbered),
					 * the border stays invisible despite the width.
					 * SelectTrigger reported as borderless 2026-04-30. */
					-webkit-appearance: none;
					appearance: none;
					border-style: solid;
					border-width: 0;
					border-color: transparent;
					background: transparent;
					box-shadow: none;
					color: inherit;
					font: inherit;
					cursor: pointer;
					line-height: inherit;
					padding: 0;
				}
				#doi-admin-root input,
				#doi-admin-root select,
				#doi-admin-root textarea {
					font: inherit;
				}
			</style>';
			},
			999
		);

		// Inject configuration for the React app
		$config = array(
			'restUrl'        => esc_url_raw( rest_url( 'f12-doi/v1/' ) ),
			'nonce'          => wp_create_nonce( 'wp_rest' ),
			'isProActive'    => (bool) apply_filters( 'f12_doi_is_pro_active', false ),
			'isProInstalled' => defined( 'F12_DOI_PRO_VERSION' ),
			'loadedAddons'     => $this->getLoadedAddonIds(),
			'registeredAddons' => $this->getRegisteredAddonIds(),
			'version'        => defined( 'FORGE12_OPTIN_VERSION' ) ? FORGE12_OPTIN_VERSION : '0.0.0',
			'emailEditorUrl' => admin_url( 'admin.php?page=f12-doi-admin#/email-templates' ),
			'upgradeUrl'     => 'https://www.forge12.com',
			'adminUrl'       => admin_url(),
			// Nonce for the legacy admin-ajax `doi_export_consent`
			// handler. The handler hard-requires `_wpnonce` in
			// `$_REQUEST` keyed on action `doi_consent_export`; without
			// this the SPA's Export JSON/CSV links 403 with
			// "Sicherheitsprüfung fehlgeschlagen" (user-reported
			// 2026-05-13). REST routes use the separate `nonce` field
			// above with action `wp_rest` — they live in different
			// namespaces, so a single shared nonce won't work.
			'consentExportNonce' => wp_create_nonce( 'doi_consent_export' ),
		);

		/**
		 * Filter the admin SPA configuration data.
		 *
		 * Allows Pro and other extensions to inject additional data
		 * into the frontend configuration object.
		 *
		 * @param array $config The configuration array.
		 *
		 * @since 4.2.0
		 */
		$config = apply_filters( 'f12_doi_admin_localize_data', $config );

		wp_localize_script( 'doi-admin-ui', 'doiAdmin', $config );
	}

	/**
	 * Feed the active locale's "double-opt-in" translations to wp.i18n so the
	 * React admin renders in the WP-admin language.
	 *
	 * WordPress's own wp_set_script_translations() keys its JSON by the md5 of
	 * each source JS file's path, which doesn't fit a single bundled IIFE. So
	 * instead we build a Jed-format locale-data object by reading the plugin's
	 * .mo directly and hand it to wp.i18n.setLocaleData() via an inline script
	 * that runs *before* the bundle (hence the 'before' position + the wp-i18n
	 * dependency).
	 *
	 * @param string $handle Enqueued script handle to attach the inline script to.
	 *
	 * @return void
	 */
	private function injectSpaTranslations( string $handle ): void {
		$locale = determine_locale();

		// English is the source language — nothing to translate.
		if ( $locale === 'en_US' || $locale === 'en' ) {
			return;
		}

		// Read the .mo directly with POMO rather than
		// get_translations_for_domain(): under WP 6.5+'s new
		// WP_Translation_Controller that call returns a proxy whose ->entries
		// is EMPTY even when __() resolves, so iterating it injects nothing.
		$moFile = plugin_dir_path( dirname( __DIR__ ) ) . 'languages/double-opt-in-' . $locale . '.mo';
		if ( ! is_readable( $moFile ) ) {
			return;
		}

		if ( ! class_exists( '\\MO' ) ) {
			require_once ABSPATH . WPINC . '/pomo/mo.php';
		}

		$mo = new \MO();
		if ( ! $mo->import_from_file( $moFile ) || empty( $mo->entries ) ) {
			return;
		}

		$locale_data = array(
			'' => array(
				'domain'       => 'double-opt-in',
				'lang'         => $locale,
				'plural-forms' => $mo->headers['Plural-Forms'] ?? 'nplurals=2; plural=(n != 1);',
			),
		);

		foreach ( $mo->entries as $entry ) {
			// Jed prefixes context entries with "<context><msgid>".
			$key = ( isset( $entry->context ) && $entry->context !== '' )
				? $entry->context . "\4" . $entry->singular
				: $entry->singular;

			$locale_data[ $key ] = $entry->translations;
		}

		wp_add_inline_script(
			$handle,
			'wp.i18n.setLocaleData( ' . wp_json_encode( $locale_data ) . ', "double-opt-in" );',
			'before'
		);
	}

	/**
	 * Render the React SPA mount point.
	 *
	 * @return void
	 */
	public function renderApp(): void {
		echo '<div id="doi-admin-root"></div>';
	}

	/**
	 * IDs of every addon currently loaded AND available on this site.
	 *
	 * Used by the React SPA to gate per-feature settings cards
	 * (Reminder, MX Validation, Domain Blocklist, etc.) — drift to
	 * gating on `isProActive` alone re-introduces the user-reported
	 * bug where the Pro Bundle license shows feature settings for
	 * addons that aren`t even installed on the site.
	 *
	 * Returns an empty list when AddonRegistry isn`t loaded yet (very
	 * early boot or unit tests without it). The frontend treats an
	 * empty list as "no addons available", which is the safe default.
	 *
	 * @return list<string>
	 */
	private function getLoadedAddonIds(): array {
		if ( ! class_exists( '\\Forge12\\DoubleOptIn\\Addon\\AddonRegistry' ) ) {
			return array();
		}

		$registry  = \Forge12\DoubleOptIn\Addon\AddonRegistry::getInstance();
		$available = $registry->available();

		return array_values( array_keys( $available ) );
	}

	/**
	 * IDs of every addon plugin that is loaded/registered, regardless of
	 * whether its license is currently active.
	 *
	 * Distinguishes "addon plugin missing" from "addon plugin installed
	 * but not unlocked". The frontend ProGate component uses both lists
	 * to render the right upsell. Under bundle-only licensing a covered
	 * addon is unlocked whenever the Pro bundle is active, so the middle
	 * state means the bundle isn't active — not a per-module license:
	 *
	 *   - id in loadedAddons    → fully active, no gate
	 *   - id in registeredAddons but NOT in loadedAddons
	 *                           → "Pro Bundle Required" (plugin is here,
	 *                              the bundle isn't active)
	 *   - id in neither          → "Addon Required" (plugin not installed)
	 *
	 * @return list<string>
	 */
	private function getRegisteredAddonIds(): array {
		if ( ! class_exists( '\\Forge12\\DoubleOptIn\\Addon\\AddonRegistry' ) ) {
			return array();
		}
		$registry = \Forge12\DoubleOptIn\Addon\AddonRegistry::getInstance();
		return array_values( array_keys( $registry->all() ) );
	}
}
