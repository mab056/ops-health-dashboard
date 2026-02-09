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
}
