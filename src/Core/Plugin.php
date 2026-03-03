<?php
/**
 * Main Plugin Class
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
 * Class Plugin
 *
 * Main plugin orchestrator with dependency injection.
 */
class Plugin {

	/**
	 * Container for dependency injection
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
	 * Receives the container via dependency injection.
	 *
	 * @param Container $container Container DI.
	 */
	public function __construct( Container $container ) {
		$this->container = $container;
	}

	/**
	 * Initializes the plugin
	 *
	 * Idempotent - safe to call multiple times.
	 *
	 * @return void
	 */
	public function init(): void {
		if ( $this->initialized ) {
			return;
		}

		// Registers the admin menu.
		$menu = $this->container->make( \OpsHealthDashboard\Admin\Menu::class );
		$menu->register_hooks();

		// Registers the dashboard widget.
		$widget = $this->container->make( \OpsHealthDashboard\Admin\DashboardWidget::class );
		$widget->register_hooks();

		// Registers health screen styles.
		$health_screen = $this->container->make( \OpsHealthDashboard\Admin\HealthScreen::class );
		$health_screen->register_hooks();

		// Registers alert settings page assets.
		$alert_settings = $this->container->make( \OpsHealthDashboard\Admin\AlertSettings::class );
		$alert_settings->register_hooks();

		// Registers the WP-Cron scheduler.
		$scheduler = $this->container->make( \OpsHealthDashboard\Services\Scheduler::class );
		$scheduler->register_hooks();

		$this->initialized = true;
	}

	/**
	 * Gets the container instance
	 *
	 * @return Container
	 */
	public function get_container(): Container {
		return $this->container;
	}
}
