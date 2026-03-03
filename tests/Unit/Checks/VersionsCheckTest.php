<?php
/**
 * Unit Test for VersionsCheck
 *
 * Unit test with Brain\Monkey for VersionsCheck.
 * Uses Mockery partial mock for protected methods.
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
 * Unit test for VersionsCheck.
 */
class VersionsCheckTest extends TestCase {
	use MockeryPHPUnitIntegration;

	/**
	 * Setup for each test
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	/**
	 * Teardown after each test
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Creates a partial mock of VersionsCheck with mockable protected methods
	 *
	 * @return \Mockery\MockInterface|VersionsCheck
	 */
	private function create_check_mock() {
		return Mockery::mock( VersionsCheck::class )
			->makePartial()
			->shouldAllowMockingProtectedMethods();
	}

	/**
	 * Sets up WordPress i18n mocks
	 */
	private function mock_i18n(): void {
		Functions\when( '__' )->returnArg();
		Functions\when( 'esc_html__' )->returnArg();
	}

	/**
	 * Sets up the mock for a check with all versions and updates
	 *
	 * @param \Mockery\MockInterface $check           Check mock.
	 * @param string                 $wp_version      WordPress version.
	 * @param string                 $php_version     PHP version.
	 * @param array                  $core_updates    Core updates.
	 * @param array                  $plugin_updates  Plugin updates.
	 * @param array                  $theme_updates   Theme updates.
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
	 * Tests that the class is NOT final
	 */
	public function test_class_is_not_final() {
		$reflection = new \ReflectionClass( VersionsCheck::class );
		$this->assertFalse( $reflection->isFinal(), 'Class should NOT be final' );
	}

	/**
	 * Tests that NO static methods exist
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
	 * Tests that NO static properties exist
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
	 * Tests that VersionsCheck implements CheckInterface
	 */
	public function test_implements_check_interface() {
		$check = new VersionsCheck();
		$this->assertInstanceOf( CheckInterface::class, $check );
	}

	/**
	 * Tests that get_id returns 'versions'
	 */
	public function test_get_id_returns_versions() {
		$check = new VersionsCheck();
		$this->assertEquals( 'versions', $check->get_id() );
	}

	/**
	 * Tests that get_name returns the check name
	 */
	public function test_get_name_returns_name() {
		$this->mock_i18n();
		$check = new VersionsCheck();
		$this->assertNotEmpty( $check->get_name() );
		$this->assertIsString( $check->get_name() );
	}

	/**
	 * Tests that is_enabled always returns true
	 */
	public function test_is_enabled_returns_true() {
		$check = new VersionsCheck();
		$this->assertTrue( $check->is_enabled() );
	}

	/**
	 * Tests that run returns an array with the correct structure
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
	 * Tests that all up to date and recent PHP returns 'ok'
	 */
	public function test_all_up_to_date_returns_ok() {
		$this->mock_i18n();
		$check = $this->create_check_mock();

		// Core "latest" (no updates).
		$latest        = new \stdClass();
		$latest->response = 'latest';
		$this->setup_versions( $check, '6.7', '8.3.0', [ $latest ], [], [] );

		$result = $check->run();

		$this->assertEquals( 'ok', $result['status'] );
	}

	/**
	 * Tests that all up to date with empty core_updates array returns ok
	 */
	public function test_empty_core_updates_returns_ok() {
		$this->mock_i18n();
		$check = $this->create_check_mock();
		$this->setup_versions( $check, '6.7', '8.3.0', [], [], [] );

		$result = $check->run();

		$this->assertEquals( 'ok', $result['status'] );
	}

	/**
	 * Tests OK message contains 'up to date'
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
	 * Tests that available core update returns 'critical'
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
	 * Tests that available plugin updates returns 'warning'
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
	 * Tests that available theme updates returns 'warning'
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
	 * Tests that outdated PHP returns 'warning'
	 */
	public function test_old_php_returns_warning() {
		$this->mock_i18n();
		$check = $this->create_check_mock();
		$this->setup_versions( $check, '6.7', '7.4.33', [], [], [] );

		$result = $check->run();

		$this->assertEquals( 'warning', $result['status'] );
	}

	/**
	 * Tests that core update takes priority over plugin/theme update (critical > warning)
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
	 * Tests that details contain version information
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
	 * Tests that updates_available is an array
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
	 * Tests that updates_available reports the available updates
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
		// Should contain reference to the core update.
		$joined = implode( ' ', $updates );
		$this->assertStringContainsString( '6.8', $joined );
	}

	// ─── Edge Cases ─────────────────────────────────────────────────

	/**
	 * Tests that core_updates with 'latest' response is filtered
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
	 * Tests graceful degradation when load_update_functions fails
	 */
	public function test_graceful_when_update_functions_fail() {
		$this->mock_i18n();
		$check = $this->create_check_mock();

		$check->shouldReceive( 'get_wp_version' )->andReturn( '6.7' );
		$check->shouldReceive( 'get_php_version' )->andReturn( '8.3.0' );
		$check->shouldReceive( 'load_update_functions' )
			->andThrow( new \Exception( 'File not found' ) );

		$result = $check->run();

		// Must not crash, returns warning because update check not completed.
		$this->assertEquals( 'warning', $result['status'] );
		$this->assertStringContainsString( 'Unable to check', $result['message'] );
		$this->assertArrayHasKey( 'wp_version', $result['details'] );
		$this->assertArrayHasKey( 'php_version', $result['details'] );
	}

