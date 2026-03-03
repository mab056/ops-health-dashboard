<?php
/**
 * Integration Test for ErrorLogCheck
 *
 * Integration test with filesystem and real WordPress.
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
 * Integration test for ErrorLogCheck with real WordPress.
 */
class ErrorLogCheckTest extends WP_UnitTestCase {

	/**
	 * Temporary log file for tests
	 *
	 * @var string
	 */
	private $temp_log_file;

	/**
	 * Setup for each test
	 */
	public function setUp(): void {
		parent::setUp();
		$this->temp_log_file = tempnam( sys_get_temp_dir(), 'ops_health_test_log_' );
	}

	/**
	 * Temporary symlink path (if created)
	 *
	 * @var string
	 */
	private $temp_symlink = '';

	/**
	 * Teardown after each test
	 */
	public function tearDown(): void {
		if ( '' !== $this->temp_symlink && is_link( $this->temp_symlink ) ) {
			unlink( $this->temp_symlink );
		}
		if ( file_exists( $this->temp_log_file ) ) {
			// Restore permissions if modified.
			chmod( $this->temp_log_file, 0644 );
			unlink( $this->temp_log_file );
		}
		parent::tearDown();
	}

	/**
	 * Creates an ErrorLogCheck with the temporary file
	 *
	 * @return ErrorLogCheck
	 */
	private function create_check_with_temp_log() {
		$redaction = new Redaction( ABSPATH, WP_CONTENT_DIR );
		$check     = new TestableErrorLogCheck( $redaction, $this->temp_log_file );
		return $check;
	}

	/**
	 * Verifies that ErrorLogCheck can run without crash
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
	 * Verifies that the result has a valid structure
	 */
	public function test_error_log_check_returns_valid_structure() {
		// Write content to the test log.
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

		// Verify details structure.
		$this->assertArrayHasKey( 'counts', $result['details'] );
		$this->assertArrayHasKey( 'lines_analyzed', $result['details'] );
		$this->assertArrayHasKey( 'recent_samples', $result['details'] );
	}

	/**
	 * Tests end-to-end check with real Redaction service
	 */
	public function test_error_log_check_with_real_redaction() {
		// Write content with sensitive data.
		$content = sprintf(
			"[08-Feb-2026 12:00:00 UTC] PHP Fatal error: in %swp-config.php on line 10\n"
			. "[08-Feb-2026 12:01:00 UTC] PHP Warning: for admin@example.com\n",
			ABSPATH
		);
		file_put_contents( $this->temp_log_file, $content );

		$check  = $this->create_check_with_temp_log();
		$result = $check->run();

		// Must be critical due to the fatal error.
		$this->assertEquals( 'critical', $result['status'] );

		// Samples must be redacted.
		$samples_json = json_encode( $result['details']['recent_samples'] );
		$this->assertStringNotContainsString( ABSPATH, $samples_json );
		$this->assertStringNotContainsString( 'admin@example.com', $samples_json );
	}

	/**
	 * Verifies that missing log file is handled gracefully
	 */
	public function test_error_log_check_handles_missing_log_gracefully() {
		// Remove the temporary file.
		unlink( $this->temp_log_file );

		$check  = $this->create_check_with_temp_log();
		$result = $check->run();

		// Must not crash, should return ok (file configured but not yet created).
		$this->assertEquals( 'ok', $result['status'] );
		$this->assertStringContainsString( 'does not exist yet', strtolower( $result['message'] ) );
	}

	/**
	 * Verifies that it implements CheckInterface
	 */
	public function test_implements_check_interface() {
		$redaction = new Redaction( ABSPATH, WP_CONTENT_DIR );
		$check     = new ErrorLogCheck( $redaction );
		$this->assertInstanceOf( CheckInterface::class, $check );
	}

	/**
	 * Verifies that an empty log file (0 bytes) returns ok
	 */
	public function test_error_log_check_returns_ok_for_empty_file() {
		// File exists but is empty (tempnam creates empty file).
		file_put_contents( $this->temp_log_file, '' );

		$check  = $this->create_check_with_temp_log();
		$result = $check->run();

		$this->assertEquals( 'ok', $result['status'] );
		$this->assertStringContainsString( 'empty', strtolower( $result['message'] ) );
	}

	/**
	 * Verifies that a symlink is rejected for security
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
	 * Verifies that an unreadable file returns warning
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
	 * Verifies that only warning/deprecated produce warning status
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
	 * Verifies that only notices produce ok status
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
	 * Verifies that get_id(), get_name() and is_enabled() work correctly
	 */
	public function test_check_interface_accessors() {
		$check = $this->create_check_with_temp_log();

		$this->assertEquals( 'error_log', $check->get_id() );
		$this->assertNotEmpty( $check->get_name() );
		$this->assertIsString( $check->get_name() );
		$this->assertTrue( $check->is_enabled() );
	}

	/**
	 * Verifies that resolve_log_path() with WP_DEBUG_LOG=true uses WP_CONTENT_DIR/debug.log
	 *
	 * Covers lines 182, 184, 194, 195 of resolve_log_path().
	 */
	public function test_error_log_check_with_real_resolve_log_path() {
		$redaction = new Redaction( ABSPATH, WP_CONTENT_DIR );
		$check     = new ErrorLogCheck( $redaction );
		$result    = $check->run();

		// WP_DEBUG_LOG=true in the test env -> resolve_log_path returns WP_CONTENT_DIR/debug.log.
		// The file probably does not exist, so "ok" with "does not exist".
		// Or it exists: in both cases, it must not crash.
		$this->assertIsArray( $result );
		$this->assertContains( $result['status'], [ 'ok', 'warning', 'critical' ] );
	}

