<?php
/**
 * Addon License Registry
 *
 * @package Forge12\DoubleOptIn\Licensing
 * @since   4.3.0
 */

namespace Forge12\DoubleOptIn\Licensing;

use Forge12\Shared\LoggerInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AddonLicenseRegistry
 *
 * In-memory, request-scoped state store for addon license entitlements.
 *
 * Entitlements are populated by license providers during their own boot
 * cycle. Because the registry is in-memory only, providers must re-grant
 * entitlements on every request. This is deliberate: it avoids stale
 * cached entitlements surviving a license revocation, and it keeps the
 * registry simple.
 *
 * Providers that need persistence (to avoid calling an update server on
 * every request) are responsible for their own caching (transients,
 * options) and re-grant the entitlements on each bootstrap from cache.
 */
final class AddonLicenseRegistry implements AddonLicenseRegistryInterface {

	/**
	 * Singleton instance.
	 *
	 * @var AddonLicenseRegistry|null
	 */
	private static ?AddonLicenseRegistry $instance = null;

	/**
	 * Licensed addons, keyed by addon ID, values are provider sources.
	 *
	 * @var array<string, string>
	 */
	private array $entitlements = array();

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
	 * @return AddonLicenseRegistry
	 */
	public static function getInstance(): AddonLicenseRegistry {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Reset the singleton. Tests only.
	 *
	 * @internal
	 * @return void
	 */
	public static function resetInstance(): void {
		self::$instance = null;
	}

	/**
	 * Set the logger.
	 *
	 * @param LoggerInterface $logger The logger.
	 * @return void
	 */
	public function setLogger( LoggerInterface $logger ): void {
		$this->logger = $logger;
	}

	/**
	 * {@inheritdoc}
	 */
	public function grant( string $addonId, string $source = 'default' ): void {
		$this->entitlements[ $addonId ] = $source;

		$this->log(
			'info',
			'License granted',
			array(
				'addon_id' => $addonId,
				'source'   => $source,
			)
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function revoke( string $addonId ): void {
		if ( ! isset( $this->entitlements[ $addonId ] ) ) {
			return;
		}

		unset( $this->entitlements[ $addonId ] );

		$this->log(
			'info',
			'License revoked',
			array(
				'addon_id' => $addonId,
			)
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function isLicensed( string $addonId ): bool {
		return isset( $this->entitlements[ $addonId ] );
	}

	/**
	 * {@inheritdoc}
	 */
	public function getSource( string $addonId ): ?string {
		return $this->entitlements[ $addonId ] ?? null;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getLicensedAddons(): array {
		return array_keys( $this->entitlements );
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
				'component' => 'addon-license-registry',
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
