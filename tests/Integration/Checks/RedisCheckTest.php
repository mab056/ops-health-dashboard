<?php
/**
 * Integration Test for RedisCheck
 *
 * Integration test with real WordPress.
 * Uses TestableRedisCheck to control protected methods.
 *
 * @package OpsHealthDashboard\Tests\Integration\Checks
 */

namespace OpsHealthDashboard\Tests\Integration\Checks;

use OpsHealthDashboard\Checks\RedisCheck;
use OpsHealthDashboard\Interfaces\CheckInterface;
use OpsHealthDashboard\Services\Redaction;
use WP_UnitTestCase;

/**
 * Class RedisCheckTest
 *
 * Integration test for RedisCheck with real WordPress.
 */
class RedisCheckTest extends WP_UnitTestCase {

	/**
	 * Verifies that RedisCheck implements CheckInterface
	 */
	public function test_redis_check_implements_interface() {
		$redaction = new Redaction( ABSPATH, WP_CONTENT_DIR );
		$check     = new RedisCheck( $redaction );
		$this->assertInstanceOf( CheckInterface::class, $check );
	}

	/**
	 * Verifies that RedisCheck runs without crash
	 */
	public function test_redis_check_runs_without_crash() {
		$redaction = new Redaction( ABSPATH, WP_CONTENT_DIR );
		$check     = new RedisCheck( $redaction );
		$result    = $check->run();

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'status', $result );
		$this->assertArrayHasKey( 'message', $result );
		$this->assertArrayHasKey( 'details', $result );
		$this->assertArrayHasKey( 'duration', $result );
	}

	/**
	 * Verifies that RedisCheck returns a valid structure
	 */
	public function test_redis_check_returns_valid_structure() {
		$redaction = new Redaction( ABSPATH, WP_CONTENT_DIR );
		$check     = new RedisCheck( $redaction );
		$result    = $check->run();

		$this->assertContains( $result['status'], [ 'ok', 'warning', 'critical' ] );
		$this->assertIsString( $result['message'] );
		$this->assertIsArray( $result['details'] );
		$this->assertIsFloat( $result['duration'] );
		$this->assertGreaterThanOrEqual( 0, $result['duration'] );
	}

	/**
	 * Tests graceful degradation when extension is not loaded
	 */
	public function test_redis_check_graceful_when_no_extension() {
		$redaction = new Redaction( ABSPATH, WP_CONTENT_DIR );
		$check     = new TestableRedisCheck( $redaction, false, null );
		$result    = $check->run();

		$this->assertEquals( 'warning', $result['status'] );
		$this->assertStringContainsString( 'extension', strtolower( $result['message'] ) );
	}

	/**
	 * Tests graceful degradation when connection fails
	 */
	public function test_redis_check_graceful_when_connection_fails() {
		$redaction = new Redaction( ABSPATH, WP_CONTENT_DIR );
		$check     = new TestableRedisCheck( $redaction, true, new \Exception( 'Connection refused' ) );
		$result    = $check->run();

		$this->assertEquals( 'warning', $result['status'] );
		$this->assertStringContainsString( 'connection', strtolower( $result['message'] ) );
	}

	/**
	 * Tests that host is redacted with the real Redaction service
	 */
	public function test_redis_check_with_real_redaction() {
		$redaction = new Redaction( ABSPATH, WP_CONTENT_DIR );
		$check     = new RedisCheck( $redaction );
		$result    = $check->run();

		// Regardless of status, ABSPATH must not appear.
		$as_string = json_encode( $result );
		$this->assertStringNotContainsString( ABSPATH, $as_string );
	}

	/**
	 * Tests graceful degradation when authentication fails
	 *
	 * @requires extension redis
	 */
	public function test_redis_check_graceful_on_auth_failure() {
		$redaction = new Redaction( ABSPATH, WP_CONTENT_DIR );
		$check     = new AuthFailRedisCheck( $redaction );
		$result    = $check->run();

		$this->assertEquals( 'warning', $result['status'] );
		$this->assertStringContainsString( 'authentication', strtolower( $result['message'] ) );
	}

	/**
	 * Tests graceful degradation when smoke test throws exception
	 *
	 * @requires extension redis
	 */
	public function test_redis_check_graceful_on_smoke_test_exception() {
		$redaction = new Redaction( ABSPATH, WP_CONTENT_DIR );
		$check     = new SmokeFailRedisCheck( $redaction );
		$result    = $check->run();

		$this->assertEquals( 'warning', $result['status'] );
		$this->assertStringContainsString( 'smoke test', strtolower( $result['message'] ) );
	}

	/**
	 * Tests graceful degradation when GET returns different value
	 *
	 * @requires extension redis
	 */
	public function test_redis_check_graceful_on_get_mismatch() {
		$redaction = new Redaction( ABSPATH, WP_CONTENT_DIR );
		$check     = new MismatchRedisCheck( $redaction );
		$result    = $check->run();

		$this->assertEquals( 'warning', $result['status'] );
		$this->assertStringContainsString( 'mismatch', strtolower( $result['message'] ) );
	}

	/**
	 * Verifies that get_id(), get_name() and is_enabled() work correctly
	 */
	public function test_check_interface_accessors() {
		$redaction = new Redaction( ABSPATH, WP_CONTENT_DIR );
		$check     = new RedisCheck( $redaction );

		$this->assertEquals( 'redis', $check->get_id() );
		$this->assertNotEmpty( $check->get_name() );
		$this->assertIsString( $check->get_name() );
		$this->assertTrue( $check->is_enabled() );
	}

	/**
	 * Tests full success when Redis is available
	 *
	 * @group redis
	 * @requires extension redis
	 */
	public function test_redis_check_successful_when_redis_available() {

		$redis = new \Redis();
		try {
			$redis->connect( '127.0.0.1', 6379, 1.0 );
			$redis->close();
		} catch ( \Exception $e ) {
			$this->markTestSkipped( 'Redis server not running: ' . $e->getMessage() );
		}

		$redaction = new Redaction( ABSPATH, WP_CONTENT_DIR );
		$check     = new RedisCheck( $redaction );
		$result    = $check->run();

		$this->assertEquals( 'ok', $result['status'] );
		$this->assertArrayHasKey( 'response_time', $result['details'] );
		$this->assertStringContainsString( 'ms', $result['details']['response_time'] );
	}

	/**
	 * Tests graceful degradation when database selection fails
	 *
	 * @requires extension redis
	 */
	public function test_redis_check_graceful_on_db_select_failure() {
		$redaction = new Redaction( ABSPATH, WP_CONTENT_DIR );
		$check     = new DbSelectFailRedisCheck( $redaction );
		$result    = $check->run();

		$this->assertEquals( 'warning', $result['status'] );
		$this->assertStringContainsString( 'database selection', strtolower( $result['message'] ) );
	}

	/**
	 * Tests graceful degradation when SET returns false
	 *
	 * @requires extension redis
	 */
	public function test_redis_check_graceful_on_set_returns_false() {
		$redaction = new Redaction( ABSPATH, WP_CONTENT_DIR );
		$check     = new SetFalseRedisCheck( $redaction );
		$result    = $check->run();

		$this->assertEquals( 'warning', $result['status'] );
		$this->assertStringContainsString( 'SET returned false', $result['message'] );
	}

	/**
	 * Tests that Redis returns warning for slow response (> 100ms)
	 *
	 * @requires extension redis
	 */
	public function test_redis_check_warning_on_slow_response() {
		$redaction = new Redaction( ABSPATH, WP_CONTENT_DIR );
		$check     = new SlowResponseRedisCheck( $redaction );
		$result    = $check->run();

		$this->assertEquals( 'warning', $result['status'] );
		$this->assertStringContainsString( 'slow', strtolower( $result['message'] ) );
		$this->assertArrayHasKey( 'response_time', $result['details'] );
	}

	/**
	 * Tests that close_connection ignores exception from close()
	 *
	 * Covers line 282 of close_connection().
	 *
	 * @requires extension redis
	 */
	public function test_redis_check_close_connection_ignores_exception() {
		$redaction = new Redaction( ABSPATH, WP_CONTENT_DIR );
		$check     = new CloseFailRedisCheck( $redaction );
		$result    = $check->run();

		// Auth fails -> close_connection is called, close() throws exception -> ignored.
		$this->assertEquals( 'warning', $result['status'] );
		$this->assertStringContainsString( 'authentication', strtolower( $result['message'] ) );
	}

	/**
	 * Tests that cleanup_and_close ignores exception from del()
	 *
	 * Covers line 297 of cleanup_and_close().
	 *
	 * @requires extension redis
	 */
	public function test_redis_check_cleanup_and_close_ignores_exception() {
		$redaction = new Redaction( ABSPATH, WP_CONTENT_DIR );
		$check     = new CleanupFailRedisCheck( $redaction );
		$result    = $check->run();

		// SET returns false -> cleanup_and_close -> del() throws exception -> ignored.
		$this->assertEquals( 'warning', $result['status'] );
		$this->assertStringContainsString( 'SET returned false', $result['message'] );
	}
}

