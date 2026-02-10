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
		$redaction     = new Redaction();
		$runner        = new CheckRunner( $this->storage, $redaction );
		global $wpdb;
		$runner->add_check( new DatabaseCheck( $wpdb, $redaction ) );

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
		$this->assertFalse( $this->scheduler->is_scheduled(), 'Should not be scheduled initially' );

		$this->scheduler->schedule();

		$this->assertTrue( $this->scheduler->is_scheduled(), 'Should be scheduled after schedule()' );
	}

	/**
	 * Testa self-healing: register_hooks() ri-schedula se cron mancante
	 */
	public function test_register_hooks_reschedules_when_cron_missing() {
		$this->assertFalse( $this->scheduler->is_scheduled(), 'Should not be scheduled initially' );

		// Assicura throttle scaduto per attivare self-healing.
		delete_transient( 'ops_health_cron_check' );
		$this->scheduler->register_hooks();

		$this->assertTrue( $this->scheduler->is_scheduled(), 'Should be scheduled after register_hooks() (self-healing)' );
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

	/**
	 * Testa che run_checks() con AlertManager processa alert su cambiamenti di stato
	 */
	public function test_run_checks_with_alert_manager_processes_state_changes() {
		$redaction     = new Redaction();
		$alert_manager = new AlertManager( $this->storage, $redaction );

		$runner = new CheckRunner( $this->storage, $redaction );
		global $wpdb;
		$runner->add_check( new DatabaseCheck( $wpdb, $redaction ) );

		// Salva risultati "precedenti" fittizi con stato critical.
		$this->storage->set( 'latest_results', [
			'database' => [
				'status'  => 'critical',
				'message' => 'Simulated failure',
				'name'    => 'Database',
			],
		] );

		$scheduler = new Scheduler( $runner, $alert_manager );
		$scheduler->run_checks();

		// I risultati correnti dovrebbero essere salvati (database ok in test env).
		$results = $this->storage->get( 'latest_results' );
		$this->assertIsArray( $results );
		$this->assertArrayHasKey( 'database', $results );

		// Verifica che alert_log è stato creato (recovery: critical→ok).
		$log = $this->storage->get( 'alert_log', [] );
		$this->assertIsArray( $log );

		// Cleanup.
		$this->storage->delete( 'latest_results' );
		$this->storage->delete( 'alert_log' );
	}

	/**
	 * Testa backward compatibility: Scheduler senza AlertManager funziona come prima
	 */
	public function test_backward_compatibility_without_alert_manager() {
		$this->storage->delete( 'latest_results' );

		// Scheduler creato senza AlertManager (come prima di M4).
		$this->scheduler->run_checks();

		$results = $this->storage->get( 'latest_results' );
		$this->assertIsArray( $results );
		$this->assertArrayHasKey( 'database', $results );

		// Nessun alert_log dovrebbe essere creato.
		$log = $this->storage->get( 'alert_log', [] );
		$this->assertEmpty( $log );

		// Cleanup.
		$this->storage->delete( 'latest_results' );
	}

	/**
	 * Testa che run_checks() continua se AlertManager lancia eccezione
	 */
	public function test_run_checks_continues_when_alert_manager_throws() {
		$throwing_manager = new ThrowingAlertManager();

		$redaction = new Redaction();
		$runner    = new CheckRunner( $this->storage, $redaction );
		global $wpdb;
		$runner->add_check( new DatabaseCheck( $wpdb, $redaction ) );

		// Salva risultati precedenti per triggerare un cambiamento di stato.
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

		// I risultati correnti DEVONO essere salvati nonostante l'eccezione.
		$results = $this->storage->get( 'latest_results' );
		$this->assertIsArray( $results );
		$this->assertArrayHasKey( 'database', $results );
		$this->assertEquals( 'ok', $results['database']['status'] );

		// Cleanup.
		$this->storage->delete( 'latest_results' );
	}

	/**
	 * Testa che run_checks() con AlertManager non alerta al primo avvio con ok
	 */
	public function test_run_checks_no_alert_on_first_run_with_ok_status() {
		$redaction     = new Redaction();
		$alert_manager = new AlertManager( $this->storage, $redaction );

		$runner = new CheckRunner( $this->storage, $redaction );
		global $wpdb;
		$runner->add_check( new DatabaseCheck( $wpdb, $redaction ) );

		// Nessun risultato precedente (primo avvio).
		$this->storage->delete( 'latest_results' );

		$scheduler = new Scheduler( $runner, $alert_manager );
		$scheduler->run_checks();

		// Primo avvio con ok: nessun alert.
		$log = $this->storage->get( 'alert_log', [] );
		$this->assertEmpty( $log, 'No alert expected on first run with ok status' );

		// Cleanup.
		$this->storage->delete( 'latest_results' );
	}
}

/**
 * AlertManager che lancia eccezione per testare resilienza del cron.
 */
class ThrowingAlertManager implements AlertManagerInterface {

	/**
	 * Non usato in questo test.
	 *
	 * @param AlertChannelInterface $channel Canale da aggiungere.
	 * @return void
	 */
	public function add_channel( AlertChannelInterface $channel ): void {
	}

	/**
	 * Lancia sempre eccezione.
	 *
	 * @param array $current_results  Risultati correnti.
	 * @param array $previous_results Risultati precedenti.
	 * @return array Mai raggiunto.
	 * @throws \RuntimeException Sempre.
	 */
	public function process( array $current_results, array $previous_results ): array {
		throw new \RuntimeException( 'Simulated alert failure' );
	}
}
