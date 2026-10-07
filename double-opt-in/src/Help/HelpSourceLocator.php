<?php
/**
 * Finds every place that ships help articles: the core, plus each registered
 * addon that has a `help/` directory next to its `src/`.
 *
 * Addons need no code for this. An addon whose layout differs adds itself
 * through the `f12_doi_help_sources` filter.
 *
 * @package Forge12\DoubleOptIn\Help
 * @since   5.11.0
 */

declare( strict_types=1 );

namespace Forge12\DoubleOptIn\Help;

use Forge12\DoubleOptIn\Addon\AddonInterface;
use Forge12\DoubleOptIn\Addon\AddonRegistry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HelpSourceLocator {

	/**
	 * @return HelpSource[]
	 */
	public function locate(): array {
		$coreDir = dirname( __DIR__, 2 );
		$sources = array();

		if ( is_dir( $coreDir . '/help' ) ) {
			$sources[] = new HelpSource(
				'core',
				__( 'Double Opt-In', 'double-opt-in' ),
				$coreDir . '/help',
				plugins_url( 'help/', F12_DOUBLEOPTIN_PLUGIN_FILE ),
				HelpSource::GROUP_CORE
			);
		}

		$addons = AddonRegistry::getInstance()->all();
		uasort(
			$addons,
			static function ( AddonInterface $a, AddonInterface $b ): int {
				return strcmp( $a->getName(), $b->getName() );
			}
		);
		foreach ( $addons as $id => $addon ) {
			$source = $this->sourceForAddon( (string) $id, $addon );
			if ( null !== $source ) {
				$sources[] = $source;
			}
		}

		/**
		 * Lets a plugin add or replace help sources.
		 *
		 * @param HelpSource[] $sources
		 */
		$filtered = apply_filters( 'f12_doi_help_sources', $sources );
		if ( ! is_array( $filtered ) ) {
			return $sources;
		}
		return array_values(
			array_filter(
				$filtered,
				static function ( $source ): bool {
					return $source instanceof HelpSource;
				}
			)
		);
	}

	private function sourceForAddon( string $id, AddonInterface $addon ): ?HelpSource {
		$file = ( new \ReflectionClass( $addon ) )->getFileName();
		if ( false === $file ) {
			return null;
		}
		// <addon>/src/<Name>Addon.php -> <addon>
		$root = dirname( $file, 2 );
		$dir  = $root . '/help';
		if ( ! is_dir( $dir ) ) {
			return null;
		}

		$group = HelpSource::GROUP_MODULE;
		foreach ( $addon->getCapabilities() as $capability ) {
			if ( 0 === strpos( (string) $capability, 'form.' ) ) {
				$group = HelpSource::GROUP_INTEGRATION;
				break;
			}
		}

		return new HelpSource(
			sanitize_key( $id ),
			$addon->getName(),
			$dir,
			plugins_url( 'help/', $root . '/addon.php' ),
			$group
		);
	}
}
