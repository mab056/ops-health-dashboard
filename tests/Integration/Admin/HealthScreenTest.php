<?php
/**
 * Integration Test for HealthScreen
 *
 * Integration test with real WordPress admin.
 *
 * @package OpsHealthDashboard\Tests\Integration\Admin
 */

namespace OpsHealthDashboard\Tests\Integration\Admin;

use OpsHealthDashboard\Admin\HealthScreen;
use OpsHealthDashboard\Admin\Menu;
use OpsHealthDashboard\Checks\DatabaseCheck;
use OpsHealthDashboard\Services\CheckRunner;
use OpsHealthDashboard\Services\Redaction;
use OpsHealthDashboard\Services\Storage;
use WP_UnitTestCase;

/**
 * Class HealthScreenTest
 *
 * Integration test for HealthScreen with real WordPress.
 */
class HealthScreenTest extends WP_UnitTestCase {

	/**
	 * Clean up after each test
	 */
	public function tearDown(): void {
		unset( $_POST['ops_health_action'], $_POST['_ops_health_nonce'] );
		delete_transient( 'ops_health_admin_notice' );
		delete_option( 'ops_health_latest_results' );
		parent::tearDown();
	}

	/**
	 * Creates a HealthScreen instance with real dependencies
	 *
	 * @return HealthScreen
	 */
	private function create_health_screen(): HealthScreen {
		$storage   = new Storage();
		$redaction = new Redaction();
		$runner    = new CheckRunner( $storage, $redaction );
		return new HealthScreen( $runner, $storage );
	}

	/**
	 * Creates a TestableHealthScreen with real CheckRunner
	 *
	 * @return TestableHealthScreen
	 */
	private function create_testable_screen(): TestableHealthScreen {
		$storage   = new Storage();
		$redaction = new Redaction();
		$runner    = new CheckRunner( $storage, $redaction );
		return new TestableHealthScreen( $runner, $storage );
	}

	/**
	 * Creates a TestableHealthScreen with real DatabaseCheck
	 *
	 * @return TestableHealthScreen
	 */
	private function create_testable_screen_with_db_check(): TestableHealthScreen {
		$storage   = new Storage();
		$redaction = new Redaction();
		$runner    = new CheckRunner( $storage, $redaction );

		global $wpdb;
		$runner->add_check( new DatabaseCheck( $wpdb, $redaction ) );

		return new TestableHealthScreen( $runner, $storage );
	}

	/**
	 * Verifies that render() produces output with capability check
	 */
	public function test_render_outputs_html_for_admin() {
		$user_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );

		$health_screen = $this->create_health_screen();

