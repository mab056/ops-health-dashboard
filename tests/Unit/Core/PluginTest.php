<?php
/**
 * Test del Plugin
 *
 * @package OpsHealthDashboard\Tests\Unit\Core
 */

namespace OpsHealthDashboard\Tests\Unit\Core;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use OpsHealthDashboard\Core\Container;
use OpsHealthDashboard\Core\Plugin;
use PHPUnit\Framework\TestCase;

/**
 * Class PluginTest
 *
 * TDD per la classe principale Plugin con constructor injection (NO singleton).
 */
class PluginTest extends TestCase {
	use MockeryPHPUnitIntegration;

	/**
	 * Testa che Plugin può essere istanziato con Container
	 */
	public function test_plugin_can_be_instantiated_with_container() {
		$container = new Container();
		$plugin    = new Plugin( $container );

		$this->assertInstanceOf( Plugin::class, $plugin );
	}

	/**
	 * Testa che Plugin riceve Container tramite constructor injection
	 */
	public function test_plugin_receives_container_via_constructor() {
		$container = new Container();
		$plugin    = new Plugin( $container );

		$retrieved_container = $plugin->get_container();

		$this->assertSame( $container, $retrieved_container );
	}

	/**
	 * Testa che il metodo init() esiste
	 */
	public function test_init_method_exists() {
		$container = new Container();
		$plugin    = new Plugin( $container );

		$this->assertTrue( method_exists( $plugin, 'init' ) );
	}

	/**
	 * Testa che init() registra hooks per Menu e Scheduler
	 */
	public function test_init_registers_menu_and_scheduler_hooks() {
		$container = Mockery::mock( Container::class );
		$menu      = Mockery::mock( 'OpsHealthDashboard\Admin\Menu' );
		$scheduler = Mockery::mock( 'OpsHealthDashboard\Services\Scheduler' );

		$menu->shouldReceive( 'register_hooks' )->once();
		$scheduler->shouldReceive( 'register_hooks' )->once();

		$container->shouldReceive( 'make' )
			->with( 'OpsHealthDashboard\Admin\Menu' )
			->once()
			->andReturn( $menu );

		$container->shouldReceive( 'make' )
			->with( 'OpsHealthDashboard\Services\Scheduler' )
			->once()
			->andReturn( $scheduler );

		$plugin = new Plugin( $container );
		$plugin->init();

		// Mockery verifica che register_hooks sia chiamato una volta per ciascuno.
		$this->assertInstanceOf( Plugin::class, $plugin );
	}

	/**
	 * Testa che init() è idempotente - hooks registrati una sola volta
	 */
	public function test_init_is_idempotent() {
		$container = Mockery::mock( Container::class );
		$menu      = Mockery::mock( 'OpsHealthDashboard\Admin\Menu' );
		$scheduler = Mockery::mock( 'OpsHealthDashboard\Services\Scheduler' );

		// Gli hook devono essere registrati SOLO UNA volta (idempotenza).
		$menu->shouldReceive( 'register_hooks' )->once();
		$scheduler->shouldReceive( 'register_hooks' )->once();

		$container->shouldReceive( 'make' )
			->with( 'OpsHealthDashboard\Admin\Menu' )
			->once()
			->andReturn( $menu );

		$container->shouldReceive( 'make' )
			->with( 'OpsHealthDashboard\Services\Scheduler' )
			->once()
			->andReturn( $scheduler );

		$plugin = new Plugin( $container );

		$plugin->init();
		$plugin->init();
		$plugin->init();

		// Mockery verifica che le aspettative 'once()' siano rispettate.
		$this->assertInstanceOf( Plugin::class, $plugin );
	}

	/**
	 * Testa che la classe NON è final
	 */
	public function test_class_is_not_final() {
		$reflection = new \ReflectionClass( Plugin::class );
		$this->assertFalse( $reflection->isFinal(), 'Plugin should NOT be final' );
	}

	/**
	 * Testa che NON esistono metodi static
	 */
	public function test_no_static_methods() {
		$reflection = new \ReflectionClass( Plugin::class );
		$methods    = $reflection->getMethods( \ReflectionMethod::IS_STATIC );

		$static_methods = array_filter( $methods, function ( $method ) {
			return ! str_starts_with( $method->getName(), '__' );
		} );

		$this->assertEmpty( $static_methods, 'Plugin should have NO static methods (no singleton pattern)' );
	}

	/**
	 * Testa che NON esistono proprietà static
	 */
	public function test_no_static_properties() {
		$reflection = new \ReflectionClass( Plugin::class );
		$properties = $reflection->getProperties( \ReflectionProperty::IS_STATIC );

		$static_props = array_filter( $properties, function ( $prop ) {
			return ! str_starts_with( $prop->getName(), '__' );
		} );

		$this->assertEmpty( $static_props, 'Plugin should have NO static properties (no singleton state)' );
	}
}
