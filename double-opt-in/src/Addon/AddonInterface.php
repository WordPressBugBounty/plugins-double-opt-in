<?php
/**
 * Addon Interface
 *
 * Public contract that every addon plugin implements to register itself
 * with the Double Opt-In core.
 *
 * @package Forge12\DoubleOptIn\Addon
 * @since   4.3.0
 */

namespace Forge12\DoubleOptIn\Addon;

use Forge12\DoubleOptIn\Container\ContainerInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface AddonInterface
 *
 * @api
 *
 * Implemented by every Double Opt-In addon. Addons register themselves
 * by responding to the `f12_cf7_doubleoptin_register_addons` action hook
 * with an instance that implements this interface:
 *
 * <code>
 * add_action( 'f12_cf7_doubleoptin_register_addons', function( AddonRegistry $registry ) {
 *     $registry->register( new MyAddon() );
 * } );
 * </code>
 *
 * The core calls {@see AddonInterface::boot()} once all addons are registered,
 * after which the addon may wire services into the container, attach event
 * listeners, and register WordPress hooks.
 */
interface AddonInterface {

	/**
	 * Unique machine ID for the addon.
	 *
	 * Must be lowercase kebab-case and stable across versions of the addon.
	 * Used as a primary key in the registry, for capability lookups, and for
	 * license matching.
	 *
	 * @return string e.g. "elementor", "reminder", "analytics"
	 */
	public function getId(): string;

	/**
	 * Human-readable addon name. Must be translatable.
	 *
	 * @return string
	 */
	public function getName(): string;

	/**
	 * Addon version. Must match the version declared in the addon's
	 * plugin header and composer.json.
	 *
	 * @return string Semver-compatible version, e.g. "1.0.0".
	 */
	public function getVersion(): string;

	/**
	 * Required core version as a semver constraint.
	 *
	 * The core will silently skip booting an addon whose requirement is not
	 * met by the currently loaded core version and surface an admin notice.
	 *
	 * @return string e.g. "^4.0", ">=4.3 <5.0"
	 */
	public function getCoreVersionRequirement(): string;

	/**
	 * Runtime availability check.
	 *
	 * Return false when prerequisites such as a required third-party plugin,
	 * PHP extension, or license entitlement are not met. The registry will
	 * accept the addon for introspection (so admin UI can surface it) but
	 * {@see self::boot()} will not be called.
	 *
	 * @return bool
	 */
	public function isAvailable(): bool;

	/**
	 * Boot the addon.
	 *
	 * Called once during core bootstrap after all addons are registered and
	 * all core service providers are available. At this point the addon may:
	 *  - register services in the container,
	 *  - attach listeners via the EventDispatcher,
	 *  - register form integrations in FormIntegrationRegistry,
	 *  - add WordPress hooks.
	 *
	 * Expensive work (network, heavy DB) MUST be deferred via
	 * `wp_schedule_single_event` or event listeners — not performed inline.
	 *
	 * @param ContainerInterface $container Core DI container.
	 * @return void
	 */
	public function boot( ContainerInterface $container ): void;

	/**
	 * Capabilities advertised by this addon.
	 *
	 * Used by other addons and the core to detect features without
	 * hard-coupling to class names. Return a flat list of stable string IDs.
	 *
	 * Examples:
	 *  - "form.elementor" (provides an Elementor form integration)
	 *  - "mail.reminder" (provides reminder emails)
	 *  - "admin.ui.settings-tab" (contributes a settings tab to admin UI)
	 *
	 * @return string[]
	 */
	public function getCapabilities(): array;
}
