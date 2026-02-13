<?php
/**
 * Form Integration Registry
 *
 * @package Forge12\DoubleOptIn\Integration
 * @since   4.0.0
 */

namespace Forge12\DoubleOptIn\Integration;

use Forge12\DoubleOptIn\Container\Container;
use Forge12\DoubleOptIn\EventSystem\EventDispatcherInterface;
use Forge12\DoubleOptIn\Events\Integration\IntegrationRegisteredEvent;
use Forge12\Shared\LoggerInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FormIntegrationRegistry
 *
 * Central registry for all form integrations.
 * Manages registration, discovery, and lifecycle of form system integrations.
 */
final class FormIntegrationRegistry {

	/**
	 * Singleton instance.
	 *
	 * @var FormIntegrationRegistry|null
	 */
	private static ?FormIntegrationRegistry $instance = null;

	/**
	 * Registered integrations.
	 *
	 * @var array<string, FormIntegrationInterface>
	 */
	private array $integrations = [];

	/**
	 * Whether integrations have been initialized.
	 *
	 * @var bool
	 */
	private bool $initialized = false;

	/**
	 * Logger instance.
	 *
	 * @var LoggerInterface|null
	 */
	private ?LoggerInterface $logger = null;

	/**
	 * Private constructor - use getInstance().
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
	 * @return FormIntegrationRegistry
	 */
	public static function getInstance(): FormIntegrationRegistry {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Reset the singleton instance.
	 *
	 * Useful for testing.
	 *
	 * @return void
	 */
	public static function resetInstance(): void {
		self::$instance = null;
	}

	/**
	 * Set the logger instance.
	 *
	 * @param LoggerInterface $logger The logger.
	 *
	 * @return void
	 */
	public function setLogger( LoggerInterface $logger ): void {
		$this->logger = $logger;
	}

	/**
	 * Register an integration.
	 *
	 * @param FormIntegrationInterface $integration The integration to register.
	 *
	 * @return bool True if registered successfully, false if already exists.
	 */
	public function register( FormIntegrationInterface $integration ): bool {
		$identifier = $integration->getIdentifier();

		if ( isset( $this->integrations[ $identifier ] ) ) {
			$this->log( 'warning', 'Integration already registered', [
				'identifier' => $identifier,
			] );
			return false;
		}

		$this->integrations[ $identifier ] = $integration;

		$this->log( 'info', 'Integration registered', [
			'identifier' => $identifier,
			'available'  => $integration->isAvailable(),
		] );

		// If registry is already initialized and the integration is available,
		// register its hooks immediately (late registration support)
		if ( $this->initialized && $integration->isAvailable() ) {
			try {
				$integration->registerHooks();
				$this->log( 'info', 'Late registration: Integration hooks registered', [
					'identifier' => $identifier,
				] );
			} catch ( \Exception $e ) {
				$this->log( 'error', 'Late registration: Failed to register integration hooks', [
					'identifier' => $identifier,
					'error'      => $e->getMessage(),
				] );
			}
		}

		// Dispatch event
		$this->dispatchIntegrationRegisteredEvent( $integration );

		// Allow external code to react to registration
		do_action( 'f12_cf7_doubleoptin_integration_registered', $integration, $identifier );

		return true;
	}

	/**
	 * Unregister an integration.
	 *
	 * @param string $identifier The integration identifier.
	 *
	 * @return bool True if unregistered successfully.
	 */
	public function unregister( string $identifier ): bool {
		if ( ! isset( $this->integrations[ $identifier ] ) ) {
			return false;
		}

		unset( $this->integrations[ $identifier ] );

		$this->log( 'info', 'Integration unregistered', [
			'identifier' => $identifier,
		] );

		return true;
	}

	/**
	 * Get an integration by identifier.
	 *
	 * @param string $identifier The integration identifier.
	 *
	 * @return FormIntegrationInterface|null The integration or null if not found.
	 */
	public function get( string $identifier ): ?FormIntegrationInterface {
		return $this->integrations[ $identifier ] ?? null;
	}

	/**
	 * Check if an integration exists.
	 *
	 * @param string $identifier The integration identifier.
	 *
	 * @return bool True if the integration is registered.
	 */
	public function has( string $identifier ): bool {
		return isset( $this->integrations[ $identifier ] );
	}

	/**
	 * Get all registered integrations.
	 *
	 * @return array<string, FormIntegrationInterface>
	 */
	public function getAll(): array {
		return $this->integrations;
	}

	/**
	 * Get all available integrations (where isAvailable() returns true).
	 *
	 * @return array<string, FormIntegrationInterface>
	 */
	public function getAvailable(): array {
		return array_filter( $this->integrations, function ( FormIntegrationInterface $integration ) {
			return $integration->isAvailable();
		} );
	}

	/**
	 * Get integration identifiers as a list.
	 *
	 * @return array<string>
	 */
	public function getIdentifiers(): array {
		return array_keys( $this->integrations );
	}

	/**
	 * Initialize all available integrations.
	 *
	 * Registers hooks for all integrations that are available.
	 *
	 * @return void
	 */
	public function initialize(): void {
		if ( $this->initialized ) {
			return;
		}

		$this->log( 'debug', 'Initializing integrations' );

		foreach ( $this->getAvailable() as $identifier => $integration ) {
			try {
				$integration->registerHooks();

				$this->log( 'info', 'Integration hooks registered', [
					'identifier' => $identifier,
				] );
			} catch ( \Exception $e ) {
				$this->log( 'error', 'Failed to register integration hooks', [
					'identifier' => $identifier,
					'error'      => $e->getMessage(),
				] );
			}
		}

		$this->initialized = true;

		do_action( 'f12_cf7_doubleoptin_integrations_initialized', $this );
	}

	/**
	 * Check if integrations have been initialized.
	 *
	 * @return bool
	 */
	public function isInitialized(): bool {
		return $this->initialized;
	}

	/**
	 * Get integrations as options for admin dropdown.
	 *
	 * @param bool $onlyAvailable Only include available integrations.
	 *
	 * @return array<string, string> Identifier => Name
	 */
	public function getAsOptions( bool $onlyAvailable = true ): array {
		$integrations = $onlyAvailable ? $this->getAvailable() : $this->getAll();
		$options      = [];

		foreach ( $integrations as $identifier => $integration ) {
			$options[ $identifier ] = $integration->getName();
		}

		return $options;
	}

	/**
	 * Find integration by form ID and type.
	 *
	 * @param int    $formId   The form ID.
	 * @param string $formType The expected form type (optional, for validation).
	 *
	 * @return FormIntegrationInterface|null The matching integration.
	 */
	public function findForForm( int $formId, string $formType = '' ): ?FormIntegrationInterface {
		if ( ! empty( $formType ) && isset( $this->integrations[ $formType ] ) ) {
			return $this->integrations[ $formType ];
		}

		// Try to detect the form type from post type or other indicators
		$post = get_post( $formId );
		if ( ! $post ) {
			return null;
		}

		switch ( $post->post_type ) {
			case 'wpcf7_contact_form':
				return $this->get( 'cf7' );
			case 'fusion_form':
				return $this->get( 'avada' );
			case 'wpforms':
				return $this->get( 'wpforms' );
			default:
				return null;
		}
	}

	/**
	 * Get all forms from all available integrations.
	 *
	 * Returns forms grouped by integration.
	 *
	 * @since 4.1.0
	 *
	 * @return array<string, array{name: string, forms: array}>
	 */
	public function getAllForms(): array {
		$result = [];

		foreach ( $this->getAvailable() as $identifier => $integration ) {
			$forms = $integration->getForms();

			if ( ! empty( $forms ) ) {
				$result[ $identifier ] = [
					'name'  => $integration->getName(),
					'forms' => $forms,
				];
			}
		}

		$this->log( 'debug', 'Retrieved all forms from integrations', [
			'integration_count' => count( $result ),
		] );

		return $result;
	}

	/**
	 * Get a flat list of all forms from all integrations.
	 *
	 * @since 4.1.0
	 *
	 * @return array<array{id: int, title: string, integration: string, integration_name: string, enabled: bool, edit_url: string}>
	 */
	public function getAllFormsFlat(): array {
		$forms = [];

		foreach ( $this->getAvailable() as $identifier => $integration ) {
			$integrationForms = $integration->getForms();

			foreach ( $integrationForms as $form ) {
				$form['integration_name'] = $integration->getName();
				$forms[] = $form;
			}
		}

		return $forms;
	}

	/**
	 * Dispatch IntegrationRegisteredEvent.
	 *
	 * @param FormIntegrationInterface $integration The registered integration.
	 *
	 * @return void
	 */
	private function dispatchIntegrationRegisteredEvent( FormIntegrationInterface $integration ): void {
		try {
			$container = Container::getInstance();
			if ( $container->has( EventDispatcherInterface::class ) ) {
				$dispatcher = $container->get( EventDispatcherInterface::class );
				$event = new IntegrationRegisteredEvent(
					$integration->getIdentifier(),
					$integration->getIdentifier(),
					$integration->isAvailable()
				);
				$dispatcher->dispatch( $event );
			}
		} catch ( \Exception $e ) {
			$this->log( 'warning', 'Failed to dispatch IntegrationRegisteredEvent', [
				'error' => $e->getMessage(),
			] );
		}
	}

	/**
	 * Log a message.
	 *
	 * @param string $level   The log level.
	 * @param string $message The message.
	 * @param array  $context The context.
	 *
	 * @return void
	 */
	private function log( string $level, string $message, array $context = [] ): void {
		if ( ! $this->logger ) {
			return;
		}

		$context = array_merge( [
			'plugin'    => 'double-opt-in',
			'component' => 'integration-registry',
		], $context );

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
