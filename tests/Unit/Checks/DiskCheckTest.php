<?php
/**
 * Unit Test per DiskCheck
 *
 * Test unitario con Brain\Monkey per DiskCheck.
 * Usa Mockery partial mock per le operazioni filesystem.
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
 * Unit test per DiskCheck.
 */
class DiskCheckTest extends TestCase {
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
	 * Crea un partial mock di DiskCheck con metodi protetti mockabili
	 *
	 * @param \Mockery\MockInterface $redaction Mock del servizio di redazione.
	 * @return \Mockery\MockInterface
	 */
	private function create_check_mock( $redaction ) {
		$check = Mockery::mock( DiskCheck::class, [ $redaction ] )
			->makePartial()
			->shouldAllowMockingProtectedMethods();
		return $check;
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

	/**
	 * Configura le aspettative size_format
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
	 * Configura un check mock con spazio disco simulato
	 *
	 * @param \Mockery\MockInterface $check   Mock del check.
	 * @param float                  $free    Spazio libero in bytes.
	 * @param float                  $total   Spazio totale in bytes.
	 * @param string                 $path    Path del disco.
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
	 * Testa che la classe NON è final
	 */
	public function test_class_is_not_final() {
		$reflection = new \ReflectionClass( DiskCheck::class );
		$this->assertFalse( $reflection->isFinal(), 'DiskCheck should NOT be final' );
	}

	/**
	 * Testa che NON esistono metodi static
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
	 * Testa che NON esistono proprietà static
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
	 * Testa che DiskCheck implementa CheckInterface
	 */
	public function test_implements_check_interface() {
		$redaction = $this->create_redaction_mock();
		$check     = new DiskCheck( $redaction );
		$this->assertInstanceOf( CheckInterface::class, $check );
	}

	/**
	 * Testa che get_id() ritorna 'disk'
	 */
	public function test_get_id_returns_disk() {
		$redaction = $this->create_redaction_mock();
		$check     = new DiskCheck( $redaction );
		$this->assertEquals( 'disk', $check->get_id() );
	}

	/**
	 * Testa che get_name() ritorna il nome corretto
	 */
	public function test_get_name_returns_correct_name() {
		$redaction = $this->create_redaction_mock();
		$check     = new DiskCheck( $redaction );

		$this->mock_i18n();

		$this->assertEquals( 'Disk Space', $check->get_name() );
	}

	/**
	 * Testa che is_enabled() ritorna bool
	 */
	public function test_is_enabled_returns_bool() {
		$redaction = $this->create_redaction_mock();
		$check     = new DiskCheck( $redaction );
		$this->assertIsBool( $check->is_enabled() );
	}

	/**
	 * Testa che is_enabled() ritorna true quando le funzioni sono disponibili
	 */
	public function test_is_enabled_returns_true_when_functions_available() {
		$redaction = $this->create_redaction_mock();
		$check     = new DiskCheck( $redaction );

		// disk_free_space e disk_total_space sono normalmente disponibili in PHP.
		$this->assertTrue( $check->is_enabled() );
	}

	// -------------------------------------------------------------------
	// Healthy disk
	// -------------------------------------------------------------------

	/**
	 * Testa che run() ritorna ok quando il disco è sano (50% libero)
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
	 * Testa che run() ha tutte le chiavi richieste nel risultato
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
	 * Testa che run() misura la durata
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
	 * Testa che run() ritorna warning quando sotto la soglia di warning (15%)
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
	 * Testa che run() ritorna critical quando sotto la soglia critica (5%)
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
	 * Testa che run() ritorna ok al confine esatto della soglia warning (20%)
	 */
	public function test_run_returns_ok_at_exact_warning_threshold() {
		$redaction = $this->create_redaction_mock();
		$check     = $this->create_check_mock( $redaction );

		// 20 GB free / 100 GB total = 20% (< 20 è warning, 20 è ok).
		$this->setup_disk_space( $check, 20.0 * 1073741824, 100.0 * 1073741824 );

		$this->mock_i18n();
		$this->mock_size_format();

		$result = $check->run();

		$this->assertEquals( 'ok', $result['status'] );
	}

	/**
	 * Testa che run() ritorna warning al confine esatto della soglia critica (10%)
	 */
	public function test_run_returns_warning_at_exact_critical_threshold() {
		$redaction = $this->create_redaction_mock();
		$check     = $this->create_check_mock( $redaction );

		// 10 GB free / 100 GB total = 10% (< 10 è critical, 10 è warning).
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
	 * Testa che run() ritorna warning quando disk_free_space ritorna false
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
	 * Testa che run() ritorna warning quando disk_total_space ritorna false
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
	 * Testa che run() ritorna warning quando lo spazio totale è zero
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
	 * Testa che run() include free_bytes, total_bytes, free_percent e path nei dettagli
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
	 * Testa che free_percent è calcolato correttamente
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
	 * Testa che il path viene redatto nei dettagli
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
	 * Testa che il path nell'errore viene redatto
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
	 * Testa che run() usa i18n per i messaggi
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
	 * Testa che le costanti di soglia sono definite correttamente
	 */
	public function test_threshold_constants_are_defined() {
		$this->assertEquals( 20, DiskCheck::WARNING_THRESHOLD );
		$this->assertEquals( 10, DiskCheck::CRITICAL_THRESHOLD );
	}

	// -------------------------------------------------------------------
	// Protected methods (Reflection)
	// -------------------------------------------------------------------

	/**
	 * Testa che get_disk_path ritorna una stringa
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
	 * Testa che get_free_space delega a disk_free_space
	 */
	public function test_get_free_space_returns_value() {
		$redaction = $this->create_redaction_mock();
		$check     = new DiskCheck( $redaction );

		$method = new \ReflectionMethod( DiskCheck::class, 'get_free_space' );
		$method->setAccessible( true );

		$result = $method->invoke( $check, '/' );

		// disk_free_space('/') ritorna float o false.
		$this->assertTrue( is_float( $result ) || false === $result );
	}

	/**
	 * Testa che get_total_space delega a disk_total_space
	 */
	public function test_get_total_space_returns_value() {
		$redaction = $this->create_redaction_mock();
		$check     = new DiskCheck( $redaction );

		$method = new \ReflectionMethod( DiskCheck::class, 'get_total_space' );
		$method->setAccessible( true );

		$result = $method->invoke( $check, '/' );

		// disk_total_space('/') ritorna float o false.
		$this->assertTrue( is_float( $result ) || false === $result );
	}
}
