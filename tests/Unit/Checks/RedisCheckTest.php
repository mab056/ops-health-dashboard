<?php
/**
 * Unit Test for RedisCheck
 *
 * Unit test with Brain\Monkey for RedisCheck.
 * Uses Mockery partial mock for Redis operations.
 *
 * @package OpsHealthDashboard\Tests\Unit\Checks
 */

namespace OpsHealthDashboard\Tests\Unit\Checks;

use Brain\Monkey\Functions;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use OpsHealthDashboard\Checks\RedisCheck;
use OpsHealthDashboard\Interfaces\CheckInterface;
use OpsHealthDashboard\Interfaces\RedactionInterface;
use PHPUnit\Framework\TestCase;

/**
 * Class RedisCheckTest
 *
 * Unit test for RedisCheck.
 */
class RedisCheckTest extends TestCase {
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
	 * Creates a RedactionInterface mock
	 *
	 * @return \Mockery\MockInterface
	 */
	private function create_redaction_mock() {
		$redaction = Mockery::mock( RedactionInterface::class );
		$redaction->shouldReceive( 'redact' )
			->andReturnUsing(
				function ( $text ) {
					return $text;
				}
			);
		return $redaction;
	}

	/**
	 * Creates a partial mock of RedisCheck with mockable protected methods
	 *
	 * @param \Mockery\MockInterface $redaction Redaction service mock.
	 * @return \Mockery\MockInterface
	 */
	private function create_check_mock( $redaction ) {
		$check = Mockery::mock( RedisCheck::class, [ $redaction ] )
			->makePartial()
			->shouldAllowMockingProtectedMethods();
		return $check;
	}

	/**
	 * Creates a Redis mock
	 *
	 * @return \Mockery\MockInterface
	 */
	private function create_redis_mock() {
		$redis = Mockery::mock( 'Redis' );
		$redis->shouldReceive( 'close' )
			->byDefault();
		return $redis;
	}

	/**
	 * Sets up standard i18n expectations
	 */
	private function mock_i18n() {
		Functions\expect( '__' )
			->andReturnUsing(
				function ( $text ) {
					return $text;
				}
			);
	}

	// -------------------------------------------------------------------
	// Pattern enforcement
	// -------------------------------------------------------------------

	/**
	 * Tests that the class is NOT final
	 */
	public function test_class_is_not_final() {
		$reflection = new \ReflectionClass( RedisCheck::class );
		$this->assertFalse( $reflection->isFinal(), 'RedisCheck should NOT be final' );
	}

	/**
	 * Tests that NO static methods exist
	 */
	public function test_no_static_methods() {
		$reflection = new \ReflectionClass( RedisCheck::class );
		$methods    = $reflection->getMethods( \ReflectionMethod::IS_STATIC );

		$static_methods = array_filter(
			$methods,
			function ( $method ) {
				return strpos( $method->getName(), '__' ) !== 0;
			}
		);

		$this->assertEmpty( $static_methods, 'RedisCheck should have NO static methods' );
	}

	/**
	 * Tests that NO static properties exist
	 */
	public function test_no_static_properties() {
		$reflection = new \ReflectionClass( RedisCheck::class );
		$properties = $reflection->getProperties( \ReflectionProperty::IS_STATIC );

		$this->assertEmpty( $properties, 'RedisCheck should have NO static properties' );
	}

	// -------------------------------------------------------------------
	// CheckInterface
	// -------------------------------------------------------------------

	/**
	 * Tests that RedisCheck implements CheckInterface
	 */
	public function test_implements_check_interface() {
		$redaction = $this->create_redaction_mock();
		$check     = new RedisCheck( $redaction );
		$this->assertInstanceOf( CheckInterface::class, $check );
	}

	/**
	 * Tests that get_id() returns 'redis'
	 */
	public function test_get_id_returns_redis() {
		$redaction = $this->create_redaction_mock();
		$check     = new RedisCheck( $redaction );
		$this->assertEquals( 'redis', $check->get_id() );
	}

