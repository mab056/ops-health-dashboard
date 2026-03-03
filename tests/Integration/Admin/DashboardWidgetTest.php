<?php
/**
 * Integration Test for DashboardWidget
 *
 * Integration test with real WordPress.
 *
 * @package OpsHealthDashboard\Tests\Integration\Admin
 */

namespace OpsHealthDashboard\Tests\Integration\Admin;

use OpsHealthDashboard\Admin\DashboardWidget;
use OpsHealthDashboard\Interfaces\CheckRunnerInterface;
use OpsHealthDashboard\Interfaces\StorageInterface;
use OpsHealthDashboard\Services\CheckRunner;
use OpsHealthDashboard\Services\Redaction;
use OpsHealthDashboard\Services\Storage;
use WP_UnitTestCase;

/**
 * Class DashboardWidgetTest
 *
 * Integration test for DashboardWidget with real WordPress.
 */
class DashboardWidgetTest extends WP_UnitTestCase {

	/**
	 * Verifies that DashboardWidget instantiates with CheckRunnerInterface
	 */
	public function test_instantiation() {
		$storage = new Storage();
		$runner  = new CheckRunner( $storage, new Redaction() );
		$widget  = new DashboardWidget( $runner, $storage );
		$this->assertInstanceOf( DashboardWidget::class, $widget );
	}

	/**
	 * Verifies that register_hooks adds the wp_dashboard_setup action
	 */
	public function test_register_hooks_adds_action() {
		$storage = new Storage();
		$runner  = new CheckRunner( $storage, new Redaction() );
		$widget  = new DashboardWidget( $runner, $storage );
		$widget->register_hooks();

		$this->assertNotFalse(
			has_action( 'wp_dashboard_setup', [ $widget, 'add_widget' ] )
		);
	}

	/**
	 * Verifies that add_widget registers the widget for admin users
	 */
	public function test_add_widget_for_admin() {
		global $wp_meta_boxes;

		require_once ABSPATH . 'wp-admin/includes/dashboard.php';

		$user_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );
		set_current_screen( 'dashboard' );

		$storage = new Storage();
		$runner  = new CheckRunner( $storage, new Redaction() );
		$widget  = new DashboardWidget( $runner, $storage );
		$widget->add_widget();

