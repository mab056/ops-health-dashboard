<?php
/**
 * Integration Test per DashboardWidget
 *
 * Test di integrazione con WordPress reale.
 *
 * @package OpsHealthDashboard\Tests\Integration\Admin
 */

namespace OpsHealthDashboard\Tests\Integration\Admin;

use OpsHealthDashboard\Admin\DashboardWidget;
use OpsHealthDashboard\Interfaces\CheckRunnerInterface;
use OpsHealthDashboard\Services\CheckRunner;
use OpsHealthDashboard\Services\Redaction;
use OpsHealthDashboard\Services\Storage;
use WP_UnitTestCase;

/**
 * Class DashboardWidgetTest
 *
 * Integration test per DashboardWidget con WordPress reale.
 */
class DashboardWidgetTest extends WP_UnitTestCase {

	/**
	 * Testa che DashboardWidget si istanzia con CheckRunnerInterface
	 */
	public function test_instantiation() {
		$runner = new CheckRunner( new Storage(), new Redaction() );
		$widget = new DashboardWidget( $runner );
		$this->assertInstanceOf( DashboardWidget::class, $widget );
	}

	/**
	 * Testa che register_hooks aggiunge l'action wp_dashboard_setup
	 */
	public function test_register_hooks_adds_action() {
		$runner = new CheckRunner( new Storage(), new Redaction() );
		$widget = new DashboardWidget( $runner );
		$widget->register_hooks();

		$this->assertNotFalse(
			has_action( 'wp_dashboard_setup', [ $widget, 'add_widget' ] )
		);
	}

	/**
	 * Testa che add_widget registra il widget per utenti admin
	 */
	public function test_add_widget_for_admin() {
		global $wp_meta_boxes;

		require_once ABSPATH . 'wp-admin/includes/dashboard.php';

		$user_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );
		set_current_screen( 'dashboard' );

		$runner = new CheckRunner( new Storage(), new Redaction() );
		$widget = new DashboardWidget( $runner );
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
	 * Testa che add_widget non registra per utenti non admin
	 */
	public function test_add_widget_denied_for_subscriber() {
		global $wp_meta_boxes;

		require_once ABSPATH . 'wp-admin/includes/dashboard.php';

		// Rimuovi widget se già registrato da test precedenti.
		unset( $wp_meta_boxes['dashboard']['normal']['core']['ops_health_dashboard_widget'] );

		$user_id = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $user_id );

		$runner = new CheckRunner( new Storage(), new Redaction() );
		$widget = new DashboardWidget( $runner );
		$widget->add_widget();

		// Verifica che il widget NON è stato registrato.
		$registered = $wp_meta_boxes['dashboard']['normal']['core'] ?? [];
		$this->assertArrayNotHasKey( 'ops_health_dashboard_widget', $registered );
	}

	/**
	 * Testa che render produce output per admin senza risultati
	 */
	public function test_render_empty_results_for_admin() {
		$user_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );

		$runner = new CheckRunner( new Storage(), new Redaction() );
		$widget = new DashboardWidget( $runner );

		ob_start();
		$widget->render();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'No health checks', $output );
	}

	/**
	 * Testa che render non produce output per subscriber
	 */
	public function test_render_denied_for_subscriber() {
		$user_id = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $user_id );

		$runner = new CheckRunner( new Storage(), new Redaction() );
		$widget = new DashboardWidget( $runner );

		ob_start();
		$widget->render();
		$output = ob_get_clean();

		$this->assertEmpty( $output );
	}

	/**
	 * Testa che render mostra risultati quando disponibili
	 */
	public function test_render_with_stored_results() {
		$user_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );

		// Salva risultati finti nello storage.
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
		$widget = new DashboardWidget( $runner );

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
	 * Testa che render include il link alla dashboard completa
	 */
	public function test_render_includes_dashboard_link() {
		$user_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );

		$runner = new CheckRunner( new Storage(), new Redaction() );
		$widget = new DashboardWidget( $runner );

		ob_start();
		$widget->render();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'ops-health-dashboard', $output );
	}

	// ─── enqueue_styles ─────────────────────────────────────────────

	/**
	 * Testa che enqueue_styles registra il CSS sulla dashboard
	 */
	public function test_enqueue_styles_registers_stylesheet_on_dashboard() {
		$user_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );
		set_current_screen( 'dashboard' );

		$runner = new CheckRunner( new Storage(), new Redaction() );
		$widget = new DashboardWidget( $runner );
		$widget->enqueue_styles();

		$this->assertTrue( wp_style_is( 'ops-health-dashboard-widget', 'enqueued' ) );

		wp_dequeue_style( 'ops-health-dashboard-widget' );
	}

	/**
	 * Testa che enqueue_styles NON registra il CSS per subscriber sulla dashboard
	 */
	public function test_enqueue_styles_not_enqueued_for_subscriber_on_dashboard() {
		$user_id = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $user_id );
		set_current_screen( 'dashboard' );

		$runner = new CheckRunner( new Storage(), new Redaction() );
		$widget = new DashboardWidget( $runner );
		$widget->enqueue_styles();

		$this->assertFalse( wp_style_is( 'ops-health-dashboard-widget', 'enqueued' ) );
	}

	/**
	 * Testa che enqueue_styles NON registra il CSS su altre schermate
	 */
	public function test_enqueue_styles_not_registered_on_other_screens() {
		$user_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );
		set_current_screen( 'plugins' );

		$runner = new CheckRunner( new Storage(), new Redaction() );
		$widget = new DashboardWidget( $runner );
		$widget->enqueue_styles();

		$this->assertFalse( wp_style_is( 'ops-health-dashboard-widget', 'enqueued' ) );
	}

	/**
	 * Testa che register_hooks aggiunge l'action admin_enqueue_scripts
	 */
	public function test_register_hooks_adds_enqueue_action() {
		$runner = new CheckRunner( new Storage(), new Redaction() );
		$widget = new DashboardWidget( $runner );
		$widget->register_hooks();

		$this->assertNotFalse(
			has_action( 'admin_enqueue_scripts', [ $widget, 'enqueue_styles' ] )
		);
	}

	// ─── Output escaping ────────────────────────────────────────────

	/**
	 * Testa che output è correttamente escaped (no tag non previsti)
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
		$widget = new DashboardWidget( $runner );

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
