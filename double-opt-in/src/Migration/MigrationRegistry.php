<?php
/**
 * Migration Registry
 *
 * @package Forge12\DoubleOptIn\Migration
 * @since   4.3.0
 */

namespace Forge12\DoubleOptIn\Migration;

use Forge12\Shared\LoggerInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MigrationRegistry
 *
 * @api
 *
 * Collects migration declarations from Core and addons, then applies any
 * that have not yet run on this site. Applied migration IDs are persisted
 * in the WordPress option `f12_doi_applied_migrations`.
 *
 * Addons register migrations during their `boot()` method; the registry
 * does not schedule itself — the integrating provider calls
 * {@see runPending()} once addons are registered (typically on
 * `admin_init` after addon bootstrap).
 */
final class MigrationRegistry {

	private const OPTION_KEY = 'f12_doi_applied_migrations';

	/**
	 * Singleton instance.
	 *
	 * @var MigrationRegistry|null
	 */
	private static ?MigrationRegistry $instance = null;

	/**
	 * Registered migrations, keyed by ID.
	 *
	 * @var array<string, MigrationInterface>
	 */
	private array $migrations = array();

	/**
	 * Logger.
	 *
	 * @var LoggerInterface|null
	 */
	private ?LoggerInterface $logger = null;

	private function __construct() {}

	private function __clone() {}

	/**
	 * @throws \Exception
	 */
	public function __wakeup() {
		throw new \Exception( 'Cannot unserialize singleton' );
	}

	public static function getInstance(): MigrationRegistry {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * @internal Tests only.
	 */
	public static function resetInstance(): void {
		self::$instance = null;
	}

	public function setLogger( LoggerInterface $logger ): void {
		$this->logger = $logger;
	}

	/**
	 * Register a migration.
	 *
	 * Duplicate IDs are refused with a warning — a migration ID is a
	 * primary key and accidentally re-using one would corrupt the
	 * applied-migrations bookkeeping.
	 *
	 * @param MigrationInterface $migration The migration to register.
	 * @return bool True if registered, false if ID collision.
	 */
	public function register( MigrationInterface $migration ): bool {
		$id = $migration->getId();

		if ( isset( $this->migrations[ $id ] ) ) {
			$this->log(
				'warning',
				'Migration ID collision — second registration ignored',
				array(
					'migration_id' => $id,
				)
			);
			return false;
		}

		$this->migrations[ $id ] = $migration;

		$this->log(
			'debug',
			'Migration registered',
			array(
				'migration_id' => $id,
			)
		);

		return true;
	}

	/**
	 * All registered migration IDs.
	 *
	 * @return string[]
	 */
	public function getRegisteredIds(): array {
		return array_keys( $this->migrations );
	}

	/**
	 * IDs of migrations that have already been applied on this site.
	 *
	 * @return string[]
	 */
	public function getAppliedIds(): array {
		$applied = get_option( self::OPTION_KEY, array() );
		return is_array( $applied ) ? array_values( $applied ) : array();
	}

	/**
	 * IDs of migrations that are registered but not yet applied.
	 *
	 * @return string[]
	 */
	public function getPendingIds(): array {
		return array_values(
			array_diff(
				$this->getRegisteredIds(),
				$this->getAppliedIds()
			)
		);
	}

	/**
	 * Apply every registered migration that has not yet been applied.
	 *
	 * Failures in one migration do not abort the loop; each migration is
	 * isolated. A failing migration stays in the pending list and will
	 * be retried on the next bootstrap.
	 *
	 * @return array{applied: string[], failed: string[]}
	 */
	public function runPending(): array {
		global $wpdb;

		$applied = $this->getAppliedIds();
		$results = array(
			'applied' => array(),
			'failed'  => array(),
		);

		foreach ( $this->getPendingIds() as $id ) {
			$migration = $this->migrations[ $id ];

			try {
				$migration->up( $wpdb );

				$applied[]            = $id;
				$results['applied'][] = $id;

				$this->log(
					'info',
					'Migration applied',
					array(
						'migration_id' => $id,
						'description'  => $migration->getDescription(),
					)
				);
			} catch ( \Throwable $e ) {
				$results['failed'][] = $id;
				$this->log(
					'error',
					'Migration failed — will retry next bootstrap',
					array(
						'migration_id' => $id,
						'error'        => $e->getMessage(),
						'file'         => $e->getFile(),
						'line'         => $e->getLine(),
					)
				);
			}
		}

		// Persist the cumulative applied list in one write. We dedupe
		// defensively in case a migration was recorded elsewhere and now
		// also flows through here.
		$applied = array_values( array_unique( $applied ) );
		update_option( self::OPTION_KEY, $applied, false );

		return $results;
	}

	/**
	 * Log helper.
	 *
	 * @param string $level Log level.
	 * @param string $message Message.
	 * @param array  $context Context.
	 * @return void
	 */
	private function log( string $level, string $message, array $context = array() ): void {
		if ( $this->logger === null ) {
			return;
		}

		$context = array_merge(
			array(
				'plugin'    => 'double-opt-in',
				'component' => 'migration-registry',
			),
			$context
		);

		switch ( $level ) {
			case 'error':
				$this->logger->error( $message, $context );
				break;
			case 'warning':
				$this->logger->warning( $message, $context );
				break;
			case 'info':
				$this->logger->info( $message, $context );
				break;
			default:
				$this->logger->debug( $message, $context );
		}
	}
}
