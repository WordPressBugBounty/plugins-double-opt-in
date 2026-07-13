<?php
/**
 * Addon License Registry Interface
 *
 * @package Forge12\DoubleOptIn\Licensing
 * @since   4.3.0
 */

namespace Forge12\DoubleOptIn\Licensing;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface AddonLicenseRegistryInterface
 *
 * @api
 *
 * The license registry is a pure state holder. It does NOT perform license
 * validation — that responsibility belongs to a license provider (e.g. the
 * Pro bundle's LicenseManager). A license provider validates keys against
 * its own server and then calls {@see grant()} on the registry for each
 * addon the license entitles.
 *
 * Addons consume the registry read-only via {@see isLicensed()}. They
 * MUST NOT grant or revoke entitlements themselves — that would defeat
 * the separation of concerns.
 *
 * Free addons do not need to interact with this registry at all; only
 * paid addons gate themselves on {@see isLicensed()}.
 */
interface AddonLicenseRegistryInterface {

	/**
	 * Grant a license entitlement for an addon.
	 *
	 * Called by a license provider after successful validation. Idempotent —
	 * re-granting the same addon overrides only the source, not the
	 * licensed state.
	 *
	 * @param string $addonId Addon ID, must match {@see \Forge12\DoubleOptIn\Addon\AddonInterface::getId()}.
	 * @param string $source  Free-form provider identifier for audit logs,
	 *                        e.g. "pro-bundle", "elementor-standalone". Defaults to "default".
	 * @return void
	 */
	public function grant( string $addonId, string $source = 'default' ): void;

	/**
	 * Revoke an addon's entitlement.
	 *
	 * Called when a license expires or is removed. No-op if the addon has
	 * no entitlement.
	 *
	 * @param string $addonId Addon ID.
	 * @return void
	 */
	public function revoke( string $addonId ): void;

	/**
	 * Check whether an addon currently has a valid entitlement.
	 *
	 * This is the primary method called by paid addons during bootstrap
	 * to decide whether to activate their features.
	 *
	 * @param string $addonId Addon ID.
	 * @return bool
	 */
	public function isLicensed( string $addonId ): bool;

	/**
	 * Return the source of an addon's entitlement, or null if unlicensed.
	 *
	 * Useful for debugging and admin UI ("licensed via Pro bundle").
	 *
	 * @param string $addonId Addon ID.
	 * @return string|null
	 */
	public function getSource( string $addonId ): ?string;

	/**
	 * List all addon IDs currently entitled.
	 *
	 * @return string[]
	 */
	public function getLicensedAddons(): array;
}
