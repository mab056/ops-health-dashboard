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

		$health_screen = \Mockery::mock( \OpsHealthDashboard\Admin\HealthScreen::class );
		$menu          = new Menu( $health_screen );
		$menu->add_menu();

		// Mockery verifica che add_menu_page sia stato chiamato.
		$this->assertInstanceOf( Menu::class, $menu );
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
}
