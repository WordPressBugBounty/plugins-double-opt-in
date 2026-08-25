<?php
/**
 * Health check: a required database table exists.
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
 * Verifies that one `$wpdb->prefix`-scoped table is present.
 *
 * Reusable on purpose: the opt-out table was not the first table this
 * project shipped without an install path, and it will not be the last.
 * Any package that owns a table registers one of these instead of
 * writing its own probe.
 *
 * Cost: a single `SHOW TABLES LIKE`, memoised per table name for the
 * duration of the request. That is deliberate — Action Scheduler's
 * missing-table incidents (woocommerce/action-scheduler#744) are the
 * cautionary tale for probing storage on every page load.
 */
final class DatabaseTableHealthCheck implements HealthCheckInterface {

	/**
	 * Per-request memo, keyed by fully-prefixed table name.
	 *
	 * @var array<string,bool>
	 */
	private static $existsCache = array();

	/**
	 * @var string
	 */
	private $id;

	/**
	 * @var string
	 */
	private $package;

	/**
	 * Table name WITHOUT the `$wpdb->prefix`.
	 *
	 * @var string
	 */
	private $tableSuffix;

	/**
	 * What breaks when the table is missing, in the operator's terms.
	 *
	 * @var string
	 */
	private $featureLabel;

	/**
	 * @var string
	 */
	private $actionLabel;

	/**
	 * @var string
	 */
	private $actionUrl;

	public function __construct(
		string $id,
		string $package,
		string $tableSuffix,
		string $featureLabel,
		string $actionLabel = '',
		string $actionUrl = ''
	) {
		$this->id           = $id;
		$this->package      = $package;
		$this->tableSuffix  = $tableSuffix;
		$this->featureLabel = $featureLabel;
		$this->actionLabel  = $actionLabel;
		$this->actionUrl    = $actionUrl;
	}

	public function getId(): string {
		return $this->id;
	}

	public function getLabel(): string {
		return $this->featureLabel;
	}

	public function getPackage(): string {
		return $this->package;
	}

	/**
	 * Fully-prefixed table name.
	 */
	public function getTableName(): string {
		global $wpdb;

		return $wpdb->prefix . $this->tableSuffix;
	}

	/**
	 * Probe the table, memoised per request.
	 */
	public function tableExists(): bool {
		$table = $this->getTableName();

		if ( array_key_exists( $table, self::$existsCache ) ) {
			return self::$existsCache[ $table ];
		}

		global $wpdb;

		// `SHOW TABLES LIKE` takes a pattern, so the name has to go
		// through esc_like() before prepare() — otherwise a prefix
		// containing `_` would match more broadly than intended.
		$found = $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) )
		);

		self::$existsCache[ $table ] = ( $found === $table );

		return self::$existsCache[ $table ];
	}

	/**
	 * Drop the memo. Only needed after a table was just created inside
	 * the same request — otherwise the check would keep reporting the
	 * pre-repair state.
	 */
	public static function flushCache(): void {
		self::$existsCache = array();
	}

	public function run(): HealthCheckResult {
		$table = $this->getTableName();

		if ( $this->tableExists() ) {
			return new HealthCheckResult(
				HealthCheckResult::STATUS_GOOD,
				sprintf(
					/* translators: %s: human-readable feature name, e.g. "Opt-out self-service". */
					__( '%s: the database table is present', 'double-opt-in' ),
					$this->featureLabel
				),
				sprintf(
					/* translators: %s: database table name. */
					__( 'The table %s exists and is writable by the plugin.', 'double-opt-in' ),
					$table
				),
				'ok'
			);
		}

		return new HealthCheckResult(
			HealthCheckResult::STATUS_CRITICAL,
			sprintf(
				/* translators: %s: human-readable feature name, e.g. "Opt-out self-service". */
				__( '%s: a required database table is missing', 'double-opt-in' ),
				$this->featureLabel
			),
			sprintf(
				/* translators: 1: database table name, 2: human-readable feature name. */
				__( 'The table %1$s does not exist, so "%2$s" cannot store anything and will fail for your visitors. Double Opt-In tries to create the table automatically whenever an administrator opens the WordPress admin. If this message persists, the database user is most likely missing the CREATE privilege — your hosting provider can grant it.', 'double-opt-in' ),
				$table,
				$this->featureLabel
			),
			'missing',
			$this->actionLabel,
			$this->actionUrl
		);
	}
}
