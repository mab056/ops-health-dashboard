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
use OpsHealthDashboard\Interfaces\CheckInterface;
use OpsHealthDashboard\Services\CheckRunner;
use OpsHealthDashboard\Services\Redaction;
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
		$redaction     = new Redaction();
		$this->runner  = new CheckRunner( $this->storage, $redaction );

		global $wpdb;
		$this->runner->add_check( new DatabaseCheck( $wpdb, $redaction ) );

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
		$redaction  = new Redaction();
		$new_runner = new CheckRunner( $this->storage, $redaction );
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

	/**
	 * Testa che run_all() salta i check disabilitati
	 */
	public function test_run_all_skips_disabled_check() {
		$this->runner->add_check( new DisabledCheck() );

		$results = $this->runner->run_all();

		$this->assertArrayHasKey( 'database', $results );
		$this->assertArrayNotHasKey( 'disabled', $results );
	}

	/**
	 * Testa che run_all() cattura eccezioni dai check
	 */
	public function test_run_all_catches_check_exception() {
		$storage   = new Storage();
		$redaction = new Redaction();
		$runner    = new CheckRunner( $storage, $redaction );
		$runner->add_check( new FailingCheck() );

		$results = $runner->run_all();

		$this->assertArrayHasKey( 'failing', $results );
		$this->assertEquals( 'critical', $results['failing']['status'] );
		$this->assertStringContainsString( 'Check exception', $results['failing']['message'] );
	}

	/**
	 * Testa che run_all() redige i messaggi di eccezione
	 */
	public function test_run_all_redacts_exception_message() {
		$storage   = new Storage();
		$redaction = new Redaction( ABSPATH, WP_CONTENT_DIR );
		$runner    = new CheckRunner( $storage, $redaction );
		$runner->add_check( new FailingCheck() );

		$results = $runner->run_all();

		$this->assertArrayHasKey( 'failing', $results );
		$this->assertStringNotContainsString( ABSPATH, $results['failing']['message'] );
	}

	/**
	 * Testa che clear_results() rimuove i dati dallo storage
	 */
	public function test_clear_results_removes_stored_data() {
		$this->runner->run_all();

		// Verifica che i risultati esistano.
		$this->assertNotEmpty( $this->runner->get_latest_results() );

		$this->runner->clear_results();

		$this->assertEmpty( $this->runner->get_latest_results() );
	}
}

/**
 * Check disabilitato per test
 *
 * Implementa CheckInterface con is_enabled() = false.
 */
class DisabledCheck implements CheckInterface {

	/**
	 * Esegue il check
	 *
	 * @return array Risultati.
	 */
	public function run(): array {
		return [
			'status'   => 'ok',
			'message'  => 'Should not run',
			'details'  => [],
			'duration' => 0,
		];
	}

	/**
	 * ID del check
	 *
	 * @return string ID.
	 */
	public function get_id(): string {
		return 'disabled';
	}

	/**
	 * Nome del check
	 *
	 * @return string Nome.
	 */
	public function get_name(): string {
		return 'Disabled Check';
	}

	/**
	 * Check disabilitato
	 *
	 * @return bool False.
	 */
	public function is_enabled(): bool {
		return false;
	}
}

/**
 * Check che lancia eccezione per test
 *
 * Implementa CheckInterface con run() che lancia RuntimeException.
 */
class FailingCheck implements CheckInterface {

	/**
	 * Esegue il check (lancia eccezione)
	 *
	 * @return array Mai raggiunto.
	 * @throws \RuntimeException Sempre.
	 */
	public function run(): array {
		throw new \RuntimeException( 'Sensitive error at ' . ABSPATH . 'wp-config.php' );
	}

	/**
	 * ID del check
	 *
	 * @return string ID.
	 */
	public function get_id(): string {
		return 'failing';
	}

	/**
	 * Nome del check
	 *
	 * @return string Nome.
	 */
	public function get_name(): string {
		return 'Failing Check';
	}

	/**
	 * Check abilitato
	 *
	 * @return bool True.
	 */
	public function is_enabled(): bool {
		return true;
	}
}
