<?php
/**
 * Unit Test per VersionsCheck
 *
 * Test unitario con Brain\Monkey per VersionsCheck.
 * Usa Mockery partial mock per i metodi protetti.
 *
 * @package OpsHealthDashboard\Tests\Unit\Checks
 */

namespace OpsHealthDashboard\Tests\Unit\Checks;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use OpsHealthDashboard\Checks\VersionsCheck;
use OpsHealthDashboard\Interfaces\CheckInterface;
use PHPUnit\Framework\TestCase;

/**
 * Class VersionsCheckTest
 *
 * Unit test per VersionsCheck.
 */
class VersionsCheckTest extends TestCase {
	use MockeryPHPUnitIntegration;

	/**
	 * Setup per ogni test
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	/**
	 * Teardown per ogni test
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Crea un mock parziale di VersionsCheck con metodi protetti mockabili
	 *
	 * @return \Mockery\MockInterface|VersionsCheck
	 */
	private function create_check_mock() {
		return Mockery::mock( VersionsCheck::class )
			->makePartial()
			->shouldAllowMockingProtectedMethods();
	}

	/**
	 * Configura i mock di i18n WordPress
	 */
	private function mock_i18n(): void {
		Functions\when( '__' )->returnArg();
		Functions\when( 'esc_html__' )->returnArg();
	}

	/**
	 * Configura il mock per un check con tutte le versioni e aggiornamenti
	 *
	 * @param \Mockery\MockInterface $check           Mock del check.
	 * @param string                 $wp_version      Versione WordPress.
	 * @param string                 $php_version     Versione PHP.
	 * @param array                  $core_updates    Aggiornamenti core.
	 * @param array                  $plugin_updates  Aggiornamenti plugin.
	 * @param array                  $theme_updates   Aggiornamenti tema.
	 */
	private function setup_versions(
		$check,
		string $wp_version = '6.7',
		string $php_version = '8.3.0',
		array $core_updates = [],
		array $plugin_updates = [],
		array $theme_updates = []
	): void {
		$check->shouldReceive( 'get_wp_version' )
			->andReturn( $wp_version );
		$check->shouldReceive( 'get_php_version' )
			->andReturn( $php_version );
		$check->shouldReceive( 'load_update_functions' )
			->andReturn( true );
		$check->shouldReceive( 'get_core_updates' )
			->andReturn( $core_updates );
		$check->shouldReceive( 'get_plugin_updates' )
			->andReturn( $plugin_updates );
		$check->shouldReceive( 'get_theme_updates' )
			->andReturn( $theme_updates );
	}

	// ─── Pattern Enforcement ──────────────────────────────────────────

	/**
	 * Testa che la classe NON è final
	 */
	public function test_class_is_not_final() {
		$reflection = new \ReflectionClass( VersionsCheck::class );
		$this->assertFalse( $reflection->isFinal(), 'Class should NOT be final' );
	}

	/**
	 * Testa che NON esistono metodi static
	 */
	public function test_no_static_methods() {
		$reflection = new \ReflectionClass( VersionsCheck::class );
		$methods    = $reflection->getMethods( \ReflectionMethod::IS_STATIC );

		$static_methods = array_filter(
			$methods,
			function ( $method ) {
				return strpos( $method->getName(), '__' ) !== 0;
			}
		);

		$this->assertEmpty( $static_methods, 'Class should have NO static methods' );
	}

	/**
	 * Testa che NON esistono proprietà static
	 */
	public function test_no_static_properties() {
		$reflection = new \ReflectionClass( VersionsCheck::class );
		$this->assertEmpty(
			$reflection->getProperties( \ReflectionProperty::IS_STATIC ),
			'Class should have NO static properties'
		);
	}

	// ─── Interface Contract ───────────────────────────────────────────

	/**
	 * Testa che VersionsCheck implementa CheckInterface
	 */
	public function test_implements_check_interface() {
		$check = new VersionsCheck();
		$this->assertInstanceOf( CheckInterface::class, $check );
	}

	/**
	 * Testa che get_id ritorna 'versions'
	 */
	public function test_get_id_returns_versions() {
		$check = new VersionsCheck();
		$this->assertEquals( 'versions', $check->get_id() );
	}

