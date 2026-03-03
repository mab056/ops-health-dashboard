<?php
/**
 * Integration Test for Admin Menu
 *
 * Integration test with real WordPress admin.
 *
 * @package OpsHealthDashboard\Tests\Integration\Admin
 */

namespace OpsHealthDashboard\Tests\Integration\Admin;

use OpsHealthDashboard\Admin\AlertSettings;
use OpsHealthDashboard\Admin\HealthScreen;
use OpsHealthDashboard\Admin\Menu;
use OpsHealthDashboard\Services\CheckRunner;
use OpsHealthDashboard\Services\Redaction;
use OpsHealthDashboard\Services\Storage;
use WP_UnitTestCase;

/**
 * Class MenuTest
 *
 * Integration test for Menu with real WordPress.
 */
class MenuTest extends WP_UnitTestCase {

	/**
	 * Creates a Menu instance with real dependencies
	 *
	 * @return Menu
	 */
	private function create_menu(): Menu {
		$storage       = new Storage();
		$redaction     = new Redaction();
		$runner        = new CheckRunner( $storage, $redaction );
		$health_screen = new HealthScreen( $runner, $storage );
		return new Menu( $health_screen );
	}

	/**
	 * Creates a Menu instance with AlertSettings
	 *
	 * @return Menu
	 */
	private function create_menu_with_alert_settings(): Menu {
		$storage        = new Storage();
		$redaction      = new Redaction();
		$runner         = new CheckRunner( $storage, $redaction );
		$health_screen  = new HealthScreen( $runner, $storage );
		$alert_settings = new AlertSettings( $storage );
		return new Menu( $health_screen, $alert_settings );
	}

	/**
	 * Verifies that the menu can be registered
	 */
	public function test_menu_can_be_registered() {
		$menu = $this->create_menu();
		$menu->register_hooks();

		$this->assertTrue( has_action( 'admin_menu' ) !== false );
	}

	/**
	 * Verifies that add_menu registers the page
	 */
	public function test_add_menu_registers_page() {
		global $admin_page_hooks;

		$menu = $this->create_menu();
		$menu->add_menu();

		// Verify that the page is registered.
		$this->assertArrayHasKey( 'ops-health-dashboard', $admin_page_hooks );
	}

	/**
	 * Verifies that add_menu with AlertSettings registers the submenu
	 */
	public function test_add_menu_with_alert_settings_registers_submenu() {
		global $submenu;

		$user_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );

		$menu = $this->create_menu_with_alert_settings();
		$menu->add_menu();

		$this->assertIsArray( $submenu );
		$this->assertArrayHasKey( 'ops-health-dashboard', $submenu );

		$slugs = array_column( $submenu['ops-health-dashboard'], 2 );
		$this->assertContains( 'ops-health-alert-settings', $slugs );
	}

	/**
	 * Verifies that render_alert_settings delegates to AlertSettings
	 */
	public function test_render_alert_settings_delegates_to_alert_settings() {
		$user_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );
		set_current_screen( 'dashboard' );

		$menu = $this->create_menu_with_alert_settings();
		$menu->add_menu();

		ob_start();
		$menu->render_alert_settings();
		$output = ob_get_clean();

		$this->assertStringContainsString( '<form', $output );
	}

	/**
	 * Verifies that render_alert_settings does nothing without AlertSettings
	 */
	public function test_render_alert_settings_does_nothing_without_alert_settings() {
		$menu = $this->create_menu();

		ob_start();
		$menu->render_alert_settings();
		$output = ob_get_clean();

		$this->assertEmpty( $output );
	}

	/**
	 * Verifies that add_menu registers load hook for AlertSettings
	 */
	public function test_add_menu_registers_load_hook_for_alert_settings() {
		$user_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );

		$menu = $this->create_menu_with_alert_settings();
		$menu->add_menu();

		// The load hook for the submenu page should be registered.
		global $submenu;
		$this->assertIsArray( $submenu );
		$this->assertArrayHasKey( 'ops-health-dashboard', $submenu );

		$slugs = array_column( $submenu['ops-health-dashboard'], 2 );
		$this->assertContains( 'ops-health-alert-settings', $slugs );
	}
}
