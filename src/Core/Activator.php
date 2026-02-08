<?php
/**
 * Gestore Attivazione/Disattivazione Plugin
 *
 * @package OpsHealthDashboard\Core
 */

namespace OpsHealthDashboard\Core;

/**
 * Class Activator
 *
 * Gestisce l'attivazione e la disattivazione del plugin.
 * NO singleton, NO metodi static, NO modificatore final.
 */
class Activator {

	/**
	 * Gestore attivazione plugin
	 *
	 * Configura il plugin alla prima attivazione.
	 *
	 * @return void
	 */
	public function activate(): void {
		// Memorizza il timestamp di attivazione.
		if ( ! get_option( 'ops_health_activated_at' ) ) {
			update_option( 'ops_health_activated_at', time() );
		}

		// Memorizza la versione del plugin.
		if ( defined( 'OPS_HEALTH_DASHBOARD_VERSION' ) ) {
			update_option( 'ops_health_version', OPS_HEALTH_DASHBOARD_VERSION );
		}

		// Svuota le rewrite rules se necessario.
		flush_rewrite_rules();
	}

	/**
	 * Gestore disattivazione plugin
	 *
	 * Pulizia alla disattivazione (ma preserva i dati).
	 *
	 * @return void
	 */
	public function deactivate(): void {
		// Cancella gli hook schedulati.
		wp_clear_scheduled_hook( 'ops_health_scheduled_check' );

		// Svuota le rewrite rules.
		flush_rewrite_rules();
	}
}
