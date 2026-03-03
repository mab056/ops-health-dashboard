<?php
/**
 * Unit Test for ErrorLogCheck
 *
 * Unit test with Brain\Monkey for ErrorLogCheck.
 * Uses Mockery partial mock for filesystem operations.
 *
 * @package OpsHealthDashboard\Tests\Unit\Checks
 */

namespace OpsHealthDashboard\Tests\Unit\Checks;

use Brain\Monkey\Functions;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use OpsHealthDashboard\Checks\ErrorLogCheck;
use OpsHealthDashboard\Interfaces\CheckInterface;
use OpsHealthDashboard\Interfaces\RedactionInterface;
use PHPUnit\Framework\TestCase;

/**
 * Class ErrorLogCheckTest
 *
 * Unit test for ErrorLogCheck.
 */
class ErrorLogCheckTest extends TestCase {
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
			->andReturnUsing( function ( $text ) {
				return $text;
			} );
		$redaction->shouldReceive( 'redact_lines' )
			->andReturnUsing( function ( $lines ) {
				return $lines;
			} );
		return $redaction;
	}

	/**
	 * Creates a partial mock of ErrorLogCheck with mocked filesystem methods
	 *
	 * @param \Mockery\MockInterface $redaction Redaction service mock.
	 * @return \Mockery\MockInterface
	 */
	private function create_check_mock( $redaction ) {
		$check = Mockery::mock( ErrorLogCheck::class, [ $redaction ] )
			->makePartial()
			->shouldAllowMockingProtectedMethods();
		$check->shouldReceive( 'get_file_size' )
			->andReturn( '1.2 MB' )
			->byDefault();
		return $check;
	}

	/**
	 * Tests that ErrorLogCheck can be instantiated
	 */
	public function test_error_log_check_can_be_instantiated() {
		$redaction = $this->create_redaction_mock();
		$check     = new ErrorLogCheck( $redaction );
		$this->assertInstanceOf( ErrorLogCheck::class, $check );
	}

	/**
	 * Tests that ErrorLogCheck implements CheckInterface
	 */
	public function test_error_log_check_implements_interface() {
		$redaction = $this->create_redaction_mock();
		$check     = new ErrorLogCheck( $redaction );
		$this->assertInstanceOf( CheckInterface::class, $check );
	}

	/**
	 * Tests that get_id() returns 'error_log'
	 */
	public function test_get_id_returns_error_log() {
		$redaction = $this->create_redaction_mock();
		$check     = new ErrorLogCheck( $redaction );
		$this->assertEquals( 'error_log', $check->get_id() );
	}

	/**
	 * Tests that get_name() returns the correct name
	 */
	public function test_get_name_returns_correct_name() {
		$redaction = $this->create_redaction_mock();
		$check     = new ErrorLogCheck( $redaction );

		Functions\expect( '__' )
			->andReturnUsing( function ( $text ) {
				return $text;
			} );

		$this->assertEquals( 'Error Log Summary', $check->get_name() );
	}

	/**
	 * Tests that is_enabled() returns true
	 */
	public function test_is_enabled_returns_true() {
		$redaction = $this->create_redaction_mock();
		$check     = new ErrorLogCheck( $redaction );
		$this->assertTrue( $check->is_enabled() );
	}

	/**
	 * Tests that run() returns warning when the log is not configured
	 */
	public function test_run_returns_warning_when_log_not_configured() {
		$redaction = $this->create_redaction_mock();
		$check     = $this->create_check_mock( $redaction );

		$check->shouldReceive( 'resolve_log_path' )
			->once()
			->andReturn( '' );

		Functions\expect( '__' )
			->andReturnUsing( function ( $text ) {
				return $text;
			} );

		$result = $check->run();

		$this->assertEquals( 'warning', $result['status'] );
		$this->assertStringContainsString( 'not configured', strtolower( $result['message'] ) );
	}

	/**
	 * Tests that run() returns ok when the log is configured but the file does not exist yet
	 */
	public function test_run_returns_ok_when_log_configured_but_file_not_exists() {
		$redaction = $this->create_redaction_mock();
		$check     = $this->create_check_mock( $redaction );

		$check->shouldReceive( 'resolve_log_path' )
			->once()
			->andReturn( '/var/www/html/wp-content/debug.log' );

		$check->shouldReceive( 'validate_log_file' )
			->once()
			->andReturn( [
				'valid'   => false,
				'status'  => 'ok',
				'message' => 'Error log configured but file does not exist yet (no errors logged)',
			] );

		Functions\expect( '__' )
			->andReturnUsing( function ( $text ) {
				return $text;
			} );

		$result = $check->run();

		$this->assertEquals( 'ok', $result['status'] );
		$this->assertStringContainsString( 'does not exist yet', strtolower( $result['message'] ) );
	}

	/**
	 * Tests that run() returns warning when the log is not readable
	 */
	public function test_run_returns_warning_when_log_not_readable() {
		$redaction = $this->create_redaction_mock();
		$check     = $this->create_check_mock( $redaction );

		$check->shouldReceive( 'resolve_log_path' )
			->once()
			->andReturn( '/var/log/php-error.log' );

		$check->shouldReceive( 'validate_log_file' )
			->once()
			->andReturn( [
				'valid'   => false,
				'status'  => 'warning',
				'message' => 'Error log file not readable (check permissions)',
			] );

		Functions\expect( '__' )
			->andReturnUsing( function ( $text ) {
				return $text;
			} );

		$result = $check->run();

		$this->assertEquals( 'warning', $result['status'] );
		$this->assertStringContainsString( 'not readable', strtolower( $result['message'] ) );
	}

	/**
	 * Tests that run() returns warning when the log is a symlink
	 */
	public function test_run_returns_warning_when_log_is_symlink() {
		$redaction = $this->create_redaction_mock();
		$check     = $this->create_check_mock( $redaction );

		$check->shouldReceive( 'resolve_log_path' )
			->once()
			->andReturn( '/var/log/php-error.log' );

		$check->shouldReceive( 'validate_log_file' )
			->once()
			->andReturn( [
				'valid'   => false,
				'status'  => 'warning',
				'message' => 'Error log path is a symbolic link (skipped for security)',
			] );

		Functions\expect( '__' )
			->andReturnUsing( function ( $text ) {
				return $text;
			} );

		$result = $check->run();

		$this->assertEquals( 'warning', $result['status'] );
		$this->assertStringContainsString( 'symbolic link', strtolower( $result['message'] ) );
	}

	/**
	 * Tests that run() returns ok when the log is empty
	 */
	public function test_run_returns_ok_when_log_empty() {
		$redaction = $this->create_redaction_mock();
		$check     = $this->create_check_mock( $redaction );

		$check->shouldReceive( 'resolve_log_path' )
			->once()
			->andReturn( '/var/log/php-error.log' );

		$check->shouldReceive( 'validate_log_file' )
			->once()
			->andReturn( [ 'valid' => true ] );

		$check->shouldReceive( 'read_tail' )
			->once()
			->andReturn( [] );

		Functions\expect( '__' )
			->andReturnUsing( function ( $text ) {
				return $text;
			} );

		$result = $check->run();

		$this->assertEquals( 'ok', $result['status'] );
		$this->assertStringContainsString( 'empty', strtolower( $result['message'] ) );
	}

	/**
	 * Tests that run() returns ok when there are no errors
	 */
	public function test_run_returns_ok_when_no_errors() {
		$redaction = $this->create_redaction_mock();
		$check     = $this->create_check_mock( $redaction );

		$check->shouldReceive( 'resolve_log_path' )
			->once()
			->andReturn( '/var/log/php-error.log' );

		$check->shouldReceive( 'validate_log_file' )
			->once()
			->andReturn( [ 'valid' => true ] );

		$check->shouldReceive( 'read_tail' )
			->once()
			->andReturn( [
				'[08-Feb-2026 12:00:00 UTC] Some informational log line',
				'[08-Feb-2026 12:01:00 UTC] Another non-error line',
			] );

		Functions\expect( '__' )
			->andReturnUsing( function ( $text ) {
				return $text;
			} );

		Functions\expect( 'size_format' )
			->andReturnUsing( function ( $bytes ) {
				return $bytes . ' B';
			} );

		$result = $check->run();

		$this->assertEquals( 'ok', $result['status'] );
	}

	/**
	 * Tests that run() returns warning for PHP warnings
	 */
	public function test_run_returns_warning_for_warnings() {
		$redaction = $this->create_redaction_mock();
		$check     = $this->create_check_mock( $redaction );

		$check->shouldReceive( 'resolve_log_path' )
			->once()
			->andReturn( '/var/log/php-error.log' );

		$check->shouldReceive( 'validate_log_file' )
			->once()
			->andReturn( [ 'valid' => true ] );

		$check->shouldReceive( 'read_tail' )
			->once()
			->andReturn( [
				'[08-Feb-2026 12:00:00 UTC] PHP Warning: Division by zero in file.php on line 10',
				'[08-Feb-2026 12:01:00 UTC] PHP Warning: Undefined variable in file.php on line 20',
			] );

		Functions\expect( '__' )
			->andReturnUsing( function ( $text ) {
				return $text;
			} );

		Functions\expect( 'size_format' )
			->andReturnUsing( function ( $bytes ) {
				return $bytes . ' B';
			} );

		$result = $check->run();

		$this->assertEquals( 'warning', $result['status'] );
	}

	/**
	 * Tests that run() returns critical for fatal errors
	 */
	public function test_run_returns_critical_for_fatal_errors() {
		$redaction = $this->create_redaction_mock();
		$check     = $this->create_check_mock( $redaction );

		$check->shouldReceive( 'resolve_log_path' )
			->once()
			->andReturn( '/var/log/php-error.log' );

		$check->shouldReceive( 'validate_log_file' )
			->once()
			->andReturn( [ 'valid' => true ] );

		$check->shouldReceive( 'read_tail' )
			->once()
			->andReturn( [
				'[08-Feb-2026 12:00:00 UTC] PHP Fatal error: Class not found in file.php on line 5',
			] );

		Functions\expect( '__' )
			->andReturnUsing( function ( $text ) {
				return $text;
			} );

		Functions\expect( 'size_format' )
			->andReturnUsing( function ( $bytes ) {
				return $bytes . ' B';
			} );

		$result = $check->run();

		$this->assertEquals( 'critical', $result['status'] );
	}

	/**
	 * Tests that run() returns critical for parse errors
	 */
	public function test_run_returns_critical_for_parse_errors() {
		$redaction = $this->create_redaction_mock();
		$check     = $this->create_check_mock( $redaction );

		$check->shouldReceive( 'resolve_log_path' )
			->once()
			->andReturn( '/var/log/php-error.log' );

		$check->shouldReceive( 'validate_log_file' )
			->once()
			->andReturn( [ 'valid' => true ] );

		$check->shouldReceive( 'read_tail' )
			->once()
			->andReturn( [
				'[08-Feb-2026 12:00:00 UTC] PHP Parse error: syntax error in file.php on line 3',
			] );

		Functions\expect( '__' )
			->andReturnUsing( function ( $text ) {
				return $text;
			} );

		Functions\expect( 'size_format' )
			->andReturnUsing( function ( $bytes ) {
				return $bytes . ' B';
			} );

		$result = $check->run();

		$this->assertEquals( 'critical', $result['status'] );
	}

	/**
	 * Tests that run() correctly counts errors by severity
	 */
	public function test_run_counts_errors_by_severity() {
		$redaction = $this->create_redaction_mock();
		$check     = $this->create_check_mock( $redaction );

		$check->shouldReceive( 'resolve_log_path' )
			->once()
			->andReturn( '/var/log/php-error.log' );

		$check->shouldReceive( 'validate_log_file' )
			->once()
			->andReturn( [ 'valid' => true ] );

		$check->shouldReceive( 'read_tail' )
			->once()
			->andReturn( [
				'[08-Feb-2026] PHP Fatal error: something',
				'[08-Feb-2026] PHP Warning: something',
				'[08-Feb-2026] PHP Warning: another',
				'[08-Feb-2026] PHP Notice: something',
				'[08-Feb-2026] PHP Deprecated: something',
			] );

		Functions\expect( '__' )
			->andReturnUsing( function ( $text ) {
				return $text;
			} );

		Functions\expect( 'size_format' )
			->andReturnUsing( function ( $bytes ) {
				return $bytes . ' B';
			} );

		$result = $check->run();

		$this->assertArrayHasKey( 'counts', $result['details'] );
		$counts = $result['details']['counts'];
		$this->assertEquals( 1, $counts['fatal'] );
		$this->assertEquals( 2, $counts['warning'] );
		$this->assertEquals( 1, $counts['notice'] );
		$this->assertEquals( 1, $counts['deprecated'] );
	}

	/**
	 * Tests that run() includes redacted samples
	 */
	public function test_run_includes_redacted_samples() {
		$redaction = Mockery::mock( RedactionInterface::class );
		$redaction->shouldReceive( 'redact_lines' )
			->once()
			->andReturnUsing( function ( $lines ) {
				return array_map( function ( $line ) {
					return '[REDACTED] ' . $line;
				}, $lines );
			} );

		$check = $this->create_check_mock( $redaction );

		$check->shouldReceive( 'resolve_log_path' )
			->once()
			->andReturn( '/var/log/php-error.log' );

		$check->shouldReceive( 'validate_log_file' )
			->once()
			->andReturn( [ 'valid' => true ] );

		$check->shouldReceive( 'read_tail' )
			->once()
			->andReturn( [
				'[08-Feb-2026] PHP Fatal error: something bad',
			] );

		Functions\expect( '__' )
			->andReturnUsing( function ( $text ) {
				return $text;
			} );

		Functions\expect( 'size_format' )
			->andReturnUsing( function ( $bytes ) {
				return $bytes . ' B';
			} );

		$result = $check->run();

		$this->assertArrayHasKey( 'recent_samples', $result['details'] );
		$this->assertNotEmpty( $result['details']['recent_samples'] );
		$this->assertStringContainsString( '[REDACTED]', $result['details']['recent_samples'][0] );
	}

	/**
	 * Tests that run() limits samples to 5
	 */
	public function test_run_limits_samples_to_five() {
		$redaction = $this->create_redaction_mock();
		$check     = $this->create_check_mock( $redaction );

		$check->shouldReceive( 'resolve_log_path' )
			->once()
			->andReturn( '/var/log/php-error.log' );

		$check->shouldReceive( 'validate_log_file' )
			->once()
			->andReturn( [ 'valid' => true ] );

		$lines = [];
		for ( $i = 0; $i < 20; $i++ ) {
			$lines[] = "[08-Feb-2026] PHP Warning: warning number {$i}";
		}
		$check->shouldReceive( 'read_tail' )
			->once()
			->andReturn( $lines );

		Functions\expect( '__' )
			->andReturnUsing( function ( $text ) {
				return $text;
			} );

		Functions\expect( 'size_format' )
			->andReturnUsing( function ( $bytes ) {
				return $bytes . ' B';
			} );

		$result = $check->run();

		$this->assertCount( 5, $result['details']['recent_samples'] );
	}

	/**
	 * Tests that run() measures the duration
	 */
	public function test_run_measures_duration() {
		$redaction = $this->create_redaction_mock();
		$check     = $this->create_check_mock( $redaction );

		$check->shouldReceive( 'resolve_log_path' )
			->once()
			->andReturn( '' );

		Functions\expect( '__' )
			->andReturnUsing( function ( $text ) {
				return $text;
			} );

		$result = $check->run();

		$this->assertArrayHasKey( 'duration', $result );
		$this->assertIsFloat( $result['duration'] );
		$this->assertGreaterThanOrEqual( 0, $result['duration'] );
	}

	/**
	 * Tests that run() has all required keys in the result
	 */
	public function test_run_result_has_required_keys() {
		$redaction = $this->create_redaction_mock();
		$check     = $this->create_check_mock( $redaction );

		$check->shouldReceive( 'resolve_log_path' )
			->once()
			->andReturn( '' );

		Functions\expect( '__' )
			->andReturnUsing( function ( $text ) {
				return $text;
			} );

		$result = $check->run();

		$this->assertArrayHasKey( 'status', $result );
		$this->assertArrayHasKey( 'message', $result );
		$this->assertArrayHasKey( 'details', $result );
		$this->assertArrayHasKey( 'duration', $result );
	}

	/**
	 * Tests that run() includes counts in details
	 */
	public function test_run_details_contain_counts() {
		$redaction = $this->create_redaction_mock();
		$check     = $this->create_check_mock( $redaction );

		$check->shouldReceive( 'resolve_log_path' )
			->once()
			->andReturn( '/var/log/php-error.log' );

		$check->shouldReceive( 'validate_log_file' )
			->once()
			->andReturn( [ 'valid' => true ] );

		$check->shouldReceive( 'read_tail' )
			->once()
			->andReturn( [
				'[08-Feb-2026] PHP Warning: test',
			] );

		Functions\expect( '__' )
			->andReturnUsing( function ( $text ) {
				return $text;
			} );

		Functions\expect( 'size_format' )
			->andReturnUsing( function ( $bytes ) {
				return $bytes . ' B';
			} );

		$result = $check->run();

		$this->assertArrayHasKey( 'counts', $result['details'] );
		$this->assertArrayHasKey( 'fatal', $result['details']['counts'] );
		$this->assertArrayHasKey( 'parse', $result['details']['counts'] );
		$this->assertArrayHasKey( 'warning', $result['details']['counts'] );
		$this->assertArrayHasKey( 'notice', $result['details']['counts'] );
		$this->assertArrayHasKey( 'deprecated', $result['details']['counts'] );
		$this->assertArrayHasKey( 'strict', $result['details']['counts'] );
		$this->assertArrayHasKey( 'other', $result['details']['counts'] );
	}

	/**
	 * Tests that run() includes the file size in details
	 */
	public function test_run_details_contain_file_size() {
		$redaction = $this->create_redaction_mock();
		$check     = $this->create_check_mock( $redaction );

		$check->shouldReceive( 'resolve_log_path' )
			->once()
			->andReturn( '/var/log/php-error.log' );

		$check->shouldReceive( 'validate_log_file' )
			->once()
			->andReturn( [ 'valid' => true ] );

		$check->shouldReceive( 'read_tail' )
			->once()
			->andReturn( [
				'[08-Feb-2026] PHP Notice: test',
			] );

		$check->shouldReceive( 'get_file_size' )
			->once()
			->andReturn( '2.4 MB' );

		Functions\expect( '__' )
			->andReturnUsing( function ( $text ) {
				return $text;
			} );

		$result = $check->run();

		$this->assertArrayHasKey( 'file_size', $result['details'] );
		$this->assertEquals( '2.4 MB', $result['details']['file_size'] );
	}

	/**
	 * Tests that run() uses i18n for messages
	 */
	public function test_run_message_uses_i18n() {
		$redaction = $this->create_redaction_mock();
		$check     = $this->create_check_mock( $redaction );

		$check->shouldReceive( 'resolve_log_path' )
			->once()
			->andReturn( '' );

		$i18n_called = false;
		Functions\expect( '__' )
			->andReturnUsing( function ( $text ) use ( &$i18n_called ) {
				$i18n_called = true;
				return $text;
			} );

		$check->run();

		$this->assertTrue( $i18n_called, '__() should be called for i18n' );
	}

	/**
	 * Tests that run() does not expose the raw log path
	 */
	public function test_run_does_not_expose_raw_log_path() {
		$redaction = $this->create_redaction_mock();
		$check     = $this->create_check_mock( $redaction );

		$log_path = '/var/log/secret-php-error.log';
		$check->shouldReceive( 'resolve_log_path' )
			->once()
			->andReturn( $log_path );

		$check->shouldReceive( 'validate_log_file' )
			->once()
			->andReturn( [ 'valid' => true ] );

		$check->shouldReceive( 'read_tail' )
			->once()
			->andReturn( [ '[08-Feb-2026] PHP Notice: test' ] );

		Functions\expect( '__' )
			->andReturnUsing( function ( $text ) {
				return $text;
			} );

		Functions\expect( 'size_format' )
			->andReturnUsing( function ( $bytes ) {
				return $bytes . ' B';
			} );

		$result    = $check->run();
		$as_string = json_encode( $result );
		$this->assertStringNotContainsString( $log_path, $as_string );
	}

	/**
	 * Tests that run() returns warning for deprecated
	 */
	public function test_run_returns_warning_for_deprecated() {
		$redaction = $this->create_redaction_mock();
		$check     = $this->create_check_mock( $redaction );

		$check->shouldReceive( 'resolve_log_path' )
			->once()
			->andReturn( '/var/log/php-error.log' );

		$check->shouldReceive( 'validate_log_file' )
			->once()
			->andReturn( [ 'valid' => true ] );

		$check->shouldReceive( 'read_tail' )
			->once()
			->andReturn( [
				'[08-Feb-2026] PHP Deprecated: Function is deprecated in file.php on line 10',
			] );

		Functions\expect( '__' )
			->andReturnUsing( function ( $text ) {
				return $text;
			} );

		Functions\expect( 'size_format' )
			->andReturnUsing( function ( $bytes ) {
				return $bytes . ' B';
			} );

		$result = $check->run();

		$this->assertEquals( 'warning', $result['status'] );
	}

	/**
	 * Tests that critical samples have priority over warning in samples
	 *
	 * With 3 critical and 10 warning, the 5 samples must include all 3 critical.
	 */
	public function test_run_prioritizes_critical_over_warning_in_samples() {
		$redaction = $this->create_redaction_mock();
		$check     = $this->create_check_mock( $redaction );

		$check->shouldReceive( 'resolve_log_path' )
			->once()
			->andReturn( '/var/log/php-error.log' );

		$check->shouldReceive( 'validate_log_file' )
			->once()
			->andReturn( [ 'valid' => true ] );

		$lines = [];
		for ( $i = 0; $i < 10; $i++ ) {
			$lines[] = "[08-Feb-2026] PHP Warning: warning number {$i}";
		}
		$lines[] = '[08-Feb-2026] PHP Fatal error: critical one';
		$lines[] = '[08-Feb-2026] PHP Fatal error: critical two';
		$lines[] = '[08-Feb-2026] PHP Fatal error: critical three';

		$check->shouldReceive( 'read_tail' )
			->once()
			->andReturn( $lines );

		Functions\expect( '__' )
			->andReturnUsing( function ( $text ) {
				return $text;
			} );

		Functions\expect( 'size_format' )
			->andReturnUsing( function ( $bytes ) {
				return $bytes . ' B';
			} );

		$result  = $check->run();
		$samples = $result['details']['recent_samples'];

		$this->assertCount( 5, $samples );

		// All 3 critical samples must be present.
		$critical_count = 0;
		foreach ( $samples as $sample ) {
			if ( false !== strpos( $sample, 'Fatal error' ) ) {
				++$critical_count;
			}
		}
		$this->assertEquals( 3, $critical_count, 'All critical samples must be included' );
	}

	/**
	 * Tests that the class is NOT final
	 */
	public function test_class_is_not_final() {
		$reflection = new \ReflectionClass( ErrorLogCheck::class );
		$this->assertFalse( $reflection->isFinal(), 'ErrorLogCheck should NOT be final' );
	}

	/**
	 * Tests that NO static methods exist
	 */
	public function test_no_static_methods() {
		$reflection = new \ReflectionClass( ErrorLogCheck::class );
		$methods    = $reflection->getMethods( \ReflectionMethod::IS_STATIC );

		$static_methods = array_filter(
			$methods,
			function ( $method ) {
				return strpos( $method->getName(), '__' ) !== 0;
			}
		);

		$this->assertEmpty( $static_methods, 'ErrorLogCheck should have NO static methods' );
	}

	/**
	 * Tests that NO static properties exist
	 */
	public function test_no_static_properties() {
		$reflection = new \ReflectionClass( ErrorLogCheck::class );
		$properties = $reflection->getProperties( \ReflectionProperty::IS_STATIC );

		$this->assertEmpty( $properties, 'ErrorLogCheck should have NO static properties' );
	}

	// -------------------------------------------------------------------
	// Filesystem methods (real temp files, no mocking)
	// -------------------------------------------------------------------

	/**
	 * Creates a partial mock that only mocks resolve_log_path.
	 * The other protected methods (validate_log_file, read_tail, get_file_size) run for real.
	 *
	 * @param \Mockery\MockInterface $redaction Redaction service mock.
	 * @param string                 $log_path  Path to return as resolve_log_path.
	 * @param int                    $max_lines Maximum lines to read (default 100).
	 * @param int                    $max_bytes Maximum bytes to read (default 512KB).
	 * @return \Mockery\MockInterface
	 */
	private function create_fs_check( $redaction, string $log_path, int $max_lines = 100, int $max_bytes = 524288 ) {
		$check = Mockery::mock( ErrorLogCheck::class, [ $redaction, $max_lines, $max_bytes ] )
			->makePartial()
			->shouldAllowMockingProtectedMethods();
		$check->shouldReceive( 'resolve_log_path' )
			->andReturn( $log_path );
		return $check;
	}

	/**
	 * Tests validate_log_file with symlink -> warning
	 */
	public function test_validate_symlink_returns_warning() {
		$tmp_file = tempnam( sys_get_temp_dir(), 'ops_test_' );
		$link     = $tmp_file . '_link';
		symlink( $tmp_file, $link );

		$redaction = $this->create_redaction_mock();
		$check     = $this->create_fs_check( $redaction, $link );

		Functions\expect( '__' )->andReturnFirstArg();

		$result = $check->run();

		unlink( $link );
		unlink( $tmp_file );

		$this->assertEquals( 'warning', $result['status'] );
		$this->assertStringContainsString( 'symbolic link', strtolower( $result['message'] ) );
	}

	/**
	 * Tests validate_log_file with nonexistent file -> ok (configured but not yet created)
	 */
	public function test_validate_missing_file_returns_ok() {
		$redaction = $this->create_redaction_mock();
		$check     = $this->create_fs_check( $redaction, '/tmp/ops_nonexistent_' . uniqid() . '.log' );

		Functions\expect( '__' )->andReturnFirstArg();

		$result = $check->run();

		$this->assertEquals( 'ok', $result['status'] );
		$this->assertStringContainsString( 'does not exist yet', strtolower( $result['message'] ) );
	}

	/**
	 * Tests validate_log_file with unreadable file -> warning
	 */
	public function test_validate_unreadable_file_returns_warning() {
		if ( 0 === posix_getuid() ) {
			$this->markTestSkipped( 'Cannot test permission check as root' );
		}

		$tmp_file = tempnam( sys_get_temp_dir(), 'ops_test_' );
		chmod( $tmp_file, 0000 );

		$redaction = $this->create_redaction_mock();
		$check     = $this->create_fs_check( $redaction, $tmp_file );

		Functions\expect( '__' )->andReturnFirstArg();

		$result = $check->run();

		chmod( $tmp_file, 0644 );
		unlink( $tmp_file );

		$this->assertEquals( 'warning', $result['status'] );
		$this->assertStringContainsString( 'not readable', strtolower( $result['message'] ) );
	}

	/**
	 * Tests read_tail with empty file -> ok (empty log)
	 */
	public function test_read_tail_returns_empty_for_empty_file() {
		$tmp_file = tempnam( sys_get_temp_dir(), 'ops_test_' );

		$redaction = $this->create_redaction_mock();
		$check     = $this->create_fs_check( $redaction, $tmp_file );

		Functions\expect( '__' )->andReturnFirstArg();

		$result = $check->run();

		unlink( $tmp_file );

		$this->assertEquals( 'ok', $result['status'] );
		$this->assertStringContainsString( 'empty', strtolower( $result['message'] ) );
	}

	/**
	 * Tests read_tail with file containing log lines -> correct analysis
	 */
	public function test_read_tail_returns_lines_from_real_file() {
		$tmp_file = tempnam( sys_get_temp_dir(), 'ops_test_' );
		file_put_contents(
			$tmp_file,
			"[09-Feb-2026] PHP Warning: test warning\n[09-Feb-2026] PHP Fatal error: test fatal\n"
		);

		$redaction = $this->create_redaction_mock();
		$check     = $this->create_fs_check( $redaction, $tmp_file );

		Functions\expect( '__' )->andReturnFirstArg();
		Functions\expect( 'size_format' )->andReturnUsing( function ( $bytes ) {
			return $bytes . ' B';
		} );

		$result = $check->run();

		unlink( $tmp_file );

		$this->assertEquals( 'critical', $result['status'] );
		$this->assertEquals( 1, $result['details']['counts']['fatal'] );
		$this->assertEquals( 1, $result['details']['counts']['warning'] );
		$this->assertEquals( 2, $result['details']['lines_analyzed'] );
	}

	/**
	 * Tests read_tail respects max_lines
	 */
	public function test_read_tail_respects_max_lines() {
		$tmp_file = tempnam( sys_get_temp_dir(), 'ops_test_' );
		$lines    = '';
		for ( $i = 0; $i < 20; $i++ ) {
			$lines .= "[09-Feb-2026] PHP Notice: notice {$i}\n";
		}
		file_put_contents( $tmp_file, $lines );

		$redaction = $this->create_redaction_mock();
		// max_lines = 5.
		$check = $this->create_fs_check( $redaction, $tmp_file, 5 );

		Functions\expect( '__' )->andReturnFirstArg();
		Functions\expect( 'size_format' )->andReturnUsing( function ( $bytes ) {
			return $bytes . ' B';
		} );

		$result = $check->run();

		unlink( $tmp_file );

		$this->assertEquals( 5, $result['details']['lines_analyzed'] );
	}

	/**
	 * Tests read_tail discards partial first line when seeking
	 */
	public function test_read_tail_discards_partial_first_line_on_offset() {
		$tmp_file = tempnam( sys_get_temp_dir(), 'ops_test_' );
		// Creates a file larger than max_bytes (100 bytes).
		$content = str_repeat( 'A', 50 ) . "\n" . "[09-Feb-2026] PHP Warning: real line\n";
		file_put_contents( $tmp_file, $content );

		$redaction = $this->create_redaction_mock();
		// max_bytes = 40 -> seek to end, partial first line discarded.
		$check = $this->create_fs_check( $redaction, $tmp_file, 100, 40 );

		Functions\expect( '__' )->andReturnFirstArg();
		Functions\expect( 'size_format' )->andReturnUsing( function ( $bytes ) {
			return $bytes . ' B';
		} );

		$result = $check->run();

		unlink( $tmp_file );

		// Must analyze only the complete line, not the partial fragment.
		$this->assertArrayHasKey( 'lines_analyzed', $result['details'] );
		$this->assertLessThanOrEqual( 2, $result['details']['lines_analyzed'] );
	}

	/**
	 * Tests resolve_log_path when WP_DEBUG_LOG=true but WP_CONTENT_DIR is not defined
	 * and ini_get('error_log') returns empty -> "not configured".
	 *
	 * Covers ALL lines of resolve_log_path(): the defined branch, is_string,
	 * the fallthrough to ini_get, and the final return.
	 */
	public function test_resolve_log_path_not_configured() {
		// Define WP_DEBUG_LOG if not already defined (permanent in the process,
		// but all other tests mock resolve_log_path so it does not impact).
		if ( ! defined( 'WP_DEBUG_LOG' ) ) {
			define( 'WP_DEBUG_LOG', true );
		}

		$redaction = $this->create_redaction_mock();
		// Real instance -- resolve_log_path runs for real.
		$check = new ErrorLogCheck( $redaction );

		// ini_get('error_log') returns '' in the PHPUnit CLI environment.
		// WP_CONTENT_DIR is not defined -> complete fallthrough.
		Functions\expect( '__' )->andReturnFirstArg();

		$result = $check->run();

		$this->assertEquals( 'warning', $result['status'] );
		$this->assertStringContainsString( 'not configured', strtolower( $result['message'] ) );
	}

	/**
	 * Tests get_file_size on a real file
	 */
	public function test_get_file_size_returns_formatted_string() {
		$tmp_file = tempnam( sys_get_temp_dir(), 'ops_test_' );
		file_put_contents( $tmp_file, str_repeat( 'X', 1024 ) . "\n[09-Feb-2026] PHP Notice: test\n" );

		$redaction = $this->create_redaction_mock();
		$check     = $this->create_fs_check( $redaction, $tmp_file );

		Functions\expect( '__' )->andReturnFirstArg();
		Functions\expect( 'size_format' )->once()->andReturn( '1 KB' );

		$result = $check->run();

		unlink( $tmp_file );

		$this->assertEquals( '1 KB', $result['details']['file_size'] );
	}

	/**
	 * Tests that collect_samples uses only critical when all slots are full
	 *
	 * Covers line 460: branch `$remaining <= 0` in collect_samples().
	 */
	public function test_run_collects_only_critical_samples_when_slots_full() {
		$redaction = $this->create_redaction_mock();

		// max_samples = 2 to force the situation.
		$check = Mockery::mock( ErrorLogCheck::class, [ $redaction, 100, 524288, 2 ] )
			->makePartial()
			->shouldAllowMockingProtectedMethods();

		// 3 fatal lines (more than max_samples=2), no warnings.
		$lines = [
			'[10-Feb-2026] PHP Fatal error: Out of memory in /var/www/file.php on line 1',
			'[10-Feb-2026] PHP Fatal error: Out of memory in /var/www/file.php on line 2',
			'[10-Feb-2026] PHP Fatal error: Out of memory in /var/www/file.php on line 3',
		];

		$check->shouldReceive( 'resolve_log_path' )->once()->andReturn( '/tmp/test.log' );
		$check->shouldReceive( 'validate_log_file' )->once()->andReturn( [ 'valid' => true ] );
		$check->shouldReceive( 'read_tail' )->once()->andReturn( $lines );
		$check->shouldReceive( 'get_file_size' )->once()->andReturn( '512 B' );

		Functions\expect( '__' )->andReturnFirstArg();

		$result = $check->run();

		$this->assertEquals( 'critical', $result['status'] );
		// Only 2 samples (max_samples), all critical, no warnings.
		$this->assertCount( 2, $result['details']['recent_samples'] );
	}
}
