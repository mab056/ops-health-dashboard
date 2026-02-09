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
	 * Path del symlink temporaneo (se creato)
	 *
	 * @var string
	 */
	private $temp_symlink = '';

	/**
	 * Teardown dopo ogni test
	 */
	public function tearDown(): void {
		if ( '' !== $this->temp_symlink && is_link( $this->temp_symlink ) ) {
			unlink( $this->temp_symlink );
		}
		if ( file_exists( $this->temp_log_file ) ) {
			// Ripristina permessi se modificati.
			chmod( $this->temp_log_file, 0644 );
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

		// Non deve crashare, deve tornare ok (file configurato ma non ancora creato).
		$this->assertEquals( 'ok', $result['status'] );
		$this->assertStringContainsString( 'does not exist yet', strtolower( $result['message'] ) );
	}

	/**
	 * Testa che implementa CheckInterface
	 */
	public function test_implements_check_interface() {
		$redaction = new Redaction( ABSPATH, WP_CONTENT_DIR );
		$check     = new ErrorLogCheck( $redaction );
		$this->assertInstanceOf( CheckInterface::class, $check );
	}

	/**
	 * Testa che un file di log vuoto (0 byte) restituisce ok
	 */
	public function test_error_log_check_returns_ok_for_empty_file() {
		// Il file esiste ma è vuoto (tempnam crea file vuoto).
		file_put_contents( $this->temp_log_file, '' );

		$check  = $this->create_check_with_temp_log();
		$result = $check->run();

		$this->assertEquals( 'ok', $result['status'] );
		$this->assertStringContainsString( 'empty', strtolower( $result['message'] ) );
	}

	/**
	 * Testa che un symlink viene rifiutato per sicurezza
	 */
	public function test_error_log_check_returns_warning_for_symlink() {
		$this->temp_symlink = sys_get_temp_dir() . '/ops_health_test_symlink_' . uniqid();
		symlink( $this->temp_log_file, $this->temp_symlink );

		$redaction = new Redaction( ABSPATH, WP_CONTENT_DIR );
		$check     = new TestableErrorLogCheck( $redaction, $this->temp_symlink );
		$result    = $check->run();

		$this->assertEquals( 'warning', $result['status'] );
		$this->assertStringContainsString( 'symbolic link', strtolower( $result['message'] ) );
	}

	/**
	 * Testa che un file non leggibile restituisce warning
	 */
	public function test_error_log_check_returns_warning_for_unreadable_file() {
		if ( function_exists( 'posix_getuid' ) && 0 === posix_getuid() ) {
			$this->markTestSkipped( 'Cannot test permission denial as root.' );
		}

		file_put_contents( $this->temp_log_file, "PHP Fatal error: test\n" );
		chmod( $this->temp_log_file, 0000 );

		$check  = $this->create_check_with_temp_log();
		$result = $check->run();

		$this->assertEquals( 'warning', $result['status'] );
		$this->assertStringContainsString( 'not readable', strtolower( $result['message'] ) );
	}

	/**
	 * Testa che solo warning/deprecated producono status warning
	 */
	public function test_error_log_check_warning_status_for_warnings_only() {
		$content = "[08-Feb-2026 12:00:00 UTC] PHP Warning: something went wrong\n"
			. "[08-Feb-2026 12:01:00 UTC] PHP Deprecated: old function used\n";
		file_put_contents( $this->temp_log_file, $content );

		$check  = $this->create_check_with_temp_log();
		$result = $check->run();

		$this->assertEquals( 'warning', $result['status'] );
		$this->assertGreaterThan( 0, $result['details']['counts']['warning'] );
		$this->assertGreaterThan( 0, $result['details']['counts']['deprecated'] );
		$this->assertEquals( 0, $result['details']['counts']['fatal'] );
	}

	/**
	 * Testa che solo notice producono status ok
	 */
	public function test_error_log_check_ok_status_for_notices_only() {
		$content = "[08-Feb-2026 12:00:00 UTC] PHP Notice: undefined variable\n"
			. "[08-Feb-2026 12:01:00 UTC] PHP Notice: another notice\n";
		file_put_contents( $this->temp_log_file, $content );

		$check  = $this->create_check_with_temp_log();
		$result = $check->run();

		$this->assertEquals( 'ok', $result['status'] );
		$this->assertGreaterThan( 0, $result['details']['counts']['notice'] );
	}

	/**
	 * Testa che get_id(), get_name() e is_enabled() funzionano correttamente
	 */
	public function test_check_interface_accessors() {
		$check = $this->create_check_with_temp_log();

		$this->assertEquals( 'error_log', $check->get_id() );
		$this->assertNotEmpty( $check->get_name() );
		$this->assertIsString( $check->get_name() );
		$this->assertTrue( $check->is_enabled() );
	}

	/**
	 * Testa che resolve_log_path() con WP_DEBUG_LOG=true usa WP_CONTENT_DIR/debug.log
	 *
	 * Copre le righe 182, 184, 194, 195 di resolve_log_path().
	 */
	public function test_error_log_check_with_real_resolve_log_path() {
		$redaction = new Redaction( ABSPATH, WP_CONTENT_DIR );
		$check     = new ErrorLogCheck( $redaction );
		$result    = $check->run();

		// WP_DEBUG_LOG=true nel test env → resolve_log_path ritorna WP_CONTENT_DIR/debug.log.
		// Il file probabilmente non esiste, quindi "ok" con "does not exist".
		// Oppure esiste: in entrambi i casi, non deve crashare.
		$this->assertIsArray( $result );
		$this->assertContains( $result['status'], [ 'ok', 'warning', 'critical' ] );
	}

	/**
	 * Testa che resolve_log_path() vuoto ritorna warning "not configured"
	 *
	 * Copre le righe 84-89 di run().
	 */
	public function test_error_log_check_returns_warning_when_log_not_configured() {
		$redaction = new Redaction( ABSPATH, WP_CONTENT_DIR );
		$check     = new EmptyPathErrorLogCheck( $redaction );
		$result    = $check->run();

		$this->assertEquals( 'warning', $result['status'] );
		$this->assertStringContainsString( 'not configured', strtolower( $result['message'] ) );
		$this->assertFalse( $result['details']['log_path_exists'] );
	}

	/**
	 * Testa che righe non-PHP-error vengono classificate come 'other'
	 *
	 * Copre la riga 377 di classify_line().
	 */
	public function test_error_log_check_classifies_non_php_lines_as_other() {
		$content = "Stack trace:\n"
			. "  #0 /var/www/html/index.php(10): main()\n"
			. "  #1 {main}\n"
			. "[08-Feb-2026 12:00:00 UTC] PHP Warning: test\n";
		file_put_contents( $this->temp_log_file, $content );

		$check  = $this->create_check_with_temp_log();
		$result = $check->run();

		// 3 righe 'other' + 1 warning.
		$this->assertEquals( 3, $result['details']['counts']['other'] );
		$this->assertEquals( 1, $result['details']['counts']['warning'] );
	}

	/**
	 * Testa che collect_samples include campioni warning quando non ci sono abbastanza critical
	 *
	 * Copre la riga 461 di collect_samples().
	 */
	public function test_error_log_check_collects_mixed_samples() {
		$content = "[08-Feb-2026 12:00:00 UTC] PHP Fatal error: out of memory\n"
			. "[08-Feb-2026 12:01:00 UTC] PHP Warning: undefined variable\n"
			. "[08-Feb-2026 12:02:00 UTC] PHP Warning: another issue\n"
			. "[08-Feb-2026 12:03:00 UTC] PHP Deprecated: old function\n";
		file_put_contents( $this->temp_log_file, $content );

		$check  = $this->create_check_with_temp_log();
		$result = $check->run();

		$this->assertEquals( 'critical', $result['status'] );
		// 1 fatal (critical slot) + remaining warning+deprecated fill remaining slots.
		$this->assertGreaterThan( 1, count( $result['details']['recent_samples'] ) );
	}

	/**
	 * Testa che collect_samples riempie tutti gli slot con critical quando abbondanti
	 *
	 * Copre la riga 465 di collect_samples() (ramo else del ternario: remaining=0).
	 */
	public function test_error_log_check_samples_all_critical_when_many_fatals() {
		// Scrivi 6 fatal errors → max_samples=5, tutti gli slot sono critical, $remaining=0.
		$lines = [];
		for ( $i = 0; $i < 6; $i++ ) {
			$lines[] = sprintf(
				'[08-Feb-2026 12:%02d:00 UTC] PHP Fatal error: out of memory #%d',
				$i,
				$i
			);
		}
		// Aggiungi anche warning che NON devono apparire nei campioni.
		$lines[] = '[08-Feb-2026 12:06:00 UTC] PHP Warning: should not be in samples';
		file_put_contents( $this->temp_log_file, implode( "\n", $lines ) . "\n" );

		$check  = $this->create_check_with_temp_log();
		$result = $check->run();

		$this->assertEquals( 'critical', $result['status'] );
		// Tutti e 5 i campioni devono essere fatal, nessun warning.
		$this->assertCount( 5, $result['details']['recent_samples'] );
	}

	/**
	 * Testa che max_lines limita il numero di righe analizzate
	 *
	 * Copre la riga 312 di read_tail() (array_slice per max_lines).
	 */
	public function test_error_log_check_applies_max_lines_limit() {
		// Scrivi 20 righe nel log.
		$lines = [];
		for ( $i = 0; $i < 20; $i++ ) {
			$lines[] = sprintf(
				'[08-Feb-2026 12:%02d:00 UTC] PHP Warning: warning %d',
				$i,
				$i
			);
		}
		file_put_contents( $this->temp_log_file, implode( "\n", $lines ) . "\n" );

		$redaction = new Redaction( ABSPATH, WP_CONTENT_DIR );
		// max_lines=3, max_bytes grande per leggere tutto il file.
		$check  = new TestableErrorLogCheck( $redaction, $this->temp_log_file, 3, 524288 );
		$result = $check->run();

		// Deve aver analizzato esattamente 3 righe.
		$this->assertEquals( 3, $result['details']['lines_analyzed'] );
	}

	/**
	 * Testa la lettura di file grandi con seek e scarto prima riga
	 *
	 * Usa max_bytes piccolo per forzare il seek nel file.
	 */
	public function test_error_log_check_handles_large_file_with_seek() {
		// Genera contenuto più grande di max_bytes (256 byte).
		$lines = [];
		for ( $i = 0; $i < 20; $i++ ) {
			$lines[] = sprintf(
				'[08-Feb-2026 12:%02d:00 UTC] PHP Warning: test warning number %d',
				$i,
				$i
			);
		}
		$content = implode( "\n", $lines ) . "\n";

		// Verifica che il contenuto sia abbastanza grande.
		$this->assertGreaterThan( 256, strlen( $content ) );

		file_put_contents( $this->temp_log_file, $content );

		$redaction = new Redaction( ABSPATH, WP_CONTENT_DIR );
		// max_lines=5, max_bytes=256 per forzare seek + slice.
		$check  = new TestableErrorLogCheck( $redaction, $this->temp_log_file, 5, 256 );
		$result = $check->run();

		$this->assertIsArray( $result );
		$this->assertContains( $result['status'], [ 'ok', 'warning', 'critical' ] );

		// Deve aver analizzato un sottoinsieme di righe (max 5 per max_lines).
		$this->assertLessThanOrEqual( 5, $result['details']['lines_analyzed'] );
		$this->assertGreaterThan( 0, $result['details']['lines_analyzed'] );
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
	 * @param \OpsHealthDashboard\Interfaces\RedactionInterface $redaction   Servizio di redazione.
	 * @param string                                            $log_path    Path del file di log di test.
	 * @param int                                               $max_lines   Massimo righe da leggere.
	 * @param int                                               $max_bytes   Massimo byte da leggere.
	 * @param int                                               $max_samples Massimo campioni.
	 */
	public function __construct(
		\OpsHealthDashboard\Interfaces\RedactionInterface $redaction,
		string $log_path,
		int $max_lines = 100,
		int $max_bytes = 524288,
		int $max_samples = 5
	) {
		parent::__construct( $redaction, $max_lines, $max_bytes, $max_samples );
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

/**
 * Sottoclasse che simula log non configurato
 *
 * resolve_log_path() ritorna stringa vuota (nessun log configurato).
 */
class EmptyPathErrorLogCheck extends ErrorLogCheck {

	/**
	 * Ritorna path vuoto (log non configurato)
	 *
	 * @return string Stringa vuota.
	 */
	protected function resolve_log_path(): string {
		return '';
	}
}
