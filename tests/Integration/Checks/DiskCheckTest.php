<?php
/**
 * Integration Test per DiskCheck
 *
 * Test di integrazione con WordPress reale.
 * Usa TestableDiskCheck per simulare errori.
 *
 * @package OpsHealthDashboard\Tests\Integration\Checks
 */

namespace OpsHealthDashboard\Tests\Integration\Checks;

use OpsHealthDashboard\Checks\DiskCheck;
use OpsHealthDashboard\Interfaces\CheckInterface;
use OpsHealthDashboard\Services\Redaction;
use WP_UnitTestCase;

/**
 * Class DiskCheckTest
 *
 * Integration test per DiskCheck con WordPress reale.
 */
class DiskCheckTest extends WP_UnitTestCase {

	/**
	 * Testa che DiskCheck implementa CheckInterface
	 */
	public function test_disk_check_implements_interface() {
		$redaction = new Redaction( ABSPATH, WP_CONTENT_DIR );
		$check     = new DiskCheck( $redaction );
		$this->assertInstanceOf( CheckInterface::class, $check );
	}

	/**
	 * Testa che DiskCheck esegue senza crash con il filesystem reale
	 */
	public function test_disk_check_runs_without_crash() {
		$redaction = new Redaction( ABSPATH, WP_CONTENT_DIR );
		$check     = new DiskCheck( $redaction );
		$result    = $check->run();

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'status', $result );
		$this->assertArrayHasKey( 'message', $result );
		$this->assertArrayHasKey( 'details', $result );
		$this->assertArrayHasKey( 'duration', $result );
	}

	/**
	 * Testa che DiskCheck ritorna una struttura valida con stato ok
	 */
	public function test_disk_check_returns_ok_on_real_filesystem() {
		$redaction = new Redaction( ABSPATH, WP_CONTENT_DIR );
		$check     = new DiskCheck( $redaction );
		$result    = $check->run();

		$this->assertEquals( 'ok', $result['status'], 'Real disk should have > 20% free space' );
		$this->assertIsString( $result['message'] );
		$this->assertStringContainsString( '%', $result['message'] );
		$this->assertIsFloat( $result['duration'] );
		$this->assertGreaterThanOrEqual( 0, $result['duration'] );
	}

	/**
	 * Testa che DiskCheck include dettagli corretti con il filesystem reale
	 */
	public function test_disk_check_includes_expected_details() {
		$redaction = new Redaction( ABSPATH, WP_CONTENT_DIR );
		$check     = new DiskCheck( $redaction );
		$result    = $check->run();

		$this->assertArrayHasKey( 'free_bytes', $result['details'] );
		$this->assertArrayHasKey( 'total_bytes', $result['details'] );
		$this->assertArrayHasKey( 'free_percent', $result['details'] );
		$this->assertArrayHasKey( 'path', $result['details'] );

		$this->assertGreaterThan( 0, $result['details']['free_bytes'] );
		$this->assertGreaterThan( 0, $result['details']['total_bytes'] );
		$this->assertGreaterThan( 0, $result['details']['free_percent'] );
		$this->assertLessThanOrEqual( 100.0, $result['details']['free_percent'] );
	}

	/**
	 * Testa che get_id(), get_name() e is_enabled() funzionano correttamente
	 */
	public function test_check_interface_accessors() {
		$redaction = new Redaction( ABSPATH, WP_CONTENT_DIR );
		$check     = new DiskCheck( $redaction );

		$this->assertEquals( 'disk', $check->get_id() );
		$this->assertNotEmpty( $check->get_name() );
		$this->assertIsString( $check->get_name() );
		$this->assertTrue( $check->is_enabled() );
	}

	/**
	 * Testa che il path è redatto (non espone ABSPATH)
	 */
	public function test_disk_check_redacts_path() {
		$redaction = new Redaction( ABSPATH, WP_CONTENT_DIR );
		$check     = new DiskCheck( $redaction );
		$result    = $check->run();

		$as_string = json_encode( $result );
		$this->assertStringNotContainsString( ABSPATH, $as_string );
	}

	/**
	 * Testa graceful degradation quando disk_free_space ritorna false
	 */
	public function test_disk_check_warning_when_free_space_fails() {
		$redaction = new Redaction( ABSPATH, WP_CONTENT_DIR );
		$check     = new FreeSpaceFailDiskCheck( $redaction );
		$result    = $check->run();

		$this->assertEquals( 'warning', $result['status'] );
		$this->assertStringContainsString( 'Unable to determine', $result['message'] );
		$this->assertArrayHasKey( 'path', $result['details'] );
	}

	/**
	 * Testa graceful degradation quando disk_total_space ritorna false
	 */
	public function test_disk_check_warning_when_total_space_fails() {
		$redaction = new Redaction( ABSPATH, WP_CONTENT_DIR );
		$check     = new TotalSpaceFailDiskCheck( $redaction );
		$result    = $check->run();

		$this->assertEquals( 'warning', $result['status'] );
		$this->assertStringContainsString( 'Unable to determine', $result['message'] );
	}

	/**
	 * Testa che DiskCheck ritorna warning quando lo spazio è sotto il 20%
	 */
	public function test_disk_check_warning_on_low_space() {
		$redaction = new Redaction( ABSPATH, WP_CONTENT_DIR );
		$check     = new LowSpaceDiskCheck( $redaction );
		$result    = $check->run();

		$this->assertEquals( 'warning', $result['status'] );
		$this->assertStringContainsString( '%', $result['message'] );
	}

	/**
	 * Testa che DiskCheck ritorna critical quando lo spazio è sotto il 10%
	 */
	public function test_disk_check_critical_on_very_low_space() {
		$redaction = new Redaction( ABSPATH, WP_CONTENT_DIR );
		$check     = new CriticalSpaceDiskCheck( $redaction );
		$result    = $check->run();

		$this->assertEquals( 'critical', $result['status'] );
		$this->assertStringContainsString( '%', $result['message'] );
	}

	/**
	 * Testa che DiskCheck è consistente su esecuzioni multiple
	 */
	public function test_disk_check_is_consistent() {
		$redaction = new Redaction( ABSPATH, WP_CONTENT_DIR );
		$check     = new DiskCheck( $redaction );

		$results = array();
		for ( $i = 0; $i < 3; $i++ ) {
			$results[] = $check->run();
		}

		$statuses = array_column( $results, 'status' );
		$this->assertCount( 1, array_unique( $statuses ), 'Status should be consistent across runs' );
	}

	/**
	 * Testa che DiskCheck misura la durata correttamente
	 */
	public function test_disk_check_measures_duration() {
		$redaction = new Redaction( ABSPATH, WP_CONTENT_DIR );
		$check     = new DiskCheck( $redaction );
		$result    = $check->run();

		$this->assertIsFloat( $result['duration'] );
		$this->assertGreaterThan( 0, $result['duration'], 'Duration should be positive' );
		$this->assertLessThan( 1, $result['duration'], 'Duration should be < 1s for disk check' );
	}
}

