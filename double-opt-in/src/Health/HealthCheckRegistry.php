<?php
/**
 * Registry of runtime health checks.
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
 * Collects every {@see HealthCheckInterface} the Core and its addons
 * contribute, and runs them on demand.
 *
 * Two ways in:
 *
 *  - `register()` — used by Core itself and by anything holding the
 *    container instance.
 *  - the `f12_doi_health_checks` filter — the addon-facing path. An
 *    addon appends its check objects to the array; if it runs against
 *    an older Core that has no Health API, the filter simply never
 *    fires and the addon keeps working.
 *
 * Results are memoised per request: the Site Health page and the admin
 * notice both ask for them, and a `SHOW TABLES` per asker would be
 * wasteful for no gain.
 */
final class HealthCheckRegistry {

	/**
	 * @var array<string,HealthCheckInterface>
	 */
	private $checks = array();

	/**
	 * Memoised results, keyed by check id.
	 *
	 * @var array<string,HealthCheckResult>|null
	 */
	private $results = null;

	/**
	 * Whether the filter pass has already run. Guarded so a second
	 * call doesn't re-append the same objects.
	 *
	 * @var bool
	 */
	private $filtered = false;

	public function register( HealthCheckInterface $check ): void {
		$this->checks[ $check->getId() ] = $check;
		$this->results                   = null;
	}

	/**
	 * Every registered check, addon contributions included.
	 *
	 * @return array<string,HealthCheckInterface>
	 */
	public function all(): array {
		if ( ! $this->filtered ) {
			$this->filtered = true;

			/**
			 * Filter the list of Double Opt-In health checks.
			 *
			 * Addons append their own {@see HealthCheckInterface}
			 * instances here. Entries that are not health checks are
			 * dropped silently — a broken addon must not take the
			 * Site Health page down with it.
			 *
			 * @since 5.3.0
			 *
			 * @param HealthCheckInterface[] $checks
			 */
			$contributed = apply_filters( 'f12_doi_health_checks', array() );

			if ( is_array( $contributed ) ) {
				foreach ( $contributed as $check ) {
					if ( $check instanceof HealthCheckInterface ) {
						$this->checks[ $check->getId() ] = $check;
					}
				}
			}

			$this->results = null;
		}

		return $this->checks;
	}

	/**
	 * Run every check once per request.
	 *
	 * A check that throws is reported as `recommended` rather than
	 * being allowed to bubble — Site Health is a diagnostic screen and
	 * must stay reachable exactly when something is broken.
	 *
	 * @return array<string,HealthCheckResult>
	 */
	public function runAll(): array {
		if ( $this->results !== null ) {
			return $this->results;
		}

		$out = array();
		foreach ( $this->all() as $id => $check ) {
			try {
				$out[ $id ] = $check->run();
			} catch ( \Throwable $e ) {
				$out[ $id ] = new HealthCheckResult(
					HealthCheckResult::STATUS_RECOMMENDED,
					$check->getLabel(),
					sprintf(
						/* translators: %s: the error message thrown by the check. */
						__( 'This check could not be completed: %s', 'double-opt-in' ),
						$e->getMessage()
					),
					'error'
				);
			}
		}

		$this->results = $out;

		return $out;
	}

	/**
	 * Only the results that need the operator's attention.
	 *
	 * @return array<string,HealthCheckResult>
	 */
	public function criticals(): array {
		return array_filter(
			$this->runAll(),
			static function ( HealthCheckResult $result ): bool {
				return $result->isCritical();
			}
		);
	}
}
