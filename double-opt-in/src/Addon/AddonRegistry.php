<?php
/**
 * Addon Registry
 *
 * Central registry for all Double Opt-In addons.
 *
 * @package Forge12\DoubleOptIn\Addon
 * @since   4.3.0
 */

namespace Forge12\DoubleOptIn\Addon;

use Forge12\DoubleOptIn\Container\ContainerInterface;
use Forge12\DoubleOptIn\Versioning\SemverConstraint;
use Forge12\Shared\LoggerInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AddonRegistry
 *
 * @api
 *
 * Singleton registry that collects addon registrations from plugins hooking
 * into `f12_cf7_doubleoptin_register_addons` and boots them in a single pass
 * once registration is complete.
 *
 * An addon is identified by its {@see AddonInterface::getId()}. Duplicate
 * registrations are ignored with a warning — the first-registered addon
 * wins. This protects against double-activation when an addon is bundled
 * inside both a free build and a paid bundle.
 */
final class AddonRegistry {

	/**
	 * Singleton instance.
	 *
	 * @var AddonRegistry|null
	 */
	private static ?AddonRegistry $instance = null;

	/**
	 * Registered addons, keyed by addon ID.
	 *
	 * @var array<string, AddonInterface>
	 */
	private array $addons = array();

	/**
	 * Set of addon IDs that have completed booting.
	 *
	 * @var array<string, true>
	 */
	private array $booted = array();

	/**
	 * Whether bootAll() has run.
	 *
	 * @var bool
	 */
	private bool $bootCompleted = false;

	/**
	 * Logger instance.
	 *
	 * @var LoggerInterface|null
	 */
	private ?LoggerInterface $logger = null;

	/**
	 * Private constructor — use {@see getInstance()}.
	 */
	private function __construct() {}

	/**
	 * Prevent cloning.
	 */
	private function __clone() {}

	/**
	 * Prevent unserialization.
	 *
	 * @throws \Exception
	 */
	public function __wakeup() {
		throw new \Exception( 'Cannot unserialize singleton' );
	}

	/**
	 * Get the singleton instance.
	 *
	 * @return AddonRegistry
	 */
	public static function getInstance(): AddonRegistry {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Reset the singleton instance. Intended for tests only.
	 *
	 * @internal
	 * @return void
	 */
	public static function resetInstance(): void {
		self::$instance = null;
	}

	/**
	 * Set the logger instance.
	 *
	 * @param LoggerInterface $logger The logger.
	 * @return void
	 */
	public function setLogger( LoggerInterface $logger ): void {
		$this->logger = $logger;
	}

	/**
	 * Register an addon.
	 *
	 * Addons register themselves in response to the
	 * `f12_cf7_doubleoptin_register_addons` action.
	 *
	 * If {@see bootAll()} has already run and the addon being registered is
	 * available, it will be booted immediately (late registration).
	 *
	 * @param AddonInterface $addon The addon to register.
	 * @return bool True if registered, false if an addon with the same ID
	 *              was already registered.
	 */
	public function register( AddonInterface $addon ): bool {
		$id = $addon->getId();

		if ( isset( $this->addons[ $id ] ) ) {
			$this->log(
				'warning',
				'Addon already registered — skipping duplicate',
				array(
					'addon_id'       => $id,
					'existing_class' => get_class( $this->addons[ $id ] ),
					'new_class'      => get_class( $addon ),
				)
			);
			return false;
		}

		$this->addons[ $id ] = $addon;

		$this->log(
			'info',
			'Addon registered',
			array(
				'addon_id'  => $id,
				'version'   => $addon->getVersion(),
				'available' => $addon->isAvailable(),
			)
		);

		return true;
	}

	/**
	 * Get an addon by ID.
	 *
	 * @param string $id The addon ID.
	 * @return AddonInterface|null The addon, or null if not registered.
	 */
	public function get( string $id ): ?AddonInterface {
		return $this->addons[ $id ] ?? null;
	}

	/**
	 * Check whether an addon is registered.
	 *
	 * @param string $id The addon ID.
	 * @return bool
	 */
	public function has( string $id ): bool {
		return isset( $this->addons[ $id ] );
	}

	/**
	 * Get all registered addons, regardless of availability.
	 *
	 * @return array<string, AddonInterface>
	 */
	public function all(): array {
		return $this->addons;
	}

	/**
	 * Get all addons for which {@see AddonInterface::isAvailable()} returns true.
	 *
	 * @return array<string, AddonInterface>
	 */
	public function available(): array {
		return array_filter(
			$this->addons,
			static function ( AddonInterface $addon ) {
				return $addon->isAvailable();
			}
		);
	}

	/**
	 * Check whether any registered, available addon advertises the given capability.
	 *
	 * @param string $capability Capability ID, e.g. "mail.reminder".
	 * @return bool
	 */
	public function hasCapability( string $capability ): bool {
		foreach ( $this->available() as $addon ) {
			if ( in_array( $capability, $addon->getCapabilities(), true ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Find all available addons that advertise the given capability.
	 *
	 * @param string $capability Capability ID.
	 * @return AddonInterface[] List of matching addons. May be empty.
	 */
	public function findByCapability( string $capability ): array {
		$matches = array();
		foreach ( $this->available() as $addon ) {
			if ( in_array( $capability, $addon->getCapabilities(), true ) ) {
				$matches[] = $addon;
			}
		}
		return $matches;
	}

	/**
	 * Boot all available, not-yet-booted addons.
	 *
	 * Called by the core once addon registration is complete. Idempotent —
	 * safe to call multiple times; each addon boots at most once.
	 *
	 * Failures in a single addon's boot() do not abort the loop; they are
	 * logged and the remaining addons still boot.
	 *
	 * @param ContainerInterface $container The core DI container.
	 * @return void
	 */
	public function bootAll( ContainerInterface $container ): void {
		$coreApiVersion = defined( 'F12_DOI_CORE_API_VERSION' )
			? F12_DOI_CORE_API_VERSION
			: '0.0.0';

		foreach ( $this->available() as $id => $addon ) {
			if ( isset( $this->booted[ $id ] ) ) {
				continue;
			}

			// Core-version compatibility check. Addons that declare a
			// requirement incompatible with the current core are skipped
			// rather than booted into a broken state. This mirrors
			// Composer's behaviour of refusing to install incompatible
			// packages rather than crashing at runtime.
			$requirement = $addon->getCoreVersionRequirement();
			if ( $requirement !== '' && ! SemverConstraint::matches( $coreApiVersion, $requirement ) ) {
				$this->log(
					'warning',
					'Addon skipped: core version requirement not met',
					array(
						'addon_id'         => $id,
						'required_core'    => $requirement,
						'current_core_api' => $coreApiVersion,
					)
				);
				continue;
			}

			try {
				$addon->boot( $container );
				$this->booted[ $id ] = true;

				$this->log(
					'info',
					'Addon booted',
					array(
						'addon_id' => $id,
					)
				);
			} catch ( \Throwable $e ) {
				$this->log(
					'error',
					'Addon failed to boot',
					array(
						'addon_id' => $id,
						'error'    => $e->getMessage(),
						'file'     => $e->getFile(),
						'line'     => $e->getLine(),
					)
				);
			}
		}

		$this->bootCompleted = true;
	}

	/**
	 * Whether {@see bootAll()} has been invoked at least once.
	 *
	 * @return bool
	 */
	public function isBootCompleted(): bool {
		return $this->bootCompleted;
	}

	/**
	 * Log a message through the configured logger, if any.
	 *
	 * @param string $level   Log level.
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
				'component' => 'addon-registry',
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
