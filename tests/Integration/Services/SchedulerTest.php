<?php
/**
 * Integration Test per Scheduler
 *
 * Test di integrazione con WP-Cron reale.
 *
 * @package OpsHealthDashboard\Tests\Integration\Services
 */

namespace OpsHealthDashboard\Tests\Integration\Services;

use OpsHealthDashboard\Checks\DatabaseCheck;
use OpsHealthDashboard\Services\CheckRunner;
use OpsHealthDashboard\Services\Scheduler;
use OpsHealthDashboard\Services\Storage;
use WP_UnitTestCase;

/**
 * Class SchedulerTest
 *
 * Integration test per Scheduler con WordPress reale.
 */
class SchedulerTest extends WP_UnitTestCase {

	/**
	 * Scheduler per i test
	 *
	 * @var Scheduler
	 */
	private $scheduler;

	/**
	 * Storage per i test
	 *
	 * @var Storage
	 */
	private $storage;

	/**
	 * Setup per ogni test
	 */
	public function setUp(): void {
		parent::setUp();

		$this->storage = new Storage();
		$runner        = new CheckRunner( $this->storage );
		global $wpdb;
		$runner->add_check( new DatabaseCheck( $wpdb ) );

		$this->scheduler = new Scheduler( $runner );

		// Cleanup cron precedenti.
		$this->scheduler->unschedule();
	}

	/**
	 * Cleanup dopo ogni test
	 */
	public function tearDown(): void {
		$this->scheduler->unschedule();
		parent::tearDown();
	}

	/**
	 * Testa che schedule() registra un evento cron reale
	 */
	public function test_schedule_registers_real_cron_event() {
		$this->scheduler->register_hooks();
		$this->assertFalse( $this->scheduler->is_scheduled(), 'Should not be scheduled initially' );

		$this->scheduler->schedule();

		$this->assertTrue( $this->scheduler->is_scheduled(), 'Should be scheduled after schedule()' );
	}

	/**
	 * Testa che unschedule() rimuove l'evento cron
	 */
	public function test_unschedule_removes_cron_event() {
		$this->scheduler->register_hooks();
		$this->scheduler->schedule();
		$this->assertTrue( $this->scheduler->is_scheduled() );

		$this->scheduler->unschedule();

		$this->assertFalse( $this->scheduler->is_scheduled(), 'Should not be scheduled after unschedule()' );
	}

	/**
	 * Testa che schedule() non registra duplicati
	 */
	public function test_schedule_does_not_register_duplicates() {
		$this->scheduler->register_hooks();
		$this->scheduler->schedule();
		$first_timestamp = wp_next_scheduled( 'ops_health_run_checks' );

		// Schedula di nuovo.
		$this->scheduler->schedule();
		$second_timestamp = wp_next_scheduled( 'ops_health_run_checks' );

		$this->assertEquals( $first_timestamp, $second_timestamp, 'Timestamps should be the same' );
	}

	/**
	 * Testa che il custom interval è registrato
	 */
	public function test_custom_interval_is_registered() {
		$this->scheduler->register_hooks();

		$schedules = wp_get_schedules();

		$this->assertArrayHasKey( 'every_15_minutes', $schedules );
		$this->assertEquals( 15 * MINUTE_IN_SECONDS, $schedules['every_15_minutes']['interval'] );
	}

	/**
	 * Testa che run_checks() esegue i check realmente
	 */
	public function test_run_checks_executes_real_checks() {
		$this->storage->delete( 'latest_results' );

		$this->scheduler->run_checks();

		// Verifica che i risultati sono stati salvati.
		$results = $this->storage->get( 'latest_results' );

		$this->assertIsArray( $results );
		$this->assertArrayHasKey( 'database', $results );

		// Cleanup.
		$this->storage->delete( 'latest_results' );
	}
}
