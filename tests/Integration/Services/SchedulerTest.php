<?php
/**
 * Integration Test for Scheduler
 *
 * Integration test with real WP-Cron.
 *
 * @package OpsHealthDashboard\Tests\Integration\Services
 */

namespace OpsHealthDashboard\Tests\Integration\Services;

use OpsHealthDashboard\Checks\DatabaseCheck;
use OpsHealthDashboard\Interfaces\AlertChannelInterface;
use OpsHealthDashboard\Interfaces\AlertManagerInterface;
use OpsHealthDashboard\Services\AlertManager;
use OpsHealthDashboard\Services\CheckRunner;
use OpsHealthDashboard\Services\Redaction;
use OpsHealthDashboard\Services\Scheduler;
use OpsHealthDashboard\Services\Storage;
use WP_UnitTestCase;

/**
 * Class SchedulerTest
 *
 * Integration test for Scheduler with real WordPress.
 */
class SchedulerTest extends WP_UnitTestCase {

	/**
	 * Scheduler for tests
	 *
	 * @var Scheduler
	 */
	private $scheduler;

	/**
	 * Storage for tests
	 *
	 * @var Storage
	 */
	private $storage;

	/**
	 * Setup for each test
	 */
	public function setUp(): void {
		parent::setUp();

		$this->storage = new Storage();
		$redaction     = new Redaction();
		$runner        = new CheckRunner( $this->storage, $redaction );
		global $wpdb;
		$runner->add_check( new DatabaseCheck( $wpdb, $redaction ) );

		$this->scheduler = new Scheduler( $runner );

		// Cleanup previous cron events.
		$this->scheduler->unschedule();
	}

	/**
	 * Cleanup after each test
	 */
	public function tearDown(): void {
		$this->scheduler->unschedule();
		parent::tearDown();
	}

	/**
	 * Tests that schedule() registers a real cron event
	 */
	public function test_schedule_registers_real_cron_event() {
		$this->assertFalse( $this->scheduler->is_scheduled(), 'Should not be scheduled initially' );

		$this->scheduler->schedule();

		$this->assertTrue( $this->scheduler->is_scheduled(), 'Should be scheduled after schedule()' );
	}

	/**
	 * Tests self-healing: register_hooks() reschedules when cron is missing
	 */
	public function test_register_hooks_reschedules_when_cron_missing() {
		$this->assertFalse( $this->scheduler->is_scheduled(), 'Should not be scheduled initially' );

		// Ensure throttle expired to activate self-healing.
		delete_transient( 'ops_health_cron_check' );
		$this->scheduler->register_hooks();

		$this->assertTrue( $this->scheduler->is_scheduled(), 'Should be scheduled after register_hooks() (self-healing)' );
	}

	/**
	 * Tests that unschedule() removes the cron event
	 */
	public function test_unschedule_removes_cron_event() {
		$this->scheduler->register_hooks();
		$this->scheduler->schedule();
		$this->assertTrue( $this->scheduler->is_scheduled() );

		$this->scheduler->unschedule();

		$this->assertFalse( $this->scheduler->is_scheduled(), 'Should not be scheduled after unschedule()' );
	}

	/**
	 * Tests that schedule() does not register duplicates
	 */
	public function test_schedule_does_not_register_duplicates() {
		$this->scheduler->register_hooks();
		$this->scheduler->schedule();
		$first_timestamp = wp_next_scheduled( 'ops_health_run_checks' );

		// Schedule again.
		$this->scheduler->schedule();
		$second_timestamp = wp_next_scheduled( 'ops_health_run_checks' );

		$this->assertEquals( $first_timestamp, $second_timestamp, 'Timestamps should be the same' );
	}

	/**
	 * Tests that the custom interval is registered
	 */
	public function test_custom_interval_is_registered() {
		$this->scheduler->register_hooks();

		$schedules = wp_get_schedules();

		$this->assertArrayHasKey( 'every_15_minutes', $schedules );
		$this->assertEquals( 15 * MINUTE_IN_SECONDS, $schedules['every_15_minutes']['interval'] );
	}

	/**
	 * Tests that run_checks() actually executes checks
	 */
	public function test_run_checks_executes_real_checks() {
		$this->storage->delete( 'latest_results' );

		$this->scheduler->run_checks();

		// Verify that results have been saved.
		$results = $this->storage->get( 'latest_results' );

		$this->assertIsArray( $results );
		$this->assertArrayHasKey( 'database', $results );

		// Cleanup.
		$this->storage->delete( 'latest_results' );
	}

