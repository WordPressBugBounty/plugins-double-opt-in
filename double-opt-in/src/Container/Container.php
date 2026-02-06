<?php
/**
 * Dependency Injection Container
 *
 * @package Forge12\DoubleOptIn\Container
 * @since   4.0.0
 */

namespace Forge12\DoubleOptIn\Container;

use Forge12\Shared\LoggerInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Container
 *
 * A simple PSR-11 compatible dependency injection container with auto-wiring support.
 */
class Container implements ContainerInterface {

	/**
	 * Registered bindings.
	 *
	 * @var array<string, array{concrete: string|callable, shared: bool}>
	 */
	private array $bindings = [];

	/**
	 * Resolved singleton instances.
	 *
	 * @var array<string, mixed>
	 */
	private array $instances = [];

	/**
	 * Factory callables for lazy loading.
	 *
	 * @var array<string, callable>
	 */
	private array $factories = [];

	/**
	 * Registered service providers.
	 *
	 * @var ServiceProviderInterface[]
	 */
	private array $providers = [];

	/**
	 * Logger instance for debugging.
	 *
	 * @var LoggerInterface|null
	 */
	private ?LoggerInterface $logger = null;

	/**
	 * Singleton instance.
	 *
	 * @var Container|null
	 */
	private static ?Container $instance = null;

	/**
	 * Get the singleton container instance.
	 *
	 * Used for backward compatibility during migration.
	 *
	 * @return Container
	 */
	public static function getInstance(): Container {
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
	 * Register a binding in the container.
	 *
	 * @param string               $abstract The abstract type (interface or class name).
	 * @param string|callable|null $concrete The concrete implementation.
	 * @param bool                 $shared   Whether to share the instance (singleton).
	 *
	 * @return void
	 */
	public function bind( string $abstract, $concrete = null, bool $shared = false ): void {
		$concrete = $concrete ?? $abstract;

		$this->bindings[ $abstract ] = [
			'concrete' => $concrete,
			'shared'   => $shared,
		];

		$this->log( 'Binding registered', [ 'abstract' => $abstract, 'shared' => $shared ] );
	}

	/**
	 * Register a singleton binding.
	 *
	 * @param string               $abstract The abstract type.
	 * @param string|callable|null $concrete The concrete implementation.
	 *
	 * @return void
	 */
	public function singleton( string $abstract, $concrete = null ): void {
		$this->bind( $abstract, $concrete, true );
	}

	/**
	 * Register a factory for lazy loading.
	 *
	 * @param string   $abstract The abstract type.
	 * @param callable $factory  The factory callable.
	 *
	 * @return void
	 */
	public function factory( string $abstract, callable $factory ): void {
		$this->factories[ $abstract ] = $factory;
		$this->log( 'Factory registered', [ 'abstract' => $abstract ] );
	}

	/**
	 * Register an existing instance in the container.
	 *
	 * @param string $abstract The abstract type.
	 * @param mixed  $instance The instance.
	 *
	 * @return void
	 */
	public function instance( string $abstract, $instance ): void {
		$this->instances[ $abstract ] = $instance;
		$this->log( 'Instance registered', [ 'abstract' => $abstract ] );
	}

	/**
	 * Register a service provider.
	 *
	 * @param ServiceProviderInterface $provider The service provider.
	 *
	 * @return void
	 */
	public function addProvider( ServiceProviderInterface $provider ): void {
		$this->providers[] = $provider;
		$provider->register( $this );
		$this->log( 'Provider registered', [ 'provider' => get_class( $provider ) ] );
	}

	/**
	 * Boot all registered providers.
	 *
	 * @return void
	 */
	public function boot(): void {
		foreach ( $this->providers as $provider ) {
			if ( $provider instanceof BootableProviderInterface ) {
				$provider->boot( $this );
				$this->log( 'Provider booted', [ 'provider' => get_class( $provider ) ] );
			}
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function get( string $id ) {
		// Return existing instance if available
		if ( isset( $this->instances[ $id ] ) ) {
			return $this->instances[ $id ];
		}

		// Use factory if available
		if ( isset( $this->factories[ $id ] ) ) {
			$instance                 = ( $this->factories[ $id ] )( $this );
			$this->instances[ $id ] = $instance;
			return $instance;
		}

		// Check bindings
		if ( isset( $this->bindings[ $id ] ) ) {
			$binding  = $this->bindings[ $id ];
			$concrete = $binding['concrete'];

			if ( $binding['shared'] && isset( $this->instances[ $id ] ) ) {
				return $this->instances[ $id ];
			}

			$instance = is_callable( $concrete )
				? $concrete( $this )
				: $this->resolve( $concrete );

			if ( $binding['shared'] ) {
				$this->instances[ $id ] = $instance;
			}

			return $instance;
		}

		// Auto-wire if class exists
		if ( class_exists( $id ) ) {
			return $this->resolve( $id );
		}

		throw new NotFoundException( "No binding found for: {$id}" );
	}

	/**
	 * {@inheritdoc}
	 */
	public function has( string $id ): bool {
		return isset( $this->bindings[ $id ] )
			|| isset( $this->instances[ $id ] )
			|| isset( $this->factories[ $id ] )
			|| class_exists( $id );
	}

	/**
	 * Resolve a class with automatic dependency injection.
	 *
	 * @param string $class The class name.
	 *
	 * @return object The resolved instance.
	 * @throws NotFoundException If dependencies cannot be resolved.
	 */
	private function resolve( string $class ): object {
		$reflector = new \ReflectionClass( $class );

		if ( ! $reflector->isInstantiable() ) {
			throw new NotFoundException( "Class {$class} is not instantiable" );
		}

		$constructor = $reflector->getConstructor();

		if ( $constructor === null ) {
			return new $class();
		}

		$dependencies = [];
		foreach ( $constructor->getParameters() as $param ) {
			$type = $param->getType();

			if ( $type === null || $type->isBuiltin() ) {
				if ( $param->isDefaultValueAvailable() ) {
					$dependencies[] = $param->getDefaultValue();
				} else {
					throw new NotFoundException(
						"Cannot resolve parameter {$param->getName()} for {$class}"
					);
				}
			} else {
				$typeName = $type instanceof \ReflectionNamedType ? $type->getName() : (string) $type;
				$dependencies[] = $this->get( $typeName );
			}
		}

		return $reflector->newInstanceArgs( $dependencies );
	}

	/**
	 * Log a debug message.
	 *
	 * @param string $message The message.
	 * @param array  $context The context.
	 *
	 * @return void
	 */
	private function log( string $message, array $context = [] ): void {
		if ( $this->logger ) {
			$this->logger->debug( $message, array_merge( [ 'plugin' => 'double-opt-in', 'component' => 'container' ], $context ) );
		}
	}
}
