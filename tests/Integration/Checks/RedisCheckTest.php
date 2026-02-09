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
