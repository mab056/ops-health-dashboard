<?php
/**
 * Main Plugin Class
 *
 * @package OpsHealthDashboard\Core
 */

namespace OpsHealthDashboard\Core;

/**
 * Class Plugin
 *
 * Main plugin orchestrator with dependency injection.
 * NO singleton pattern, NO static methods, NO final modifier.
 */
class Plugin {

	/**
	 * Dependency injection container
	 *
	 * @var Container
	 */
	private $container;

	/**
	 * Initialization flag
	 *
	 * @var bool
	 */
	private $initialized = false;

	/**
	 * Constructor
	 *
	 * Receives container via dependency injection (NO singleton).
	 *
	 * @param Container $container DI container.
	 */
	public function __construct( Container $container ) {
		$this->container = $container;
	}

	/**
	 * Initialize plugin
	 *
	 * Idempotent - safe to call multiple times.
	 *
	 * @return void
	 */
	public function init(): void {
		if ( $this->initialized ) {
			return;
		}

		// TODO: Initialize components when they exist
		// Example:
		// $admin = $this->container->make( Admin\Menu::class );
		// $admin->register_hooks();

		$this->initialized = true;
	}

	/**
	 * Get container instance
	 *
	 * @return Container
	 */
	public function get_container(): Container {
		return $this->container;
	}
}
