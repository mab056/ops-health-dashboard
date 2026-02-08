<?php
/**
 * Plugin Test
 *
 * @package OpsHealthDashboard\Tests\Unit\Core
 */

namespace OpsHealthDashboard\Tests\Unit\Core;

use OpsHealthDashboard\Core\Container;
use OpsHealthDashboard\Core\Plugin;
use PHPUnit\Framework\TestCase;

/**
 * Class PluginTest
 *
 * TDD for main Plugin class with constructor injection (NO singleton).
 */
class PluginTest extends TestCase {

	/**
	 * Test Plugin can be instantiated with Container
	 *
	 * RED: Will fail until Plugin class exists
	 */
	public function test_plugin_can_be_instantiated_with_container() {
		$container = new Container();
		$plugin    = new Plugin( $container );

		$this->assertInstanceOf( Plugin::class, $plugin );
	}

	/**
	 * Test Plugin receives Container via constructor injection
	 *
	 * NO singleton pattern - dependency injection only
	 */
	public function test_plugin_receives_container_via_constructor() {
		$container = new Container();
		$plugin    = new Plugin( $container );

		$retrieved_container = $plugin->get_container();

		$this->assertSame( $container, $retrieved_container );
	}

	/**
	 * Test init() method exists
	 */
	public function test_init_method_exists() {
		$container = new Container();
		$plugin    = new Plugin( $container );

		$this->assertTrue( method_exists( $plugin, 'init' ) );
	}

	/**
	 * Test init() can be called without errors
	 */
	public function test_init_runs_without_errors() {
		$container = new Container();
		$plugin    = new Plugin( $container );

		// Should not throw exception.
		$plugin->init();

		$this->assertTrue( true );
	}

	/**
	 * Test init() can be called multiple times safely
	 *
	 * Should be idempotent - safe to call multiple times
	 */
	public function test_init_is_idempotent() {
		$container = new Container();
		$plugin    = new Plugin( $container );

		$plugin->init();
		$plugin->init();
		$plugin->init();

		$this->assertTrue( true );
	}

	/**
	 * Test class is NOT final
	 */
	public function test_class_is_not_final() {
		$reflection = new \ReflectionClass( Plugin::class );
		$this->assertFalse( $reflection->isFinal(), 'Plugin should NOT be final' );
	}

	/**
	 * Test NO static methods exist
	 *
	 * Ensures NO get_instance() or similar singleton methods
	 */
	public function test_no_static_methods() {
		$reflection = new \ReflectionClass( Plugin::class );
		$methods    = $reflection->getMethods( \ReflectionMethod::IS_STATIC );

		$static_methods = array_filter( $methods, function( $method ) {
			return ! str_starts_with( $method->getName(), '__' );
		} );

		$this->assertEmpty( $static_methods, 'Plugin should have NO static methods (no singleton pattern)' );
	}

	/**
	 * Test NO static properties exist
	 */
	public function test_no_static_properties() {
		$reflection = new \ReflectionClass( Plugin::class );
		$properties = $reflection->getProperties( \ReflectionProperty::IS_STATIC );

		$static_props = array_filter( $properties, function( $prop ) {
			return ! str_starts_with( $prop->getName(), '__' );
		} );

		$this->assertEmpty( $static_props, 'Plugin should have NO static properties (no singleton state)' );
	}
}