	/**
	 * Testa che get_name ritorna il nome del check
	 */
	public function test_get_name_returns_name() {
		$this->mock_i18n();
		$check = new VersionsCheck();
		$this->assertNotEmpty( $check->get_name() );
		$this->assertIsString( $check->get_name() );
	}

	/**
	 * Testa che is_enabled ritorna sempre true
	 */
	public function test_is_enabled_returns_true() {
		$check = new VersionsCheck();
		$this->assertTrue( $check->is_enabled() );
	}

	/**
	 * Testa che run ritorna un array con la struttura corretta
	 */
	public function test_run_returns_valid_structure() {
		$this->mock_i18n();
		$check = $this->create_check_mock();
		$this->setup_versions( $check );

		$result = $check->run();

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'status', $result );
		$this->assertArrayHasKey( 'message', $result );
		$this->assertArrayHasKey( 'details', $result );
		$this->assertArrayHasKey( 'duration', $result );
	}

	// ─── Happy Path ──────────────────────────────────────────────────

	/**
	 * Testa che tutto aggiornato e PHP recente restituisce 'ok'
	 */
	public function test_all_up_to_date_returns_ok() {
		$this->mock_i18n();
		$check = $this->create_check_mock();

		// Core "latest" (nessun aggiornamento).
		$latest        = new \stdClass();
		$latest->response = 'latest';
		$this->setup_versions( $check, '6.7', '8.3.0', [ $latest ], [], [] );

		$result = $check->run();

		$this->assertEquals( 'ok', $result['status'] );
	}

	/**
	 * Testa che tutto aggiornato con array vuoto di core_updates restituisce ok
	 */
	public function test_empty_core_updates_returns_ok() {
		$this->mock_i18n();
		$check = $this->create_check_mock();
		$this->setup_versions( $check, '6.7', '8.3.0', [], [], [] );

		$result = $check->run();

		$this->assertEquals( 'ok', $result['status'] );
	}

	/**
	 * Testa message OK contiene 'up to date'
	 */
	public function test_ok_message_content() {
		$this->mock_i18n();
		$check = $this->create_check_mock();
		$this->setup_versions( $check, '6.7', '8.3.0', [], [], [] );

		$result = $check->run();

		$this->assertStringContainsString( 'up to date', strtolower( $result['message'] ) );
	}

	// ─── Status Determination ────────────────────────────────────────

	/**
	 * Testa che core update disponibile restituisce 'critical'
	 */
	public function test_core_update_returns_critical() {
		$this->mock_i18n();
		$check = $this->create_check_mock();

		$update           = new \stdClass();
		$update->response = 'upgrade';
		$update->version  = '6.8';
		$this->setup_versions( $check, '6.7', '8.3.0', [ $update ], [], [] );

		$result = $check->run();

		$this->assertEquals( 'critical', $result['status'] );
	}

	/**
	 * Testa che plugin updates disponibili restituisce 'warning'
	 */
	public function test_plugin_updates_returns_warning() {
		$this->mock_i18n();
		$check = $this->create_check_mock();

		$plugin_update          = new \stdClass();
		$plugin_update->Name    = 'Test Plugin';
		$plugin_update->update  = new \stdClass();
		$this->setup_versions( $check, '6.7', '8.3.0', [], [ 'test/test.php' => $plugin_update ], [] );

		$result = $check->run();

		$this->assertEquals( 'warning', $result['status'] );
	}

	/**
	 * Testa che theme updates disponibili restituisce 'warning'
	 */
	public function test_theme_updates_returns_warning() {
		$this->mock_i18n();
		$check = $this->create_check_mock();

		$theme_update         = new \stdClass();
		$theme_update->update = new \stdClass();
		$this->setup_versions( $check, '6.7', '8.3.0', [], [], [ 'twentytwentyfour' => $theme_update ] );

		$result = $check->run();

		$this->assertEquals( 'warning', $result['status'] );
	}

	/**
	 * Testa che PHP obsoleto restituisce 'warning'
	 */
	public function test_old_php_returns_warning() {
		$this->mock_i18n();
		$check = $this->create_check_mock();
		$this->setup_versions( $check, '6.7', '7.4.33', [], [], [] );

		$result = $check->run();

		$this->assertEquals( 'warning', $result['status'] );
	}

	/**
	 * Testa che core update ha priorità su plugin/theme update (critical > warning)
	 */
	public function test_core_update_takes_priority_over_plugin() {
		$this->mock_i18n();
		$check = $this->create_check_mock();

		$core_update           = new \stdClass();
		$core_update->response = 'upgrade';
		$core_update->version  = '6.8';

		$plugin_update         = new \stdClass();
		$plugin_update->Name   = 'Test Plugin';
		$plugin_update->update = new \stdClass();

		$this->setup_versions( $check, '6.7', '8.3.0', [ $core_update ], [ 'test/test.php' => $plugin_update ], [] );

		$result = $check->run();

		$this->assertEquals( 'critical', $result['status'] );
	}

	// ─── Details ─────────────────────────────────────────────────────

	/**
	 * Testa che i dettagli contengono le informazioni di versione
	 */
	public function test_details_contain_version_info() {
		$this->mock_i18n();
		$check = $this->create_check_mock();
		$this->setup_versions( $check, '6.7', '8.3.0', [], [], [] );

		$result = $check->run();

		$this->assertArrayHasKey( 'wp_version', $result['details'] );
		$this->assertArrayHasKey( 'php_version', $result['details'] );
		$this->assertArrayHasKey( 'php_recommended', $result['details'] );
		$this->assertArrayHasKey( 'updates_available', $result['details'] );
		$this->assertEquals( '6.7', $result['details']['wp_version'] );
		$this->assertEquals( '8.3.0', $result['details']['php_version'] );
	}

	/**
	 * Testa che updates_available è un array
	 */
	public function test_details_updates_available_is_array() {
		$this->mock_i18n();
		$check = $this->create_check_mock();
		$this->setup_versions( $check, '6.7', '8.3.0', [], [], [] );

		$result = $check->run();

		$this->assertIsArray( $result['details']['updates_available'] );
		$this->assertEmpty( $result['details']['updates_available'] );
	}

	/**
	 * Testa che updates_available riporta gli aggiornamenti disponibili
	 */
	public function test_details_updates_lists_available_updates() {
		$this->mock_i18n();
		$check = $this->create_check_mock();

		$core_update           = new \stdClass();
		$core_update->response = 'upgrade';
		$core_update->version  = '6.8';

		$plugin_update         = new \stdClass();
		$plugin_update->Name   = 'WooCommerce';
		$plugin_update->update = new \stdClass();

		$this->setup_versions( $check, '6.7', '8.3.0', [ $core_update ], [ 'woocommerce/woo.php' => $plugin_update ], [] );

		$result  = $check->run();
		$updates = $result['details']['updates_available'];

		$this->assertNotEmpty( $updates );
		// Dovrebbe contenere riferimento al core update.
		$joined = implode( ' ', $updates );
		$this->assertStringContainsString( '6.8', $joined );
	}

	// ─── Edge Cases ─────────────────────────────────────────────────

	/**
	 * Testa che core_updates con 'latest' response viene filtrato
	 */
	public function test_core_updates_latest_is_filtered() {
		$this->mock_i18n();
		$check = $this->create_check_mock();

		$latest           = new \stdClass();
		$latest->response = 'latest';

		$this->setup_versions( $check, '6.7', '8.3.0', [ $latest ], [], [] );

		$result = $check->run();

		$this->assertEquals( 'ok', $result['status'] );
	}

	/**
	 * Testa graceful degradation quando load_update_functions fallisce
	 */
	public function test_graceful_when_update_functions_fail() {
		$this->mock_i18n();
		$check = $this->create_check_mock();

		$check->shouldReceive( 'get_wp_version' )->andReturn( '6.7' );
		$check->shouldReceive( 'get_php_version' )->andReturn( '8.3.0' );
		$check->shouldReceive( 'load_update_functions' )
			->andThrow( new \Exception( 'File not found' ) );

		$result = $check->run();

		// Non deve crashare, ritorna warning perché update check non completato.
		$this->assertEquals( 'warning', $result['status'] );
		$this->assertStringContainsString( 'Unable to check', $result['message'] );
		$this->assertArrayHasKey( 'wp_version', $result['details'] );
		$this->assertArrayHasKey( 'php_version', $result['details'] );
	}

	/**
	 * Testa graceful degradation quando get_core_updates fallisce (dopo load)
	 */
	public function test_graceful_when_get_core_updates_throws() {
		$this->mock_i18n();
		$check = $this->create_check_mock();

		$check->shouldReceive( 'get_wp_version' )->andReturn( '6.7' );
		$check->shouldReceive( 'get_php_version' )->andReturn( '8.3.0' );
		$check->shouldReceive( 'load_update_functions' )->once();
		$check->shouldReceive( 'get_core_updates' )
			->andThrow( new \Exception( 'API error' ) );

		$result = $check->run();

		$this->assertEquals( 'warning', $result['status'] );
		$this->assertStringContainsString( 'Unable to check', $result['message'] );
	}

	/**
	 * Testa che core_updates con risposta 'development' viene filtrato
	 */
	public function test_core_updates_development_is_filtered() {
		$this->mock_i18n();
		$check = $this->create_check_mock();

		$dev           = new \stdClass();
		$dev->response = 'development';

		$this->setup_versions( $check, '6.7', '8.3.0', [ $dev ], [], [] );

		$result = $check->run();

		// Development response is not a real update.
		$this->assertEquals( 'ok', $result['status'] );
	}

	/**
	 * Testa che PHP esattamente alla versione raccomandata restituisce ok
	 */
	public function test_php_at_recommended_version_returns_ok() {
		$this->mock_i18n();
		$check = $this->create_check_mock();
		$this->setup_versions( $check, '6.7', '8.1.0', [], [], [] );

		$result = $check->run();

		$this->assertEquals( 'ok', $result['status'] );
	}

	/**
	 * Testa che PHP sotto la versione raccomandata ma sopra 8.0 restituisce warning
	 */
	public function test_php_below_recommended_returns_warning() {
		$this->mock_i18n();
		$check = $this->create_check_mock();
		$this->setup_versions( $check, '6.7', '8.0.30', [], [], [] );

		$result = $check->run();

		$this->assertEquals( 'warning', $result['status'] );
	}

	// ─── Duration ────────────────────────────────────────────────────

	/**
	 * Testa che la durata è un float positivo
	 */
	public function test_duration_is_positive_float() {
		$this->mock_i18n();
		$check = $this->create_check_mock();
		$this->setup_versions( $check, '6.7', '8.3.0', [], [], [] );

		$result = $check->run();

		$this->assertIsFloat( $result['duration'] );
		$this->assertGreaterThanOrEqual( 0, $result['duration'] );
	}

	// ─── Constants ───────────────────────────────────────────────────

	/**
	 * Testa che la costante RECOMMENDED_PHP_VERSION è definita
	 */
	public function test_recommended_php_version_constant() {
		$this->assertEquals( '8.1', VersionsCheck::RECOMMENDED_PHP_VERSION );
	}

	// ─── Protected Methods ──────────────────────────────────────────

	/**
	 * Testa che get_wp_version è un metodo protetto
	 */
	public function test_get_wp_version_is_protected() {
		$reflection = new \ReflectionMethod( VersionsCheck::class, 'get_wp_version' );
		$this->assertTrue( $reflection->isProtected() );
	}

	/**
	 * Testa che get_php_version è un metodo protetto
	 */
	public function test_get_php_version_is_protected() {
		$reflection = new \ReflectionMethod( VersionsCheck::class, 'get_php_version' );
		$this->assertTrue( $reflection->isProtected() );
	}

	/**
	 * Testa che load_update_functions è un metodo protetto
	 */
	public function test_load_update_functions_is_protected() {
		$reflection = new \ReflectionMethod( VersionsCheck::class, 'load_update_functions' );
		$this->assertTrue( $reflection->isProtected() );
	}

	/**
	 * Testa che get_core_updates è un metodo protetto
	 */
	public function test_get_core_updates_is_protected() {
		$reflection = new \ReflectionMethod( VersionsCheck::class, 'get_core_updates' );
		$this->assertTrue( $reflection->isProtected() );
	}

	/**
	 * Testa che get_plugin_updates è un metodo protetto
	 */
	public function test_get_plugin_updates_is_protected() {
		$reflection = new \ReflectionMethod( VersionsCheck::class, 'get_plugin_updates' );
		$this->assertTrue( $reflection->isProtected() );
	}

	/**
	 * Testa che get_theme_updates è un metodo protetto
	 */
	public function test_get_theme_updates_is_protected() {
		$reflection = new \ReflectionMethod( VersionsCheck::class, 'get_theme_updates' );
		$this->assertTrue( $reflection->isProtected() );
	}
}