	/**
	 * Tests that run_checks() with AlertManager processes alerts on state changes
	 */
	public function test_run_checks_with_alert_manager_processes_state_changes() {
		$redaction     = new Redaction();
		$alert_manager = new AlertManager( $this->storage, $redaction );

		$runner = new CheckRunner( $this->storage, $redaction );
		global $wpdb;
		$runner->add_check( new DatabaseCheck( $wpdb, $redaction ) );

		// Save fake "previous" results with critical status.
		$this->storage->set( 'latest_results', [
			'database' => [
				'status'  => 'critical',
				'message' => 'Simulated failure',
				'name'    => 'Database',
			],
		] );

		$scheduler = new Scheduler( $runner, $alert_manager );
		$scheduler->run_checks();

		// Current results should be saved (database ok in test env).
		$results = $this->storage->get( 'latest_results' );
		$this->assertIsArray( $results );
		$this->assertArrayHasKey( 'database', $results );

		// Verify that alert_log was created (recovery: critical→ok).
		$log = $this->storage->get( 'alert_log', [] );
		$this->assertIsArray( $log );

		// Cleanup.
		$this->storage->delete( 'latest_results' );
		$this->storage->delete( 'alert_log' );
	}

	/**
	 * Tests backward compatibility: Scheduler without AlertManager works as before
	 */
	public function test_backward_compatibility_without_alert_manager() {
		$this->storage->delete( 'latest_results' );

		// Scheduler created without AlertManager (as before M4).
		$this->scheduler->run_checks();

		$results = $this->storage->get( 'latest_results' );
		$this->assertIsArray( $results );
		$this->assertArrayHasKey( 'database', $results );

		// No alert_log should be created.
		$log = $this->storage->get( 'alert_log', [] );
		$this->assertEmpty( $log );

		// Cleanup.
		$this->storage->delete( 'latest_results' );
	}

	/**
	 * Tests that run_checks() continues when AlertManager throws exception
	 */
	public function test_run_checks_continues_when_alert_manager_throws() {
		$throwing_manager = new ThrowingAlertManager();

		$redaction = new Redaction();
		$runner    = new CheckRunner( $this->storage, $redaction );
		global $wpdb;
		$runner->add_check( new DatabaseCheck( $wpdb, $redaction ) );

		// Save previous results to trigger a state change.
		$this->storage->set(
			'latest_results',
			[
				'database' => [
					'status'  => 'critical',
					'message' => 'Simulated failure',
					'name'    => 'Database',
				],
			]
		);

		$scheduler = new Scheduler( $runner, $throwing_manager );
		$scheduler->run_checks();

		// Current results MUST be saved despite the exception.
		$results = $this->storage->get( 'latest_results' );
		$this->assertIsArray( $results );
		$this->assertArrayHasKey( 'database', $results );
		$this->assertEquals( 'ok', $results['database']['status'] );

		// Cleanup.
		$this->storage->delete( 'latest_results' );
	}

	/**
	 * Tests that run_checks() with AlertManager does not alert on first run with ok
	 */
	public function test_run_checks_no_alert_on_first_run_with_ok_status() {
		$redaction     = new Redaction();
		$alert_manager = new AlertManager( $this->storage, $redaction );

		$runner = new CheckRunner( $this->storage, $redaction );
		global $wpdb;
		$runner->add_check( new DatabaseCheck( $wpdb, $redaction ) );

		// No previous results (first run).
		$this->storage->delete( 'latest_results' );

		$scheduler = new Scheduler( $runner, $alert_manager );
		$scheduler->run_checks();

		// First run with ok: no alert.
		$log = $this->storage->get( 'alert_log', [] );
		$this->assertEmpty( $log, 'No alert expected on first run with ok status' );

		// Cleanup.
		$this->storage->delete( 'latest_results' );
	}
}

/**
 * AlertManager that throws exception to test cron resilience.
 */
class ThrowingAlertManager implements AlertManagerInterface {

	/**
	 * Not used in this test.
	 *
	 * @param AlertChannelInterface $channel Channel to add.
	 * @return void
	 */
	public function add_channel( AlertChannelInterface $channel ): void {
	}

	/**
	 * Always throws exception.
	 *
	 * @param array $current_results  Current results.
	 * @param array $previous_results Previous results.
	 * @return array Never reached.
	 * @throws \RuntimeException Always.
	 */
	public function process( array $current_results, array $previous_results ): array {
		throw new \RuntimeException( 'Simulated alert failure' );
	}
}