	/**
	 * Tests that get_name() returns the correct name
	 */
	public function test_get_name_returns_correct_name() {
		$redaction = $this->create_redaction_mock();
		$check     = new RedisCheck( $redaction );

		$this->mock_i18n();

		$this->assertEquals( 'Redis Cache', $check->get_name() );
	}

	/**
	 * Tests that is_enabled() returns true
	 */
	public function test_is_enabled_returns_true() {
		$redaction = $this->create_redaction_mock();
		$check     = new RedisCheck( $redaction );
		$this->assertTrue( $check->is_enabled() );
	}

	// -------------------------------------------------------------------
	// Extension detection
	// -------------------------------------------------------------------

	/**
	 * Tests that run() returns warning when the Redis extension is not loaded
	 */
	public function test_returns_warning_when_extension_not_loaded() {
		$redaction = $this->create_redaction_mock();
		$check     = $this->create_check_mock( $redaction );

		$check->shouldReceive( 'is_extension_loaded' )
			->once()
			->andReturn( false );

		$this->mock_i18n();

		$result = $check->run();

		$this->assertEquals( 'warning', $result['status'] );
		$this->assertStringContainsString( 'extension', strtolower( $result['message'] ) );
	}

	/**
	 * Tests that run() proceeds when the extension is loaded
	 */
	public function test_proceeds_when_extension_loaded() {
		$redaction = $this->create_redaction_mock();
		$check     = $this->create_check_mock( $redaction );
		$redis     = $this->create_redis_mock();

		$check->shouldReceive( 'is_extension_loaded' )
			->once()
			->andReturn( true );

		$check->shouldReceive( 'get_redis_config' )
			->once()
			->andReturn(
				[
					'host'     => '127.0.0.1',
					'port'     => 6379,
					'password' => '',
					'database' => 0,
				]
			);

		$check->shouldReceive( 'create_redis_instance' )
			->once()
			->andReturn( $redis );

		$redis->shouldReceive( 'connect' )
			->once()
			->with( '127.0.0.1', 6379, Mockery::type( 'float' ) )
			->andReturn( true );

		$redis->shouldReceive( 'set' )
			->once()
			->andReturn( true );

		$redis->shouldReceive( 'get' )
			->once()
			->andReturn( 'ops_health_test_value' );

		$redis->shouldReceive( 'del' )
			->once()
			->andReturn( 1 );

		$this->mock_i18n();

		$result = $check->run();

		$this->assertContains( $result['status'], [ 'ok', 'warning' ] );
	}

	// -------------------------------------------------------------------
	// Connection scenarios
	// -------------------------------------------------------------------

	/**
	 * Tests that run() returns warning when the connection fails
	 */
	public function test_returns_warning_when_connection_fails() {
		$redaction = $this->create_redaction_mock();
		$check     = $this->create_check_mock( $redaction );
		$redis     = $this->create_redis_mock();

		$check->shouldReceive( 'is_extension_loaded' )
			->once()
			->andReturn( true );

		$check->shouldReceive( 'get_redis_config' )
			->once()
			->andReturn(
				[
					'host'     => '127.0.0.1',
					'port'     => 6379,
					'password' => '',
					'database' => 0,
				]
			);

		$check->shouldReceive( 'create_redis_instance' )
			->once()
			->andReturn( $redis );

		$redis->shouldReceive( 'connect' )
			->once()
			->andThrow( new \Exception( 'Connection refused' ) );

		$this->mock_i18n();

		$result = $check->run();

		$this->assertEquals( 'warning', $result['status'] );
		$this->assertStringContainsString( 'connection', strtolower( $result['message'] ) );
	}

	/**
	 * Tests that it uses the default host when the constant is not defined
	 */
	public function test_uses_default_host_when_no_constant() {
		$redaction = $this->create_redaction_mock();
		$check     = new RedisCheck( $redaction );

		$reflection = new \ReflectionClass( RedisCheck::class );
		$method     = $reflection->getMethod( 'get_redis_config' );
		$method->setAccessible( true );

		$config = $method->invoke( $check );

		$this->assertEquals( '127.0.0.1', $config['host'] );
	}

