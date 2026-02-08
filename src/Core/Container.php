<?php
/**
 * Dependency Injection Container
 *
 * Lightweight DI container without singleton pattern.
 * Uses shared instances managed by container, NOT static singletons.
 *
 * @package OpsHealthDashboard\Core
 */

namespace OpsHealthDashboard\Core;

/**
 * Class Container
 *
 * Simple dependency injection container.
 * NO singleton pattern, NO static methods, NO final modifier.
 */
class Container {

	/**
	 * Registered bindings
	 *
	 * @var array
	 */
	private $bindings = [];

	/**
	 * Shared instances (not singletons - managed by container)
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
	 * Bind an abstract to a concrete implementation
	 *
	 * Creates new instance on each make() call.
	 *
	 * @param string   $abstract Abstract identifier.
	 * @param callable $concrete Closure that returns instance.
	 * @return void
	 */
	public function bind( string $abstract, callable $concrete ): void {
		$this->bindings[ $abstract ] = $concrete;
	}

	/**
	 * Register a shared binding
	 *
	 * Creates instance once, reuses on subsequent make() calls.
	 * NOT a singleton pattern - managed by container, not self-managing.
	 *
	 * @param string   $abstract Abstract identifier.
	 * @param callable $concrete Closure that returns instance.
	 * @return void
	 */
	public function share( string $abstract, callable $concrete ): void {
		$this->shared[ $abstract ] = $concrete;
	}

	/**
	 * Register an existing instance
	 *
	 * @param string $abstract Abstract identifier.
	 * @param mixed  $instance Instance to register.
	 * @return void
	 */
	public function instance( string $abstract, $instance ): void {
		$this->instances[ $abstract ] = $instance;
	}

	/**
	 * Resolve an abstract from the container
	 *
	 * @param string $abstract Abstract identifier.
	 * @return mixed Resolved instance.
	 * @throws \Exception If no binding found.
	 */
	public function make( string $abstract ) {
		// Check if instance already exists.
		if ( isset( $this->instances[ $abstract ] ) ) {
			return $this->instances[ $abstract ];
		}

		// Check if it's a shared binding.
		if ( isset( $this->shared[ $abstract ] ) ) {
			if ( ! isset( $this->instances[ $abstract ] ) ) {
				$this->instances[ $abstract ] = $this->shared[ $abstract ]( $this );
			}
			return $this->instances[ $abstract ];
		}

		// Check if it's a regular binding.
		if ( isset( $this->bindings[ $abstract ] ) ) {
			return $this->bindings[ $abstract ]( $this );
		}

		throw new \Exception( "No binding found for [{$abstract}]" );
	}
}