/**
 * Testable subclass to control protected methods
 *
 * Allows simulating absence of the Redis extension and connection failures.
 */
class TestableRedisCheck extends RedisCheck {

	/**
	 * Whether the extension is "loaded"
	 *
	 * @var bool
	 */
	private $extension_loaded;

	/**
	 * Exception to throw during Redis instance creation
	 *
	 * @var \Exception|null
	 */
	private $connect_exception;

	/**
	 * Constructor
	 *
	 * @param \OpsHealthDashboard\Interfaces\RedactionInterface $redaction         Redaction service.
	 * @param bool                                               $extension_loaded  Whether to simulate loaded extension.
	 * @param \Exception|null                                    $connect_exception Exception to simulate failure.
	 */
	public function __construct(
		\OpsHealthDashboard\Interfaces\RedactionInterface $redaction,
		bool $extension_loaded,
		?\Exception $connect_exception
	) {
		parent::__construct( $redaction );
		$this->extension_loaded  = $extension_loaded;
		$this->connect_exception = $connect_exception;
	}

	/**
	 * Override to control extension detection
	 *
	 * @return bool Value configured in constructor.
	 */
	protected function is_extension_loaded(): bool {
		return $this->extension_loaded;
	}

	/**
	 * Override to simulate connection failures
	 *
	 * @return \Redis Redis instance (or throws exception).
	 * @throws \Exception If configured to fail.
	 */
	protected function create_redis_instance(): \Redis {
		if ( null !== $this->connect_exception ) {
			throw $this->connect_exception;
		}
		return parent::create_redis_instance();
	}
}