	/**
	 * Tests that it uses the default port when the constant is not defined
	 */
	public function test_uses_default_port_when_no_constant() {
		$redaction = $this->create_redaction_mock();
		$check     = new RedisCheck( $redaction );

		$reflection = new \ReflectionClass( RedisCheck::class );
		$method     = $reflection->getMethod( 'get_redis_config' );
		$method->setAccessible( true );

		$config = $method->invoke( $check );

		$this->assertEquals( 6379, $config['port'] );
	}

	// -------------------------------------------------------------------
	// Authentication
	// -------------------------------------------------------------------

	/**
	 * Tests that it authenticates when the password is configured
	 */
	public function test_authenticates_when_password_set() {
		$redaction = $this->create_redaction_mock();
		$check     = $this->create_check_mock( $redaction );
		$redis     = $this->create_redis_mock();

		$check->shouldReceive( 'is_extension_loaded' )
			->andReturn( true );

		$check->shouldReceive( 'get_redis_config' )
			->andReturn(
				[
					'host'     => '127.0.0.1',
					'port'     => 6379,
					'password' => 'secret123',
					'database' => 0,
				]
			);

		$check->shouldReceive( 'create_redis_instance' )
			->andReturn( $redis );

		$redis->shouldReceive( 'connect' )
			->andReturn( true );

		$redis->shouldReceive( 'auth' )
			->once()
			->with( 'secret123' )
			->andReturn( true );

		$redis->shouldReceive( 'set' )->andReturn( true );
		$redis->shouldReceive( 'get' )->andReturn( 'ops_health_test_value' );
		$redis->shouldReceive( 'del' )->andReturn( 1 );

		$this->mock_i18n();

		$result = $check->run();

		$this->assertContains( $result['status'], [ 'ok', 'warning' ] );
	}

	/**
	 * Tests that it returns warning when authentication fails
	 */
	public function test_returns_warning_when_auth_fails() {
		$redaction = $this->create_redaction_mock();
		$check     = $this->create_check_mock( $redaction );
		$redis     = $this->create_redis_mock();

		$check->shouldReceive( 'is_extension_loaded' )
			->andReturn( true );

		$check->shouldReceive( 'get_redis_config' )
			->andReturn(
				[
					'host'     => '127.0.0.1',
					'port'     => 6379,
					'password' => 'wrong_password',
					'database' => 0,
				]
			);

		$check->shouldReceive( 'create_redis_instance' )
			->andReturn( $redis );

		$redis->shouldReceive( 'connect' )
			->andReturn( true );

		$redis->shouldReceive( 'auth' )
			->once()
			->andThrow( new \Exception( 'WRONGPASS invalid password' ) );

		$this->mock_i18n();

		$result = $check->run();

		$this->assertEquals( 'warning', $result['status'] );
		$this->assertStringContainsString( 'auth', strtolower( $result['message'] ) );
	}

	// -------------------------------------------------------------------
	// Smoke test
	// -------------------------------------------------------------------

	/**
	 * Tests that run() returns ok when the smoke test passes
	 */
	public function test_returns_ok_when_smoke_test_passes() {
		$redaction = $this->create_redaction_mock();
		$check     = $this->create_check_mock( $redaction );
		$redis     = $this->create_redis_mock();

		$check->shouldReceive( 'is_extension_loaded' )->andReturn( true );
		$check->shouldReceive( 'get_redis_config' )
			->andReturn(
				[
					'host'     => '127.0.0.1',
					'port'     => 6379,
					'password' => '',
					'database' => 0,
				]
			);
		$check->shouldReceive( 'create_redis_instance' )->andReturn( $redis );

		$redis->shouldReceive( 'connect' )->andReturn( true );
		$redis->shouldReceive( 'set' )
			->once()
			->with(
				Mockery::on( function ( $key ) {
					return 0 === strpos( $key, 'ops_health_smoke_test_' );
				} ),
				'ops_health_test_value'
			)
			->andReturn( true );
		$redis->shouldReceive( 'get' )
			->once()
			->with(
				Mockery::on( function ( $key ) {
					return 0 === strpos( $key, 'ops_health_smoke_test_' );
				} )
			)
			->andReturn( 'ops_health_test_value' );
		$redis->shouldReceive( 'del' )
			->once()
			->with(
				Mockery::on( function ( $key ) {
					return 0 === strpos( $key, 'ops_health_smoke_test_' );
				} )
			)
			->andReturn( 1 );

		$this->mock_i18n();

		$result = $check->run();

		$this->assertEquals( 'ok', $result['status'] );
		$this->assertStringContainsString( 'healthy', strtolower( $result['message'] ) );
	}

