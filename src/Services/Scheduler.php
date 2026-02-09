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

use OpsHealthDashboard\Interfaces\CheckRunnerInterface;

/**
 * Class Scheduler
 *
 * Servizio per schedulare l'esecuzione automatica dei check.
 */
class Scheduler {

	/**
	 * Nome dell'hook cron
	 *
	 * @var string
	 */
	const HOOK_NAME = 'ops_health_run_checks';

	/**
	 * Nome dell'intervallo cron
	 *
	 * @var string
	 */
	const INTERVAL = 'every_15_minutes';

	/**
	 * CheckRunner per eseguire i check
	 *
	 * @var CheckRunnerInterface
	 */
	private $runner;

	/**
	 * Constructor
	 *
	 * @param CheckRunnerInterface $runner CheckRunner per eseguire i check.
	 */
	public function __construct( CheckRunnerInterface $runner ) {
		$this->runner = $runner;
	}

	/**
	 * Registra gli hook WordPress
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( self::HOOK_NAME, [ $this, 'run_checks' ], 10 );
		add_filter( 'cron_schedules', [ $this, 'add_custom_cron_interval' ] );

		// Self-healing: ri-schedula se l'evento cron e' assente (solo in admin).
		if ( is_admin() ) {
			$this->schedule();
		}
	}

	/**
	 * Aggiunge l'intervallo custom 'every_15_minutes' ai cron schedules
	 *
	 * @param array $schedules Array di schedules esistenti.
	 * @return array Array con il nuovo schedule aggiunto.
	 */
	public function add_custom_cron_interval( array $schedules ): array {
		if ( ! isset( $schedules[ self::INTERVAL ] ) ) {
			$schedules[ self::INTERVAL ] = [
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
		wp_schedule_event( time(), self::INTERVAL, self::HOOK_NAME );
	}

	/**
	 * Rimuove l'evento cron schedulato
	 *
	 * @return void
	 */
	public function unschedule(): void {
		$timestamp = wp_next_scheduled( self::HOOK_NAME );

		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::HOOK_NAME );
		}
	}

	/**
	 * Verifica se l'evento è già schedulato
	 *
	 * @return bool True se schedulato.
	 */
	public function is_scheduled(): bool {
		return false !== wp_next_scheduled( self::HOOK_NAME );
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
