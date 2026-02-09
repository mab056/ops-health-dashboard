<?php
/**
 * Integration Test per RedisCheck
 *
 * Test di integrazione con WordPress reale.
 * Usa TestableRedisCheck per controllare i metodi protetti.
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
 * Integration test per RedisCheck con WordPress reale.
 */
class RedisCheckTest extends WP_UnitTestCase {

	/**
	 * Testa che RedisCheck implementa CheckInterface
	 */
	public function test_redis_check_implements_interface() {
		$redaction = new Redaction( ABSPATH, WP_CONTENT_DIR );
		$check     = new RedisCheck( $redaction );
		$this->assertInstanceOf( CheckInterface::class, $check );
	}

	/**
	 * Testa che RedisCheck esegue senza crash
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
	 * Testa che RedisCheck ritorna una struttura valida
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
	 * Testa graceful degradation quando l'estensione non è caricata
	 */
	public function test_redis_check_graceful_when_no_extension() {
		$redaction = new Redaction( ABSPATH, WP_CONTENT_DIR );
		$check     = new TestableRedisCheck( $redaction, false, null );
		$result    = $check->run();

		$this->assertEquals( 'warning', $result['status'] );
		$this->assertStringContainsString( 'extension', strtolower( $result['message'] ) );
	}

	/**
	 * Testa graceful degradation quando la connessione fallisce
	 */
	public function test_redis_check_graceful_when_connection_fails() {
		$redaction = new Redaction( ABSPATH, WP_CONTENT_DIR );
		$check     = new TestableRedisCheck( $redaction, true, new \Exception( 'Connection refused' ) );
		$result    = $check->run();

		$this->assertEquals( 'warning', $result['status'] );
		$this->assertStringContainsString( 'connection', strtolower( $result['message'] ) );
	}

	/**
	 * Testa che host viene redatto con il servizio Redaction reale
	 */
	public function test_redis_check_with_real_redaction() {
		$redaction = new Redaction( ABSPATH, WP_CONTENT_DIR );
		$check     = new RedisCheck( $redaction );
		$result    = $check->run();

		// Indipendentemente dallo stato, ABSPATH non deve apparire.
		$as_string = json_encode( $result );
		$this->assertStringNotContainsString( ABSPATH, $as_string );
	}

	/**
	 * Testa graceful degradation quando l'autenticazione fallisce
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
	 * Testa graceful degradation quando lo smoke test lancia eccezione
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
	 * Testa graceful degradation quando GET restituisce valore diverso
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
	 * Testa che get_id(), get_name() e is_enabled() funzionano correttamente
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
	 * Testa il successo completo quando Redis è disponibile
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
	 * Testa graceful degradation quando la selezione database fallisce
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
	 * Testa graceful degradation quando SET restituisce false
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
	 * Testa che Redis restituisce warning per risposta lenta (> 100ms)
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
	 * Testa che close_connection ignora eccezione da close()
	 *
	 * Copre la riga 282 di close_connection().
	 *
	 * @requires extension redis
	 */
	public function test_redis_check_close_connection_ignores_exception() {
		$redaction = new Redaction( ABSPATH, WP_CONTENT_DIR );
		$check     = new CloseFailRedisCheck( $redaction );
		$result    = $check->run();

		// Auth fallisce → close_connection viene chiamato, close() lancia eccezione → ignorata.
		$this->assertEquals( 'warning', $result['status'] );
		$this->assertStringContainsString( 'authentication', strtolower( $result['message'] ) );
	}

	/**
	 * Testa che cleanup_and_close ignora eccezione da del()
	 *
	 * Copre la riga 297 di cleanup_and_close().
	 *
	 * @requires extension redis
	 */
	public function test_redis_check_cleanup_and_close_ignores_exception() {
		$redaction = new Redaction( ABSPATH, WP_CONTENT_DIR );
		$check     = new CleanupFailRedisCheck( $redaction );
		$result    = $check->run();

		// SET ritorna false → cleanup_and_close → del() lancia eccezione → ignorata.
		$this->assertEquals( 'warning', $result['status'] );
		$this->assertStringContainsString( 'SET returned false', $result['message'] );
	}
}

/**
 * Sottoclasse testabile per controllare i metodi protetti
 *
 * Permette di simulare l'assenza dell'estensione Redis e fallimenti di connessione.
 */
class TestableRedisCheck extends RedisCheck {

	/**
	 * Se l'estensione è "caricata"
	 *
	 * @var bool
	 */
	private $extension_loaded;

	/**
	 * Eccezione da lanciare durante la creazione dell'istanza Redis
	 *
	 * @var \Exception|null
	 */
	private $connect_exception;

	/**
	 * Constructor
	 *
	 * @param \OpsHealthDashboard\Interfaces\RedactionInterface $redaction         Servizio di redazione.
	 * @param bool                                               $extension_loaded  Se simulare estensione caricata.
	 * @param \Exception|null                                    $connect_exception Eccezione per simulare fallimento.
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
	 * Override per controllare il rilevamento estensione
	 *
	 * @return bool Valore configurato nel costruttore.
	 */
	protected function is_extension_loaded(): bool {
		return $this->extension_loaded;
	}

	/**
	 * Override per simulare fallimenti di connessione
	 *
	 * @return \Redis Istanza Redis (o lancia eccezione).
	 * @throws \Exception Se configurato per fallire.
	 */
	protected function create_redis_instance(): \Redis {
		if ( null !== $this->connect_exception ) {
			throw $this->connect_exception;
		}
		return parent::create_redis_instance();
	}
}