	/**
	 * Tests that run() returns warning when SET fails
	 */
	public function test_returns_warning_when_set_fails() {
		$redaction = $this->create_redaction_mock();
		$check     = $this->create_check_mock( $redaction );
		$redis     = $this->create_redis_mock();

		$check->shouldReceive( 'is_extension_loaded' )->andReturn( true );
		$check->shouldReceive( 'get_redis_config' )
			->andReturn(
				[
					'host'     => '127.0.0.1',
					'port'     => 6379,
					'password' => '',
					'database' => 0,
				]
			);
		$check->shouldReceive( 'create_redis_instance' )->andReturn( $redis );

		$redis->shouldReceive( 'connect' )->andReturn( true );
		$redis->shouldReceive( 'set' )
			->once()
			->andReturn( false );
		$redis->shouldReceive( 'del' )->byDefault();

		$this->mock_i18n();

		$result = $check->run();

		$this->assertEquals( 'warning', $result['status'] );
		$this->assertStringContainsString( 'smoke test', strtolower( $result['message'] ) );
	}

	/**
	 * Tests that run() returns warning when GET does not match the value
	 */
	public function test_returns_warning_when_get_mismatch() {
		$redaction = $this->create_redaction_mock();
		$check     = $this->create_check_mock( $redaction );
		$redis     = $this->create_redis_mock();

		$check->shouldReceive( 'is_extension_loaded' )->andReturn( true );
		$check->shouldReceive( 'get_redis_config' )
			->andReturn(
				[
					'host'     => '127.0.0.1',
					'port'     => 6379,
					'password' => '',
					'database' => 0,
				]
			);
		$check->shouldReceive( 'create_redis_instance' )->andReturn( $redis );

		$redis->shouldReceive( 'connect' )->andReturn( true );
		$redis->shouldReceive( 'set' )->andReturn( true );
		$redis->shouldReceive( 'get' )
			->once()
			->andReturn( 'wrong_value' );
		$redis->shouldReceive( 'del' )->byDefault();

		$this->mock_i18n();

		$result = $check->run();

		$this->assertEquals( 'warning', $result['status'] );
		$this->assertStringContainsString( 'smoke test', strtolower( $result['message'] ) );
	}

	/**
	 * Tests that run() includes the response time in details
	 */
	public function test_measures_response_time() {
		$redaction = $this->create_redaction_mock();
		$check     = $this->create_check_mock( $redaction );
		$redis     = $this->create_redis_mock();

		$check->shouldReceive( 'is_extension_loaded' )->andReturn( true );
		$check->shouldReceive( 'get_redis_config' )
			->andReturn(
				[
					'host'     => '127.0.0.1',
					'port'     => 6379,
					'password' => '',
					'database' => 0,
				]
			);
		$check->shouldReceive( 'create_redis_instance' )->andReturn( $redis );

		$redis->shouldReceive( 'connect' )->andReturn( true );
		$redis->shouldReceive( 'set' )->andReturn( true );
		$redis->shouldReceive( 'get' )->andReturn( 'ops_health_test_value' );
		$redis->shouldReceive( 'del' )->andReturn( 1 );

		$this->mock_i18n();

		$result = $check->run();

		$this->assertArrayHasKey( 'response_time', $result['details'] );
		$this->assertStringContainsString( 'ms', $result['details']['response_time'] );
	}

