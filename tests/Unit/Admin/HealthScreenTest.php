<?php
/**
 * Unit Test for HealthScreen
 *
 * Unit test with Brain\Monkey per HealthScreen.
 *
 * @package OpsHealthDashboard\Tests\Unit\Admin
 */

namespace OpsHealthDashboard\Tests\Unit\Admin;

use Brain\Monkey\Functions;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use OpsHealthDashboard\Admin\HealthScreen;
use OpsHealthDashboard\Interfaces\CheckRunnerInterface;
use OpsHealthDashboard\Interfaces\StorageInterface;
use PHPUnit\Framework\TestCase;

/**
 * Class HealthScreenTest
 *
 * Unit test for HealthScreen.
 */
class HealthScreenTest extends TestCase {
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
		unset( $_POST['ops_health_action'], $_POST['_ops_health_nonce'] );
		\Brain\Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Creates a mock StorageInterface for the tests
	 *
	 * @param int $last_run_at Value of last_run_at to return.
	 * @return \Mockery\MockInterface
	 */
	private function create_storage_mock( int $last_run_at = 0 ) {
		$storage = Mockery::mock( StorageInterface::class );
		$storage->shouldReceive( 'get' )
			->with( 'last_run_at', 0 )
			->andReturn( $last_run_at );
		return $storage;
	}

	/**
	 * Tests that HealthScreen can be instantiated with dependencies
	 */
	public function test_health_screen_can_be_instantiated() {
		$runner  = Mockery::mock( CheckRunnerInterface::class );
		$storage = Mockery::mock( StorageInterface::class );
		$screen  = new HealthScreen( $runner, $storage );

		$this->assertInstanceOf( HealthScreen::class, $screen );
	}

	/**
	 * Tests that render() shows the check results
	 */
	public function test_render_shows_check_results() {
		$runner = Mockery::mock( CheckRunnerInterface::class );
		$runner->shouldReceive( 'get_latest_results' )
			->once()
			->andReturn( [
				'database' => [
					'status'   => 'ok',
					'message'  => 'Database OK',
					'name'     => 'Database Connection',
					'details'  => [],
					'duration' => 0,
				],
			] );

		$storage = $this->create_storage_mock();
		$this->mock_render_functions();

		$screen = new HealthScreen( $runner, $storage );

		ob_start();
		$screen->render();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Ops Health Dashboard', $output );
		$this->assertStringContainsString( 'Database Connection', $output );
		$this->assertStringContainsString( 'ops-health-check-ok', $output );
	}

