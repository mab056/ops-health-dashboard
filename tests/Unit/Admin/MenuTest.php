<?php
/**
 * Unit Test per Admin Menu
 *
 * Test unitario con Brain\Monkey per Menu.
 *
 * @package OpsHealthDashboard\Tests\Unit\Admin
 */

namespace OpsHealthDashboard\Tests\Unit\Admin;

use Brain\Monkey\Functions;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use OpsHealthDashboard\Admin\AlertSettings;
use OpsHealthDashboard\Admin\Menu;
use PHPUnit\Framework\TestCase;

/**
 * Class MenuTest
 *
 * Unit test per Menu.
 */
class MenuTest extends TestCase {
	use MockeryPHPUnitIntegration;

	/**
	 * Setup per ogni test
	 */
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();
	}

	/**
	 * Teardown dopo ogni test
	 */
	protected function tearDown(): void {
		\Brain\Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Testa che Menu può essere istanziato con dipendenze
	 */
	public function test_menu_can_be_instantiated() {
		$health_screen = \Mockery::mock( \OpsHealthDashboard\Admin\HealthScreen::class );
		$menu          = new Menu( $health_screen );
		$this->assertInstanceOf( Menu::class, $menu );
	}

	/**
	 * Testa che register_hooks() registra l'action admin_menu
	 */
	public function test_register_hooks_registers_admin_menu() {
		Functions\expect( 'add_action' )
			->once()
			->with( 'admin_menu', \Mockery::type( 'array' ), 10 );

		$health_screen = \Mockery::mock( \OpsHealthDashboard\Admin\HealthScreen::class );
		$menu          = new Menu( $health_screen );
		$menu->register_hooks();

		// Mockery verifica che add_action sia stato chiamato correttamente.
		$this->assertInstanceOf( Menu::class, $menu );
	}

	/**
	 * Testa che add_menu_page() viene chiamato con parametri corretti
	 */
	public function test_add_menu_registers_page() {
		Functions\expect( '__' )
			->andReturnUsing( function ( $text ) {
				return $text;
			} );

		Functions\expect( 'add_menu_page' )
			->once()
			->with(
				'Ops Health Dashboard',
				'Ops Health',
				'manage_options',
				'ops-health-dashboard',
				\Mockery::type( 'array' ),
				'dashicons-heart',
				80
			)
			->andReturn( 'ops-health-page' );

		Functions\expect( 'add_action' )
			->twice()
			->with(
				'load-ops-health-page',
				\Mockery::type( 'array' )
			);

		$health_screen = \Mockery::mock( \OpsHealthDashboard\Admin\HealthScreen::class );
		$menu          = new Menu( $health_screen );
		$menu->add_menu();

		// Mockery verifica che add_menu_page e add_action siano stati chiamati.
		$this->assertInstanceOf( Menu::class, $menu );
	}

	/**
	 * Testa che add_menu() non registra load-hook se add_menu_page ritorna false
	 */
	public function test_add_menu_skips_load_hook_when_menu_page_returns_false() {
		Functions\expect( '__' )
			->andReturnUsing( function ( $text ) {
				return $text;
			} );

		Functions\expect( 'add_menu_page' )
			->once()
			->andReturn( false );

		Functions\expect( 'add_action' )->never();

		$health_screen = \Mockery::mock( \OpsHealthDashboard\Admin\HealthScreen::class );
		$menu          = new Menu( $health_screen );
		$menu->add_menu();

		// Brain\Monkey verifica expect('add_action')->never() via MockeryPHPUnitIntegration.
		$this->assertInstanceOf( Menu::class, $menu );
	}

	/**
	 * Testa che Menu può essere istanziato con AlertSettings
	 */
	public function test_menu_can_be_instantiated_with_alert_settings() {
		$health_screen  = \Mockery::mock( \OpsHealthDashboard\Admin\HealthScreen::class );
		$alert_settings = \Mockery::mock( AlertSettings::class );
		$menu           = new Menu( $health_screen, $alert_settings );
		$this->assertInstanceOf( Menu::class, $menu );
	}

	/**
	 * Testa che add_menu() registra submenu Alert Settings quando presente
	 */
	public function test_add_menu_registers_alert_settings_submenu() {
		Functions\expect( '__' )
			->andReturnUsing( function ( $text ) {
				return $text;
			} );

		Functions\expect( 'add_menu_page' )
			->once()
			->andReturn( 'ops-health-page' );

		Functions\expect( 'add_submenu_page' )
			->once()
			->with(
				'ops-health-dashboard',
				'Alert Settings',
				'Alert Settings',
				'manage_options',
				'ops-health-alert-settings',
				\Mockery::type( 'array' )
			)
			->andReturn( 'ops-health-alerts-page' );

		// add_action: 2× load-ops-health-page + 2× load-ops-health-alerts-page.
		Functions\expect( 'add_action' )
			->times( 4 )
			->with( \Mockery::type( 'string' ), \Mockery::type( 'array' ) );

		$health_screen  = \Mockery::mock( \OpsHealthDashboard\Admin\HealthScreen::class );
		$alert_settings = \Mockery::mock( AlertSettings::class );
		$menu           = new Menu( $health_screen, $alert_settings );
		$menu->add_menu();

		$this->assertInstanceOf( Menu::class, $menu );
	}

	/**
	 * Testa che add_menu() non registra submenu senza AlertSettings
	 */
	public function test_add_menu_skips_submenu_without_alert_settings() {
		Functions\expect( '__' )
			->andReturnUsing( function ( $text ) {
				return $text;
			} );

		Functions\expect( 'add_menu_page' )
			->once()
			->andReturn( 'ops-health-page' );

		Functions\expect( 'add_submenu_page' )->never();

		Functions\expect( 'add_action' )
			->twice()
			->with(
				'load-ops-health-page',
				\Mockery::type( 'array' )
			);

		$health_screen = \Mockery::mock( \OpsHealthDashboard\Admin\HealthScreen::class );
		$menu          = new Menu( $health_screen );
		$menu->add_menu();

		$this->assertInstanceOf( Menu::class, $menu );
	}

	/**
	 * Testa che render_alert_settings() chiama AlertSettings::render()
	 */
	public function test_render_alert_settings_calls_alert_settings_render() {
		$health_screen  = \Mockery::mock( \OpsHealthDashboard\Admin\HealthScreen::class );
		$alert_settings = \Mockery::mock( AlertSettings::class );
		$alert_settings->shouldReceive( 'render' )->once();

		$menu = new Menu( $health_screen, $alert_settings );
		$menu->render_alert_settings();

		$this->assertInstanceOf( Menu::class, $menu );
	}

	/**
	 * Testa che render_alert_settings() non fa nulla senza AlertSettings
	 */
	public function test_render_alert_settings_noop_without_alert_settings() {
		$health_screen = \Mockery::mock( \OpsHealthDashboard\Admin\HealthScreen::class );

		$menu = new Menu( $health_screen );
		$menu->render_alert_settings();

		// Nessuna eccezione, nessun rendering.
		$this->assertInstanceOf( Menu::class, $menu );
	}

	/**
	 * Testa submenu non registra load-hook se add_submenu_page ritorna false
	 */
	public function test_submenu_skips_load_hook_when_returns_false() {
		Functions\expect( '__' )
			->andReturnUsing( function ( $text ) {
				return $text;
			} );

		Functions\expect( 'add_menu_page' )
			->once()
			->andReturn( 'ops-health-page' );

		Functions\expect( 'add_submenu_page' )
			->once()
			->andReturn( false );

		// Solo i load-hook del menu principale (process_actions + add_help_tabs).
		Functions\expect( 'add_action' )
			->twice()
			->with(
				'load-ops-health-page',
				\Mockery::type( 'array' )
			);

		$health_screen  = \Mockery::mock( \OpsHealthDashboard\Admin\HealthScreen::class );
		$alert_settings = \Mockery::mock( AlertSettings::class );
		$menu           = new Menu( $health_screen, $alert_settings );
		$menu->add_menu();

		$this->assertInstanceOf( Menu::class, $menu );
	}

	/**
	 * Testa che add_menu registra il callback add_help_tabs
	 */
	public function test_add_menu_registers_help_tabs_callback() {
		Functions\expect( '__' )
			->andReturnUsing( function ( $text ) {
				return $text;
			} );

		Functions\expect( 'add_menu_page' )
			->once()
			->andReturn( 'ops-health-page' );

		$captured_callbacks = [];
		Functions\expect( 'add_action' )
			->twice()
			->with( \Mockery::type( 'string' ), \Mockery::type( 'array' ) )
			->andReturnUsing( function ( $hook, $callback ) use ( &$captured_callbacks ) {
				$captured_callbacks[] = $callback;
			} );

		$health_screen = \Mockery::mock( \OpsHealthDashboard\Admin\HealthScreen::class );
		$menu          = new Menu( $health_screen );
		$menu->add_menu();

		$methods = array_map( function ( $cb ) {
			return $cb[1];
		}, $captured_callbacks );

		$this->assertContains( 'add_help_tabs', $methods );
		$this->assertContains( 'process_actions', $methods );
	}

	/**
	 * Testa che la classe NON è final
	 */
	public function test_class_is_not_final() {
		$reflection = new \ReflectionClass( Menu::class );
		$this->assertFalse( $reflection->isFinal(), 'Menu should NOT be final' );
	}

	/**
	 * Testa che NON esistono metodi static
	 */
	public function test_no_static_methods() {
		$reflection = new \ReflectionClass( Menu::class );
		$methods    = $reflection->getMethods( \ReflectionMethod::IS_STATIC );

		$static_methods = array_filter(
			$methods,
			function ( $method ) {
				return strpos( $method->getName(), '__' ) !== 0;
			}
		);

		$this->assertEmpty( $static_methods, 'Menu should have NO static methods' );
	}

	/**
	 * Testa che NON esistono proprietà static
	 */
	public function test_no_static_properties() {
		$reflection = new \ReflectionClass( Menu::class );
		$properties = $reflection->getProperties( \ReflectionProperty::IS_STATIC );

		$this->assertEmpty( $properties, 'Menu should have NO static properties' );
	}

	/**
	 * Testa che render_page() chiama HealthScreen::render()
	 */
	public function test_render_page_calls_health_screen_render() {
		$health_screen = \Mockery::mock( \OpsHealthDashboard\Admin\HealthScreen::class );
		$health_screen->shouldReceive( 'render' )
			->once();

		$menu = new Menu( $health_screen );
		$menu->render_page();

		// Mockery verifica shouldReceive('render')->once() via MockeryPHPUnitIntegration.
		$this->assertInstanceOf( Menu::class, $menu );
	}
}