	/**
	 * Tests graceful degradation when get_core_updates fails (after load)
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
	 * Tests that core_updates with 'development' response is filtered
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
	 * Tests that PHP at exactly the recommended version returns ok
	 */
	public function test_php_at_recommended_version_returns_ok() {
		$this->mock_i18n();
		$check = $this->create_check_mock();
		$this->setup_versions( $check, '6.7', '8.3.0', [], [], [] );

		$result = $check->run();

		$this->assertEquals( 'ok', $result['status'] );
	}

	/**
	 * Tests that PHP below the recommended version but above 8.0 returns warning
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
	 * Tests that the duration is a positive float
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
	 * Tests that the RECOMMENDED_PHP_VERSION constant is defined
	 */
	public function test_recommended_php_version_constant() {
		$this->assertEquals( '8.3', VersionsCheck::RECOMMENDED_PHP_VERSION );
	}

	// ─── Protected Methods ──────────────────────────────────────────

	/**
	 * Tests that get_wp_version is a protected method
	 */
	public function test_get_wp_version_is_protected() {
		$reflection = new \ReflectionMethod( VersionsCheck::class, 'get_wp_version' );
		$this->assertTrue( $reflection->isProtected() );
	}

	/**
	 * Tests that get_php_version is a protected method
	 */
	public function test_get_php_version_is_protected() {
		$reflection = new \ReflectionMethod( VersionsCheck::class, 'get_php_version' );
		$this->assertTrue( $reflection->isProtected() );
	}

	/**
	 * Tests that load_update_functions is a protected method
	 */
	public function test_load_update_functions_is_protected() {
		$reflection = new \ReflectionMethod( VersionsCheck::class, 'load_update_functions' );
		$this->assertTrue( $reflection->isProtected() );
	}

	/**
	 * Tests that get_core_updates is a protected method
	 */
	public function test_get_core_updates_is_protected() {
		$reflection = new \ReflectionMethod( VersionsCheck::class, 'get_core_updates' );
		$this->assertTrue( $reflection->isProtected() );
	}

	/**
	 * Tests that get_plugin_updates is a protected method
	 */
	public function test_get_plugin_updates_is_protected() {
		$reflection = new \ReflectionMethod( VersionsCheck::class, 'get_plugin_updates' );
		$this->assertTrue( $reflection->isProtected() );
	}

	/**
	 * Tests that get_theme_updates is a protected method
	 */
	public function test_get_theme_updates_is_protected() {
		$reflection = new \ReflectionMethod( VersionsCheck::class, 'get_theme_updates' );
		$this->assertTrue( $reflection->isProtected() );
	}

	// ─── Reflection-based Coverage for Protected Methods ────────────

	/**
	 * Tests that get_php_version returns PHP_VERSION
	 */
	public function test_get_php_version_returns_current_version() {
		$check      = new VersionsCheck();
		$reflection = new \ReflectionMethod( VersionsCheck::class, 'get_php_version' );
		$reflection->setAccessible( true );

		$this->assertEquals( PHP_VERSION, $reflection->invoke( $check ) );
	}

	/**
	 * Tests that get_wp_version returns the value of the $wp_version global
	 */
	public function test_get_wp_version_returns_global() {
		global $wp_version;
		$wp_version = '6.7.1';

		$check      = new VersionsCheck();
		$reflection = new \ReflectionMethod( VersionsCheck::class, 'get_wp_version' );
		$reflection->setAccessible( true );

		$this->assertEquals( '6.7.1', $reflection->invoke( $check ) );

		unset( $wp_version );
	}

	/**
	 * Tests that get_wp_version returns empty string if global is not defined
	 */
	public function test_get_wp_version_returns_empty_when_undefined() {
		// Ensure the global is not defined.
		unset( $GLOBALS['wp_version'] );

		$check      = new VersionsCheck();
		$reflection = new \ReflectionMethod( VersionsCheck::class, 'get_wp_version' );
		$reflection->setAccessible( true );

		$this->assertEquals( '', $reflection->invoke( $check ) );
	}

	/**
	 * Tests that load_update_functions does not throw when the function exists
	 */
	public function test_load_update_functions_when_function_exists() {
		// Brain\Monkey mock makes get_core_updates visible to function_exists().
		Functions\when( 'get_core_updates' )->justReturn( [] );

		$check      = new VersionsCheck();
		$reflection = new \ReflectionMethod( VersionsCheck::class, 'load_update_functions' );
		$reflection->setAccessible( true );

		// function_exists('get_core_updates') is true -> skip require_once.
		$reflection->invoke( $check );
		$this->assertTrue( true );
	}

