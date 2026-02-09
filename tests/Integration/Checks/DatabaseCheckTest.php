<?php
/**
 * Integration Test per DatabaseCheck
 *
 * Test di integrazione con WordPress $wpdb reale.
 *
 * @package OpsHealthDashboard\Tests\Integration\Checks
 */

namespace OpsHealthDashboard\Tests\Integration\Checks;

use OpsHealthDashboard\Checks\DatabaseCheck;
use OpsHealthDashboard\Services\Redaction;
use WP_UnitTestCase;

/**
 * Class DatabaseCheckTest
 *
 * Integration test per DatabaseCheck con WordPress reale.
 */
class DatabaseCheckTest extends WP_UnitTestCase {

	/**
	 * Il check da testare
	 *
	 * @var DatabaseCheck
	 */
	private $check;

	/**
	 * Setup per ogni test - centralizza $wpdb injection
	 */
	public function setUp(): void {
		parent::setUp();
		global $wpdb;
		$redaction   = new Redaction();
		$this->check = new DatabaseCheck( $wpdb, $redaction );
	}

	/**
	 * Testa che DatabaseCheck esegue correttamente con WordPress reale
	 */
	public function test_database_check_runs_successfully() {
		$result = $this->check->run();

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'status', $result );
		$this->assertArrayHasKey( 'message', $result );
		$this->assertArrayHasKey( 'details', $result );
		$this->assertArrayHasKey( 'duration', $result );

		// Con WordPress test suite funzionante, dovrebbe essere 'ok'.
		$this->assertEquals( 'ok', $result['status'], 'Database should be healthy' );
	}

	/**
	 * Testa che DatabaseCheck misura la durata correttamente
	 */
	public function test_database_check_measures_duration() {
		$result = $this->check->run();

		$this->assertIsFloat( $result['duration'] );
		$this->assertGreaterThan( 0, $result['duration'], 'Duration should be positive' );
		$this->assertLessThan( 1, $result['duration'], 'Duration should be < 1s for simple query' );
	}

	/**
	 * Testa che DatabaseCheck include query_time nei dettagli ma non info sensibili
	 */
	public function test_database_check_includes_safe_details() {
		$result = $this->check->run();

		$this->assertArrayHasKey( 'query_time', $result['details'] );
		$this->assertStringContainsString( 'ms', $result['details']['query_time'] );

		// Non deve esporre informazioni sensibili.
		$this->assertArrayNotHasKey( 'db_host', $result['details'] );
		$this->assertArrayNotHasKey( 'db_name', $result['details'] );
	}

	/**
	 * Testa che get_id(), get_name() e is_enabled() funzionano correttamente
	 */
	public function test_check_interface_accessors() {
		$this->assertEquals( 'database', $this->check->get_id() );
		$this->assertNotEmpty( $this->check->get_name() );
		$this->assertIsString( $this->check->get_name() );
		$this->assertTrue( $this->check->is_enabled() );
	}

	/**
	 * Testa che una query veloce restituisce status ok con query_time formattato
	 */
	public function test_database_check_returns_ok_for_fast_query() {
		$result = $this->check->run();

		$this->assertEquals( 'ok', $result['status'] );
		$this->assertArrayHasKey( 'query_time', $result['details'] );
		$this->assertStringContainsString( 'ms', $result['details']['query_time'] );
		$this->assertLessThan( 0.5, $result['duration'] );
	}

	/**
	 * Testa che DatabaseCheck è consistente su multiple esecuzioni
	 */
	public function test_database_check_is_consistent() {
		// Esegui il check 3 volte.
		$results = array();
		for ( $i = 0; $i < 3; $i++ ) {
			$results[] = $this->check->run();
		}

		// Tutti dovrebbero avere lo stesso status.
		$statuses = array_column( $results, 'status' );
		$this->assertCount( 1, array_unique( $statuses ), 'Status should be consistent' );
	}

	/**
	 * Testa che DatabaseCheck restituisce critical quando la query fallisce
	 */
	public function test_database_check_returns_critical_on_query_error() {
		$fake_wpdb = new FailingWpdb();
		$redaction = new Redaction( ABSPATH, WP_CONTENT_DIR );
		$check     = new DatabaseCheck( $fake_wpdb, $redaction );
		$result    = $check->run();

		$this->assertEquals( 'critical', $result['status'] );
		$this->assertStringContainsString( 'failed', strtolower( $result['message'] ) );
		$this->assertArrayHasKey( 'error', $result['details'] );

		// Il messaggio di errore deve essere redatto (non contiene ABSPATH).
		$this->assertStringNotContainsString( ABSPATH, $result['details']['error'] );
	}

	/**
	 * Testa che DatabaseCheck restituisce critical con errore sconosciuto
	 *
	 * Copre il branch dove query ritorna false ma last_error è vuoto.
	 */
	public function test_database_check_returns_critical_with_unknown_error() {
		$fake_wpdb = new EmptyErrorWpdb();
		$redaction = new Redaction();
		$check     = new DatabaseCheck( $fake_wpdb, $redaction );
		$result    = $check->run();

		$this->assertEquals( 'critical', $result['status'] );
		$this->assertStringContainsString( 'Unknown error', $result['details']['error'] );
	}

	/**
	 * Testa che DatabaseCheck restituisce warning per query lenta (> 0.5s)
	 */
	public function test_database_check_returns_warning_on_slow_query() {
		$slow_wpdb = new SlowWpdb();
		$redaction = new Redaction();
		$check     = new DatabaseCheck( $slow_wpdb, $redaction );
		$result    = $check->run();

		$this->assertEquals( 'warning', $result['status'] );
		$this->assertGreaterThan( 0.5, $result['duration'] );
		$this->assertArrayHasKey( 'query_time', $result['details'] );
	}
}

