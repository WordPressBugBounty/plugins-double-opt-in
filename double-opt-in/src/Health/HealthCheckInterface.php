<?php
/**
 * Health check contract.
 *
 * @package Forge12\DoubleOptIn\Health
 * @since   5.3.0
 */

declare( strict_types=1 );

namespace Forge12\DoubleOptIn\Health;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A single runtime precondition that can be verified and reported.
 *
 * Implementations are cheap by contract: `run()` is called on every
 * Site Health page load and on every admin request that renders the
 * notice, so anything expensive has to be cached inside the check.
 *
 * Addons register their checks through the `f12_doi_health_checks`
 * filter rather than through a method on AddonInterface — that keeps
 * the addon loadable on Core versions that predate this API.
 */
interface HealthCheckInterface {

	/**
	 * Stable identifier, used as the Site Health test id.
	 *
	 * Must be prefixed with `f12_doi_` so it cannot collide with
	 * another plugin's test — WordPress keys the global test array by
	 * this string.
	 */
	public function getId(): string;

	/**
	 * Short human-readable name, shown as the test's row label in the
	 * Site Health accordion.
	 */
	public function getLabel(): string;

	/**
	 * Which package this check belongs to (`core`, `opt-out`, …).
	 * Used to group the Site Health → Info output.
	 */
	public function getPackage(): string;

	/**
	 * Evaluate the precondition.
	 */
	public function run(): HealthCheckResult;
}
