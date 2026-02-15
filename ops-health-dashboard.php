<?php
/**
 * Plugin Name: Ops Health Dashboard
 * Plugin URI: https://github.com/mab056/ops-health-dashboard
 * Description: Production-grade WordPress health monitoring with automated checks and configurable alerts.
 * Version: 0.6.1
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Author: Mattia Bondrano
 * Author URI: https://www.mattiabondrano.dev
 * License: GPL-3.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: ops-health-dashboard
 * Domain Path: /languages
 *
 * @package OpsHealthDashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'OPS_HEALTH_DASHBOARD_VERSION', '0.6.1' );
define( 'OPS_HEALTH_DASHBOARD_FILE', __FILE__ );
define( 'OPS_HEALTH_DASHBOARD_PATH', plugin_dir_path( __FILE__ ) );
define( 'OPS_HEALTH_DASHBOARD_URL', plugin_dir_url( __FILE__ ) );

// Carica l'autoloader di Composer.
if ( ! file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	add_action(
		'admin_notices',
		function () {
			$msg = __(
				'Ops Health Dashboard: autoloader missing. Please run "composer install".',
				'ops-health-dashboard'
			);
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html( $msg )
			);
		}
	);
	return;
}
require_once __DIR__ . '/vendor/autoload.php';

// Carica la configurazione di bootstrap.
require_once __DIR__ . '/config/bootstrap.php';

/**
 * Hook di attivazione.
 */
register_activation_hook(
	__FILE__,
	function () {
		$activator = new \OpsHealthDashboard\Core\Activator();
		$activator->activate();
	}
);

/**
 * Hook di disattivazione.
 */
register_deactivation_hook(
	__FILE__,
	function () {
		$activator = new \OpsHealthDashboard\Core\Activator();
		$activator->deactivate();
	}
);

/**
 * Inizializza il plugin.
 *
 * Bootstrap crea il container, configura i binding, restituisce l'istanza del
 * plugin.
 */
add_action(
	'plugins_loaded',
	function () {
		$plugin = \OpsHealthDashboard\bootstrap();
		$plugin->init();
	},
	10
);
