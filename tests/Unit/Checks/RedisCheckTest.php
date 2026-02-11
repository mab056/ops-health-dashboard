<?php
/**
 * Unit Test per RedisCheck
 *
 * Test unitario con Brain\Monkey per RedisCheck.
 * Usa Mockery partial mock per le operazioni Redis.
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
 * Unit test per RedisCheck.
 */
class RedisCheckTest extends TestCase {
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
	 * Crea un mock di RedactionInterface
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
	 * Crea un partial mock di RedisCheck con metodi protetti mockabili
	 *
	 * @param \Mockery\MockInterface $redaction Mock del servizio di redazione.
	 * @return \Mockery\MockInterface
	 */
	private function create_check_mock( $redaction ) {
		$check = Mockery::mock( RedisCheck::class, [ $redaction ] )
			->makePartial()
			->shouldAllowMockingProtectedMethods();
		return $check;
	}

	/**
	 * Crea un mock di Redis
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
	 * Configura le aspettative i18n standard
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
	 * Testa che la classe NON è final
	 */
	public function test_class_is_not_final() {
		$reflection = new \ReflectionClass( RedisCheck::class );
		$this->assertFalse( $reflection->isFinal(), 'RedisCheck should NOT be final' );
	}

	/**
	 * Testa che NON esistono metodi static
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
	 * Testa che NON esistono proprietà static
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
	 * Testa che RedisCheck implementa CheckInterface
	 */
	public function test_implements_check_interface() {
		$redaction = $this->create_redaction_mock();
		$check     = new RedisCheck( $redaction );
		$this->assertInstanceOf( CheckInterface::class, $check );
	}

	/**
	 * Testa che get_id() ritorna 'redis'
	 */
	public function test_get_id_returns_redis() {
		$redaction = $this->create_redaction_mock();
		$check     = new RedisCheck( $redaction );
		$this->assertEquals( 'redis', $check->get_id() );
	}

	/**
	 * Testa che get_name() ritorna il nome corretto
	 */
	public function test_get_name_returns_correct_name() {
		$redaction = $this->create_redaction_mock();
		$check     = new RedisCheck( $redaction );

		$this->mock_i18n();

		$this->assertEquals( 'Redis Cache', $check->get_name() );
	}

	/**
	 * Testa che is_enabled() ritorna true
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
	 * Testa che run() ritorna warning quando l'estensione Redis non è caricata
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
	 * Testa che run() procede quando l'estensione è caricata
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
	 * Testa che run() ritorna warning quando la connessione fallisce
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
	 * Testa che usa l'host di default quando la costante non è definita
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
	 * Testa che usa la porta di default quando la costante non è definita
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
	 * Testa che autentica quando la password è configurata
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
	 * Testa che ritorna warning quando l'autenticazione fallisce
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
	 * Testa che run() ritorna ok quando lo smoke test passa
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
	 * Testa che run() ritorna warning quando SET fallisce
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
	 * Testa che run() ritorna warning quando GET non matcha il valore
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
	 * Testa che run() include il tempo di risposta nei dettagli
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
	 * Testa che run() ritorna warning quando il tempo di risposta è lento
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
				// Simula 300ms di latenza (soglia 100ms, margine 3x).
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
	 * Testa che run() ha tutte le chiavi richieste nel risultato
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
	 * Testa che run() misura la durata
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
	 * Testa che run() usa i18n per i messaggi
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
	 * Testa che l'host viene redatto nei dettagli
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
	 * Testa che gli errori vengono redatti nei dettagli
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
	 * Testa che seleziona il database quando configurato
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
	 * Testa che close_connection() gestisce eccezione senza propagarla
	 *
	 * Quando auth fallisce, close_connection viene chiamato.
	 * Se anche close() lancia, deve essere inghiottita.
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
		// close() lancia eccezione — deve essere inghiottita.
		$redis->shouldReceive( 'close' )
			->andThrow( new \Exception( 'Connection already closed' ) );

		$this->mock_i18n();

		$result = $check->run();

		$this->assertEquals( 'warning', $result['status'] );
		$this->assertStringContainsString( 'auth', strtolower( $result['message'] ) );
	}

	/**
	 * Testa che cleanup_and_close() gestisce eccezione su del() senza propagarla
	 *
	 * Quando SET ritorna false, cleanup_and_close tenta del() + close().
	 * Se del() lancia, deve essere inghiottita.
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
		// del() durante cleanup lancia eccezione — deve essere inghiottita.
		$redis->shouldReceive( 'del' )
			->andThrow( new \Exception( 'READONLY cannot del' ) );
		$redis->shouldReceive( 'close' )->byDefault();

		$this->mock_i18n();

		$result = $check->run();

		$this->assertEquals( 'warning', $result['status'] );
		$this->assertStringContainsString( 'smoke test', strtolower( $result['message'] ) );
	}

	/**
	 * Testa che run() ritorna warning quando lo smoke test lancia eccezione
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
	 * Testa che restituisce warning quando database selection fallisce
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
					'database' => 999, // Database non valido.
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
	 * Testa che is_extension_loaded ritorna un booleano
	 *
	 * Copre la riga 237: `extension_loaded('redis')`.
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
	 * Testa che create_redis_instance ritorna un'istanza Redis
	 *
	 * Copre la riga 246: `new \Redis()`.
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
	 * Testa che TypeError durante connessione viene catturato (catch Throwable)
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
	 * Testa che TypeError durante smoke test viene catturato (catch Throwable)
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
	 * Testa che TypeError in close_connection viene inghiottito (catch Throwable)
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
