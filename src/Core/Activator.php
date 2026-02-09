<?php
/**
 * Gestore Attivazione/Disattivazione Plugin
 *
 * @package OpsHealthDashboard\Core
 */

namespace OpsHealthDashboard\Core;

use OpsHealthDashboard\Services\Scheduler;

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
		if ( ! get_option( 'ops_health_activated_at' ) ) {
			update_option( 'ops_health_activated_at', time() );
		}

		if ( defined( 'OPS_HEALTH_DASHBOARD_VERSION' ) ) {
			update_option( 'ops_health_version', OPS_HEALTH_DASHBOARD_VERSION );
		}

		// Registra l'intervallo custom e schedula il cron.
		add_filter(
			'cron_schedules',
			function ( $schedules ) {
				if ( ! isset( $schedules[ Scheduler::INTERVAL ] ) ) {
					$schedules[ Scheduler::INTERVAL ] = [
						'interval' => 15 * MINUTE_IN_SECONDS,
						'display'  => __( 'Every 15 minutes', 'ops-health-dashboard' ),
					];
				}
				return $schedules;
			}
		);

		if ( ! wp_next_scheduled( Scheduler::HOOK_NAME ) ) {
			wp_schedule_event( time(), Scheduler::INTERVAL, Scheduler::HOOK_NAME );
		}
	}

	/**
	 * Gestore disattivazione plugin
	 *
	 * Pulizia alla disattivazione (ma preserva i dati).
	 *
	 * @return void
	 */
	public function deactivate(): void {
		wp_clear_scheduled_hook( Scheduler::HOOK_NAME );
	}
}
