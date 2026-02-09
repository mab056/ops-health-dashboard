<?php
/**
 * Unit Test per DatabaseCheck
 *
 * Test unitario con Brain\Monkey per DatabaseCheck.
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
 * Unit test per DatabaseCheck.
 */
class DatabaseCheckTest extends TestCase {
	use MockeryPHPUnitIntegration;

	/**
	 * Setup per ogni test
	 */
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();
	}

	/**
	 * Teardown dopo ogni test
	 */
	protected function tearDown(): void {
		\Brain\Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Crea un mock wpdb per i test
	 *
	 * @return \Mockery\MockInterface
	 */
	private function create_wpdb_mock() {
		$wpdb = Mockery::mock( 'wpdb' );
		$wpdb->last_error = '';
		return $wpdb;
	}

	/**
	 * Crea un mock RedactionInterface per i test
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
	 * Testa che DatabaseCheck può essere istanziato con $wpdb e RedactionInterface
	 */
	public function test_database_check_can_be_instantiated() {
		$wpdb      = $this->create_wpdb_mock();
		$redaction = $this->create_redaction_mock();
		$check     = new DatabaseCheck( $wpdb, $redaction );
		$this->assertInstanceOf( DatabaseCheck::class, $check );
	}

	/**
	 * Testa che DatabaseCheck implementa CheckInterface
	 */
	public function test_database_check_implements_interface() {
		$wpdb      = $this->create_wpdb_mock();
		$redaction = $this->create_redaction_mock();
		$check     = new DatabaseCheck( $wpdb, $redaction );
		$this->assertInstanceOf( CheckInterface::class, $check );
	}

	/**
	 * Testa che get_id() ritorna 'database'
	 */
	public function test_get_id_returns_database() {
		$wpdb      = $this->create_wpdb_mock();
		$redaction = $this->create_redaction_mock();
		$check     = new DatabaseCheck( $wpdb, $redaction );
		$this->assertEquals( 'database', $check->get_id() );
	}

	/**
	 * Testa che get_name() ritorna il nome corretto
	 */
	public function test_get_name_returns_correct_name() {
		$wpdb      = $this->create_wpdb_mock();
		$redaction = $this->create_redaction_mock();
		$check     = new DatabaseCheck( $wpdb, $redaction );
		$this->assertEquals( 'Database Connection', $check->get_name() );
	}

	/**
	 * Testa che is_enabled() ritorna true di default
	 */
	public function test_is_enabled_returns_true_by_default() {
		$wpdb      = $this->create_wpdb_mock();
		$redaction = $this->create_redaction_mock();
		$check     = new DatabaseCheck( $wpdb, $redaction );
		$this->assertTrue( $check->is_enabled() );
	}

	/**
	 * Testa che run() ritorna 'ok' quando il DB è sano
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
	 * Testa che run() ritorna 'critical' quando il DB fallisce e redaziona l'errore
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
	 * Testa che run() ritorna 'critical' con last_error anche se query non ritorna false
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
	 * Testa che run() non espone db_host o db_name nei dettagli
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
	 * Testa che run() misura la durata dell'esecuzione
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
	 * Testa che la classe NON è final
	 */
	public function test_class_is_not_final() {
		$reflection = new \ReflectionClass( DatabaseCheck::class );
		$this->assertFalse( $reflection->isFinal(), 'DatabaseCheck should NOT be final' );
	}

	/**
	 * Testa che NON esistono metodi static
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
	 * Testa che NON esistono proprietà static
	 */
	public function test_no_static_properties() {
		$reflection = new \ReflectionClass( DatabaseCheck::class );
		$properties = $reflection->getProperties( \ReflectionProperty::IS_STATIC );

		$this->assertEmpty( $properties, 'DatabaseCheck should have NO static properties' );
	}
}
