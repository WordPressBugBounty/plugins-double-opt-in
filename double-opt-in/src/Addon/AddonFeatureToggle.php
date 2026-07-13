<?php
/**
 * Addon Feature Toggle helper.
 *
 * Reads the per-addon settings option managed by Core's
 * AddonSettingsPage (`f12_doi_addon_{id}_settings`) and reports whether
 * the addon's feature is currently enabled. Distinct from WP plugin
 * activation: a plugin can be active in WordPress while the user has
 * temporarily paused its feature here.
 *
 * Default `true` matches the convention "activating the plugin =
 * opting in to its default behaviour" (Akismet-style). Addons that
 * inherit a legacy on/off setting (e.g. `f12-doi-settings.mx_validation_enabled`)
 * implement their own `isFeatureEnabled()` with a fallback chain — see
 * `MxValidator::isFeatureEnabled()` for the reference pattern.
 *
 * @package Forge12\DoubleOptIn\Addon
 * @since   4.4.0
 */

namespace Forge12\DoubleOptIn\Addon;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AddonFeatureToggle {

	private const OPTION_PREFIX = 'f12_doi_addon_';
	private const OPTION_SUFFIX = '_settings';

	/**
	 * Is the addon's feature currently enabled?
	 *
	 * @param string $addonId Internal addon ID matching `AddonInterface::getId()`.
	 * @param bool   $default Returned when the option has not been written yet.
	 */
	public static function isEnabled( string $addonId, bool $default = true ): bool {
		$settings = get_option( self::OPTION_PREFIX . $addonId . self::OPTION_SUFFIX, null );
		if ( is_array( $settings ) && array_key_exists( 'enabled', $settings ) ) {
			return (bool) $settings['enabled'];
		}
		return $default;
	}
}