	/**
	 * Verifies that empty resolve_log_path() returns warning "not configured"
	 *
	 * Covers lines 84-89 of run().
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
	 * Verifies that non-PHP-error lines are classified as 'other'
	 *
	 * Covers line 377 of classify_line().
	 */
	public function test_error_log_check_classifies_non_php_lines_as_other() {
		$content = "Stack trace:\n"
			. "  #0 /var/www/html/index.php(10): main()\n"
			. "  #1 {main}\n"
			. "[08-Feb-2026 12:00:00 UTC] PHP Warning: test\n";
		file_put_contents( $this->temp_log_file, $content );

		$check  = $this->create_check_with_temp_log();
		$result = $check->run();

		// 3 'other' lines + 1 warning.
		$this->assertEquals( 3, $result['details']['counts']['other'] );
		$this->assertEquals( 1, $result['details']['counts']['warning'] );
	}

	/**
	 * Verifies that collect_samples includes warning samples when not enough critical ones
	 *
	 * Covers line 461 of collect_samples().
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
	 * Verifies that collect_samples fills all slots with critical when abundant
	 *
	 * Covers line 465 of collect_samples() (else branch of ternary: remaining=0).
	 */
	public function test_error_log_check_samples_all_critical_when_many_fatals() {
		// Write 6 fatal errors -> max_samples=5, all slots are critical, $remaining=0.
		$lines = [];
		for ( $i = 0; $i < 6; $i++ ) {
			$lines[] = sprintf(
				'[08-Feb-2026 12:%02d:00 UTC] PHP Fatal error: out of memory #%d',
				$i,
				$i
			);
		}
		// Also add warnings that must NOT appear in samples.
		$lines[] = '[08-Feb-2026 12:06:00 UTC] PHP Warning: should not be in samples';
		file_put_contents( $this->temp_log_file, implode( "\n", $lines ) . "\n" );

		$check  = $this->create_check_with_temp_log();
		$result = $check->run();

		$this->assertEquals( 'critical', $result['status'] );
		// All 5 samples must be fatal, no warnings.
		$this->assertCount( 5, $result['details']['recent_samples'] );
	}

	/**
	 * Verifies that max_lines limits the number of lines analyzed
	 *
	 * Covers line 312 of read_tail() (array_slice for max_lines).
	 */
	public function test_error_log_check_applies_max_lines_limit() {
		// Write 20 lines to the log.
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
		// max_lines=3, large max_bytes to read the entire file.
		$check  = new TestableErrorLogCheck( $redaction, $this->temp_log_file, 3, 524288 );
		$result = $check->run();

		// Must have analyzed exactly 3 lines.
		$this->assertEquals( 3, $result['details']['lines_analyzed'] );
	}

	/**
	 * Tests reading large files with seek and first line discard
	 *
	 * Uses small max_bytes to force seek in the file.
	 */
	public function test_error_log_check_handles_large_file_with_seek() {
		// Generate content larger than max_bytes (256 bytes).
		$lines = [];
		for ( $i = 0; $i < 20; $i++ ) {
			$lines[] = sprintf(
				'[08-Feb-2026 12:%02d:00 UTC] PHP Warning: test warning number %d',
				$i,
				$i
			);
		}
		$content = implode( "\n", $lines ) . "\n";

		// Verify that the content is large enough.
		$this->assertGreaterThan( 256, strlen( $content ) );

		file_put_contents( $this->temp_log_file, $content );

		$redaction = new Redaction( ABSPATH, WP_CONTENT_DIR );
		// max_lines=5, max_bytes=256 to force seek + slice.
		$check  = new TestableErrorLogCheck( $redaction, $this->temp_log_file, 5, 256 );
		$result = $check->run();

		$this->assertIsArray( $result );
		$this->assertContains( $result['status'], [ 'ok', 'warning', 'critical' ] );

		// Must have analyzed a subset of lines (max 5 per max_lines).
		$this->assertLessThanOrEqual( 5, $result['details']['lines_analyzed'] );
		$this->assertGreaterThan( 0, $result['details']['lines_analyzed'] );
	}
}

/**
 * Testable subclass to inject the log file path
 *
 * Overrides resolve_log_path() to use a temporary file.
 */
class TestableErrorLogCheck extends ErrorLogCheck {

	/**
	 * Test log file path
	 *
	 * @var string
	 */
	private $test_log_path;

	/**
	 * Constructor
	 *
	 * @param \OpsHealthDashboard\Interfaces\RedactionInterface $redaction   Redaction service.
	 * @param string                                            $log_path    Test log file path.
	 * @param int                                               $max_lines   Maximum lines to read.
	 * @param int                                               $max_bytes   Maximum bytes to read.
	 * @param int                                               $max_samples Maximum samples.
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
	 * Overrides resolve_log_path to use the test file
	 *
	 * @return string Test log file path.
	 */
	protected function resolve_log_path(): string {
		return $this->test_log_path;
	}
}

/**
 * Subclass that simulates unconfigured log
 *
 * resolve_log_path() returns empty string (no log configured).
 */
class EmptyPathErrorLogCheck extends ErrorLogCheck {

	/**
	 * Returns empty path (log not configured)
	 *
	 * @return string Empty string.
	 */
	protected function resolve_log_path(): string {
		return '';
	}
}
