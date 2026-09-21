<?php
/**
 * Registry of follow-up adapters.
 *
 * @package Forge12\DoubleOptIn\FollowUp
 * @since   5.6.0
 */

declare( strict_types=1 );

namespace Forge12\DoubleOptIn\FollowUp;

use forge12\contactform7\CF7DoubleOptIn\OptIn;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Maps an integration identifier to its adapter.
 *
 * Core and the form addons register directly (container lookup in
 * `boot()`). Third parties can use the `f12_doi_register_follow_up_adapters`
 * action, which fires once, lazily, on first lookup.
 */
final class FollowUpAdapterRegistry {

	/** @var array<string, FollowUpAdapterInterface> */
	private $adapters = array();

	/** @var bool */
	private $hookFired = false;

	public function register( FollowUpAdapterInterface $adapter ): void {
		$this->adapters[ $adapter->getIntegration() ] = $adapter;
	}

	public function get( string $integration ): ?FollowUpAdapterInterface {
		$this->fireHookOnce();
		return $this->adapters[ $integration ] ?? null;
	}

	/**
	 * Find the adapter responsible for an opt-in.
	 *
	 * `OptIn::isType()` is a lookup per call (post type / post meta), so
	 * each registered integration is asked at most once.
	 */
	public function forOptIn( OptIn $optIn ): ?FollowUpAdapterInterface {
		$this->fireHookOnce();
		foreach ( $this->adapters as $integration => $adapter ) {
			if ( $optIn->isType( $integration ) ) {
				return $adapter;
			}
		}
		return null;
	}

	/**
	 * @return string[]
	 */
	public function integrations(): array {
		$this->fireHookOnce();
		return array_keys( $this->adapters );
	}

	private function fireHookOnce(): void {
		if ( $this->hookFired ) {
			return;
		}
		$this->hookFired = true;

		if ( function_exists( 'do_action' ) ) {
			/**
			 * Register additional follow-up adapters.
			 *
			 * @param FollowUpAdapterRegistry $registry
			 *
			 * @since 5.6.0
			 */
			do_action( 'f12_doi_register_follow_up_adapters', $this );
		}
	}
}