		ob_start();
		$health_screen->render();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Ops Health Dashboard', $output );
		$this->assertStringContainsString( 'wrap', $output );
	}

	/**
	 * Verifies that render() shows "no checks" message when no results
	 */
	public function test_render_shows_no_checks_message_when_empty() {
		$user_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );

		// Clean up any previous results.
		delete_option( 'ops_health_latest_results' );

		$health_screen = $this->create_health_screen();

		ob_start();
		$health_screen->render();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'No health checks have been run yet', $output );
	}

	/**
	 * Verifies that Menu::render_page() delegates to HealthScreen::render()
	 */
	public function test_menu_render_page_delegates_to_health_screen() {
		$user_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );

		$health_screen = $this->create_health_screen();
		$menu          = new Menu( $health_screen );

		ob_start();
		$menu->render_page();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Ops Health Dashboard', $output );
	}

	/**
	 * Verifies that render() shows action buttons for admin
	 */
	public function test_render_shows_action_buttons_for_admin() {
		$user_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );

		$health_screen = $this->create_health_screen();

		ob_start();
		$health_screen->render();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'run_now', $output );
		$this->assertStringContainsString( 'clear_cache', $output );
		$this->assertStringContainsString( '_ops_health_nonce', $output );
	}

	/**
	 * Verifies that process_actions() returns early without POST action
	 */
	public function test_process_actions_returns_early_without_post() {
		unset( $_POST['ops_health_action'] );

		$screen = $this->create_testable_screen();
		$screen->process_actions();

		// No transient set = early return.
		$this->assertFalse( get_transient( 'ops_health_admin_notice' ) );
	}

	/**
	 * Verifies that process_actions() returns early with invalid nonce
	 */
	public function test_process_actions_returns_early_with_bad_nonce() {
		$user_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );

		$_POST['ops_health_action'] = 'run_now';
		$_POST['_ops_health_nonce'] = 'invalid_nonce_value';

		$screen = $this->create_testable_screen();
		$screen->process_actions();

		$this->assertFalse( get_transient( 'ops_health_admin_notice' ) );
	}

	/**
	 * Verifies that process_actions() returns early without capability
	 */
	public function test_process_actions_returns_early_without_capability() {
		$user_id = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $user_id );

		$_POST['ops_health_action'] = 'run_now';
		$_POST['_ops_health_nonce'] = wp_create_nonce( 'ops_health_admin_action' );

		$screen = $this->create_testable_screen();
		$screen->process_actions();

		$this->assertFalse( get_transient( 'ops_health_admin_notice' ) );
	}

	/**
	 * Verifies that process_actions() executes run_all with action run_now
	 */
	public function test_process_actions_run_now_executes_and_redirects() {
		$user_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );

		$_POST['ops_health_action'] = 'run_now';
		$_POST['_ops_health_nonce'] = wp_create_nonce( 'ops_health_admin_action' );

		$screen = $this->create_testable_screen_with_db_check();

		// Intercept wp_redirect to avoid "headers already sent" in tests.
		add_filter( 'wp_redirect', '__return_false' );
		$screen->process_actions();
		remove_filter( 'wp_redirect', '__return_false' );

		$notice = get_transient( 'ops_health_admin_notice' );
		$this->assertNotFalse( $notice );
		$this->assertStringContainsString( 'executed', strtolower( $notice ) );

		// Verify that results have been saved.
		$results = get_option( 'ops_health_latest_results' );
		$this->assertIsArray( $results );
		$this->assertArrayHasKey( 'database', $results );
	}

	/**
	 * Verifies that process_actions() executes clear_results with action clear_cache
	 */
	public function test_process_actions_clear_cache_clears_and_redirects() {
		$user_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );

		// First save some results.
		update_option( 'ops_health_latest_results', [ 'database' => [ 'status' => 'ok' ] ] );

		$_POST['ops_health_action'] = 'clear_cache';
		$_POST['_ops_health_nonce'] = wp_create_nonce( 'ops_health_admin_action' );

		$screen = $this->create_testable_screen();

		add_filter( 'wp_redirect', '__return_false' );
		$screen->process_actions();
		remove_filter( 'wp_redirect', '__return_false' );

		$notice = get_transient( 'ops_health_admin_notice' );
		$this->assertNotFalse( $notice );
		$this->assertStringContainsString( 'cleared', strtolower( $notice ) );

		// Verify that results have been cleared.
		$this->assertFalse( get_option( 'ops_health_latest_results' ) );
	}

	/**
	 * Verifies that render() shows notice from transient
	 */
	public function test_render_shows_notice_from_transient() {
		$user_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );

		set_transient( 'ops_health_admin_notice', 'Test notice message', 30 );

		$screen = $this->create_health_screen();

		ob_start();
		$screen->render();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Test notice message', $output );
		$this->assertStringContainsString( 'notice-success', $output );

		// Transient must be deleted after render.
		$this->assertFalse( get_transient( 'ops_health_admin_notice' ) );
	}

	/**
	 * Verifies that render() displays check results
	 */
	public function test_render_displays_check_results() {
		$user_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );

		// Save fake results.
		update_option(
			'ops_health_latest_results',
			[
				'database' => [
					'status'  => 'ok',
					'message' => 'Database connection healthy',
					'name'    => 'Database Connection',
				],
			]
		);

		$screen = $this->create_health_screen();

		ob_start();
		$screen->render();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Database Connection', $output );
		$this->assertStringContainsString( 'Database connection healthy', $output );
		$this->assertStringContainsString( 'ops-health-check-ok', $output );
		$this->assertStringNotContainsString( 'No health checks have been run yet', $output );
	}

	/**
	 * Verifies that render() uses default values for missing keys in results
	 */
	public function test_render_uses_defaults_for_missing_result_keys() {
		$user_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );

		// Save results without status, message and name.
		update_option(
			'ops_health_latest_results',
			[
				'custom_check' => [],
			]
		);

		$screen = $this->create_health_screen();

		ob_start();
		$screen->render();
		$output = ob_get_clean();

		// Must use defaults: ucfirst(check_id) for name, 'unknown' for status.
		$this->assertStringContainsString( 'Custom_check', $output );
		$this->assertStringContainsString( 'ops-health-check-unknown', $output );
		$this->assertStringNotContainsString( 'No health checks have been run yet', $output );
	}

	/**
	 * Verifies that render() denies access without manage_options capability
	 */
	public function test_render_denies_access_without_capability() {
		$user_id = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $user_id );

		$screen = $this->create_health_screen();

		$this->expectException( 'WPDieException' );
		$screen->render();
	}

	// ─── enqueue_styles ────────────────────────────────────────────

	/**
	 * Verifies that enqueue_styles registers the CSS on the health screen
	 */
	public function test_enqueue_styles_registers_stylesheet_on_health_screen() {
		$user_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );
		set_current_screen( 'toplevel_page_ops-health-dashboard' );

		$screen = $this->create_health_screen();
		$screen->enqueue_styles();

		$this->assertTrue( wp_style_is( 'ops-health-dashboard-screen', 'enqueued' ) );

		wp_dequeue_style( 'ops-health-dashboard-screen' );
	}

	/**
	 * Verifies that enqueue_styles does NOT register the CSS on other screens
	 */
	public function test_enqueue_styles_not_registered_on_other_screens() {
		$user_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );
		set_current_screen( 'plugins' );

		$screen = $this->create_health_screen();
		$screen->enqueue_styles();

		$this->assertFalse( wp_style_is( 'ops-health-dashboard-screen', 'enqueued' ) );
	}

	/**
	 * Verifies that register_hooks adds the admin_enqueue_scripts action
	 */
	public function test_register_hooks_adds_enqueue_action() {
		$screen = $this->create_health_screen();
		$screen->register_hooks();

		$this->assertNotFalse(
			has_action( 'admin_enqueue_scripts', [ $screen, 'enqueue_styles' ] )
		);
	}

	// ─── Summary banner ────────────────────────────────────────────

	/**
	 * Verifies that render shows the summary banner with stored results
	 */
	public function test_render_shows_summary_banner_with_stored_results() {
		$user_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );

		update_option(
			'ops_health_latest_results',
			[
				'database' => [
					'status'  => 'ok',
					'message' => 'OK',
					'name'    => 'Database',
				],
			]
		);

		$screen = $this->create_health_screen();

		ob_start();
		$screen->render();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'ops-health-summary', $output );
		$this->assertStringContainsString( 'ops-health-summary-ok', $output );
		$this->assertStringContainsString( 'Healthy', $output );
	}

	/**
	 * Verifies that render does not contain inline styles
	 */
	public function test_render_has_no_inline_styles() {
		$user_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );

		$screen = $this->create_health_screen();

		ob_start();
		$screen->render();
		$output = ob_get_clean();

		$this->assertStringNotContainsString( 'style="', $output );
	}

	/**
	 * Verifies that determine_overall_status returns 'unknown' for empty results
	 */
	public function test_determine_overall_status_empty_returns_unknown() {
		$screen = $this->create_health_screen();

		$reflection = new \ReflectionMethod( $screen, 'determine_overall_status' );
		$reflection->setAccessible( true );

		$this->assertSame( 'unknown', $reflection->invoke( $screen, [] ) );
	}

	// ─── Help tabs ────────────────────────────────────────────────

	/**
	 * Verifies that add_help_tabs registers 3 tabs on the health screen
	 */
	public function test_add_help_tabs_registers_tabs_on_health_screen() {
		$user_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );
		set_current_screen( 'toplevel_page_ops-health-dashboard' );

		$screen = $this->create_health_screen();
		$screen->add_help_tabs();

		$wp_screen = get_current_screen();
		$tabs      = $wp_screen->get_help_tabs();

		$tab_ids = array_column( $tabs, 'id' );
		$this->assertContains( 'ops_health_overview', $tab_ids );
		$this->assertContains( 'ops_health_checks', $tab_ids );
		$this->assertContains( 'ops_health_actions', $tab_ids );
	}

	/**
	 * Verifies that add_help_tabs sets the sidebar with GitHub link
	 */
	public function test_add_help_tabs_sets_sidebar_on_health_screen() {
		$user_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );
		set_current_screen( 'toplevel_page_ops-health-dashboard' );

		$screen = $this->create_health_screen();
		$screen->add_help_tabs();

		$wp_screen = get_current_screen();
		$sidebar   = $wp_screen->get_help_sidebar();

		$this->assertStringContainsString( 'github.com', $sidebar );
	}

	// ─── Pattern enforcement ───────────────────────────────────────

	/**
	 * Verifies that the class is NOT final
	 */
	public function test_class_is_not_final() {
		$reflection = new \ReflectionClass( HealthScreen::class );
		$this->assertFalse( $reflection->isFinal(), 'HealthScreen should NOT be final' );
	}

	/**
	 * Verifies that NO static methods exist
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
}

/**
 * Testable HealthScreen with do_exit() no-op
 *
 * Avoids calling exit() during process_actions() tests.
 */
class TestableHealthScreen extends HealthScreen {

	/**
	 * Override do_exit() to avoid exit in tests
	 *
	 * @return void
	 */
	protected function do_exit(): void {
		// No-op for testability.
	}
}
