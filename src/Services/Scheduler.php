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

if ( ! defined( 'ABSPATH' ) ) {
	// phpcs:ignore Squiz.Commenting.InlineComment.InvalidEndChar
	// @codeCoverageIgnoreStart
	exit;
	// phpcs:ignore Squiz.Commenting.InlineComment.InvalidEndChar
	// @codeCoverageIgnoreEnd
}

use OpsHealthDashboard\Interfaces\AlertManagerInterface;
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
	 * Alert manager per notifiche su cambiamenti di stato
	 *
	 * @var AlertManagerInterface|null
	 */
	private $alert_manager;

	/**
	 * Constructor
	 *
	 * @param CheckRunnerInterface       $runner        CheckRunner per eseguire i check.
	 * @param AlertManagerInterface|null $alert_manager Alert manager opzionale.
	 */
	public function __construct( CheckRunnerInterface $runner, AlertManagerInterface $alert_manager = null ) {
		$this->runner        = $runner;
		$this->alert_manager = $alert_manager;
	}

	/**
	 * Registra gli hook WordPress
	 *
	 * Self-healing: ri-schedula se l'evento cron è assente.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( self::HOOK_NAME, [ $this, 'run_checks' ], 10 );
		add_filter( 'cron_schedules', [ $this, 'add_custom_cron_interval' ] );

		if ( false === get_transient( 'ops_health_cron_check' ) ) {
			$this->schedule();
			set_transient( 'ops_health_cron_check', 1, HOUR_IN_SECONDS );
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
		if ( $this->is_scheduled() ) {
			return;
		}

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
	 * Esegue i check e processa alert (chiamato dal cron hook)
	 *
	 * Quando alert_manager è presente, legge i risultati precedenti PRIMA
	 * di eseguire run_all(), poi chiama process() per rilevare cambiamenti.
	 *
	 * @return void
	 */
	public function run_checks(): void {
		$previous = [];

		if ( null !== $this->alert_manager ) {
			$previous = $this->runner->get_latest_results();
		}

		$current = $this->runner->run_all();

		if ( null !== $this->alert_manager ) {
			try {
				$this->alert_manager->process( $current, $previous );
				// phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			} catch ( \Throwable $e ) { // Alert failure must not break cron.
			}
		}
	}
}