	/**
	 * Tests that run() returns warning when the response time is slow
	 */
	public function test_returns_warning_when_slow_response() {
		$redaction = $this->create_redaction_mock();
		$check     = $this->create_check_mock( $redaction );
		$redis     = $this->create_redis_mock();

		$check->shouldReceive( 'is_extension_loaded' )->andReturn( true );
		$check->shouldReceive( 'get_redis_config' )
			->andReturn(
				[
					'host'     => '127.0.0.1',
					'port'     => 6379,
					'password' => '',
					'database' => 0,
				]
			);
		$check->shouldReceive( 'create_redis_instance' )->andReturn( $redis );

		$redis->shouldReceive( 'connect' )->andReturn( true );
		$redis->shouldReceive( 'set' )->andReturnUsing(
			function () {
				// Simulates 300ms of latency (threshold 100ms, margin 3x).
				usleep( 300000 );
				return true;
			}
		);
		$redis->shouldReceive( 'get' )->andReturn( 'ops_health_test_value' );
		$redis->shouldReceive( 'del' )->andReturn( 1 );

		$this->mock_i18n();

		$result = $check->run();

		$this->assertEquals( 'warning', $result['status'] );
		$this->assertStringContainsString( 'slow', strtolower( $result['message'] ) );
	}

	// -------------------------------------------------------------------
	// Result structure
	// -------------------------------------------------------------------

	/**
	 * Tests that run() has all required keys in the result
	 */
	public function test_run_returns_required_keys() {
		$redaction = $this->create_redaction_mock();
		$check     = $this->create_check_mock( $redaction );

		$check->shouldReceive( 'is_extension_loaded' )
			->andReturn( false );

		$this->mock_i18n();

		$result = $check->run();

		$this->assertArrayHasKey( 'status', $result );
		$this->assertArrayHasKey( 'message', $result );
		$this->assertArrayHasKey( 'details', $result );
		$this->assertArrayHasKey( 'duration', $result );
	}

	/**
	 * Tests that run() measures the duration
	 */
	public function test_run_measures_duration() {
		$redaction = $this->create_redaction_mock();
		$check     = $this->create_check_mock( $redaction );

		$check->shouldReceive( 'is_extension_loaded' )
			->andReturn( false );

		$this->mock_i18n();

		$result = $check->run();

		$this->assertIsFloat( $result['duration'] );
		$this->assertGreaterThanOrEqual( 0, $result['duration'] );
	}

	/**
	 * Tests that run() uses i18n for messages
	 */
	public function test_uses_i18n_for_messages() {
		$redaction = $this->create_redaction_mock();
		$check     = $this->create_check_mock( $redaction );

		$check->shouldReceive( 'is_extension_loaded' )
			->andReturn( false );

		$i18n_called = false;
		Functions\expect( '__' )
			->andReturnUsing(
				function ( $text ) use ( &$i18n_called ) {
					$i18n_called = true;
					return $text;
				}
			);

		$check->run();

		$this->assertTrue( $i18n_called, '__() should be called for i18n' );
	}

	// -------------------------------------------------------------------
	// Security
	// -------------------------------------------------------------------

	/**
	 * Tests that the host is redacted in details
	 */
	public function test_redacts_host_in_details() {
		$redaction = Mockery::mock( RedactionInterface::class );
		$redaction->shouldReceive( 'redact' )
			->andReturnUsing(
				function ( $text ) {
					return str_replace( '192.168.1.100', '[REDACTED]', $text );
				}
			);

		$check = $this->create_check_mock( $redaction );
		$redis = $this->create_redis_mock();

		$check->shouldReceive( 'is_extension_loaded' )->andReturn( true );
		$check->shouldReceive( 'get_redis_config' )
			->andReturn(
				[
					'host'     => '192.168.1.100',
					'port'     => 6379,
					'password' => '',
					'database' => 0,
				]
			);
		$check->shouldReceive( 'create_redis_instance' )->andReturn( $redis );

		$redis->shouldReceive( 'connect' )->andReturn( true );
		$redis->shouldReceive( 'set' )->andReturn( true );
		$redis->shouldReceive( 'get' )->andReturn( 'ops_health_test_value' );
		$redis->shouldReceive( 'del' )->andReturn( 1 );

		$this->mock_i18n();

		$result    = $check->run();
		$as_string = json_encode( $result );

		$this->assertStringNotContainsString( '192.168.1.100', $as_string );
	}

