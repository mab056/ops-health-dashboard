<?php
/**
 * Scheduler Service
 *
 * Gestisce la schedulazione automatica dei check tramite WP-Cron.
 * Registra un evento ricorrente per eseguire i check ogni 15 minuti.
 *
 * @package OpsHealthDashboard\Services
 */

namespace OpsHealthDashboard\Services;

/**
 * Class Scheduler
 *
 * Servizio per schedulare l'esecuzione automatica dei check.
 * NO singleton, NO static methods, NO final.
 */
class Scheduler {

	/**
	 * Nome dell'hook cron
	 *
	 * @var string
	 */
	private $hook_name = 'ops_health_run_checks';

	/**
	 * Intervallo cron (15 minuti)
	 *
	 * @var string
	 */
	private $interval = 'every_15_minutes';

	/**
	 * CheckRunner per eseguire i check
	 *
	 * @var CheckRunner
	 */
	private $runner;

	/**
	 * Constructor
	 *
	 * @param CheckRunner $runner CheckRunner per eseguire i check.
	 */
	public function __construct( CheckRunner $runner ) {
		$this->runner = $runner;
	}

	/**
	 * Registra gli hook WordPress
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( $this->hook_name, [ $this, 'run_checks' ], 10 );
		add_filter( 'cron_schedules', [ $this, 'add_custom_cron_interval' ] );
	}

	/**
	 * Aggiunge l'intervallo custom 'every_15_minutes' ai cron schedules
	 *
	 * @param array $schedules Array di schedules esistenti.
	 * @return array Array con il nuovo schedule aggiunto.
	 */
	public function add_custom_cron_interval( array $schedules ): array {
		if ( ! isset( $schedules['every_15_minutes'] ) ) {
			$schedules['every_15_minutes'] = [
				'interval' => 15 * MINUTE_IN_SECONDS,
				'display'  => __( 'Every 15 minutes', 'ops-health-dashboard' ),
			];
		}

		return $schedules;
	}

	/**
	 * Schedula l'evento cron se non esiste già
	 *
	 * @return void
	 */
	public function schedule(): void {
		// Verifica se è già schedulato.
		if ( $this->is_scheduled() ) {
			return;
		}

		// Schedula l'evento ricorrente.
		wp_schedule_event( time(), $this->interval, $this->hook_name );
	}

	/**
	 * Rimuove l'evento cron schedulato
	 *
	 * @return void
	 */
	public function unschedule(): void {
		$timestamp = wp_next_scheduled( $this->hook_name );

		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, $this->hook_name );
		}
	}

	/**
	 * Verifica se l'evento è già schedulato
	 *
	 * @return bool True se schedulato.
	 */
	public function is_scheduled(): bool {
		return false !== wp_next_scheduled( $this->hook_name );
	}

	/**
	 * Esegue i check (chiamato dal cron hook)
	 *
	 * @return void
	 */
	public function run_checks(): void {
		$this->runner->run_all();
	}
}