// Carica helper FakeRedis con firme compatibili con la versione PHP corrente.
// Le classi FakeRedis* estendono \Redis, quindi richiedono ext-redis.
// PHP 8.0+ phpredis dichiara union return types; PHP 7.4 no.
if ( extension_loaded( 'redis' ) ) {
	if ( PHP_VERSION_ID >= 80000 ) {
		require_once __DIR__ . '/FakeRedisHelpers.php';
	} else {
		require_once __DIR__ . '/FakeRedisHelpersLegacy.php';
	}
}

/**
 * RedisCheck testabile che usa FakeRedisAuthFail
 *
 * Simula connessione riuscita ma autenticazione fallita.
 */
class AuthFailRedisCheck extends RedisCheck {

	/**
	 * Estensione sempre caricata
	 *
	 * @return bool True.
	 */
	protected function is_extension_loaded(): bool {
		return true;
	}

	/**
	 * Crea FakeRedis con auth fallita
	 *
	 * @return \Redis FakeRedisAuthFail.
	 */
	protected function create_redis_instance(): \Redis {
		return new FakeRedisAuthFail();
	}

	/**
	 * Config con password per triggerare auth
	 *
	 * @return array Config Redis.
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
 * RedisCheck testabile che usa FakeRedisSmokeTestFail
 */
class SmokeFailRedisCheck extends RedisCheck {

	/**
	 * Estensione sempre caricata
	 *
	 * @return bool True.
	 */
	protected function is_extension_loaded(): bool {
		return true;
	}

	/**
	 * Crea FakeRedis con smoke test fallito
	 *
	 * @return \Redis FakeRedisSmokeTestFail.
	 */
	protected function create_redis_instance(): \Redis {
		return new FakeRedisSmokeTestFail();
	}

	/**
	 * Config senza password (no auth path)
	 *
	 * @return array Config Redis.
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
 * RedisCheck testabile che usa FakeRedisGetMismatch
 */
class MismatchRedisCheck extends RedisCheck {

	/**
	 * Estensione sempre caricata
	 *
	 * @return bool True.
	 */
	protected function is_extension_loaded(): bool {
		return true;
	}

	/**
	 * Crea FakeRedis con GET mismatch
	 *
	 * @return \Redis FakeRedisGetMismatch.
	 */
	protected function create_redis_instance(): \Redis {
		return new FakeRedisGetMismatch();
	}

	/**
	 * Config senza password (no auth path)
	 *
	 * @return array Config Redis.
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
 * RedisCheck testabile che usa FakeRedisDbSelectFail
 *
 * Configura database=1 per triggerare il percorso di selezione.
 */
class DbSelectFailRedisCheck extends RedisCheck {

	/**
	 * Estensione sempre caricata
	 *
	 * @return bool True.
	 */
	protected function is_extension_loaded(): bool {
		return true;
	}

	/**
	 * Crea FakeRedis con select fallita
	 *
	 * @return \Redis FakeRedisDbSelectFail.
	 */
	protected function create_redis_instance(): \Redis {
		return new FakeRedisDbSelectFail();
	}

	/**
	 * Config con database=1 per triggerare select
	 *
	 * @return array Config Redis.
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
 * RedisCheck testabile che usa FakeRedisSetFalse
 */
class SetFalseRedisCheck extends RedisCheck {

	/**
	 * Estensione sempre caricata
	 *
	 * @return bool True.
	 */
	protected function is_extension_loaded(): bool {
		return true;
	}

	/**
	 * Crea FakeRedis con SET false
	 *
	 * @return \Redis FakeRedisSetFalse.
	 */
	protected function create_redis_instance(): \Redis {
		return new FakeRedisSetFalse();
	}

	/**
	 * Config senza password (no auth path)
	 *
	 * @return array Config Redis.
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
 * RedisCheck testabile che usa FakeRedisSlowResponse
 */
class SlowResponseRedisCheck extends RedisCheck {

	/**
	 * Estensione sempre caricata
	 *
	 * @return bool True.
	 */
	protected function is_extension_loaded(): bool {
		return true;
	}

	/**
	 * Crea FakeRedis con risposta lenta
	 *
	 * @return \Redis FakeRedisSlowResponse.
	 */
	protected function create_redis_instance(): \Redis {
		return new FakeRedisSlowResponse();
	}

	/**
	 * Config senza password (no auth path)
	 *
	 * @return array Config Redis.
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
 * RedisCheck testabile che usa FakeRedisCloseFail
 *
 * Auth fallisce + close() lancia eccezione → copre catch in close_connection.
 */
class CloseFailRedisCheck extends RedisCheck {

	/**
	 * Estensione sempre caricata
	 *
	 * @return bool True.
	 */
	protected function is_extension_loaded(): bool {
		return true;
	}

	/**
	 * Crea FakeRedis con close fallita
	 *
	 * @return \Redis FakeRedisCloseFail.
	 */
	protected function create_redis_instance(): \Redis {
		return new FakeRedisCloseFail();
	}

	/**
	 * Config con password per triggerare auth
	 *
	 * @return array Config Redis.
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
 * RedisCheck testabile che usa FakeRedisCleanupFail
 *
 * SET ritorna false → cleanup_and_close → del() e close() lanciano eccezione.
 */
class CleanupFailRedisCheck extends RedisCheck {

	/**
	 * Estensione sempre caricata
	 *
	 * @return bool True.
	 */
	protected function is_extension_loaded(): bool {
		return true;
	}

	/**
	 * Crea FakeRedis con cleanup fallito
	 *
	 * @return \Redis FakeRedisCleanupFail.
	 */
	protected function create_redis_instance(): \Redis {
		return new FakeRedisCleanupFail();
	}

	/**
	 * Config senza password (no auth path)
	 *
	 * @return array Config Redis.
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