	/**
	 * Tests that errors are redacted in details
	 */
	public function test_redacts_error_messages() {
		$redaction = Mockery::mock( RedactionInterface::class );
		$redaction->shouldReceive( 'redact' )
			->andReturnUsing(
				function ( $text ) {
					return str_replace( 'Connection refused to 10.0.0.1', '[REDACTED]', $text );
				}
			);

		$check = $this->create_check_mock( $redaction );
		$redis = $this->create_redis_mock();

		$check->shouldReceive( 'is_extension_loaded' )->andReturn( true );
		$check->shouldReceive( 'get_redis_config' )
			->andReturn(
				[
					'host'     => '10.0.0.1',
					'port'     => 6379,
					'password' => '',
					'database' => 0,
				]
			);
		$check->shouldReceive( 'create_redis_instance' )->andReturn( $redis );

		$redis->shouldReceive( 'connect' )
			->andThrow( new \Exception( 'Connection refused to 10.0.0.1' ) );

		$this->mock_i18n();

		$result    = $check->run();
		$as_string = json_encode( $result );

		$this->assertStringNotContainsString( 'Connection refused to 10.0.0.1', $as_string );
	}

	// -------------------------------------------------------------------
	// Database selection
	// -------------------------------------------------------------------

	/**
	 * Tests that it selects the database when configured
	 */
	public function test_selects_database_when_configured() {
		$redaction = $this->create_redaction_mock();
		$check     = $this->create_check_mock( $redaction );
		$redis     = $this->create_redis_mock();

		$check->shouldReceive( 'is_extension_loaded' )->andReturn( true );
		$check->shouldReceive( 'get_redis_config' )
			->andReturn(
				[
					'host'     => '127.0.0.1',
					'port'     => 6379,
					'password' => '',
					'database' => 3,
				]
			);
		$check->shouldReceive( 'create_redis_instance' )->andReturn( $redis );

		$redis->shouldReceive( 'connect' )->andReturn( true );
		$redis->shouldReceive( 'select' )
			->once()
			->with( 3 )
			->andReturn( true );
		$redis->shouldReceive( 'set' )->andReturn( true );
		$redis->shouldReceive( 'get' )->andReturn( 'ops_health_test_value' );
		$redis->shouldReceive( 'del' )->andReturn( 1 );

		$this->mock_i18n();

		$result = $check->run();

		$this->assertContains( $result['status'], [ 'ok', 'warning' ] );
	}

	// -------------------------------------------------------------------
	// Exception handling in private cleanup methods
	// -------------------------------------------------------------------

	/**
	 * Tests that close_connection() handles exception without propagating
	 *
	 * When auth fails, close_connection is called.
	 * If close() also throws, it must be swallowed.
	 */
	public function test_close_connection_swallows_exception() {
		$redaction = $this->create_redaction_mock();
		$check     = $this->create_check_mock( $redaction );
		$redis     = Mockery::mock( 'Redis' );

		$check->shouldReceive( 'is_extension_loaded' )->andReturn( true );
		$check->shouldReceive( 'get_redis_config' )
			->andReturn(
				[
					'host'     => '127.0.0.1',
					'port'     => 6379,
					'password' => 'secret',
					'database' => 0,
				]
			);
		$check->shouldReceive( 'create_redis_instance' )->andReturn( $redis );

		$redis->shouldReceive( 'connect' )->andReturn( true );
		$redis->shouldReceive( 'auth' )
			->andThrow( new \Exception( 'AUTH failed' ) );
		// close() throws exception -- must be swallowed.
		$redis->shouldReceive( 'close' )
			->andThrow( new \Exception( 'Connection already closed' ) );

		$this->mock_i18n();

		$result = $check->run();

		$this->assertEquals( 'warning', $result['status'] );
		$this->assertStringContainsString( 'auth', strtolower( $result['message'] ) );
	}

