<?php
/**
 * Integration Test per VersionsCheck
 *
 * Test di integrazione con WordPress reale.
 * Usa TestableVersionsCheck per controllare i metodi protetti.
 *
 * @package OpsHealthDashboard\Tests\Integration\Checks
 */

namespace OpsHealthDashboard\Tests\Integration\Checks;

use OpsHealthDashboard\Checks\VersionsCheck;
use OpsHealthDashboard\Interfaces\CheckInterface;
use WP_UnitTestCase;

/**
 * Class VersionsCheckTest
 *
 * Integration test per VersionsCheck con WordPress reale.
 */
class VersionsCheckTest extends WP_UnitTestCase {

	/**
	 * Testa che VersionsCheck implementa CheckInterface
	 */
	public function test_versions_check_implements_interface() {
		$check = new VersionsCheck();
		$this->assertInstanceOf( CheckInterface::class, $check );
	}

	/**
	 * Testa che VersionsCheck esegue senza crash
	 */
	public function test_versions_check_runs_without_crash() {
		$check  = new VersionsCheck();
		$result = $check->run();

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'status', $result );
		$this->assertArrayHasKey( 'message', $result );
		$this->assertArrayHasKey( 'details', $result );
		$this->assertArrayHasKey( 'duration', $result );
	}

	/**
	 * Testa che VersionsCheck ritorna una struttura valida
	 */
	public function test_versions_check_returns_valid_structure() {
		$check  = new VersionsCheck();
		$result = $check->run();

		$this->assertContains( $result['status'], [ 'ok', 'warning', 'critical' ] );
		$this->assertIsString( $result['message'] );
		$this->assertIsArray( $result['details'] );
		$this->assertIsFloat( $result['duration'] );
		$this->assertGreaterThanOrEqual( 0, $result['duration'] );
	}

	/**
	 * Testa che i dettagli contengono le informazioni di versione corrette
	 */
	public function test_versions_check_details_match_real_versions() {
		global $wp_version;

		$check  = new VersionsCheck();
		$result = $check->run();

		$this->assertEquals( $wp_version, $result['details']['wp_version'] );
		$this->assertEquals( PHP_VERSION, $result['details']['php_version'] );
		$this->assertEquals( '8.1', $result['details']['php_recommended'] );
		$this->assertIsArray( $result['details']['updates_available'] );
	}

	/**
	 * Testa che get_id(), get_name() e is_enabled() funzionano correttamente
	 */
	public function test_check_interface_accessors() {
		$check = new VersionsCheck();

		$this->assertEquals( 'versions', $check->get_id() );
		$this->assertNotEmpty( $check->get_name() );
		$this->assertIsString( $check->get_name() );
		$this->assertTrue( $check->is_enabled() );
	}

	/**
	 * Testa che VersionsCheck è consistente su esecuzioni multiple
	 */
	public function test_versions_check_is_consistent() {
		$check = new VersionsCheck();

		$results = array();
		for ( $i = 0; $i < 3; $i++ ) {
			$results[] = $check->run();
		}

		$statuses = array_column( $results, 'status' );
		$this->assertCount( 1, array_unique( $statuses ), 'Status should be consistent across runs' );
	}

	/**
	 * Testa che VersionsCheck misura la durata correttamente
	 */
	public function test_versions_check_measures_duration() {
		$check  = new VersionsCheck();
		$result = $check->run();

		$this->assertIsFloat( $result['duration'] );
		$this->assertGreaterThan( 0, $result['duration'], 'Duration should be positive' );
		$this->assertLessThan( 5, $result['duration'], 'Duration should be < 5s' );
	}

	/**
	 * Testa graceful degradation con load_update_functions che lancia
	 */
	public function test_versions_check_graceful_on_update_failure() {
		$check  = new FailingUpdateVersionsCheck();
		$result = $check->run();

		// Non deve crashare.
		$this->assertContains( $result['status'], [ 'ok', 'warning' ] );
		$this->assertArrayHasKey( 'wp_version', $result['details'] );
		$this->assertArrayHasKey( 'php_version', $result['details'] );
	}

	/**
	 * Testa che core update produce status critical
	 */
	public function test_versions_check_critical_on_core_update() {
		$check  = new CoreUpdateVersionsCheck();
		$result = $check->run();

		$this->assertEquals( 'critical', $result['status'] );
		$this->assertNotEmpty( $result['details']['updates_available'] );
	}

	/**
	 * Testa che plugin update produce status warning
	 */
	public function test_versions_check_warning_on_plugin_update() {
		$check  = new PluginUpdateVersionsCheck();
		$result = $check->run();

		$this->assertEquals( 'warning', $result['status'] );
		$this->assertNotEmpty( $result['details']['updates_available'] );
	}

	/**
	 * Testa che PHP obsoleto produce status warning
	 */
	public function test_versions_check_warning_on_old_php() {
		$check  = new OldPhpVersionsCheck();
		$result = $check->run();

		$this->assertEquals( 'warning', $result['status'] );
	}
}

/**
 * VersionsCheck con load_update_functions che lancia eccezione
 */
class FailingUpdateVersionsCheck extends VersionsCheck {

	/**
	 * Simula fallimento nel caricamento funzioni update
	 *
	 * @return void
	 * @throws \Exception Sempre.
	 */
	protected function load_update_functions(): void {
		throw new \Exception( 'Update functions unavailable' );
	}
}

/**
 * VersionsCheck con core update disponibile
 */
class CoreUpdateVersionsCheck extends VersionsCheck {

	/**
	 * Simula un core update disponibile
	 *
	 * @return array Core updates.
	 */
	protected function get_core_updates(): array {
		$update           = new \stdClass();
		$update->response = 'upgrade';
		$update->version  = '99.0';
		return [ $update ];
	}

	/**
	 * Nessun plugin update
	 *
	 * @return array Vuoto.
	 */
	protected function get_plugin_updates(): array {
		return [];
	}

	/**
	 * Nessun theme update
	 *
	 * @return array Vuoto.
	 */
	protected function get_theme_updates(): array {
		return [];
	}
}

/**
 * VersionsCheck con plugin update disponibile
 */
class PluginUpdateVersionsCheck extends VersionsCheck {

	/**
	 * Nessun core update
	 *
	 * @return array Vuoto.
	 */
	protected function get_core_updates(): array {
		return [];
	}

	/**
	 * Simula un plugin update
	 *
	 * @return array Plugin updates.
	 */
	protected function get_plugin_updates(): array {
		$plugin_update         = new \stdClass();
		$plugin_update->Name   = 'Test Plugin';
		$plugin_update->update = new \stdClass();
		return [ 'test/test.php' => $plugin_update ];
	}

	/**
	 * Nessun theme update
	 *
	 * @return array Vuoto.
	 */
	protected function get_theme_updates(): array {
		return [];
	}
}

/**
 * VersionsCheck con PHP vecchio simulato
 */
class OldPhpVersionsCheck extends VersionsCheck {

	/**
	 * Simula PHP 7.4
	 *
	 * @return string Versione PHP vecchia.
	 */
	protected function get_php_version(): string {
		return '7.4.33';
	}

	/**
	 * Nessun core update
	 *
	 * @return array Vuoto.
	 */
	protected function get_core_updates(): array {
		return [];
	}

	/**
	 * Nessun plugin update
	 *
	 * @return array Vuoto.
	 */
	protected function get_plugin_updates(): array {
		return [];
	}

	/**
	 * Nessun theme update
	 *
	 * @return array Vuoto.
	 */
	protected function get_theme_updates(): array {
		return [];
	}
}
