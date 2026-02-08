<?php
/**
 * Integration Test per CheckRunner
 *
 * Test di integrazione con check e storage reali.
 *
 * @package OpsHealthDashboard\Tests\Integration\Services
 */

namespace OpsHealthDashboard\Tests\Integration\Services;

use OpsHealthDashboard\Checks\DatabaseCheck;
use OpsHealthDashboard\Services\CheckRunner;
use OpsHealthDashboard\Services\Storage;
use WP_UnitTestCase;

/**
 * Class CheckRunnerTest
 *
 * Integration test per CheckRunner con WordPress reale.
 */
class CheckRunnerTest extends WP_UnitTestCase {

	/**
	 * Storage per i test
	 *
	 * @var Storage
	 */
	private $storage;

	/**
	 * CheckRunner con DatabaseCheck già aggiunto
	 *
	 * @var CheckRunner
	 */
	private $runner;

	/**
	 * Setup per ogni test
	 */
	public function setUp(): void {
		parent::setUp();
		$this->storage = new Storage();
		$this->runner  = new CheckRunner( $this->storage );

		global $wpdb;
		$this->runner->add_check( new DatabaseCheck( $wpdb ) );

		// Pulisci i risultati precedenti.
		$this->storage->delete( 'latest_results' );
	}

	/**
	 * Cleanup dopo ogni test
	 */
	public function tearDown(): void {
		$this->storage->delete( 'latest_results' );
		parent::tearDown();
	}

	/**
	 * Testa che CheckRunner esegue check reali
	 */
	public function test_check_runner_executes_real_checks() {
		$results = $this->runner->run_all();

		$this->assertIsArray( $results );
		$this->assertArrayHasKey( 'database', $results );
		$this->assertArrayHasKey( 'status', $results['database'] );
		$this->assertEquals( 'ok', $results['database']['status'] );
	}

	/**
	 * Testa che CheckRunner salva i risultati nello storage
	 */
	public function test_check_runner_saves_results_to_storage() {
		$this->runner->run_all();

		// Recupera i risultati dallo storage.
		$stored_results = $this->storage->get( 'latest_results' );

		$this->assertIsArray( $stored_results );
		$this->assertArrayHasKey( 'database', $stored_results );
	}

	/**
	 * Testa che get_latest_results() recupera i risultati salvati
	 */
	public function test_get_latest_results_retrieves_saved_results() {
		$this->runner->run_all();

		// Crea un nuovo runner e recupera i risultati.
		$new_runner = new CheckRunner( $this->storage );
		$results    = $new_runner->get_latest_results();

		$this->assertIsArray( $results );
		$this->assertArrayHasKey( 'database', $results );
	}

	/**
	 * Testa che CheckRunner gestisce multiple esecuzioni
	 */
	public function test_check_runner_handles_multiple_executions() {
		// Prima esecuzione.
		$results1 = $this->runner->run_all();
		$this->assertArrayHasKey( 'database', $results1 );

		// Seconda esecuzione (dovrebbe sovrascrivere).
		$results2 = $this->runner->run_all();
		$this->assertArrayHasKey( 'database', $results2 );

		// I risultati salvati dovrebbero essere quelli della seconda esecuzione.
		$stored = $this->runner->get_latest_results();
		$this->assertEquals( $results2, $stored );
	}
}
