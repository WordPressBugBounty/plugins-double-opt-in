<?php
/**
 * Which third-party form plugins are installed, and whether their addon is loaded.
 *
 * The setup wizard uses this to name the addon a site actually needs, instead
 * of advertising every addon to everyone. The support diagnosis uses it for
 * the version list. Only presence is detected here; the forms themselves are
 * read by the addons, never by Core.
 *
 * @package Forge12\DoubleOptIn\Setup
 * @since   5.7.0
 */

declare( strict_types=1 );

namespace Forge12\DoubleOptIn\Setup;

use Forge12\DoubleOptIn\Addon\AddonRegistry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FormPluginDetector {

	/**
	 * Known form plugins that have a paid addon.
	 *
	 * @var array<string, array{name: string, addonId: string}>
	 */
	private const PLUGINS = array(
		'elementor_pro' => array(
			'name'    => 'Elementor Pro',
			'addonId' => 'elementor',
		),
		'wpforms'       => array(
			'name'    => 'WPForms',
			'addonId' => 'wpforms',
		),
		'gravityforms'  => array(
			'name'    => 'Gravity Forms',
			'addonId' => 'gravity-forms',
		),
		'avada'         => array(
			'name'    => 'Avada Forms',
			'addonId' => 'avada',
		),
	);

	/** @var callable(string):?string */
	private $versionOf;

	/** @var callable():string[] */
	private $loadedAddons;

	/**
	 * @param callable|null $versionOf    Returns the version of a plugin id, or null
	 *                                    when it is not installed (test seam).
	 * @param callable|null $loadedAddons Returns the ids of loaded addons (test seam).
	 */
	public function __construct( ?callable $versionOf = null, ?callable $loadedAddons = null ) {
		$this->versionOf    = $versionOf ?? array( self::class, 'detectVersion' );
		$this->loadedAddons = $loadedAddons ?? static function (): array {
			if ( ! class_exists( AddonRegistry::class ) ) {
				return array();
			}
			return array_map( 'strval', array_keys( AddonRegistry::getInstance()->available() ) );
		};
	}

	/**
	 * Installed form plugins.
	 *
	 * @return array<int, array{id: string, name: string, version: string, addonId: string, addonLoaded: bool}>
	 */
	public function installed(): array {
		$loaded = (array) call_user_func( $this->loadedAddons );
		$found  = array();

		foreach ( self::PLUGINS as $id => $plugin ) {
			$version = call_user_func( $this->versionOf, $id );
			if ( $version === null ) {
				continue;
			}
			$found[] = array(
				'id'          => $id,
				'name'        => $plugin['name'],
				'version'     => (string) $version,
				'addonId'     => $plugin['addonId'],
				'addonLoaded' => in_array( $plugin['addonId'], $loaded, true ),
			);
		}

		/**
		 * Filter the detected form plugins.
		 *
		 * @param array $found Detected plugins.
		 *
		 * @since 5.7.0
		 */
		return (array) apply_filters( 'f12_doi_setup_form_plugins', $found );
	}

	/**
	 * Installed form plugins whose addon is not loaded — the ones worth mentioning.
	 *
	 * @return array<int, array{id: string, name: string, version: string, addonId: string, addonLoaded: bool}>
	 */
	public function withoutAddon(): array {
		return array_values(
			array_filter(
				$this->installed(),
				static function ( array $plugin ): bool {
					return empty( $plugin['addonLoaded'] );
				}
			)
		);
	}

	/**
	 * Version of a known form plugin, or null when it is not active.
	 */
	public static function detectVersion( string $id ): ?string {
		switch ( $id ) {
			case 'elementor_pro':
				return defined( 'ELEMENTOR_PRO_VERSION' ) ? (string) constant( 'ELEMENTOR_PRO_VERSION' ) : null;
			case 'wpforms':
				return defined( 'WPFORMS_VERSION' ) ? (string) constant( 'WPFORMS_VERSION' ) : null;
			case 'gravityforms':
				if ( class_exists( 'GFForms' ) ) {
					return isset( \GFForms::$version ) ? (string) \GFForms::$version : '';
				}
				return null;
			case 'avada':
				if ( defined( 'FUSION_BUILDER_VERSION' ) ) {
					return (string) constant( 'FUSION_BUILDER_VERSION' );
				}
				return class_exists( 'Fusion_Form_Builder' ) ? '' : null;
		}
		return null;
	}
}