/**
 * DiskCheck testabile con disk_free_space che ritorna false
 */
class FreeSpaceFailDiskCheck extends DiskCheck {

	/**
	 * Simula disk_free_space fallita
	 *
	 * @param string $path Path da controllare.
	 * @return false Sempre false.
	 */
	protected function get_free_space( string $path ) {
		return false;
	}
}

/**
 * DiskCheck testabile con disk_total_space che ritorna false
 */
class TotalSpaceFailDiskCheck extends DiskCheck {

	/**
	 * Simula disk_total_space fallita
	 *
	 * @param string $path Path da controllare.
	 * @return false Sempre false.
	 */
	protected function get_total_space( string $path ) {
		return false;
	}
}

/**
 * DiskCheck testabile con poco spazio (15% libero — warning)
 */
class LowSpaceDiskCheck extends DiskCheck {

	/**
	 * Simula 15% spazio libero
	 *
	 * @param string $path Path da controllare.
	 * @return float Spazio libero simulato.
	 */
	protected function get_free_space( string $path ) {
		return 15000000000.0;
	}

	/**
	 * Simula spazio totale 100GB
	 *
	 * @param string $path Path da controllare.
	 * @return float Spazio totale simulato.
	 */
	protected function get_total_space( string $path ) {
		return 100000000000.0;
	}
}

/**
 * DiskCheck testabile con spazio critico (5% libero — critical)
 */
class CriticalSpaceDiskCheck extends DiskCheck {

	/**
	 * Simula 5% spazio libero
	 *
	 * @param string $path Path da controllare.
	 * @return float Spazio libero simulato.
	 */
	protected function get_free_space( string $path ) {
		return 5000000000.0;
	}

	/**
	 * Simula spazio totale 100GB
	 *
	 * @param string $path Path da controllare.
	 * @return float Spazio totale simulato.
	 */
	protected function get_total_space( string $path ) {
		return 100000000000.0;
	}
}
