<?php
/**
 * Integration Test per Admin Menu
 *
 * Test di integrazione con WordPress admin reale.
 *
 * @package OpsHealthDashboard\Tests\Integration\Admin
 */

namespace OpsHealthDashboard\Tests\Integration\Admin;

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
		$health_screen = new HealthScreen( $runner );
		return new Menu( $health_screen );
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
}
