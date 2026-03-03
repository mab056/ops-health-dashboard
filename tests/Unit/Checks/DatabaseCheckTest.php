<?php
/**
 * Unit Test for DatabaseCheck
 *
 * Unit test with Brain\Monkey for DatabaseCheck.
 *
 * @package OpsHealthDashboard\Tests\Unit\Checks
 */

namespace OpsHealthDashboard\Tests\Unit\Checks;

use Brain\Monkey\Functions;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use OpsHealthDashboard\Checks\DatabaseCheck;
use OpsHealthDashboard\Interfaces\CheckInterface;
use OpsHealthDashboard\Interfaces\RedactionInterface;
use PHPUnit\Framework\TestCase;

/**
 * Class DatabaseCheckTest
 *
 * Unit test for DatabaseCheck.
 */
class DatabaseCheckTest extends TestCase {
	use MockeryPHPUnitIntegration;

	/**
	 * Setup for each test
	 */
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();
	}

	/**
	 * Teardown after each test
	 */
	protected function tearDown(): void {
		\Brain\Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Creates a wpdb mock for tests
	 *
	 * @return \Mockery\MockInterface
	 */
	private function create_wpdb_mock() {
		$wpdb = Mockery::mock( 'wpdb' );
		$wpdb->last_error = '';
		return $wpdb;
	}

	/**
	 * Creates a RedactionInterface mock for tests
	 *
	 * @return \Mockery\MockInterface
	 */
	private function create_redaction_mock() {
		$redaction = Mockery::mock( RedactionInterface::class );
		$redaction->shouldReceive( 'redact' )
			->andReturnUsing( function ( $text ) {
				return $text;
			} );
		return $redaction;
	}

	/**
	 * Tests that DatabaseCheck can be instantiated with $wpdb and RedactionInterface
	 */
	public function test_database_check_can_be_instantiated() {
		$wpdb      = $this->create_wpdb_mock();
		$redaction = $this->create_redaction_mock();
		$check     = new DatabaseCheck( $wpdb, $redaction );
		$this->assertInstanceOf( DatabaseCheck::class, $check );
	}

	/**
	 * Tests that DatabaseCheck implements CheckInterface
	 */
	public function test_database_check_implements_interface() {
		$wpdb      = $this->create_wpdb_mock();
		$redaction = $this->create_redaction_mock();
		$check     = new DatabaseCheck( $wpdb, $redaction );
		$this->assertInstanceOf( CheckInterface::class, $check );
	}

	/**
	 * Tests that get_id() returns 'database'
	 */
	public function test_get_id_returns_database() {
		$wpdb      = $this->create_wpdb_mock();
		$redaction = $this->create_redaction_mock();
		$check     = new DatabaseCheck( $wpdb, $redaction );
		$this->assertEquals( 'database', $check->get_id() );
	}

	/**
	 * Tests that get_name() returns the correct name with i18n
	 */
	public function test_get_name_returns_correct_name() {
		$wpdb      = $this->create_wpdb_mock();
		$redaction = $this->create_redaction_mock();
		$check     = new DatabaseCheck( $wpdb, $redaction );

		Functions\expect( '__' )
			->andReturnUsing( function ( $text ) {
				return $text;
			} );

		$this->assertEquals( 'Database Connection', $check->get_name() );
	}

	/**
	 * Tests that is_enabled() returns true by default
	 */
	public function test_is_enabled_returns_true_by_default() {
		$wpdb      = $this->create_wpdb_mock();
		$redaction = $this->create_redaction_mock();
		$check     = new DatabaseCheck( $wpdb, $redaction );
		$this->assertTrue( $check->is_enabled() );
	}

	/**
	 * Tests that run() returns 'ok' when the DB is healthy
	 */
	public function test_run_returns_ok_when_database_healthy() {
		$wpdb = $this->create_wpdb_mock();
		$wpdb->shouldReceive( 'query' )
			->once()
			->with( 'SELECT 1' )
			->andReturn( 1 );

		Functions\expect( '__' )
			->andReturnUsing( function ( $text ) {
				return $text;
			} );

		$redaction = $this->create_redaction_mock();
		$check     = new DatabaseCheck( $wpdb, $redaction );
		$result    = $check->run();

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'status', $result );
		$this->assertArrayHasKey( 'message', $result );
		$this->assertArrayHasKey( 'details', $result );
		$this->assertArrayHasKey( 'duration', $result );
		$this->assertEquals( 'ok', $result['status'] );
		$this->assertArrayHasKey( 'query_time', $result['details'] );
		$this->assertStringContainsString( 'ms', $result['details']['query_time'] );
	}

	/**
	 * Tests that run() returns 'critical' when the DB fails and redacts the error
	 */
	public function test_run_returns_critical_when_database_fails() {
		$wpdb = $this->create_wpdb_mock();
		$wpdb->shouldReceive( 'query' )
			->once()
			->with( 'SELECT 1' )
			->andReturn( false );
		$wpdb->last_error = 'Connection error';

		Functions\expect( '__' )
			->andReturnUsing( function ( $text ) {
				return $text;
			} );

		$redaction = Mockery::mock( RedactionInterface::class );
		$redaction->shouldReceive( 'redact' )
			->once()
			->with( 'Connection error' )
			->andReturn( '[REDACTED]' );

		$check  = new DatabaseCheck( $wpdb, $redaction );
		$result = $check->run();

		$this->assertEquals( 'critical', $result['status'] );
		$this->assertStringContainsString( 'failed', strtolower( $result['message'] ) );
		$this->assertArrayHasKey( 'error', $result['details'] );
		$this->assertEquals( '[REDACTED]', $result['details']['error'] );
	}

	/**
	 * Tests that run() returns 'critical' with last_error even if query does not return false
	 */
	public function test_run_returns_critical_when_last_error_is_set() {
		$wpdb = $this->create_wpdb_mock();
		$wpdb->shouldReceive( 'query' )
			->once()
			->with( 'SELECT 1' )
			->andReturn( 1 );
		$wpdb->last_error = 'Some DB error';

		Functions\expect( '__' )
			->andReturnUsing( function ( $text ) {
				return $text;
			} );

		$redaction = Mockery::mock( RedactionInterface::class );
		$redaction->shouldReceive( 'redact' )
			->once()
			->with( 'Some DB error' )
			->andReturn( '[REDACTED]' );

		$check  = new DatabaseCheck( $wpdb, $redaction );
		$result = $check->run();

		$this->assertEquals( 'critical', $result['status'] );
	}

	/**
	 * Tests that run() returns 'warning' when the query is slow (> 0.5s)
	 */
	public function test_run_returns_warning_when_query_slow() {
		$wpdb = $this->create_wpdb_mock();
		$wpdb->shouldReceive( 'query' )
			->once()
			->with( 'SELECT 1' )
			->andReturnUsing( function () {
				// Busy-wait for 1s (threshold 0.5s, margin 2x).
				// Loop resists EINTR interruptions on individual usleep calls.
				$target = 1.0;
				$start  = microtime( true );
				while ( ( microtime( true ) - $start ) < $target ) {
					usleep( 50000 );
				}
				return 1;
			} );

		Functions\expect( '__' )
			->andReturnUsing( function ( $text ) {
				return $text;
			} );

		$redaction = $this->create_redaction_mock();
		$check     = new DatabaseCheck( $wpdb, $redaction );
		$result    = $check->run();

		$this->assertEquals( 'warning', $result['status'] );
		$this->assertStringContainsString( 'healthy', strtolower( $result['message'] ) );
		$this->assertGreaterThan( 0.5, $result['duration'] );
	}

	/**
	 * Tests that run() returns 'critical' with Unknown message when last_error is empty
	 */
	public function test_run_returns_unknown_error_when_last_error_empty() {
		$wpdb = $this->create_wpdb_mock();
		$wpdb->shouldReceive( 'query' )
			->once()
			->with( 'SELECT 1' )
			->andReturn( false );
		$wpdb->last_error = '';

		Functions\expect( '__' )
			->andReturnUsing( function ( $text ) {
				return $text;
			} );

		$redaction = $this->create_redaction_mock();
		$check     = new DatabaseCheck( $wpdb, $redaction );
		$result    = $check->run();

		$this->assertEquals( 'critical', $result['status'] );
		$this->assertEquals( 'Unknown error', $result['details']['error'] );
	}

	/**
	 * Tests that run() does not expose db_host or db_name in details
	 */
	public function test_run_does_not_expose_database_info() {
		$wpdb = $this->create_wpdb_mock();
		$wpdb->shouldReceive( 'query' )
			->once()
			->andReturn( 1 );

		Functions\expect( '__' )
			->andReturnUsing( function ( $text ) {
				return $text;
			} );

		$redaction = $this->create_redaction_mock();
		$check     = new DatabaseCheck( $wpdb, $redaction );
		$result    = $check->run();

		$this->assertArrayNotHasKey( 'db_host', $result['details'] );
		$this->assertArrayNotHasKey( 'db_name', $result['details'] );
	}

	/**
	 * Tests that run() measures the execution duration
	 */
	public function test_run_measures_duration() {
		$wpdb = $this->create_wpdb_mock();
		$wpdb->shouldReceive( 'query' )
			->once()
			->andReturn( 1 );

		Functions\expect( '__' )
			->andReturnUsing( function ( $text ) {
				return $text;
			} );

		$redaction = $this->create_redaction_mock();
		$check     = new DatabaseCheck( $wpdb, $redaction );
		$result    = $check->run();

		$this->assertIsFloat( $result['duration'] );
		$this->assertGreaterThanOrEqual( 0, $result['duration'] );
	}

	/**
	 * Tests that the class is NOT final
	 */
	public function test_class_is_not_final() {
		$reflection = new \ReflectionClass( DatabaseCheck::class );
		$this->assertFalse( $reflection->isFinal(), 'DatabaseCheck should NOT be final' );
	}

	/**
	 * Tests that NO static methods exist
	 */
	public function test_no_static_methods() {
		$reflection = new \ReflectionClass( DatabaseCheck::class );
		$methods    = $reflection->getMethods( \ReflectionMethod::IS_STATIC );

		$static_methods = array_filter(
			$methods,
			function ( $method ) {
				return strpos( $method->getName(), '__' ) !== 0;
			}
		);

		$this->assertEmpty( $static_methods, 'DatabaseCheck should have NO static methods' );
	}

	/**
	 * Tests that NO static properties exist
	 */
	public function test_no_static_properties() {
		$reflection = new \ReflectionClass( DatabaseCheck::class );
		$properties = $reflection->getProperties( \ReflectionProperty::IS_STATIC );

		$this->assertEmpty( $properties, 'DatabaseCheck should have NO static properties' );
	}
}
