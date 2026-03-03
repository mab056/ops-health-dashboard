<?php
/**
 * Integration Test for the Container with WordPress Test Suite
 *
 * Verifies that the real bootstrap resolves all services
 * in a complete WordPress environment.
 *
 * @package OpsHealthDashboard\Tests\Integration\Core
 */

namespace OpsHealthDashboard\Tests\Integration\Core;

use OpsHealthDashboard\Admin\HealthScreen;
use OpsHealthDashboard\Admin\Menu;
use OpsHealthDashboard\Core\Container;
use OpsHealthDashboard\Core\Plugin;
use OpsHealthDashboard\Interfaces\CheckRunnerInterface;
use OpsHealthDashboard\Interfaces\RedactionInterface;
use OpsHealthDashboard\Interfaces\StorageInterface;
use OpsHealthDashboard\Services\Scheduler;
use WP_UnitTestCase;

use function OpsHealthDashboard\bootstrap;

/**
 * Class ContainerTest
 *
 * Integration test for Container and bootstrap.
 */
class ContainerTest extends WP_UnitTestCase {

	/**
	 * Tests that bootstrap() returns a Plugin instance
	 */
	public function test_bootstrap_returns_plugin_instance() {
		$plugin = bootstrap();
		$this->assertInstanceOf( Plugin::class, $plugin );
	}

	/**
	 * Tests that the container resolves StorageInterface
	 */
	public function test_container_resolves_storage() {
		$plugin    = bootstrap();
		$container = $plugin->get_container();

		$storage = $container->make( StorageInterface::class );
		$this->assertInstanceOf( StorageInterface::class, $storage );
	}

	/**
	 * Tests that the container resolves RedactionInterface
	 */
	public function test_container_resolves_redaction() {
		$plugin    = bootstrap();
		$container = $plugin->get_container();

		$redaction = $container->make( RedactionInterface::class );
		$this->assertInstanceOf( RedactionInterface::class, $redaction );
	}

	/**
	 * Tests that the container resolves CheckRunnerInterface
	 */
	public function test_container_resolves_check_runner() {
		$plugin    = bootstrap();
		$container = $plugin->get_container();

		$runner = $container->make( CheckRunnerInterface::class );
		$this->assertInstanceOf( CheckRunnerInterface::class, $runner );
	}

	/**
	 * Tests that the container resolves Scheduler
	 */
	public function test_container_resolves_scheduler() {
		$plugin    = bootstrap();
		$container = $plugin->get_container();

		$scheduler = $container->make( Scheduler::class );
		$this->assertInstanceOf( Scheduler::class, $scheduler );
	}

	/**
	 * Tests that the container resolves HealthScreen
	 */
	public function test_container_resolves_health_screen() {
		$plugin    = bootstrap();
		$container = $plugin->get_container();

		$screen = $container->make( HealthScreen::class );
		$this->assertInstanceOf( HealthScreen::class, $screen );
	}

	/**
	 * Tests that the container resolves Menu
	 */
	public function test_container_resolves_menu() {
		$plugin    = bootstrap();
		$container = $plugin->get_container();

		$menu = $container->make( Menu::class );
		$this->assertInstanceOf( Menu::class, $menu );
	}

	/**
	 * Tests that shared instances are the same on every make()
	 */
	public function test_shared_instances_are_same() {
		$plugin    = bootstrap();
		$container = $plugin->get_container();

		$storage1 = $container->make( StorageInterface::class );
		$storage2 = $container->make( StorageInterface::class );
		$this->assertSame( $storage1, $storage2, 'Shared instances should be identical' );

		$runner1 = $container->make( CheckRunnerInterface::class );
		$runner2 = $container->make( CheckRunnerInterface::class );
		$this->assertSame( $runner1, $runner2, 'Shared CheckRunner should be identical' );
	}

	/**
	 * Tests that the container injects wpdb
	 */
	public function test_container_has_wpdb() {
		global $wpdb;
		$plugin    = bootstrap();
		$container = $plugin->get_container();

		$resolved_wpdb = $container->make( 'wpdb' );
		$this->assertSame( $wpdb, $resolved_wpdb );
	}

	/**
	 * Tests that bind() creates a new instance on every make()
	 */
	public function test_bind_creates_new_instance_each_time() {
		$container = new Container();

		$container->bind( 'counter', function ( $c ) {
			return new \stdClass();
		} );

		$a = $container->make( 'counter' );
		$b = $container->make( 'counter' );

		$this->assertNotSame( $a, $b, 'bind() should create new instances' );
	}

	/**
	 * Tests that make() throws exception for unbound abstract
	 */
	public function test_make_throws_for_unbound_abstract() {
		$container = new Container();

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'No binding found for [nonexistent]' );

		$container->make( 'nonexistent' );
	}

	/**
	 * Tests that make() throws exception for circular dependencies
	 */
	public function test_make_throws_for_circular_dependency() {
		$container = new Container();

		$container->share( 'A', function ( $c ) {
			return $c->make( 'B' );
		} );
		$container->share( 'B', function ( $c ) {
			return $c->make( 'A' );
		} );

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'Circular dependency detected for [A]' );

		$container->make( 'A' );
	}

	/**
	 * Tests that the class is NOT final
	 */
	public function test_class_is_not_final() {
		$reflection = new \ReflectionClass( Container::class );
		$this->assertFalse( $reflection->isFinal(), 'Container should NOT be final' );
	}

	/**
	 * Tests that NO static methods exist
	 */
	public function test_no_static_methods() {
		$reflection = new \ReflectionClass( Container::class );
		$methods    = $reflection->getMethods( \ReflectionMethod::IS_STATIC );

		$static_methods = array_filter(
			$methods,
			function ( $method ) {
				return strpos( $method->getName(), '__' ) !== 0;
			}
		);

		$this->assertEmpty( $static_methods, 'Container should have NO static methods' );
	}

	/**
	 * Tests that NO static properties exist
	 */
	public function test_no_static_properties() {
		$reflection = new \ReflectionClass( Container::class );
		$properties = $reflection->getProperties( \ReflectionProperty::IS_STATIC );

		$this->assertEmpty( $properties, 'Container should have NO static properties' );
	}
}
