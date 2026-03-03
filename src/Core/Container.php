<?php
/**
 * Container Dependency Injection
 *
 * Lightweight DI container without singleton pattern.
 * Uses shared instances managed by the container, NOT static singletons.
 *
 * @package OpsHealthDashboard\Core
 */

namespace OpsHealthDashboard\Core;

if ( ! defined( 'ABSPATH' ) ) {
	// phpcs:ignore Squiz.Commenting.InlineComment.InvalidEndChar
	// @codeCoverageIgnoreStart
	exit;
	// phpcs:ignore Squiz.Commenting.InlineComment.InvalidEndChar
	// @codeCoverageIgnoreEnd
}

/**
 * Class Container
 *
 * Simple dependency injection container.
 */
class Container {

	/**
	 * Registered bindings
	 *
	 * @var array
	 */
	private $bindings = [];

	/**
	 * Shared instances
	 *
	 * @var array
	 */
	private $shared = [];

	/**
	 * Registered instances
	 *
	 * @var array
	 */
	private $instances = [];

	/**
	 * Abstracts being resolved (circular dependency detection)
	 *
	 * @var array
	 */
	private $resolving = [];

	/**
	 * Binds an abstract to a concrete implementation
	 *
	 * Creates a new instance on each call to make().
	 *
	 * @param string   $abstract Abstract identifier.
	 * @param callable $concrete Closure that returns the instance.
	 * @return void
	 */
	public function bind( string $abstract, callable $concrete ): void {
		$this->bindings[ $abstract ] = $concrete;
	}

	/**
	 * Registers a shared binding
	 *
	 * Creates the instance once, reuses it on subsequent calls to make().
	 * NOT a singleton pattern - managed by the container, not self-managed.
	 *
	 * @param string   $abstract Abstract identifier.
	 * @param callable $concrete Closure that returns the instance.
	 * @return void
	 */
	public function share( string $abstract, callable $concrete ): void {
		$this->shared[ $abstract ] = $concrete;
	}

	/**
	 * Registers an existing instance
	 *
	 * @param string $abstract Abstract identifier.
	 * @param mixed  $instance Instance to register.
	 * @return void
	 */
	public function instance( string $abstract, $instance ): void {
		$this->instances[ $abstract ] = $instance;
	}

	/**
	 * Resolves an abstract from the container
	 *
	 * @param string $abstract Abstract identifier.
	 * @return mixed Resolved instance.
	 * @throws \Exception If no binding found.
	 */
	public function make( string $abstract ) {
		if ( isset( $this->instances[ $abstract ] ) ) {
			return $this->instances[ $abstract ];
		}

		if ( isset( $this->resolving[ $abstract ] ) ) {
			throw new \Exception( "Circular dependency detected for [{$abstract}]" );
		}

		$this->resolving[ $abstract ] = true;

		try {
			if ( isset( $this->shared[ $abstract ] ) ) {
				if ( ! isset( $this->instances[ $abstract ] ) ) {
					$this->instances[ $abstract ] = $this->shared[ $abstract ]( $this );
				}
				return $this->instances[ $abstract ];
			}

			if ( isset( $this->bindings[ $abstract ] ) ) {
				return $this->bindings[ $abstract ]( $this );
			}

			throw new \Exception( "No binding found for [{$abstract}]" );
		} finally {
			unset( $this->resolving[ $abstract ] );
		}
	}
}