		// wp_add_dashboard_widget registers via add_meta_box into $wp_meta_boxes.
		$registered = $wp_meta_boxes['dashboard']['normal']['core'] ?? [];
		$this->assertArrayHasKey( 'ops_health_dashboard_widget', $registered );
		$this->assertSame(
			[ $widget, 'render' ],
			$registered['ops_health_dashboard_widget']['callback']
		);
	}

	/**
	 * Verifies that add_widget does not register for non-admin users
	 */
	public function test_add_widget_denied_for_subscriber() {
		global $wp_meta_boxes;

		require_once ABSPATH . 'wp-admin/includes/dashboard.php';

		// Remove widget if already registered by previous tests.
		unset( $wp_meta_boxes['dashboard']['normal']['core']['ops_health_dashboard_widget'] );

		$user_id = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $user_id );

		$storage = new Storage();
		$runner  = new CheckRunner( $storage, new Redaction() );
		$widget  = new DashboardWidget( $runner, $storage );
		$widget->add_widget();

		// Verify that the widget was NOT registered.
		$registered = $wp_meta_boxes['dashboard']['normal']['core'] ?? [];
		$this->assertArrayNotHasKey( 'ops_health_dashboard_widget', $registered );
	}

	/**
	 * Verifies that render produces output for admin without results
	 */
	public function test_render_empty_results_for_admin() {
		$user_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );

		$storage = new Storage();
		$runner  = new CheckRunner( $storage, new Redaction() );
		$widget  = new DashboardWidget( $runner, $storage );

		ob_start();
		$widget->render();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'No health checks', $output );
	}

	/**
	 * Verifies that render produces no output for subscriber
	 */
	public function test_render_denied_for_subscriber() {
		$user_id = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $user_id );

		$storage = new Storage();
		$runner  = new CheckRunner( $storage, new Redaction() );
		$widget  = new DashboardWidget( $runner, $storage );

		ob_start();
		$widget->render();
		$output = ob_get_clean();

		$this->assertEmpty( $output );
	}

	/**
	 * Verifies that render shows results when available
	 */
	public function test_render_with_stored_results() {
		$user_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );

		// Save fake results in storage.
		$storage = new Storage();
		$storage->set(
			'latest_results',
			[
				'database' => [
					'status'  => 'ok',
					'message' => 'Database healthy',
					'name'    => 'Database',
				],
			]
		);

		$runner = new CheckRunner( $storage, new Redaction() );
		$widget = new DashboardWidget( $runner, $storage );

		ob_start();
		$widget->render();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Database', $output );
		$this->assertStringContainsString( 'Healthy', $output );
		$this->assertStringContainsString( 'ops-health-dashboard', $output );

		// Cleanup.
		$storage->delete( 'latest_results' );
	}

	/**
	 * Verifies that render includes the link to the full dashboard
	 */
	public function test_render_includes_dashboard_link() {
		$user_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );

		$storage = new Storage();
		$runner  = new CheckRunner( $storage, new Redaction() );
		$widget  = new DashboardWidget( $runner, $storage );

		ob_start();
		$widget->render();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'ops-health-dashboard', $output );
	}

	// ─── enqueue_styles ─────────────────────────────────────────────

	/**
	 * Verifies that enqueue_styles registers the CSS on the dashboard
	 */
	public function test_enqueue_styles_registers_stylesheet_on_dashboard() {
		$user_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );
		set_current_screen( 'dashboard' );

		$storage = new Storage();
		$runner  = new CheckRunner( $storage, new Redaction() );
		$widget  = new DashboardWidget( $runner, $storage );
		$widget->enqueue_styles();

		$this->assertTrue( wp_style_is( 'ops-health-dashboard-widget', 'enqueued' ) );

		wp_dequeue_style( 'ops-health-dashboard-widget' );
	}

	/**
	 * Verifies that enqueue_styles does NOT register the CSS for subscriber on the dashboard
	 */
	public function test_enqueue_styles_not_enqueued_for_subscriber_on_dashboard() {
		$user_id = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $user_id );
		set_current_screen( 'dashboard' );

		$storage = new Storage();
		$runner  = new CheckRunner( $storage, new Redaction() );
		$widget  = new DashboardWidget( $runner, $storage );
		$widget->enqueue_styles();

		$this->assertFalse( wp_style_is( 'ops-health-dashboard-widget', 'enqueued' ) );
	}

	/**
	 * Verifies that enqueue_styles does NOT register the CSS on other screens
	 */
	public function test_enqueue_styles_not_registered_on_other_screens() {
		$user_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );
		set_current_screen( 'plugins' );

		$storage = new Storage();
		$runner  = new CheckRunner( $storage, new Redaction() );
		$widget  = new DashboardWidget( $runner, $storage );
		$widget->enqueue_styles();

		$this->assertFalse( wp_style_is( 'ops-health-dashboard-widget', 'enqueued' ) );
	}

	/**
	 * Verifies that register_hooks adds the admin_enqueue_scripts action
	 */
	public function test_register_hooks_adds_enqueue_action() {
		$storage = new Storage();
		$runner  = new CheckRunner( $storage, new Redaction() );
		$widget  = new DashboardWidget( $runner, $storage );
		$widget->register_hooks();

		$this->assertNotFalse(
			has_action( 'admin_enqueue_scripts', [ $widget, 'enqueue_styles' ] )
		);
	}

	// ─── Output escaping ────────────────────────────────────────────

	/**
	 * Verifies that output is correctly escaped (no unexpected tags)
	 */
	public function test_render_output_is_escaped() {
		$user_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );

		$storage = new Storage();
		$storage->set(
			'latest_results',
			[
				'xss_test' => [
					'status'  => 'ok',
					'message' => '<script>alert("XSS")</script>',
					'name'    => '<img src=x onerror=alert(1)>',
				],
			]
		);

		$runner = new CheckRunner( $storage, new Redaction() );
		$widget = new DashboardWidget( $runner, $storage );

		ob_start();
		$widget->render();
		$output = ob_get_clean();

		$this->assertStringNotContainsString( '<script>', $output );
		$this->assertStringNotContainsString( '<img', $output );
		$this->assertStringContainsString( '&lt;img', $output );

		// Cleanup.
		$storage->delete( 'latest_results' );
	}
}