// Load FakeRedis helpers with signatures compatible with the current PHP version.
// FakeRedis* classes extend \Redis, so they require ext-redis.
// PHP 8.0+ phpredis declares union return types; PHP 7.4 does not.
if ( extension_loaded( 'redis' ) ) {
	if ( PHP_VERSION_ID >= 80000 ) {
		require_once __DIR__ . '/FakeRedisHelpers.php';
	} else {
		require_once __DIR__ . '/FakeRedisHelpersLegacy.php';
	}
}

/**
 * Testable RedisCheck that uses FakeRedisAuthFail
 *
 * Simulates successful connection but failed authentication.
 */
class AuthFailRedisCheck extends RedisCheck {

	/**
	 * Extension always loaded
	 *
	 * @return bool True.
	 */
	protected function is_extension_loaded(): bool {
		return true;
	}

	/**
	 * Creates FakeRedis with failed auth
	 *
	 * @return \Redis FakeRedisAuthFail.
	 */
	protected function create_redis_instance(): \Redis {
		return new FakeRedisAuthFail();
	}

	/**
	 * Config with password to trigger auth
	 *
	 * @return array Redis config.
	 */
	protected function get_redis_config(): array {
		return [
			'host'     => '127.0.0.1',
			'port'     => 6379,
			'password' => 'wrong_password',
			'database' => 0,
		];
	}
}

/**
 * Testable RedisCheck that uses FakeRedisSmokeTestFail
 */
class SmokeFailRedisCheck extends RedisCheck {

	/**
	 * Extension always loaded
	 *
	 * @return bool True.
	 */
	protected function is_extension_loaded(): bool {
		return true;
	}

	/**
	 * Creates FakeRedis with failed smoke test
	 *
	 * @return \Redis FakeRedisSmokeTestFail.
	 */
	protected function create_redis_instance(): \Redis {
		return new FakeRedisSmokeTestFail();
	}

	/**
	 * Config without password (no auth path)
	 *
	 * @return array Redis config.
	 */
	protected function get_redis_config(): array {
		return [
			'host'     => '127.0.0.1',
			'port'     => 6379,
			'password' => '',
			'database' => 0,
		];
	}
}

/**
 * Testable RedisCheck that uses FakeRedisGetMismatch
 */
class MismatchRedisCheck extends RedisCheck {

	/**
	 * Extension always loaded
	 *
	 * @return bool True.
	 */
	protected function is_extension_loaded(): bool {
		return true;
	}

	/**
	 * Creates FakeRedis with GET mismatch
	 *
	 * @return \Redis FakeRedisGetMismatch.
	 */
	protected function create_redis_instance(): \Redis {
		return new FakeRedisGetMismatch();
	}

