<?php
/**
 * Scheduler Service
 *
 * Manages automatic check scheduling via WP-Cron.
 * Registers a recurring event to run checks every 15 minutes.
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
 * Service for scheduling automatic check execution.
 */
class Scheduler {

	/**
	 * Cron hook name
	 *
	 * @var string
	 */
	const HOOK_NAME = 'ops_health_run_checks';

	/**
	 * Cron interval name
	 *
	 * @var string
	 */
	const INTERVAL = 'every_15_minutes';

	/**
	 * CheckRunner for running checks
	 *
	 * @var CheckRunnerInterface
	 */
	private $runner;

	/**
	 * Alert manager for state change notifications
	 *
	 * @var AlertManagerInterface|null
	 */
	private $alert_manager;

	/**
	 * Constructor
	 *
	 * @param CheckRunnerInterface       $runner        CheckRunner for running checks.
	 * @param AlertManagerInterface|null $alert_manager Optional alert manager.
	 */
	public function __construct( CheckRunnerInterface $runner, AlertManagerInterface $alert_manager = null ) {
		$this->runner        = $runner;
		$this->alert_manager = $alert_manager;
	}

	/**
	 * Registers WordPress hooks
	 *
	 * Self-healing: reschedules if the cron event is missing.
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
	 * Adds the custom 'every_15_minutes' interval to cron schedules
	 *
	 * @param array $schedules Array of existing schedules.
	 * @return array Array with the new schedule added.
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
	 * Schedules the cron event if it does not already exist
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
	 * Removes the scheduled cron event
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
	 * Checks if the event is already scheduled
	 *
	 * @return bool True if scheduled.
	 */
	public function is_scheduled(): bool {
		return false !== wp_next_scheduled( self::HOOK_NAME );
	}

	/**
	 * Runs checks and processes alerts (called by the cron hook)
	 *
	 * When alert_manager is present, reads previous results BEFORE
	 * running run_all(), then calls process() to detect changes.
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
