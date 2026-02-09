<?php
/**
 * Integration Test per ErrorLogCheck
 *
 * Test di integrazione con filesystem e WordPress reale.
 *
 * @package OpsHealthDashboard\Tests\Integration\Checks
 */

namespace OpsHealthDashboard\Tests\Integration\Checks;

use OpsHealthDashboard\Checks\ErrorLogCheck;
use OpsHealthDashboard\Interfaces\CheckInterface;
use OpsHealthDashboard\Services\Redaction;
use WP_UnitTestCase;

/**
 * Class ErrorLogCheckTest
 *
 * Integration test per ErrorLogCheck con WordPress reale.
 */
class ErrorLogCheckTest extends WP_UnitTestCase {

	/**
	 * File di log temporaneo per i test
	 *
	 * @var string
	 */
	private $temp_log_file;

	/**
	 * Setup per ogni test
	 */
	public function setUp(): void {
		parent::setUp();
		$this->temp_log_file = tempnam( sys_get_temp_dir(), 'ops_health_test_log_' );
	}

	/**
	 * Teardown dopo ogni test
	 */
	public function tearDown(): void {
		if ( file_exists( $this->temp_log_file ) ) {
			unlink( $this->temp_log_file );
		}
		parent::tearDown();
	}

	/**
	 * Crea un ErrorLogCheck con il file temporaneo
	 *
	 * @return ErrorLogCheck
	 */
	private function create_check_with_temp_log() {
		$redaction = new Redaction( ABSPATH, WP_CONTENT_DIR );
		$check     = new TestableErrorLogCheck( $redaction, $this->temp_log_file );
		return $check;
	}

	/**
	 * Testa che ErrorLogCheck può eseguire senza crash
	 */
	public function test_error_log_check_runs_successfully() {
		$check  = $this->create_check_with_temp_log();
		$result = $check->run();

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'status', $result );
		$this->assertArrayHasKey( 'message', $result );
		$this->assertArrayHasKey( 'details', $result );
		$this->assertArrayHasKey( 'duration', $result );
	}

	/**
	 * Testa che il risultato ha una struttura valida
	 */
	public function test_error_log_check_returns_valid_structure() {
		// Scrivi contenuto nel log di test.
		file_put_contents(
			$this->temp_log_file,
			"[08-Feb-2026 12:00:00 UTC] PHP Warning: test warning\n"
			. "[08-Feb-2026 12:01:00 UTC] PHP Notice: test notice\n"
		);

		$check  = $this->create_check_with_temp_log();
		$result = $check->run();

		$this->assertContains( $result['status'], [ 'ok', 'warning', 'critical' ] );
		$this->assertIsString( $result['message'] );
		$this->assertIsArray( $result['details'] );
		$this->assertIsFloat( $result['duration'] );

		// Verifica struttura dettagli.
		$this->assertArrayHasKey( 'counts', $result['details'] );
		$this->assertArrayHasKey( 'lines_analyzed', $result['details'] );
		$this->assertArrayHasKey( 'recent_samples', $result['details'] );
	}

	/**
	 * Testa il check end-to-end con servizio Redaction reale
	 */
	public function test_error_log_check_with_real_redaction() {
		// Scrivi contenuto con dati sensibili.
		$content = sprintf(
			"[08-Feb-2026 12:00:00 UTC] PHP Fatal error: in %swp-config.php on line 10\n"
			. "[08-Feb-2026 12:01:00 UTC] PHP Warning: for admin@example.com\n",
			ABSPATH
		);
		file_put_contents( $this->temp_log_file, $content );

		$check  = $this->create_check_with_temp_log();
		$result = $check->run();

		// Deve essere critical per il fatal error.
		$this->assertEquals( 'critical', $result['status'] );

		// I campioni devono essere redatti.
		$samples_json = json_encode( $result['details']['recent_samples'] );
		$this->assertStringNotContainsString( ABSPATH, $samples_json );
		$this->assertStringNotContainsString( 'admin@example.com', $samples_json );
	}

	/**
	 * Testa che gestisce file di log mancante con grazia
	 */
	public function test_error_log_check_handles_missing_log_gracefully() {
		// Rimuovi il file temporaneo.
		unlink( $this->temp_log_file );

		$check  = $this->create_check_with_temp_log();
		$result = $check->run();

		// Non deve crashare, deve tornare warning.
		$this->assertEquals( 'warning', $result['status'] );
	}

	/**
	 * Testa che implementa CheckInterface
	 */
	public function test_implements_check_interface() {
		$redaction = new Redaction( ABSPATH, WP_CONTENT_DIR );
		$check     = new ErrorLogCheck( $redaction );
		$this->assertInstanceOf( CheckInterface::class, $check );
	}
}

/**
 * Sottoclasse testabile per iniettare il path del file di log
 *
 * Sovrascrive resolve_log_path() per usare un file temporaneo.
 */
class TestableErrorLogCheck extends ErrorLogCheck {

	/**
	 * Path del file di log di test
	 *
	 * @var string
	 */
	private $test_log_path;

	/**
	 * Constructor
	 *
	 * @param \OpsHealthDashboard\Interfaces\RedactionInterface $redaction Servizio di redazione.
	 * @param string                                            $log_path  Path del file di log di test.
	 */
	public function __construct(
		\OpsHealthDashboard\Interfaces\RedactionInterface $redaction,
		string $log_path
	) {
		parent::__construct( $redaction );
		$this->test_log_path = $log_path;
	}

	/**
	 * Sovrascrive resolve_log_path per usare il file di test
	 *
	 * @return string Path del file di log di test.
	 */
	protected function resolve_log_path(): string {
		return $this->test_log_path;
	}
}
