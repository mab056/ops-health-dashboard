<?php
/**
 * Integration Test for DatabaseCheck
 *
 * Integration test with real WordPress $wpdb.
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
 * Integration test for DatabaseCheck with real WordPress.
 */
class DatabaseCheckTest extends WP_UnitTestCase {

	/**
	 * The check under test
	 *
	 * @var DatabaseCheck
	 */
	private $check;

	/**
	 * Setup for each test - centralizes $wpdb injection
	 */
	public function setUp(): void {
		parent::setUp();
		global $wpdb;
		$redaction   = new Redaction();
		$this->check = new DatabaseCheck( $wpdb, $redaction );
	}

	/**
	 * Verifies that DatabaseCheck runs correctly with real WordPress
	 */
	public function test_database_check_runs_successfully() {
		$result = $this->check->run();

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'status', $result );
		$this->assertArrayHasKey( 'message', $result );
		$this->assertArrayHasKey( 'details', $result );
		$this->assertArrayHasKey( 'duration', $result );

		// With a working WordPress test suite, it should be 'ok'.
		$this->assertEquals( 'ok', $result['status'], 'Database should be healthy' );
	}

	/**
	 * Verifies that DatabaseCheck measures duration correctly
	 */
	public function test_database_check_measures_duration() {
		$result = $this->check->run();

		$this->assertIsFloat( $result['duration'] );
		$this->assertGreaterThan( 0, $result['duration'], 'Duration should be positive' );
		$this->assertLessThan( 1, $result['duration'], 'Duration should be < 1s for simple query' );
	}

	/**
	 * Verifies that DatabaseCheck includes query_time in details but no sensitive info
	 */
	public function test_database_check_includes_safe_details() {
		$result = $this->check->run();

		$this->assertArrayHasKey( 'query_time', $result['details'] );
		$this->assertStringContainsString( 'ms', $result['details']['query_time'] );

		// Must not expose sensitive information.
		$this->assertArrayNotHasKey( 'db_host', $result['details'] );
		$this->assertArrayNotHasKey( 'db_name', $result['details'] );
	}

	/**
	 * Verifies that get_id(), get_name() and is_enabled() work correctly
	 */
	public function test_check_interface_accessors() {
		$this->assertEquals( 'database', $this->check->get_id() );
		$this->assertNotEmpty( $this->check->get_name() );
		$this->assertIsString( $this->check->get_name() );
		$this->assertTrue( $this->check->is_enabled() );
	}

	/**
	 * Verifies that a fast query returns ok status with formatted query_time
	 */
	public function test_database_check_returns_ok_for_fast_query() {
		$result = $this->check->run();

		$this->assertEquals( 'ok', $result['status'] );
		$this->assertArrayHasKey( 'query_time', $result['details'] );
		$this->assertStringContainsString( 'ms', $result['details']['query_time'] );
		$this->assertLessThan( 0.5, $result['duration'] );
	}

	/**
	 * Verifies that DatabaseCheck is consistent across multiple executions
	 */
	public function test_database_check_is_consistent() {
		// Run the check 3 times.
		$results = array();
		for ( $i = 0; $i < 3; $i++ ) {
			$results[] = $this->check->run();
		}

		// All should have the same status.
		$statuses = array_column( $results, 'status' );
		$this->assertCount( 1, array_unique( $statuses ), 'Status should be consistent' );
	}

	/**
	 * Verifies that DatabaseCheck returns critical when the query fails
	 */
	public function test_database_check_returns_critical_on_query_error() {
		$fake_wpdb = new FailingWpdb();
		$redaction = new Redaction( ABSPATH, WP_CONTENT_DIR );
		$check     = new DatabaseCheck( $fake_wpdb, $redaction );
		$result    = $check->run();

		$this->assertEquals( 'critical', $result['status'] );
		$this->assertStringContainsString( 'failed', strtolower( $result['message'] ) );
		$this->assertArrayHasKey( 'error', $result['details'] );

		// The error message must be redacted (does not contain ABSPATH).
		$this->assertStringNotContainsString( ABSPATH, $result['details']['error'] );
	}

	/**
	 * Verifies that DatabaseCheck returns critical with unknown error
	 *
	 * Covers the branch where query returns false but last_error is empty.
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
	 * Verifies that DatabaseCheck returns warning for slow query (> 0.5s)
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
 * Simulated wpdb that fails on every query
 *
 * Returns false and sets last_error.
 */
class FailingWpdb extends \wpdb {

	/**
	 * No-op constructor (skips real DB connection)
	 */
	public function __construct() {
		// No-op: skip real DB connection.
	}

	/**
	 * Simulates failed query
	 *
	 * @param string $query SQL query.
	 * @return false Always false.
	 */
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	public function query( $query ) {
		$this->last_error = 'Simulated connection error at ' . ABSPATH . 'wp-config.php';
		return false;
	}
}

/**
 * Simulated wpdb that responds slowly
 *
 * Simulates a query that takes more than 0.5 seconds.
 */
class SlowWpdb extends \wpdb {

	/**
	 * No-op constructor (skips real DB connection)
	 */
	public function __construct() {
		// No-op: skip real DB connection.
	}

	/**
	 * Simulates slow query (> 0.5s)
	 *
	 * @param string $query SQL query.
	 * @return int 1 (success).
	 */
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	public function query( $query ) {
		// Busy-wait for 1s (threshold 0.5s, 2x margin).
		// Uses loop instead of single usleep to resist EINTR interruptions.
		$target = 1.0;
		$start  = microtime( true );
		while ( ( microtime( true ) - $start ) < $target ) {
			usleep( 50000 ); // 50ms increments.
		}
		$this->last_error = '';
		return 1;
	}
}

/**
 * Simulated wpdb that fails without error message
 *
 * Returns false but last_error is empty ("Unknown error" branch).
 */
class EmptyErrorWpdb extends \wpdb {

	/**
	 * No-op constructor (skips real DB connection)
	 */
	public function __construct() {
		// No-op: skip real DB connection.
	}

	/**
	 * Simulates failed query without error message
	 *
	 * @param string $query SQL query.
	 * @return false Always false.
	 */
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	public function query( $query ) {
		$this->last_error = '';
		return false;
	}
}