	/**
	 * Tests that get_core_updates invokes the WP function and handles array
	 */
	public function test_get_core_updates_returns_array() {
		$expected = [ (object) [ 'response' => 'latest' ] ];
		Functions\when( 'get_core_updates' )->justReturn( $expected );

		$check      = new VersionsCheck();
		$reflection = new \ReflectionMethod( VersionsCheck::class, 'get_core_updates' );
		$reflection->setAccessible( true );

		$result = $reflection->invoke( $check );
		$this->assertIsArray( $result );
		$this->assertCount( 1, $result );
	}

	/**
	 * Tests that get_core_updates handles non-array value (false)
	 */
	public function test_get_core_updates_handles_non_array() {
		Functions\when( 'get_core_updates' )->justReturn( false );

		$check      = new VersionsCheck();
		$reflection = new \ReflectionMethod( VersionsCheck::class, 'get_core_updates' );
		$reflection->setAccessible( true );

		$result = $reflection->invoke( $check );
		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	/**
	 * Tests that get_plugin_updates invokes the WP function
	 */
	public function test_get_plugin_updates_returns_array() {
		Functions\when( 'get_plugin_updates' )->justReturn( [] );

		$check      = new VersionsCheck();
		$reflection = new \ReflectionMethod( VersionsCheck::class, 'get_plugin_updates' );
		$reflection->setAccessible( true );

		$result = $reflection->invoke( $check );
		$this->assertIsArray( $result );
	}

	/**
	 * Tests that get_theme_updates invokes the WP function
	 */
	public function test_get_theme_updates_returns_array() {
		Functions\when( 'get_theme_updates' )->justReturn( [] );

		$check      = new VersionsCheck();
		$reflection = new \ReflectionMethod( VersionsCheck::class, 'get_theme_updates' );
		$reflection->setAccessible( true );

		$result = $reflection->invoke( $check );
		$this->assertIsArray( $result );
	}

	// ─── Update Check Failed + Old PHP ─────────────────────────────

	/**
	 * Tests update_check_failed with outdated PHP adds PHP message
	 */
	public function test_update_check_failed_with_old_php() {
		$this->mock_i18n();
		$check = $this->create_check_mock();
		$check->shouldReceive( 'get_wp_version' )->andReturn( '6.7' );
		$check->shouldReceive( 'get_php_version' )->andReturn( '7.4.33' );
		$check->shouldReceive( 'load_update_functions' )
			->andThrow( new \Exception( 'Not available' ) );

		$result = $check->run();

		$this->assertEquals( 'warning', $result['status'] );
		$this->assertStringContainsString( 'Unable to check', $result['message'] );
		$this->assertStringContainsString( 'PHP', $result['message'] );
		$this->assertStringContainsString( '8.3', $result['message'] );
	}

	/**
	 * Tests warning message with plugin updates + old PHP (double message)
	 */
	public function test_warning_message_with_plugin_updates_and_old_php() {
		$this->mock_i18n();
		$check = $this->create_check_mock();

		$plugin_update         = new \stdClass();
		$plugin_update->Name   = 'Test';
		$plugin_update->update = new \stdClass();

		$this->setup_versions(
			$check,
			'6.7',
			'7.4.33',
			[],
			[ 'test/test.php' => $plugin_update ],
			[]
		);

		$result = $check->run();

		$this->assertEquals( 'warning', $result['status'] );
		// Message contains both plugin update info and PHP recommendation.
		$this->assertStringContainsString( 'plugin', $result['message'] );
		$this->assertStringContainsString( 'PHP', $result['message'] );
	}

	/**
	 * Tests that theme updates produce correct message
	 */
	public function test_theme_update_message_content() {
		$this->mock_i18n();
		$check = $this->create_check_mock();

		$theme_update         = new \stdClass();
		$theme_update->update = new \stdClass();

		$this->setup_versions(
			$check,
			'6.7',
			'8.3.0',
			[],
			[],
			[ 'twentytwentyfour' => $theme_update ]
		);

		$result = $check->run();

		$this->assertEquals( 'warning', $result['status'] );
		$this->assertStringContainsString( 'theme', $result['message'] );
	}

	/**
	 * Tests that core update without version field handles gracefully
	 */
	public function test_core_update_without_version_field() {
		$this->mock_i18n();
		$check = $this->create_check_mock();

		$update           = new \stdClass();
		$update->response = 'upgrade';
		// No version field.
		$this->setup_versions( $check, '6.7', '8.3.0', [ $update ], [], [] );

		$result = $check->run();

		$this->assertEquals( 'critical', $result['status'] );
		$this->assertNotEmpty( $result['details']['updates_available'] );
	}

	/**
	 * Tests filter_real_updates with update without response field
	 */
	public function test_filter_real_updates_without_response() {
		$this->mock_i18n();

		$check = $this->create_check_mock();

		$no_response = new \stdClass();
		// No response field.
		$this->setup_versions( $check, '6.7', '8.3.0', [ $no_response ], [], [] );

		$result = $check->run();

		// Update without response is filtered -> ok.
		$this->assertEquals( 'ok', $result['status'] );
	}
}