	/**
	 * Tests that render() shows "no checks" message when there are no results
	 */
	public function test_render_shows_no_checks_message_when_empty() {
		$runner = Mockery::mock( CheckRunnerInterface::class );
		$runner->shouldReceive( 'get_latest_results' )
			->once()
			->andReturn( [] );

		$storage = $this->create_storage_mock();
		$this->mock_render_functions();

		$screen = new HealthScreen( $runner, $storage );

		ob_start();
		$screen->render();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'No health checks have been run yet.', $output );
		$this->assertStringContainsString( 'notice-info', $output );
	}

	/**
	 * Tests that render() blocks users without capability
	 */
	public function test_render_blocks_unauthorized_users() {
		$runner  = Mockery::mock( CheckRunnerInterface::class );
		$storage = Mockery::mock( StorageInterface::class );

		Functions\expect( 'current_user_can' )
			->once()
			->with( 'manage_options' )
			->andReturn( false );

		Functions\expect( 'esc_html__' )
			->andReturnUsing( function ( $text ) {
				return $text;
			} );

		Functions\expect( 'wp_die' )
			->once()
			->with( \Mockery::type( 'string' ) )
			->andReturnUsing( function () {
				throw new \RuntimeException( 'wp_die called' );
			} );

		$screen = new HealthScreen( $runner, $storage );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'wp_die called' );
		$screen->render();
	}

	/**
	 * Tests that render() handles results without status/message keys
	 */
	public function test_render_handles_result_with_missing_keys() {
		$runner = Mockery::mock( CheckRunnerInterface::class );
		$runner->shouldReceive( 'get_latest_results' )
			->once()
			->andReturn( [
				'test_check' => [
					'details'  => [],
					'duration' => 0,
				],
			] );

		$storage = $this->create_storage_mock();
		$this->mock_render_functions();

		$screen = new HealthScreen( $runner, $storage );

		ob_start();
		$screen->render();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'ops-health-check-unknown', $output );
		$this->assertStringContainsString( 'Test_check', $output );
	}

	/**
	 * Helper: common mocks for render con bottoni
	 *
	 * @return void
	 */
	private function mock_render_functions() {
		Functions\expect( 'current_user_can' )
			->with( 'manage_options' )
			->andReturn( true );

		Functions\expect( 'esc_html__' )
			->andReturnUsing( function ( $text ) {
				return $text;
			} );

		Functions\expect( 'esc_html' )
			->andReturnUsing( function ( $text ) {
				return $text;
			} );

		Functions\expect( 'esc_attr' )
			->andReturnUsing( function ( $text ) {
				return $text;
			} );

		Functions\expect( 'esc_url' )
			->andReturnUsing( function ( $url ) {
				return $url;
			} );

		Functions\expect( 'wp_nonce_field' )
			->andReturnUsing( function ( $action, $name ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo '<input type="hidden" name="' . $name . '" value="nonce_token" />';
			} );

		Functions\expect( 'submit_button' )
			->andReturnUsing( function ( $text, $type, $name ) {
				echo '<input type="submit" name="' . $name . '" value="' . $text . '" class="button ' . $type . '" />';
			} );

		Functions\expect( 'get_transient' )
			->andReturn( false );

		Functions\expect( 'wp_next_scheduled' )
			->andReturn( 0 );

		Functions\expect( 'admin_url' )
			->andReturnUsing( function ( $path ) {
				return 'http://example.com/wp-admin/' . $path;
			} );

		Functions\when( '__' )->returnArg();

		Functions\expect( '_n' )
			->andReturnUsing( function ( $single, $plural, $count ) {
				return $count === 1 ? $single : $plural;
			} );

		Functions\expect( 'human_time_diff' )
			->andReturn( '5 mins' );
	}

	/**
	 * Tests that render() contains the Run Now button
	 */
	public function test_render_contains_run_now_button() {
		$runner = Mockery::mock( CheckRunnerInterface::class );
		$runner->shouldReceive( 'get_latest_results' )->andReturn( [] );

		$storage = $this->create_storage_mock();
		$this->mock_render_functions();

		$screen = new HealthScreen( $runner, $storage );

		ob_start();
		$screen->render();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'run_now', $output );
	}

	/**
	 * Tests that render() contains the Clear Cache button
	 */
	public function test_render_contains_clear_cache_button() {
		$runner = Mockery::mock( CheckRunnerInterface::class );
		$runner->shouldReceive( 'get_latest_results' )->andReturn( [] );

		$storage = $this->create_storage_mock();
		$this->mock_render_functions();

		$screen = new HealthScreen( $runner, $storage );

		ob_start();
		$screen->render();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'clear_cache', $output );
	}

	/**
	 * Tests that render() contains the nonce fields for security
	 */
	public function test_render_contains_nonce_fields() {
		$runner = Mockery::mock( CheckRunnerInterface::class );
		$runner->shouldReceive( 'get_latest_results' )->andReturn( [] );

		$storage = $this->create_storage_mock();
		$this->mock_render_functions();

		$screen = new HealthScreen( $runner, $storage );

		ob_start();
		$screen->render();
		$output = ob_get_clean();

		$this->assertStringContainsString( '_ops_health_nonce', $output );
	}

	/**
	 * Helper: creates a partial mock of HealthScreen with do_exit() disabled
	 *
	 * @param \Mockery\MockInterface $runner  CheckRunner mock.
	 * @param \Mockery\MockInterface $storage Storage mock.
	 * @return \Mockery\MockInterface
	 */
	private function create_testable_screen( $runner, $storage = null ) {
		if ( null === $storage ) {
			$storage = Mockery::mock( StorageInterface::class );
		}

		$screen = Mockery::mock( HealthScreen::class, [ $runner, $storage ] )
			->makePartial()
			->shouldAllowMockingProtectedMethods();

		$screen->shouldReceive( 'do_exit' )->andReturnNull();

		return $screen;
	}

	/**
	 * Helper: common mocks for process_actions
	 *
	 * @return void
	 */
	private function mock_process_functions() {
		Functions\expect( 'sanitize_text_field' )
			->andReturnUsing( function ( $text ) {
				return $text;
			} );

		Functions\expect( 'wp_unslash' )
			->andReturnUsing( function ( $text ) {
				return $text;
			} );

		Functions\expect( '__' )
			->andReturnUsing( function ( $text ) {
				return $text;
			} );
	}

	/**
	 * Tests that process_actions() returns early without POST action
	 */
	public function test_process_actions_returns_early_without_post_action() {
		$runner  = Mockery::mock( CheckRunnerInterface::class );
		$storage = Mockery::mock( StorageInterface::class );
		$runner->shouldNotReceive( 'run_all' );
		$runner->shouldNotReceive( 'clear_results' );

		unset( $_POST['ops_health_action'] );

		$screen = new HealthScreen( $runner, $storage );
		$screen->process_actions();

		$this->assertArrayNotHasKey( 'ops_health_action', $_POST );
	}

	/**
	 * Tests that process_actions() returns early with invalid nonce
	 */
	public function test_process_actions_returns_early_with_invalid_nonce() {
		$runner  = Mockery::mock( CheckRunnerInterface::class );
		$storage = Mockery::mock( StorageInterface::class );
		$runner->shouldNotReceive( 'run_all' );
		$runner->shouldNotReceive( 'clear_results' );

		$_POST['ops_health_action'] = 'run_now';
		$_POST['_ops_health_nonce'] = 'invalid_nonce';

		$this->mock_process_functions();

		Functions\expect( 'wp_verify_nonce' )
			->once()
			->andReturn( false );

		$screen = new HealthScreen( $runner, $storage );
		$screen->process_actions();

		unset( $_POST['ops_health_action'], $_POST['_ops_health_nonce'] );

		$this->assertInstanceOf( HealthScreen::class, $screen );
	}

	/**
	 * Tests that process_actions() returns early without capability
	 */
	public function test_process_actions_returns_early_without_capability() {
		$runner  = Mockery::mock( CheckRunnerInterface::class );
		$storage = Mockery::mock( StorageInterface::class );
		$runner->shouldNotReceive( 'run_all' );
		$runner->shouldNotReceive( 'clear_results' );

		$_POST['ops_health_action'] = 'run_now';
		$_POST['_ops_health_nonce'] = 'valid_nonce';

		$this->mock_process_functions();

		Functions\expect( 'wp_verify_nonce' )
			->once()
			->andReturn( 1 );

		Functions\expect( 'current_user_can' )
			->once()
			->with( 'manage_options' )
			->andReturn( false );

		$screen = new HealthScreen( $runner, $storage );
		$screen->process_actions();

		$this->assertInstanceOf( HealthScreen::class, $screen );
	}

	/**
	 * Tests that process_actions() runs run_all with action run_now
	 */
	public function test_process_actions_runs_checks_on_run_now() {
		$runner  = Mockery::mock( CheckRunnerInterface::class );
		$storage = Mockery::mock( StorageInterface::class );
		$runner->shouldReceive( 'run_all' )
			->once()
			->andReturn( [] );

		$_POST['ops_health_action'] = 'run_now';
		$_POST['_ops_health_nonce'] = 'valid_nonce';

		$this->mock_process_functions();

		Functions\expect( 'wp_verify_nonce' )
			->once()
			->andReturn( 1 );

		Functions\expect( 'current_user_can' )
			->once()
			->with( 'manage_options' )
			->andReturn( true );

		Functions\expect( 'set_transient' )
			->once()
			->with( 'ops_health_admin_notice', \Mockery::type( 'string' ), 30 );

		Functions\expect( 'admin_url' )
			->once()
			->andReturn( 'http://example.com/wp-admin/admin.php?page=ops-health-dashboard' );

		Functions\expect( 'wp_safe_redirect' )
			->once()
			->with( 'http://example.com/wp-admin/admin.php?page=ops-health-dashboard' );

		$screen = $this->create_testable_screen( $runner, $storage );
		$screen->process_actions();

		$this->assertInstanceOf( HealthScreen::class, $screen );
	}

	/**
	 * Tests that process_actions() runs clear_results with action clear_cache
	 */
	public function test_process_actions_clears_cache_on_clear_cache() {
		$runner  = Mockery::mock( CheckRunnerInterface::class );
		$storage = Mockery::mock( StorageInterface::class );
		$runner->shouldReceive( 'clear_results' )
			->once();

		$_POST['ops_health_action'] = 'clear_cache';
		$_POST['_ops_health_nonce'] = 'valid_nonce';

		$this->mock_process_functions();

		Functions\expect( 'wp_verify_nonce' )
			->once()
			->andReturn( 1 );

		Functions\expect( 'current_user_can' )
			->once()
			->with( 'manage_options' )
			->andReturn( true );

		Functions\expect( 'set_transient' )
			->once()
			->with( 'ops_health_admin_notice', \Mockery::type( 'string' ), 30 );

		Functions\expect( 'admin_url' )
			->once()
			->andReturn( 'http://example.com/wp-admin/admin.php?page=ops-health-dashboard' );

		Functions\expect( 'wp_safe_redirect' )
			->once();

		$screen = $this->create_testable_screen( $runner, $storage );
		$screen->process_actions();

		$this->assertInstanceOf( HealthScreen::class, $screen );
	}

	/**
	 * Tests that render() shows notice when transient is set
	 */
	public function test_render_shows_notice_when_transient_set() {
		$runner = Mockery::mock( CheckRunnerInterface::class );
		$runner->shouldReceive( 'get_latest_results' )
			->once()
			->andReturn( [] );

		$storage = $this->create_storage_mock();

		Functions\expect( 'current_user_can' )
			->with( 'manage_options' )
			->andReturn( true );

		Functions\expect( 'esc_html__' )
			->andReturnUsing( function ( $text ) {
				return $text;
			} );

		Functions\expect( 'esc_html' )
			->andReturnUsing( function ( $text ) {
				return $text;
			} );

		Functions\expect( 'esc_attr' )
			->andReturnUsing( function ( $text ) {
				return $text;
			} );

		Functions\expect( 'wp_nonce_field' )
			->andReturnUsing( function ( $action, $name ) {
				echo '<input type="hidden" name="' . $name . '" value="nonce_token" />';
			} );

		Functions\expect( 'submit_button' )
			->andReturnUsing( function ( $text, $type, $name ) {
				echo '<input type="submit" name="' . $name . '" value="' . $text . '" />';
			} );

		Functions\expect( 'get_transient' )
			->once()
			->with( 'ops_health_admin_notice' )
			->andReturn( 'Test notice message' );

		Functions\expect( 'delete_transient' )
			->once()
			->with( 'ops_health_admin_notice' );

		Functions\expect( 'wp_next_scheduled' )
			->andReturn( 0 );

		$screen = new HealthScreen( $runner, $storage );

		ob_start();
		$screen->render();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'notice-success', $output );
		$this->assertStringContainsString( 'Test notice message', $output );
	}

	// ─── register_hooks ────────────────────────────────────────────

	/**
	 * Tests that register_hooks registers the admin_enqueue_scripts hook
	 */
	public function test_register_hooks_adds_admin_enqueue_scripts() {
		$runner  = Mockery::mock( CheckRunnerInterface::class );
		$storage = Mockery::mock( StorageInterface::class );
		$screen  = new HealthScreen( $runner, $storage );

		$hooks = [];
		Functions\expect( 'add_action' )
			->atLeast()
			->times( 1 )
			->andReturnUsing(
				function ( $hook, $callback ) use ( &$hooks ) {
					$hooks[ $hook ] = $callback;
				}
			);

		$screen->register_hooks();

		$this->assertArrayHasKey( 'admin_enqueue_scripts', $hooks );
		$this->assertSame( [ $screen, 'enqueue_styles' ], $hooks['admin_enqueue_scripts'] );
	}

	// ─── enqueue_styles ────────────────────────────────────────────

	/**
	 * Tests that SCREEN_ID has the correct value
	 */
	public function test_screen_id_constant_value() {
		$this->assertSame(
			'toplevel_page_ops-health-dashboard',
			HealthScreen::SCREEN_ID
		);
	}

	/**
	 * Tests that enqueue_styles loads the CSS on the health screen
	 */
	public function test_enqueue_styles_on_health_screen() {
		if ( ! defined( 'OPS_HEALTH_DASHBOARD_FILE' ) ) {
			define( 'OPS_HEALTH_DASHBOARD_FILE', '/fake/ops-health-dashboard.php' );
		}
		if ( ! defined( 'OPS_HEALTH_DASHBOARD_VERSION' ) ) {
			define( 'OPS_HEALTH_DASHBOARD_VERSION', '0.6.0' );
		}

		$screen_obj     = new \stdClass();
		$screen_obj->id = 'toplevel_page_ops-health-dashboard';

		Functions\expect( 'get_current_screen' )
			->once()
			->andReturn( $screen_obj );

		Functions\expect( 'plugin_dir_url' )
			->once()
			->andReturn( 'http://example.com/wp-content/plugins/ops-health-dashboard/' );

		Functions\expect( 'wp_enqueue_style' )
			->once()
			->with(
				'ops-health-dashboard-screen',
				Mockery::type( 'string' ),
				[],
				Mockery::type( 'string' )
			);

		$runner  = Mockery::mock( CheckRunnerInterface::class );
		$storage = Mockery::mock( StorageInterface::class );
		$screen  = new HealthScreen( $runner, $storage );
		$screen->enqueue_styles();
	}

	/**
	 * Tests that enqueue_styles does NOT load the CSS on other screens
	 */
	public function test_enqueue_styles_skips_other_screen() {
		$screen_obj     = new \stdClass();
		$screen_obj->id = 'plugins';

		Functions\expect( 'get_current_screen' )
			->once()
			->andReturn( $screen_obj );

		$runner  = Mockery::mock( CheckRunnerInterface::class );
		$storage = Mockery::mock( StorageInterface::class );
		$screen  = new HealthScreen( $runner, $storage );
		$screen->enqueue_styles();

		$this->assertTrue( true );
	}

	/**
	 * Tests that enqueue_styles handles null screen
	 */
	public function test_enqueue_styles_handles_null_screen() {
		Functions\expect( 'get_current_screen' )
			->once()
			->andReturn( null );

		$runner  = Mockery::mock( CheckRunnerInterface::class );
		$storage = Mockery::mock( StorageInterface::class );
		$screen  = new HealthScreen( $runner, $storage );
		$screen->enqueue_styles();

		$this->assertTrue( true );
	}

	// ─── determine_overall_status ──────────────────────────────────

	/**
	 * Tests that overall status for empty results is 'unknown'
	 */
	public function test_overall_status_empty_is_unknown() {
		$runner  = Mockery::mock( CheckRunnerInterface::class );
		$storage = Mockery::mock( StorageInterface::class );
		$screen  = new HealthScreen( $runner, $storage );

		$reflection = new \ReflectionMethod( $screen, 'determine_overall_status' );
		$reflection->setAccessible( true );

		$this->assertEquals( 'unknown', $reflection->invoke( $screen, [] ) );
	}

	/**
	 * Tests that overall status with all ok is 'ok'
	 */
	public function test_overall_status_all_ok() {
		$runner  = Mockery::mock( CheckRunnerInterface::class );
		$storage = Mockery::mock( StorageInterface::class );
		$screen  = new HealthScreen( $runner, $storage );

		$reflection = new \ReflectionMethod( $screen, 'determine_overall_status' );
		$reflection->setAccessible( true );

		$results = [
			'a' => [ 'status' => 'ok' ],
			'b' => [ 'status' => 'ok' ],
		];
		$this->assertEquals( 'ok', $reflection->invoke( $screen, $results ) );
	}

	/**
	 * Tests that overall status worst wins (critical > ok)
	 */
	public function test_overall_status_worst_wins() {
		$runner  = Mockery::mock( CheckRunnerInterface::class );
		$storage = Mockery::mock( StorageInterface::class );
		$screen  = new HealthScreen( $runner, $storage );

		$reflection = new \ReflectionMethod( $screen, 'determine_overall_status' );
		$reflection->setAccessible( true );

		$results = [
			'a' => [ 'status' => 'ok' ],
			'b' => [ 'status' => 'critical' ],
		];
		$this->assertEquals( 'critical', $reflection->invoke( $screen, $results ) );
	}

	/**
	 * Tests that overall status with missing status uses default
	 */
	public function test_overall_status_missing_status_field() {
		$runner  = Mockery::mock( CheckRunnerInterface::class );
		$storage = Mockery::mock( StorageInterface::class );
		$screen  = new HealthScreen( $runner, $storage );

		$reflection = new \ReflectionMethod( $screen, 'determine_overall_status' );
		$reflection->setAccessible( true );

		$results = [
			'a' => [],
			'b' => [ 'status' => 'ok' ],
		];
		$this->assertEquals( 'ok', $reflection->invoke( $screen, $results ) );
	}

	// ─── render (HTML) ────────────────────────────────────────────

	/**
	 * Tests that render does not contain inline styles
	 */
	public function test_render_has_no_inline_styles() {
		$runner = Mockery::mock( CheckRunnerInterface::class );
		$runner->shouldReceive( 'get_latest_results' )->andReturn( [] );

		$storage = $this->create_storage_mock();
		$this->mock_render_functions();

		$screen = new HealthScreen( $runner, $storage );

		ob_start();
		$screen->render();
		$output = ob_get_clean();

		$this->assertStringNotContainsString( 'style="', $output );
	}

	/**
	 * Tests that render shows the status banner when there are results
	 */
	public function test_render_shows_summary_banner_with_results() {
		$runner = Mockery::mock( CheckRunnerInterface::class );
		$runner->shouldReceive( 'get_latest_results' )->andReturn( [
			'database' => [
				'status'   => 'ok',
				'message'  => 'OK',
				'name'     => 'Database',
				'details'  => [],
				'duration' => 0,
			],
		] );

		$storage = $this->create_storage_mock();
		$this->mock_render_functions();

		$screen = new HealthScreen( $runner, $storage );

		ob_start();
		$screen->render();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'ops-health-summary', $output );
	}

	/**
	 * Tests that render hides the banner when there are no results
	 */
	public function test_render_hides_summary_when_no_results() {
		$runner = Mockery::mock( CheckRunnerInterface::class );
		$runner->shouldReceive( 'get_latest_results' )->andReturn( [] );

		$storage = $this->create_storage_mock();
		$this->mock_render_functions();

		$screen = new HealthScreen( $runner, $storage );

		ob_start();
		$screen->render();
		$output = ob_get_clean();

		$this->assertStringNotContainsString( 'ops-health-summary', $output );
	}

	// ─── v0.6.2: Summary banner enhancements ──────────────────────

	/**
	 * Tests that the banner shows the count of affected checks
	 */
	public function test_render_shows_affected_count_in_banner() {
		$runner = Mockery::mock( CheckRunnerInterface::class );
		$runner->shouldReceive( 'get_latest_results' )->andReturn( [
			'database' => [
				'status'   => 'ok',
				'message'  => 'OK',
				'name'     => 'Database',
				'details'  => [],
				'duration' => 0,
			],
			'error_log' => [
				'status'   => 'warning',
				'message'  => '5 warnings',
				'name'     => 'Error Log',
				'details'  => [],
				'duration' => 0,
			],
		] );

		$storage = $this->create_storage_mock();
		$this->mock_render_functions();

		$screen = new HealthScreen( $runner, $storage );

		ob_start();
		$screen->render();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'requires attention', $output );
		$this->assertStringContainsString( 'ops-health-summary-affected', $output );
	}

	/**
	 * Tests that the banner does not show affected count when all OK
	 */
	public function test_render_hides_affected_when_all_ok() {
		$runner = Mockery::mock( CheckRunnerInterface::class );
		$runner->shouldReceive( 'get_latest_results' )->andReturn( [
			'database' => [
				'status'   => 'ok',
				'message'  => 'OK',
				'name'     => 'Database',
				'details'  => [],
				'duration' => 0,
			],
		] );

		$storage = $this->create_storage_mock();
		$this->mock_render_functions();

		$screen = new HealthScreen( $runner, $storage );

		ob_start();
		$screen->render();
		$output = ob_get_clean();

		$this->assertStringNotContainsString( 'requires attention', $output );
	}

	/**
	 * Tests that the banner shows the list of affected checks
	 */
	public function test_render_shows_affected_check_names_in_banner() {
		$runner = Mockery::mock( CheckRunnerInterface::class );
		$runner->shouldReceive( 'get_latest_results' )->andReturn( [
			'error_log' => [
				'status'   => 'warning',
				'message'  => '5 warnings detected',
				'name'     => 'Error Log Summary',
				'details'  => [],
				'duration' => 0,
			],
		] );

		$storage = $this->create_storage_mock();
		$this->mock_render_functions();

		$screen = new HealthScreen( $runner, $storage );

		ob_start();
		$screen->render();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'ops-health-summary-issues', $output );
		$this->assertStringContainsString( 'Error Log Summary', $output );
		$this->assertStringContainsString( '5 warnings detected', $output );
	}

	/**
	 * Tests that the banner shows last run when timestamp is available
	 */
	public function test_render_shows_last_run_time() {
		$runner = Mockery::mock( CheckRunnerInterface::class );
		$runner->shouldReceive( 'get_latest_results' )->andReturn( [
			'database' => [
				'status'   => 'ok',
				'message'  => 'OK',
				'name'     => 'Database',
				'details'  => [],
				'duration' => 0,
			],
		] );

		$storage = $this->create_storage_mock( time() - 300 );
		$this->mock_render_functions();

		$screen = new HealthScreen( $runner, $storage );

		ob_start();
		$screen->render();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Last run:', $output );
		$this->assertStringContainsString( 'ops-health-summary-meta', $output );
	}

	/**
	 * Tests that the banner shows next run when scheduled
	 */
	public function test_render_shows_next_run_time() {
		$runner = Mockery::mock( CheckRunnerInterface::class );
		$runner->shouldReceive( 'get_latest_results' )->andReturn( [
			'database' => [
				'status'   => 'ok',
				'message'  => 'OK',
				'name'     => 'Database',
				'details'  => [],
				'duration' => 0,
			],
		] );

		$storage = $this->create_storage_mock();

		Functions\expect( 'current_user_can' )
			->with( 'manage_options' )
			->andReturn( true );

		Functions\expect( 'esc_html__' )
			->andReturnUsing( function ( $text ) {
				return $text;
			} );

		Functions\expect( 'esc_html' )
			->andReturnUsing( function ( $text ) {
				return $text;
			} );

		Functions\expect( 'esc_attr' )
			->andReturnUsing( function ( $text ) {
				return $text;
			} );

		Functions\expect( 'esc_url' )
			->andReturnUsing( function ( $url ) {
				return $url;
			} );

		Functions\expect( 'wp_nonce_field' )
			->andReturnUsing( function ( $action, $name ) {
				echo '<input type="hidden" name="' . $name . '" value="nonce_token" />';
			} );

		Functions\expect( 'submit_button' )
			->andReturnUsing( function ( $text, $type, $name ) {
				echo '<input type="submit" name="' . $name . '" value="' . $text . '" />';
			} );

		Functions\expect( 'get_transient' )
			->andReturn( false );

		Functions\expect( 'wp_next_scheduled' )
			->andReturn( time() + 600 );

		Functions\expect( 'admin_url' )
			->andReturnUsing( function ( $path ) {
				return 'http://example.com/wp-admin/' . $path;
			} );

		Functions\when( '__' )->returnArg();

		Functions\expect( '_n' )
			->andReturnUsing( function ( $single, $plural, $count ) {
				return $count === 1 ? $single : $plural;
			} );

		Functions\expect( 'human_time_diff' )
			->andReturn( '10 mins' );

		$screen = new HealthScreen( $runner, $storage );

		ob_start();
		$screen->render();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Next run:', $output );
	}

	/**
	 * Tests that the banner hides timing when not available
	 */
	public function test_render_hides_timing_when_no_data() {
		$runner = Mockery::mock( CheckRunnerInterface::class );
		$runner->shouldReceive( 'get_latest_results' )->andReturn( [
			'database' => [
				'status'   => 'ok',
				'message'  => 'OK',
				'name'     => 'Database',
				'details'  => [],
				'duration' => 0,
			],
		] );

		$storage = $this->create_storage_mock( 0 );
		$this->mock_render_functions();

		$screen = new HealthScreen( $runner, $storage );

		ob_start();
		$screen->render();
		$output = ob_get_clean();

		$this->assertStringNotContainsString( 'Last run:', $output );
	}

	/**
	 * Tests that the banner contains the link to Alert Settings
	 */
	public function test_render_shows_alert_settings_link() {
		$runner = Mockery::mock( CheckRunnerInterface::class );
		$runner->shouldReceive( 'get_latest_results' )->andReturn( [
			'database' => [
				'status'   => 'ok',
				'message'  => 'OK',
				'name'     => 'Database',
				'details'  => [],
				'duration' => 0,
			],
		] );

		$storage = $this->create_storage_mock();
		$this->mock_render_functions();

		$screen = new HealthScreen( $runner, $storage );

		ob_start();
		$screen->render();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Alert Settings', $output );
		$this->assertStringContainsString( 'ops-health-alert-settings', $output );
	}

	/**
	 * Tests that the banner uses notice-error for critical status
	 */
	public function test_render_uses_notice_error_for_critical() {
		$runner = Mockery::mock( CheckRunnerInterface::class );
		$runner->shouldReceive( 'get_latest_results' )->andReturn( [
			'database' => [
				'status'   => 'critical',
				'message'  => 'DB down',
				'name'     => 'Database',
				'details'  => [],
				'duration' => 0,
			],
		] );

		$storage = $this->create_storage_mock();
		$this->mock_render_functions();

		$screen = new HealthScreen( $runner, $storage );

		ob_start();
		$screen->render();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'notice-error', $output );
	}

	// ─── v0.6.2: Cards with badges and anchors ─────────────────────

	/**
	 * Tests that the cards have an ID attribute for anchor
	 */
	public function test_render_card_has_id_attribute() {
		$runner = Mockery::mock( CheckRunnerInterface::class );
		$runner->shouldReceive( 'get_latest_results' )->andReturn( [
			'database' => [
				'status'   => 'ok',
				'message'  => 'OK',
				'name'     => 'Database',
				'details'  => [],
				'duration' => 0,
			],
		] );

		$storage = $this->create_storage_mock();
		$this->mock_render_functions();

		$screen = new HealthScreen( $runner, $storage );

		ob_start();
		$screen->render();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'id="check-database"', $output );
	}

	/**
	 * Tests that the cards contain status badge
	 */
	public function test_render_card_contains_badge() {
		$runner = Mockery::mock( CheckRunnerInterface::class );
		$runner->shouldReceive( 'get_latest_results' )->andReturn( [
			'database' => [
				'status'   => 'ok',
				'message'  => 'OK',
				'name'     => 'Database',
				'details'  => [],
				'duration' => 0,
			],
		] );

		$storage = $this->create_storage_mock();
		$this->mock_render_functions();

		$screen = new HealthScreen( $runner, $storage );

		ob_start();
		$screen->render();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'ops-health-badge', $output );
		$this->assertStringContainsString( 'ops-health-badge-ok', $output );
	}

	/**
	 * Tests that the cards contain status icon
	 */
	public function test_render_card_contains_status_icon() {
		$runner = Mockery::mock( CheckRunnerInterface::class );
		$runner->shouldReceive( 'get_latest_results' )->andReturn( [
			'database' => [
				'status'   => 'ok',
				'message'  => 'OK',
				'name'     => 'Database',
				'details'  => [],
				'duration' => 0,
			],
		] );

		$storage = $this->create_storage_mock();
		$this->mock_render_functions();

		$screen = new HealthScreen( $runner, $storage );

		ob_start();
		$screen->render();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'ops-health-status-icon', $output );
		$this->assertStringContainsString( 'aria-hidden="true"', $output );
	}

	/**
	 * Tests that the cards show "Checked X ago" when timestamp is available
	 */
	public function test_render_card_shows_checked_timestamp() {
		$runner = Mockery::mock( CheckRunnerInterface::class );
		$runner->shouldReceive( 'get_latest_results' )->andReturn( [
			'database' => [
				'status'   => 'ok',
				'message'  => 'OK',
				'name'     => 'Database',
				'details'  => [],
				'duration' => 0,
			],
		] );

		$storage = $this->create_storage_mock( time() - 300 );
		$this->mock_render_functions();

		$screen = new HealthScreen( $runner, $storage );

		ob_start();
		$screen->render();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Checked', $output );
		$this->assertStringContainsString( 'ops-health-check-timestamp', $output );
	}

	// ─── v0.6.2: Expandable details ───────────────────────────────

	/**
	 * Tests that expandable details are present when the check has details
	 */
	public function test_render_check_details_shows_details_element() {
		$runner = Mockery::mock( CheckRunnerInterface::class );
		$runner->shouldReceive( 'get_latest_results' )->andReturn( [
			'database' => [
				'status'   => 'ok',
				'message'  => 'OK',
				'name'     => 'Database',
				'details'  => [ 'query_time' => '2.34ms' ],
				'duration' => 0.00234,
			],
		] );

		$storage = $this->create_storage_mock();
		$this->mock_render_functions();

		$screen = new HealthScreen( $runner, $storage );

		ob_start();
		$screen->render();
		$output = ob_get_clean();

		$this->assertStringContainsString( '<details', $output );
		$this->assertStringContainsString( 'ops-health-check-details', $output );
		$this->assertStringContainsString( 'Details', $output );
	}

	/**
	 * Tests that details do not appear when empty and without duration
	 */
	public function test_render_check_details_hidden_when_empty() {
		$runner = Mockery::mock( CheckRunnerInterface::class );
		$runner->shouldReceive( 'get_latest_results' )->andReturn( [
			'database' => [
				'status'   => 'ok',
				'message'  => 'OK',
				'name'     => 'Database',
				'details'  => [],
				'duration' => 0,
			],
		] );

		$storage = $this->create_storage_mock();
		$this->mock_render_functions();

		$screen = new HealthScreen( $runner, $storage );

		ob_start();
		$screen->render();
		$output = ob_get_clean();

		$this->assertStringNotContainsString( '<details', $output );
	}

	/**
	 * Tests that details show the duration in milliseconds
	 */
	public function test_render_check_details_shows_duration_ms() {
		$runner = Mockery::mock( CheckRunnerInterface::class );
		$runner->shouldReceive( 'get_latest_results' )->andReturn( [
			'database' => [
				'status'   => 'ok',
				'message'  => 'OK',
				'name'     => 'Database',
				'details'  => [ 'query_time' => '2.34ms' ],
				'duration' => 0.123,
			],
		] );

		$storage = $this->create_storage_mock();
		$this->mock_render_functions();

		$screen = new HealthScreen( $runner, $storage );

		ob_start();
		$screen->render();
		$output = ob_get_clean();

		$this->assertStringContainsString( '123.0 ms', $output );
		$this->assertStringContainsString( 'Duration:', $output );
	}

	/**
	 * Tests that database details show query_time
	 */
	public function test_render_database_details() {
		$runner = Mockery::mock( CheckRunnerInterface::class );
		$runner->shouldReceive( 'get_latest_results' )->andReturn( [
			'database' => [
				'status'   => 'ok',
				'message'  => 'OK',
				'name'     => 'Database',
				'details'  => [ 'query_time' => '2.34ms' ],
				'duration' => 0.00234,
			],
		] );

		$storage = $this->create_storage_mock();
		$this->mock_render_functions();

		$screen = new HealthScreen( $runner, $storage );

		ob_start();
		$screen->render();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Query Time', $output );
		$this->assertStringContainsString( '2.34ms', $output );
	}

	/**
	 * Tests that error_log details show severity counts
	 */
	public function test_render_error_log_details_shows_severity_counts() {
		$runner = Mockery::mock( CheckRunnerInterface::class );
		$runner->shouldReceive( 'get_latest_results' )->andReturn( [
			'error_log' => [
				'status'   => 'warning',
				'message'  => '5 warnings',
				'name'     => 'Error Log',
				'details'  => [
					'counts'    => [
						'fatal'   => 0,
						'warning' => 5,
						'notice'  => 2,
					],
					'file_size' => '2.5 MB',
				],
				'duration' => 0.05,
			],
		] );

		$storage = $this->create_storage_mock();
		$this->mock_render_functions();

		$screen = new HealthScreen( $runner, $storage );

		ob_start();
		$screen->render();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Fatal', $output );
		$this->assertStringContainsString( 'Warning', $output );
		$this->assertStringContainsString( 'Notice', $output );
		$this->assertStringContainsString( 'Log size:', $output );
		$this->assertStringContainsString( '2.5 MB', $output );
	}

	/**
	 * Tests that redis details show response_time
	 */
	public function test_render_redis_details_shows_response_time() {
		$runner = Mockery::mock( CheckRunnerInterface::class );
		$runner->shouldReceive( 'get_latest_results' )->andReturn( [
			'redis' => [
				'status'   => 'ok',
				'message'  => 'OK',
				'name'     => 'Redis',
				'details'  => [ 'response_time' => '5.23ms' ],
				'duration' => 0.00523,
			],
		] );

		$storage = $this->create_storage_mock();
		$this->mock_render_functions();

		$screen = new HealthScreen( $runner, $storage );

		ob_start();
		$screen->render();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Response Time', $output );
		$this->assertStringContainsString( '5.23ms', $output );
	}

	/**
	 * Tests that disk details show percentage and capacity
	 */
	public function test_render_disk_details_shows_free_space() {
		$runner = Mockery::mock( CheckRunnerInterface::class );
		$runner->shouldReceive( 'get_latest_results' )->andReturn( [
			'disk' => [
				'status'   => 'ok',
				'message'  => '49.1% free',
				'name'     => 'Disk Space',
				'details'  => [
					'free_percent' => 49.1,
					'free_bytes'   => 245300000000,
					'total_bytes'  => 500000000000,
				],
				'duration' => 0.01,
			],
		] );

		$storage = $this->create_storage_mock();
		$this->mock_render_functions();

		Functions\expect( 'size_format' )
			->andReturnUsing( function ( $bytes ) {
				return round( $bytes / 1000000000, 1 ) . ' GB';
			} );

		$screen = new HealthScreen( $runner, $storage );

		ob_start();
		$screen->render();
		$output = ob_get_clean();

		$this->assertStringContainsString( '49.1%', $output );
		$this->assertStringContainsString( 'Capacity', $output );
	}

	/**
	 * Tests that versions details show WP/PHP versions
	 */
	public function test_render_versions_details_shows_versions() {
		$runner = Mockery::mock( CheckRunnerInterface::class );
		$runner->shouldReceive( 'get_latest_results' )->andReturn( [
			'versions' => [
				'status'   => 'ok',
				'message'  => 'All up to date',
				'name'     => 'Versions',
				'details'  => [
					'wp_version'        => '6.4.2',
					'php_version'       => '8.3.0',
					'updates_available' => [],
				],
				'duration' => 0.2,
			],
		] );

		$storage = $this->create_storage_mock();
		$this->mock_render_functions();

		$screen = new HealthScreen( $runner, $storage );

		ob_start();
		$screen->render();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'WordPress', $output );
		$this->assertStringContainsString( '6.4.2', $output );
		$this->assertStringContainsString( 'PHP', $output );
		$this->assertStringContainsString( '8.3.0', $output );
	}

	/**
	 * Tests that versions details show the available updates
	 */
	public function test_render_versions_details_shows_updates_list() {
		$runner = Mockery::mock( CheckRunnerInterface::class );
		$runner->shouldReceive( 'get_latest_results' )->andReturn( [
			'versions' => [
				'status'   => 'warning',
				'message'  => 'Updates available',
				'name'     => 'Versions',
				'details'  => [
					'wp_version'        => '6.4.2',
					'php_version'       => '8.3.0',
					'updates_available' => [
						'WordPress 6.5 available',
						'3 plugin update(s) available',
					],
				],
				'duration' => 0.2,
			],
		] );

		$storage = $this->create_storage_mock();
		$this->mock_render_functions();

		$screen = new HealthScreen( $runner, $storage );

		ob_start();
		$screen->render();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'WordPress 6.5 available', $output );
		$this->assertStringContainsString( '3 plugin update(s) available', $output );
		$this->assertStringContainsString( 'ops-health-updates-list', $output );
	}

	/**
	 * Tests that details for unknown check produces no output
	 */
	public function test_render_specific_details_ignores_unknown_check() {
		$runner  = Mockery::mock( CheckRunnerInterface::class );
		$storage = Mockery::mock( StorageInterface::class );
		$screen  = new HealthScreen( $runner, $storage );

		$reflection = new \ReflectionMethod( $screen, 'render_specific_details' );
		$reflection->setAccessible( true );

		ob_start();
		$reflection->invoke( $screen, 'nonexistent_check', [ 'some' => 'data' ] );
		$output = ob_get_clean();

		$this->assertEmpty( $output );
	}

	// ─── get_status_icon ──────────────────────────────────────────

	/**
	 * Tests that get_status_icon returns the correct icons
	 */
	public function test_get_status_icon_returns_correct_icons() {
		$runner  = Mockery::mock( CheckRunnerInterface::class );
		$storage = Mockery::mock( StorageInterface::class );
		$screen  = new HealthScreen( $runner, $storage );

		$reflection = new \ReflectionMethod( $screen, 'get_status_icon' );
		$reflection->setAccessible( true );

		$this->assertEquals( "\xe2\x9c\x94", $reflection->invoke( $screen, 'ok' ) );
		$this->assertEquals( "\xe2\x9a\xa0", $reflection->invoke( $screen, 'warning' ) );
		$this->assertEquals( "\xe2\x9c\x96", $reflection->invoke( $screen, 'critical' ) );
		$this->assertEquals( '?', $reflection->invoke( $screen, 'unknown' ) );
		$this->assertEquals( '?', $reflection->invoke( $screen, 'invalid' ) );
	}

	// ─── get_affected_checks ──────────────────────────────────────

	/**
	 * Tests that get_affected_checks filters only non-OK checks
	 */
	public function test_get_affected_checks_filters_non_ok() {
		$runner  = Mockery::mock( CheckRunnerInterface::class );
		$storage = Mockery::mock( StorageInterface::class );
		$screen  = new HealthScreen( $runner, $storage );

		$reflection = new \ReflectionMethod( $screen, 'get_affected_checks' );
		$reflection->setAccessible( true );

		$results = [
			'a' => [ 'status' => 'ok', 'name' => 'A', 'message' => 'ok' ],
			'b' => [ 'status' => 'warning', 'name' => 'B', 'message' => 'warn' ],
			'c' => [ 'status' => 'critical', 'name' => 'C', 'message' => 'crit' ],
		];

		$affected = $reflection->invoke( $screen, $results );

		$this->assertCount( 2, $affected );
		$this->assertEquals( 'B', $affected[0]['name'] );
		$this->assertEquals( 'C', $affected[1]['name'] );
	}

	// ─── Help tabs ───────────────────────────────────────────────

	/**
	 * Tests that add_help_tabs registers 3 tabs
	 */
	public function test_add_help_tabs_registers_three_tabs() {
		$runner  = Mockery::mock( CheckRunnerInterface::class );
		$storage = Mockery::mock( StorageInterface::class );

		$screen_mock = Mockery::mock( 'WP_Screen' );
		$screen_mock->shouldReceive( 'add_help_tab' )->times( 3 );
		$screen_mock->shouldReceive( 'set_help_sidebar' )->once();

		Functions\expect( 'get_current_screen' )->once()->andReturn( $screen_mock );
		Functions\when( '__' )->returnArg();
		Functions\expect( 'esc_url' )->andReturnUsing( function ( $url ) {
			return $url;
		} );
		Functions\expect( 'admin_url' )->andReturnUsing( function ( $path ) {
			return 'http://example.com/wp-admin/' . $path;
		} );

		$screen = new HealthScreen( $runner, $storage );
		$screen->add_help_tabs();

		$this->assertInstanceOf( HealthScreen::class, $screen );
	}

	/**
	 * Tests that add_help_tabs uses the correct IDs
	 */
	public function test_add_help_tabs_tab_ids_are_correct() {
		$runner  = Mockery::mock( CheckRunnerInterface::class );
		$storage = Mockery::mock( StorageInterface::class );

		$captured_ids = [];
		$screen_mock  = Mockery::mock( 'WP_Screen' );
		$screen_mock->shouldReceive( 'add_help_tab' )
			->times( 3 )
			->andReturnUsing( function ( $args ) use ( &$captured_ids ) {
				$captured_ids[] = $args['id'];
			} );
		$screen_mock->shouldReceive( 'set_help_sidebar' )->once();

		Functions\expect( 'get_current_screen' )->once()->andReturn( $screen_mock );
		Functions\when( '__' )->returnArg();
		Functions\expect( 'esc_url' )->andReturnUsing( function ( $url ) {
			return $url;
		} );
		Functions\expect( 'admin_url' )->andReturnUsing( function ( $path ) {
			return 'http://example.com/wp-admin/' . $path;
		} );

		$screen = new HealthScreen( $runner, $storage );
		$screen->add_help_tabs();

		$this->assertSame(
			[ 'ops_health_overview', 'ops_health_checks', 'ops_health_actions' ],
			$captured_ids
		);
	}

	/**
	 * Tests that add_help_tabs sets the sidebar with GitHub and Alert Settings
	 */
	public function test_add_help_tabs_sets_sidebar() {
		$runner  = Mockery::mock( CheckRunnerInterface::class );
		$storage = Mockery::mock( StorageInterface::class );

		$captured_sidebar = '';
		$screen_mock      = Mockery::mock( 'WP_Screen' );
		$screen_mock->shouldReceive( 'add_help_tab' )->times( 3 );
		$screen_mock->shouldReceive( 'set_help_sidebar' )
			->once()
			->andReturnUsing( function ( $html ) use ( &$captured_sidebar ) {
				$captured_sidebar = $html;
			} );

		Functions\expect( 'get_current_screen' )->once()->andReturn( $screen_mock );
		Functions\when( '__' )->returnArg();
		Functions\expect( 'esc_url' )->andReturnUsing( function ( $url ) {
			return $url;
		} );
		Functions\expect( 'admin_url' )->andReturnUsing( function ( $path ) {
			return 'http://example.com/wp-admin/' . $path;
		} );

		$screen = new HealthScreen( $runner, $storage );
		$screen->add_help_tabs();

		$this->assertStringContainsString( 'github.com', $captured_sidebar );
		$this->assertStringContainsString( 'ops-health-alert-settings', $captured_sidebar );
	}

	/**
	 * Tests that add_help_tabs handles null screen without errors
	 */
	public function test_add_help_tabs_handles_null_screen() {
		$runner  = Mockery::mock( CheckRunnerInterface::class );
		$storage = Mockery::mock( StorageInterface::class );

		Functions\expect( 'get_current_screen' )->once()->andReturn( null );

		$screen = new HealthScreen( $runner, $storage );
		$screen->add_help_tabs();

		$this->assertInstanceOf( HealthScreen::class, $screen );
	}

	// ─── Pattern enforcement ───────────────────────────────────────

	/**
	 * Tests that the class is NOT final
	 */
	public function test_class_is_not_final() {
		$reflection = new \ReflectionClass( HealthScreen::class );
		$this->assertFalse( $reflection->isFinal(), 'HealthScreen should NOT be final' );
	}

	/**
	 * Tests that there are NO static methods
	 */
	public function test_no_static_methods() {
		$reflection = new \ReflectionClass( HealthScreen::class );
		$methods    = $reflection->getMethods( \ReflectionMethod::IS_STATIC );

		$static_methods = array_filter(
			$methods,
			function ( $method ) {
				return strpos( $method->getName(), '__' ) !== 0;
			}
		);

		$this->assertEmpty( $static_methods, 'HealthScreen should have NO static methods' );
	}

	/**
	 * Tests that there are NO static properties
	 */
	public function test_no_static_properties() {
		$reflection = new \ReflectionClass( HealthScreen::class );
		$properties = $reflection->getProperties( \ReflectionProperty::IS_STATIC );

		$this->assertEmpty( $properties, 'HealthScreen should have NO static properties' );
	}
}
