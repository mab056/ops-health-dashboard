<?php
/**
 * Plugin Name: Ops Health Dashboard
 * Plugin URI: https://github.com/mab056/ops-health-dashboard
 * Description: Monitoraggio salute WordPress production-grade con controlli automatici e alert configurabili
 * Version: 0.0.0
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Author: Ops Team
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

// Definisce le costanti del plugin.
define( 'OPS_HEALTH_DASHBOARD_VERSION', '0.0.0' );
define( 'OPS_HEALTH_DASHBOARD_FILE', __FILE__ );
define( 'OPS_HEALTH_DASHBOARD_PATH', plugin_dir_path( __FILE__ ) );
define( 'OPS_HEALTH_DASHBOARD_URL', plugin_dir_url( __FILE__ ) );

// Carica l'autoloader di Composer (se disponibile).
if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
}

// Carica la configurazione di bootstrap.
require_once __DIR__ . '/config/bootstrap.php';

/**
 * Hook di attivazione
 *
 * NO metodo static, crea un'istanza di Activator.
 */
register_activation_hook(
	__FILE__,
	function () {
		$activator = new \OpsHealthDashboard\Core\Activator();
		$activator->activate();
	}
);

/**
 * Hook di disattivazione
 *
 * NO metodo static, crea un'istanza di Activator.
 */
register_deactivation_hook(
	__FILE__,
	function () {
		$activator = new \OpsHealthDashboard\Core\Activator();
		$activator->deactivate();
	}
);

/**
 * Inizializza il plugin
 *
 * Bootstrap crea il container, configura i binding, restituisce l'istanza del
 * plugin. NO singleton, nuova istanza gestita dal sistema di hook di WordPress.
 */
add_action(
	'plugins_loaded',
	function () {
		$plugin = \OpsHealthDashboard\bootstrap();
		$plugin->init();
	},
	10
);