	/**
	 * Tests that cleanup_and_close() handles exception on del() without propagating
	 *
	 * When SET returns false, cleanup_and_close attempts del() + close().
	 * If del() throws, it must be swallowed.
	 */
	public function test_cleanup_and_close_swallows_del_exception() {
		$redaction = $this->create_redaction_mock();
		$check     = $this->create_check_mock( $redaction );
		$redis     = Mockery::mock( 'Redis' );

		$check->shouldReceive( 'is_extension_loaded' )->andReturn( true );
		$check->shouldReceive( 'get_redis_config' )
			->andReturn(
				[
					'host'     => '127.0.0.1',
					'port'     => 6379,
					'password' => '',
					'database' => 0,
				]
			);
		$check->shouldReceive( 'create_redis_instance' )->andReturn( $redis );

		$redis->shouldReceive( 'connect' )->andReturn( true );
		$redis->shouldReceive( 'set' )->andReturn( false );
		// del() during cleanup throws exception -- must be swallowed.
		$redis->shouldReceive( 'del' )
			->andThrow( new \Exception( 'READONLY cannot del' ) );
		$redis->shouldReceive( 'close' )->byDefault();

		$this->mock_i18n();

		$result = $check->run();

		$this->assertEquals( 'warning', $result['status'] );
		$this->assertStringContainsString( 'smoke test', strtolower( $result['message'] ) );
	}

	/**
	 * Tests that run() returns warning when the smoke test throws exception
	 */
	public function test_returns_warning_when_smoke_test_throws_exception() {
		$redaction = $this->create_redaction_mock();
		$check     = $this->create_check_mock( $redaction );
		$redis     = $this->create_redis_mock();

		$check->shouldReceive( 'is_extension_loaded' )->andReturn( true );
		$check->shouldReceive( 'get_redis_config' )
			->andReturn(
				[
					'host'     => '127.0.0.1',
					'port'     => 6379,
					'password' => '',
					'database' => 0,
				]
			);
		$check->shouldReceive( 'create_redis_instance' )->andReturn( $redis );

		$redis->shouldReceive( 'connect' )->andReturn( true );
		$redis->shouldReceive( 'set' )
			->andThrow( new \Exception( 'OOM command not allowed' ) );
		$redis->shouldReceive( 'del' )->byDefault();

		$this->mock_i18n();

		$result = $check->run();

		$this->assertEquals( 'warning', $result['status'] );
		$this->assertStringContainsString( 'smoke test failed', strtolower( $result['message'] ) );
		$this->assertArrayHasKey( 'error', $result['details'] );
	}

	// -------------------------------------------------------------------
	// Database selection
	// -------------------------------------------------------------------

	/**
	 * Tests that it returns warning when database selection fails
	 */
	public function test_returns_warning_when_database_selection_fails() {
		$redaction = $this->create_redaction_mock();
		$check     = $this->create_check_mock( $redaction );
		$redis     = $this->create_redis_mock();

		$check->shouldReceive( 'is_extension_loaded' )->andReturn( true );
		$check->shouldReceive( 'get_redis_config' )
			->andReturn(
				[
					'host'     => '127.0.0.1',
					'port'     => 6379,
					'password' => '',
					'database' => 999, // Invalid database.
				]
			);
		$check->shouldReceive( 'create_redis_instance' )->andReturn( $redis );

		$redis->shouldReceive( 'connect' )->andReturn( true );
		$redis->shouldReceive( 'select' )
			->once()
			->with( 999 )
			->andThrow( new \Exception( 'ERR DB index is out of range' ) );

		$this->mock_i18n();

		$result = $check->run();

		$this->assertEquals( 'warning', $result['status'] );
		$this->assertStringContainsString( 'database selection failed', $result['message'] );
	}

