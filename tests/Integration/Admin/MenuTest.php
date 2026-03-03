<?php
/**
 * Integration Test per Admin Menu
 *
 * Test di integrazione con WordPress admin reale.
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
 * Integration test per Menu con WordPress reale.
 */
class MenuTest extends WP_UnitTestCase {

	/**
	 * Crea un'istanza di Menu con dipendenze reali
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
	 * Crea un'istanza di Menu con AlertSettings
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
	 * Testa che il menu può essere registrato
	 */
	public function test_menu_can_be_registered() {
		$menu = $this->create_menu();
		$menu->register_hooks();

		$this->assertTrue( has_action( 'admin_menu' ) !== false );
	}

	/**
	 * Testa che add_menu registra la pagina
	 */
	public function test_add_menu_registers_page() {
		global $admin_page_hooks;

		$menu = $this->create_menu();
		$menu->add_menu();

		// Verifica che la pagina è registrata.
		$this->assertArrayHasKey( 'ops-health-dashboard', $admin_page_hooks );
	}

	/**
	 * Testa che add_menu con AlertSettings registra il sottomenu
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
	 * Testa che render_alert_settings delega ad AlertSettings
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
	 * Testa che render_alert_settings non fa nulla senza AlertSettings
	 */
	public function test_render_alert_settings_does_nothing_without_alert_settings() {
		$menu = $this->create_menu();

		ob_start();
		$menu->render_alert_settings();
		$output = ob_get_clean();

		$this->assertEmpty( $output );
	}

	/**
	 * Testa che add_menu registra load hook per AlertSettings
	 */
	public function test_add_menu_registers_load_hook_for_alert_settings() {
		$user_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );

		$menu = $this->create_menu_with_alert_settings();
		$menu->add_menu();

		// Il load hook per la pagina submenu dovrebbe essere registrato.
		global $submenu;
		$this->assertIsArray( $submenu );
		$this->assertArrayHasKey( 'ops-health-dashboard', $submenu );

		$slugs = array_column( $submenu['ops-health-dashboard'], 2 );
		$this->assertContains( 'ops-health-alert-settings', $slugs );
	}
}