/**
 * wpdb simulato che fallisce su ogni query
 *
 * Restituisce false e imposta last_error.
 */
class FailingWpdb extends \wpdb {

	/**
	 * Constructor no-op (salta la connessione reale)
	 */
	public function __construct() {
		// No-op: skip la connessione DB reale.
	}

	/**
	 * Simula query fallita
	 *
	 * @param string $query Query SQL.
	 * @return false Sempre false.
	 */
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	public function query( $query ) {
		$this->last_error = 'Simulated connection error at ' . ABSPATH . 'wp-config.php';
		return false;
	}
}

/**
 * wpdb simulato che risponde lentamente
 *
 * Simula una query che impiega più di 0.5 secondi.
 */
class SlowWpdb extends \wpdb {

	/**
	 * Constructor no-op (salta la connessione reale)
	 */
	public function __construct() {
		// No-op: skip la connessione DB reale.
	}

	/**
	 * Simula query lenta (> 0.5s)
	 *
	 * @param string $query Query SQL.
	 * @return int 1 (successo).
	 */
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	public function query( $query ) {
		// Busy-wait per 1s (soglia 0.5s, margine 2x).
		// Usa loop invece di usleep singolo per resistere a interruzioni EINTR.
		$target = 1.0;
		$start  = microtime( true );
		while ( ( microtime( true ) - $start ) < $target ) {
			usleep( 50000 ); // 50ms incrementi.
		}
		$this->last_error = '';
		return 1;
	}
}

/**
 * wpdb simulato che fallisce senza messaggio di errore
 *
 * Restituisce false ma last_error è vuoto (branch "Unknown error").
 */
class EmptyErrorWpdb extends \wpdb {

	/**
	 * Constructor no-op (salta la connessione reale)
	 */
	public function __construct() {
		// No-op: skip la connessione DB reale.
	}

	/**
	 * Simula query fallita senza messaggio di errore
	 *
	 * @param string $query Query SQL.
	 * @return false Sempre false.
	 */
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	public function query( $query ) {
		$this->last_error = '';
		return false;
	}
}