	/**
	 * Tests that is_extension_loaded returns a boolean
	 *
	 * Covers line 237: `extension_loaded('redis')`.
	 */
	public function test_is_extension_loaded_returns_bool() {
		$redaction = $this->create_redaction_mock();
		$check     = new RedisCheck( $redaction );

		$method = new \ReflectionMethod( RedisCheck::class, 'is_extension_loaded' );
		$method->setAccessible( true );

		$result = $method->invoke( $check );
		$this->assertIsBool( $result );
	}

	/**
	 * Tests that create_redis_instance returns a Redis instance
	 *
	 * Covers line 246: `new \Redis()`.
	 *
	 * @requires extension redis
	 */
	public function test_create_redis_instance_returns_redis_object() {
		$redaction = $this->create_redaction_mock();
		$check     = new RedisCheck( $redaction );

		$method = new \ReflectionMethod( RedisCheck::class, 'create_redis_instance' );
		$method->setAccessible( true );

		$result = $method->invoke( $check );
		$this->assertInstanceOf( \Redis::class, $result );
	}

	/**
	 * Tests that TypeError during connection is caught (catch Throwable)
	 */
	public function test_catches_throwable_on_connection() {
		$redaction = $this->create_redaction_mock();
		$check     = $this->create_check_mock( $redaction );
		$redis     = $this->create_redis_mock();

		$check->shouldReceive( 'is_extension_loaded' )->andReturn( true );
		$check->shouldReceive( 'get_redis_config' )
			->andReturn(
				[
					'host'     => '127.0.0.1',
					'port'     => 6379,
					'password' => '',
					'database' => 0,
				]
			);
		$check->shouldReceive( 'create_redis_instance' )->andReturn( $redis );

		$redis->shouldReceive( 'connect' )
			->andThrow( new \TypeError( 'Invalid argument type' ) );

		$this->mock_i18n();

		$result = $check->run();

		$this->assertEquals( 'warning', $result['status'] );
	}

	/**
	 * Tests that TypeError during smoke test is caught (catch Throwable)
	 */
	public function test_catches_throwable_on_smoke_test() {
		$redaction = $this->create_redaction_mock();
		$check     = $this->create_check_mock( $redaction );
		$redis     = $this->create_redis_mock();

		$check->shouldReceive( 'is_extension_loaded' )->andReturn( true );
		$check->shouldReceive( 'get_redis_config' )
			->andReturn(
				[
					'host'     => '127.0.0.1',
					'port'     => 6379,
					'password' => '',
					'database' => 0,
				]
			);
		$check->shouldReceive( 'create_redis_instance' )->andReturn( $redis );

		$redis->shouldReceive( 'connect' )->andReturn( true );
		$redis->shouldReceive( 'set' )
			->andThrow( new \TypeError( 'Argument must be string' ) );
		$redis->shouldReceive( 'del' )->byDefault();

		$this->mock_i18n();

		$result = $check->run();

		$this->assertEquals( 'warning', $result['status'] );
		$this->assertArrayHasKey( 'error', $result['details'] );
	}

	/**
	 * Tests that TypeError in close_connection is swallowed (catch Throwable)
	 */
	public function test_close_connection_swallows_throwable() {
		$redaction = $this->create_redaction_mock();
		$check     = $this->create_check_mock( $redaction );
		$redis     = Mockery::mock( 'Redis' );

		$check->shouldReceive( 'is_extension_loaded' )->andReturn( true );
		$check->shouldReceive( 'get_redis_config' )
			->andReturn(
				[
					'host'     => '127.0.0.1',
					'port'     => 6379,
					'password' => 'secret',
					'database' => 0,
				]
			);
		$check->shouldReceive( 'create_redis_instance' )->andReturn( $redis );

		$redis->shouldReceive( 'connect' )->andReturn( true );
		$redis->shouldReceive( 'auth' )
			->andThrow( new \TypeError( 'AUTH type error' ) );
		$redis->shouldReceive( 'close' )
			->andThrow( new \TypeError( 'Close type error' ) );

		$this->mock_i18n();

		$result = $check->run();

		$this->assertEquals( 'warning', $result['status'] );
	}
}
