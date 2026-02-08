<?php
/**
 * Plugin Name: Ops Health Dashboard
 * Plugin URI: https://github.com/ops-team/ops-health-dashboard
 * Description: Production-grade WordPress health monitoring with automated checks and configurable alerts
 * Version: 1.0.0
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Author: Ops Team
 * Author URI: https://example.com
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ops-health-dashboard
 * Domain Path: /languages
 *
 * @package OpsHealthDashboard
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants.
define( 'OPS_HEALTH_DASHBOARD_VERSION', '1.0.0' );
define( 'OPS_HEALTH_DASHBOARD_FILE', __FILE__ );
define( 'OPS_HEALTH_DASHBOARD_PATH', plugin_dir_path( __FILE__ ) );
define( 'OPS_HEALTH_DASHBOARD_URL', plugin_dir_url( __FILE__ ) );

// Load Composer autoloader (if available).
if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
}

// Load bootstrap configuration.
require_once __DIR__ . '/config/bootstrap.php';

/**
 * Activation hook
 *
 * NO static method - creates Activator instance
 */
register_activation_hook(
	__FILE__,
	function () {
		$activator = new \OpsHealthDashboard\Core\Activator();
		$activator->activate();
	}
);

/**
 * Deactivation hook
 *
 * NO static method - creates Activator instance
 */
register_deactivation_hook(
	__FILE__,
	function () {
		$activator = new \OpsHealthDashboard\Core\Activator();
		$activator->deactivate();
	}
);

/**
 * Initialize plugin
 *
 * Bootstrap creates container, configures bindings, returns plugin instance.
 * NO singleton - fresh instance managed by WordPress hook system.
 */
add_action(
	'plugins_loaded',
	function () {
		$plugin = \OpsHealthDashboard\bootstrap();
		$plugin->init();
	},
	10
);