	/**
	 * Config without password (no auth path)
	 *
	 * @return array Redis config.
	 */
	protected function get_redis_config(): array {
		return [
			'host'     => '127.0.0.1',
			'port'     => 6379,
			'password' => '',
			'database' => 0,
		];
	}
}

/**
 * Testable RedisCheck that uses FakeRedisDbSelectFail
 *
 * Configures database=1 to trigger the selection path.
 */
class DbSelectFailRedisCheck extends RedisCheck {

	/**
	 * Extension always loaded
	 *
	 * @return bool True.
	 */
	protected function is_extension_loaded(): bool {
		return true;
	}

	/**
	 * Creates FakeRedis with failed select
	 *
	 * @return \Redis FakeRedisDbSelectFail.
	 */
	protected function create_redis_instance(): \Redis {
		return new FakeRedisDbSelectFail();
	}

	/**
	 * Config with database=1 to trigger select
	 *
	 * @return array Redis config.
	 */
	protected function get_redis_config(): array {
		return [
			'host'     => '127.0.0.1',
			'port'     => 6379,
			'password' => '',
			'database' => 1,
		];
	}
}

/**
 * Testable RedisCheck that uses FakeRedisSetFalse
 */
class SetFalseRedisCheck extends RedisCheck {

	/**
	 * Extension always loaded
	 *
	 * @return bool True.
	 */
	protected function is_extension_loaded(): bool {
		return true;
	}

	/**
	 * Creates FakeRedis with SET false
	 *
	 * @return \Redis FakeRedisSetFalse.
	 */
	protected function create_redis_instance(): \Redis {
		return new FakeRedisSetFalse();
	}

	/**
	 * Config without password (no auth path)
	 *
	 * @return array Redis config.
	 */
	protected function get_redis_config(): array {
		return [
			'host'     => '127.0.0.1',
			'port'     => 6379,
			'password' => '',
			'database' => 0,
		];
	}
}

/**
 * Testable RedisCheck that uses FakeRedisSlowResponse
 */
class SlowResponseRedisCheck extends RedisCheck {

	/**
	 * Extension always loaded
	 *
	 * @return bool True.
	 */
	protected function is_extension_loaded(): bool {
		return true;
	}

	/**
	 * Creates FakeRedis with slow response
	 *
	 * @return \Redis FakeRedisSlowResponse.
	 */
	protected function create_redis_instance(): \Redis {
		return new FakeRedisSlowResponse();
	}

	/**
	 * Config without password (no auth path)
	 *
	 * @return array Redis config.
	 */
	protected function get_redis_config(): array {
		return [
			'host'     => '127.0.0.1',
			'port'     => 6379,
			'password' => '',
			'database' => 0,
		];
	}
}

/**
 * Testable RedisCheck that uses FakeRedisCloseFail
 *
 * Auth fails + close() throws exception -> covers catch in close_connection.
 */
class CloseFailRedisCheck extends RedisCheck {

	/**
	 * Extension always loaded
	 *
	 * @return bool True.
	 */
	protected function is_extension_loaded(): bool {
		return true;
	}

	/**
	 * Creates FakeRedis with failed close
	 *
	 * @return \Redis FakeRedisCloseFail.
	 */
	protected function create_redis_instance(): \Redis {
		return new FakeRedisCloseFail();
	}

	/**
	 * Config with password to trigger auth
	 *
	 * @return array Redis config.
	 */
	protected function get_redis_config(): array {
		return [
			'host'     => '127.0.0.1',
			'port'     => 6379,
			'password' => 'test_password',
			'database' => 0,
		];
	}
}

/**
 * Testable RedisCheck that uses FakeRedisCleanupFail
 *
 * SET returns false -> cleanup_and_close -> del() and close() throw exception.
 */
class CleanupFailRedisCheck extends RedisCheck {

	/**
	 * Extension always loaded
	 *
	 * @return bool True.
	 */
	protected function is_extension_loaded(): bool {
		return true;
	}

	/**
	 * Creates FakeRedis with failed cleanup
	 *
	 * @return \Redis FakeRedisCleanupFail.
	 */
	protected function create_redis_instance(): \Redis {
		return new FakeRedisCleanupFail();
	}

	/**
	 * Config without password (no auth path)
	 *
	 * @return array Redis config.
	 */
	protected function get_redis_config(): array {
		return [
			'host'     => '127.0.0.1',
			'port'     => 6379,
			'password' => '',
			'database' => 0,
		];
	}
}
