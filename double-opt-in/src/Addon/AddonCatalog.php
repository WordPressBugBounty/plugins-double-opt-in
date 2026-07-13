<?php
/**
 * Addon Catalog — canonical metadata for every Double Opt-In addon.
 *
 * Single source of truth for addon names and descriptions. Used by:
 *  - the Addons admin page to render a marketplace grid (with state-
 *    aware CTAs that depend on whether the user has the addon
 *    installed, active, or has a bundle license),
 *  - the License-page Features Overview to surface bundle-covered
 *    addons even before they are installed (via bundle-pro's hook
 *    into the `f12_doi_license_features` filter),
 *  - bundle-pro's installer to humanize addon IDs in the install row.
 *
 * Under bundle-only licensing there are no per-addon purchase pages:
 * the single Pro bundle unlocks every module, so the UI points users
 * at one bundle upgrade CTA rather than a per-addon buy link.
 *
 * Lives in Core (not bundle-pro) so the marketplace UI works on free
 * sites without bundle-pro: the page can still show what addons exist
 * and point the user at the bundle upgrade.
 *
 * @package Forge12\DoubleOptIn\Addon
 * @since   4.4.0
 */

namespace Forge12\DoubleOptIn\Addon;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AddonCatalog {

	/**
	 * Canonical catalog metadata.
	 *
	 * Keep aligned with `AddonManifest::getCoveredAddonIds()` in
	 * bundle-pro. Backward-compat aliases (`pro-bundle`, `validators`)
	 * are intentionally absent — they are internal license markers,
	 * not user-facing addons.
	 *
	 * @return array<string, array{name:string,description:string,pluginFile:string,bundleMember:bool}>
	 */
	public static function entries(): array {
		$entry = static function ( string $id, string $name, string $description, bool $bundleMember = true ): array {
			$externalSlug = 'double-opt-in-' . $id;
			return array(
				'id'           => $id,
				'name'         => $name,
				'description'  => $description,
				'pluginFile'   => $externalSlug . '/' . $externalSlug . '.php',
				'bundleMember' => $bundleMember,
			);
		};

		$entries = array(
			'analytics'                => $entry(
				'analytics',
				__( 'Analytics Dashboard', 'double-opt-in' ),
				__( 'Visualize opt-in funnel performance, conversion rates, and source breakdowns directly in the WP admin.', 'double-opt-in' )
			),
			'reminder'                 => $entry(
				'reminder',
				__( 'Reminder Emails', 'double-opt-in' ),
				__( 'Automatically nudge users who never confirmed their opt-in with one or more reminder emails.', 'double-opt-in' )
			),
			'opt-out'                  => $entry(
				'opt-out',
				__( 'Opt-Out System', 'double-opt-in' ),
				__( 'GDPR-friendly one-click unsubscribe links and an opt-out audit trail per recipient.', 'double-opt-in' )
			),
			'user-registration'        => $entry(
				'user-registration',
				__( 'Auto User Creation', 'double-opt-in' ),
				__( 'Create a WordPress user account automatically once a confirmation is recorded.', 'double-opt-in' )
			),
			'mx-validator'             => $entry(
				'mx-validator',
				__( 'MX Email Validation', 'double-opt-in' ),
				__( 'Reject addresses whose domain has no valid MX record before they hit your queue.', 'double-opt-in' )
			),
			'domain-blocklist'         => $entry(
				'domain-blocklist',
				__( 'Domain Blocklist', 'double-opt-in' ),
				__( 'Block disposable-mail providers and self-hosted lists of unwanted domains at submit time.', 'double-opt-in' )
			),
			'unique-email'             => $entry(
				'unique-email',
				__( 'Unique Email Validation', 'double-opt-in' ),
				__( 'Refuse duplicate addresses across all your forms — one email, one opt-in.', 'double-opt-in' )
			),
			'single-mail-registration' => $entry(
				'single-mail-registration',
				__( 'Single-Mail Registration', 'double-opt-in' ),
				__( 'Replace multi-step signup flows with a single email field; the rest is filled in after confirm.', 'double-opt-in' )
			),
			'consent-export'           => $entry(
				'consent-export',
				__( 'Consent Export', 'double-opt-in' ),
				__( 'Export full consent history for any opt-in as audit-ready CSV or JSON.', 'double-opt-in' )
			),
			'conditional'              => $entry(
				'conditional',
				__( 'Conditional Templates', 'double-opt-in' ),
				__( 'Pick which opt-in email a user sees based on form context, language, or category.', 'double-opt-in' )
			),
			'elementor'                => $entry(
				'elementor',
				__( 'Elementor Forms Integration', 'double-opt-in' ),
				__( 'Drop-in double opt-in for any Elementor Pro form, no code required.', 'double-opt-in' )
			),
			'wpforms'                  => $entry(
				'wpforms',
				__( 'WPForms Integration', 'double-opt-in' ),
				__( 'Add a confirmation step to any WPForms form with one toggle in the form settings.', 'double-opt-in' )
			),
			'gravity-forms'            => $entry(
				'gravity-forms',
				__( 'Gravity Forms Integration', 'double-opt-in' ),
				__( 'Wire any Gravity Forms feed through a real double opt-in confirmation.', 'double-opt-in' )
			),
			'avada'                    => $entry(
				'avada',
				__( 'Avada Forms Integration', 'double-opt-in' ),
				__( 'Restore the Avada Fusion-Forms support that used to ship inside the Pro monolith.', 'double-opt-in' )
			),
			// Pro feature: SPA-integrated visual email template
			// editor + the Email Templates list/management page.
			// Bundled via the standard Pro bundle license; stand-
			// alone keys are also accepted by addon-email-editor's
			// license bootstrap.
			'email-editor'             => $entry(
				'email-editor',
				__( 'Email Editor', 'double-opt-in' ),
				__( 'SPA-integrated visual email template editor and templates manager. Drag-and-drop blocks, custom layouts, and full template lifecycle.', 'double-opt-in' )
			),
			'cleverreach'              => $entry(
				'cleverreach',
				__( 'CleverReach', 'double-opt-in' ),
				__( 'CleverReach API integration: OAuth handshake, list synchronisation, and subscriber export. Forwards confirmed opt-ins to your CleverReach account.', 'double-opt-in' )
			),
		);

		return $entries;
	}

	/**
	 * Look up a single catalog entry by addon ID. Returns null when the
	 * ID is unknown — caller should fall back to humanising the ID.
	 *
	 * @return array{id:string,name:string,description:string,pluginFile:string,bundleMember:bool}|null
	 */
	public static function get( string $id ): ?array {
		$entries = self::entries();
		return $entries[ $id ] ?? null;
	}
}
