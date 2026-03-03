<?php
/**
 * Unit Test for DiskCheck
 *
 * Unit test with Brain\Monkey for DiskCheck.
 * Uses Mockery partial mock for filesystem operations.
 *
 * @package OpsHealthDashboard\Tests\Unit\Checks
 */

namespace OpsHealthDashboard\Tests\Unit\Checks;

use Brain\Monkey\Functions;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use OpsHealthDashboard\Checks\DiskCheck;
use OpsHealthDashboard\Interfaces\CheckInterface;
use OpsHealthDashboard\Interfaces\RedactionInterface;
use PHPUnit\Framework\TestCase;

/**
 * Class DiskCheckTest
 *
 * Unit test for DiskCheck.
 */
class DiskCheckTest extends TestCase {
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
	 * Creates a partial mock of DiskCheck with mockable protected methods
	 *
	 * @param \Mockery\MockInterface $redaction Redaction service mock.
	 * @return \Mockery\MockInterface
	 */
	private function create_check_mock( $redaction ) {
		$check = Mockery::mock( DiskCheck::class, [ $redaction ] )
			->makePartial()
			->shouldAllowMockingProtectedMethods();
		return $check;
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

	/**
	 * Sets up size_format expectations
	 */
	private function mock_size_format() {
		Functions\expect( 'size_format' )
			->andReturnUsing(
				function ( $bytes ) {
					if ( $bytes >= 1073741824 ) {
						return round( $bytes / 1073741824, 1 ) . ' GB';
					}
					if ( $bytes >= 1048576 ) {
						return round( $bytes / 1048576, 1 ) . ' MB';
					}
					return $bytes . ' B';
				}
			);
	}

	/**
	 * Sets up a check mock with simulated disk space
	 *
	 * @param \Mockery\MockInterface $check   Check mock.
	 * @param float                  $free    Free space in bytes.
	 * @param float                  $total   Total space in bytes.
	 * @param string                 $path    Disk path.
	 */
	private function setup_disk_space( $check, $free, $total, $path = '/var/www/html' ) {
		$check->shouldReceive( 'get_disk_path' )
			->andReturn( $path );
		$check->shouldReceive( 'get_free_space' )
			->with( $path )
			->andReturn( $free );
		$check->shouldReceive( 'get_total_space' )
			->with( $path )
			->andReturn( $total );
	}

	// -------------------------------------------------------------------
	// Pattern enforcement
	// -------------------------------------------------------------------

	/**
	 * Tests that the class is NOT final
	 */
	public function test_class_is_not_final() {
		$reflection = new \ReflectionClass( DiskCheck::class );
		$this->assertFalse( $reflection->isFinal(), 'DiskCheck should NOT be final' );
	}

	/**
	 * Tests that NO static methods exist
	 */
	public function test_no_static_methods() {
		$reflection = new \ReflectionClass( DiskCheck::class );
		$methods    = $reflection->getMethods( \ReflectionMethod::IS_STATIC );

		$static_methods = array_filter(
			$methods,
			function ( $method ) {
				return strpos( $method->getName(), '__' ) !== 0;
			}
		);

		$this->assertEmpty( $static_methods, 'DiskCheck should have NO static methods' );
	}

	/**
	 * Tests that NO static properties exist
	 */
	public function test_no_static_properties() {
		$reflection = new \ReflectionClass( DiskCheck::class );
		$properties = $reflection->getProperties( \ReflectionProperty::IS_STATIC );

		$this->assertEmpty( $properties, 'DiskCheck should have NO static properties' );
	}

	// -------------------------------------------------------------------
	// CheckInterface
	// -------------------------------------------------------------------

	/**
	 * Tests that DiskCheck implements CheckInterface
	 */
	public function test_implements_check_interface() {
		$redaction = $this->create_redaction_mock();
		$check     = new DiskCheck( $redaction );
		$this->assertInstanceOf( CheckInterface::class, $check );
	}

	/**
	 * Tests that get_id() returns 'disk'
	 */
	public function test_get_id_returns_disk() {
		$redaction = $this->create_redaction_mock();
		$check     = new DiskCheck( $redaction );
		$this->assertEquals( 'disk', $check->get_id() );
	}

	/**
	 * Tests that get_name() returns the correct name
	 */
	public function test_get_name_returns_correct_name() {
		$redaction = $this->create_redaction_mock();
		$check     = new DiskCheck( $redaction );

		$this->mock_i18n();

		$this->assertEquals( 'Disk Space', $check->get_name() );
	}

	/**
	 * Tests that is_enabled() returns bool
	 */
	public function test_is_enabled_returns_bool() {
		$redaction = $this->create_redaction_mock();
		$check     = new DiskCheck( $redaction );
		$this->assertIsBool( $check->is_enabled() );
	}

	/**
	 * Tests that is_enabled() returns true when functions are available
	 */
	public function test_is_enabled_returns_true_when_functions_available() {
		$redaction = $this->create_redaction_mock();
		$check     = new DiskCheck( $redaction );

		// disk_free_space and disk_total_space are normally available in PHP.
		$this->assertTrue( $check->is_enabled() );
	}

	// -------------------------------------------------------------------
	// Healthy disk
	// -------------------------------------------------------------------

	/**
	 * Tests that run() returns ok when the disk is healthy (50% free)
	 */
	public function test_run_returns_ok_when_disk_healthy() {
		$redaction = $this->create_redaction_mock();
		$check     = $this->create_check_mock( $redaction );

		// 50 GB free / 100 GB total = 50%.
		$this->setup_disk_space( $check, 50.0 * 1073741824, 100.0 * 1073741824 );

		$this->mock_i18n();
		$this->mock_size_format();

		$result = $check->run();

		$this->assertEquals( 'ok', $result['status'] );
	}

	/**
	 * Tests that run() has all required keys in the result
	 */
	public function test_run_returns_required_keys() {
		$redaction = $this->create_redaction_mock();
		$check     = $this->create_check_mock( $redaction );

		$this->setup_disk_space( $check, 50.0 * 1073741824, 100.0 * 1073741824 );

		$this->mock_i18n();
		$this->mock_size_format();

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

		$this->setup_disk_space( $check, 50.0 * 1073741824, 100.0 * 1073741824 );

		$this->mock_i18n();
		$this->mock_size_format();

		$result = $check->run();

		$this->assertIsFloat( $result['duration'] );
		$this->assertGreaterThanOrEqual( 0, $result['duration'] );
	}

	// -------------------------------------------------------------------
	// Threshold tests
	// -------------------------------------------------------------------

	/**
	 * Tests that run() returns warning when below the warning threshold (15%)
	 */
	public function test_run_returns_warning_when_below_warning_threshold() {
		$redaction = $this->create_redaction_mock();
		$check     = $this->create_check_mock( $redaction );

		// 15 GB free / 100 GB total = 15%.
		$this->setup_disk_space( $check, 15.0 * 1073741824, 100.0 * 1073741824 );

		$this->mock_i18n();
		$this->mock_size_format();

		$result = $check->run();

		$this->assertEquals( 'warning', $result['status'] );
	}

	/**
	 * Tests that run() returns critical when below the critical threshold (5%)
	 */
	public function test_run_returns_critical_when_below_critical_threshold() {
		$redaction = $this->create_redaction_mock();
		$check     = $this->create_check_mock( $redaction );

		// 5 GB free / 100 GB total = 5%.
		$this->setup_disk_space( $check, 5.0 * 1073741824, 100.0 * 1073741824 );

		$this->mock_i18n();
		$this->mock_size_format();

		$result = $check->run();

		$this->assertEquals( 'critical', $result['status'] );
	}

	/**
	 * Tests that run() returns ok at the exact warning threshold boundary (20%)
	 */
	public function test_run_returns_ok_at_exact_warning_threshold() {
		$redaction = $this->create_redaction_mock();
		$check     = $this->create_check_mock( $redaction );

		// 20 GB free / 100 GB total = 20% (< 20 is warning, 20 is ok).
		$this->setup_disk_space( $check, 20.0 * 1073741824, 100.0 * 1073741824 );

		$this->mock_i18n();
		$this->mock_size_format();

		$result = $check->run();

		$this->assertEquals( 'ok', $result['status'] );
	}

	/**
	 * Tests that run() returns warning at the exact critical threshold boundary (10%)
	 */
	public function test_run_returns_warning_at_exact_critical_threshold() {
		$redaction = $this->create_redaction_mock();
		$check     = $this->create_check_mock( $redaction );

		// 10 GB free / 100 GB total = 10% (< 10 is critical, 10 is warning).
		$this->setup_disk_space( $check, 10.0 * 1073741824, 100.0 * 1073741824 );

		$this->mock_i18n();
		$this->mock_size_format();

		$result = $check->run();

		$this->assertEquals( 'warning', $result['status'] );
	}

	// -------------------------------------------------------------------
	// Edge cases
	// -------------------------------------------------------------------

	/**
	 * Tests that run() returns warning when disk_free_space returns false
	 */
	public function test_run_returns_warning_when_free_space_returns_false() {
		$redaction = $this->create_redaction_mock();
		$check     = $this->create_check_mock( $redaction );

		$check->shouldReceive( 'get_disk_path' )->andReturn( '/var/www/html' );
		$check->shouldReceive( 'get_free_space' )->andReturn( false );
		$check->shouldReceive( 'get_total_space' )->andReturn( 100.0 * 1073741824 );

		$this->mock_i18n();

		$result = $check->run();

		$this->assertEquals( 'warning', $result['status'] );
		$this->assertStringContainsString( 'Unable to determine', $result['message'] );
	}

	/**
	 * Tests that run() returns warning when disk_total_space returns false
	 */
	public function test_run_returns_warning_when_total_space_returns_false() {
		$redaction = $this->create_redaction_mock();
		$check     = $this->create_check_mock( $redaction );

		$check->shouldReceive( 'get_disk_path' )->andReturn( '/var/www/html' );
		$check->shouldReceive( 'get_free_space' )->andReturn( 50.0 * 1073741824 );
		$check->shouldReceive( 'get_total_space' )->andReturn( false );

		$this->mock_i18n();

		$result = $check->run();

		$this->assertEquals( 'warning', $result['status'] );
	}

	/**
	 * Tests that run() returns warning when total space is zero
	 */
	public function test_run_returns_warning_when_total_space_is_zero() {
		$redaction = $this->create_redaction_mock();
		$check     = $this->create_check_mock( $redaction );

		$check->shouldReceive( 'get_disk_path' )->andReturn( '/var/www/html' );
		$check->shouldReceive( 'get_free_space' )->andReturn( 0.0 );
		$check->shouldReceive( 'get_total_space' )->andReturn( 0.0 );

		$this->mock_i18n();

		$result = $check->run();

		$this->assertEquals( 'warning', $result['status'] );
	}

	// -------------------------------------------------------------------
	// Details structure
	// -------------------------------------------------------------------

	/**
	 * Tests that run() includes free_bytes, total_bytes, free_percent and path in details
	 */
	public function test_run_includes_expected_details() {
		$redaction = $this->create_redaction_mock();
		$check     = $this->create_check_mock( $redaction );

		$this->setup_disk_space( $check, 50.0 * 1073741824, 100.0 * 1073741824 );

		$this->mock_i18n();
		$this->mock_size_format();

		$result = $check->run();

		$this->assertArrayHasKey( 'free_bytes', $result['details'] );
		$this->assertArrayHasKey( 'total_bytes', $result['details'] );
		$this->assertArrayHasKey( 'free_percent', $result['details'] );
		$this->assertArrayHasKey( 'path', $result['details'] );
	}

	/**
	 * Tests that free_percent is calculated correctly
	 */
	public function test_free_percent_is_calculated_correctly() {
		$redaction = $this->create_redaction_mock();
		$check     = $this->create_check_mock( $redaction );

		// 25 GB free / 100 GB total = 25%.
		$this->setup_disk_space( $check, 25.0 * 1073741824, 100.0 * 1073741824 );

		$this->mock_i18n();
		$this->mock_size_format();

		$result = $check->run();

		$this->assertEquals( 25.0, $result['details']['free_percent'] );
	}

	// -------------------------------------------------------------------
	// Security / redaction
	// -------------------------------------------------------------------

	/**
	 * Tests that the path is redacted in details
	 */
	public function test_redacts_path_in_details() {
		$redaction = Mockery::mock( RedactionInterface::class );
		$redaction->shouldReceive( 'redact' )
			->andReturnUsing(
				function ( $text ) {
					return str_replace( '/var/www/html', '[REDACTED]', $text );
				}
			);

		$check = $this->create_check_mock( $redaction );

		$this->setup_disk_space( $check, 50.0 * 1073741824, 100.0 * 1073741824 );

		$this->mock_i18n();
		$this->mock_size_format();

		$result    = $check->run();
		$as_string = json_encode( $result );

		$this->assertStringNotContainsString( '/var/www/html', $as_string );
	}

	/**
	 * Tests that the path in the error result is redacted
	 */
	public function test_redacts_path_in_error_result() {
		$redaction = Mockery::mock( RedactionInterface::class );
		$redaction->shouldReceive( 'redact' )
			->andReturnUsing(
				function ( $text ) {
					return str_replace( '/var/www/html', '[REDACTED]', $text );
				}
			);

		$check = $this->create_check_mock( $redaction );

		$check->shouldReceive( 'get_disk_path' )->andReturn( '/var/www/html' );
		$check->shouldReceive( 'get_free_space' )->andReturn( false );
		$check->shouldReceive( 'get_total_space' )->andReturn( false );

		$this->mock_i18n();

		$result    = $check->run();
		$as_string = json_encode( $result );

		$this->assertStringNotContainsString( '/var/www/html', $as_string );
	}

	// -------------------------------------------------------------------
	// i18n
	// -------------------------------------------------------------------

	/**
	 * Tests that run() uses i18n for messages
	 */
	public function test_uses_i18n_for_messages() {
		$redaction = $this->create_redaction_mock();
		$check     = $this->create_check_mock( $redaction );

		$check->shouldReceive( 'get_disk_path' )->andReturn( '/var/www/html' );
		$check->shouldReceive( 'get_free_space' )->andReturn( false );
		$check->shouldReceive( 'get_total_space' )->andReturn( false );

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
	// Constants
	// -------------------------------------------------------------------

	/**
	 * Tests that threshold constants are defined correctly
	 */
	public function test_threshold_constants_are_defined() {
		$this->assertEquals( 20, DiskCheck::WARNING_THRESHOLD );
		$this->assertEquals( 10, DiskCheck::CRITICAL_THRESHOLD );
	}

	// -------------------------------------------------------------------
	// Protected methods (Reflection)
	// -------------------------------------------------------------------

	/**
	 * Tests that get_disk_path returns a string
	 */
	public function test_get_disk_path_returns_string() {
		$redaction = $this->create_redaction_mock();
		$check     = new DiskCheck( $redaction );

		$method = new \ReflectionMethod( DiskCheck::class, 'get_disk_path' );
		$method->setAccessible( true );

		$result = $method->invoke( $check );
		$this->assertIsString( $result );
	}

	/**
	 * Tests that get_free_space delegates to disk_free_space
	 */
	public function test_get_free_space_returns_value() {
		$redaction = $this->create_redaction_mock();
		$check     = new DiskCheck( $redaction );

		$method = new \ReflectionMethod( DiskCheck::class, 'get_free_space' );
		$method->setAccessible( true );

		$result = $method->invoke( $check, '/' );

		// disk_free_space('/') returns float or false.
		$this->assertTrue( is_float( $result ) || false === $result );
	}

	/**
	 * Tests that get_total_space delegates to disk_total_space
	 */
	public function test_get_total_space_returns_value() {
		$redaction = $this->create_redaction_mock();
		$check     = new DiskCheck( $redaction );

		$method = new \ReflectionMethod( DiskCheck::class, 'get_total_space' );
		$method->setAccessible( true );

		$result = $method->invoke( $check, '/' );

		// disk_total_space('/') returns float or false.
		$this->assertTrue( is_float( $result ) || false === $result );
	}
}
