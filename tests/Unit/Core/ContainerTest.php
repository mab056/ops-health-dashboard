<?php
/**
 * Container Test
 *
 * @package OpsHealthDashboard\Tests\Unit\Core
 */

namespace OpsHealthDashboard\Tests\Unit\Core;

use OpsHealthDashboard\Core\Container;
use PHPUnit\Framework\TestCase;

/**
 * Class ContainerTest
 *
 * TDD for Container class - NO singleton pattern, uses share() for shared instances.
 */
class ContainerTest extends TestCase {

	/**
	 * Test that Container can be instantiated
	 *
	 * RED: This will fail until Container class exists
	 */
	public function test_container_can_be_instantiated() {
		$container = new Container();
		$this->assertInstanceOf( Container::class, $container );
	}

	/**
	 * Test bind() registers a binding
	 *
	 * RED: Will fail until bind() method exists
	 */
	public function test_bind_registers_a_binding() {
		$container = new Container();

		$container->bind( 'test', function() {
			return 'test-value';
		} );

		$result = $container->make( 'test' );
		$this->assertEquals( 'test-value', $result );
	}

	/**
	 * Test bind() creates new instance each time
	 *
	 * Ensures NO singleton pattern - each make() call creates fresh instance
	 */
	public function test_bind_creates_new_instance_each_time() {
		$container = new Container();

		$container->bind( 'object', function() {
			return new \stdClass();
		} );

		$instance1 = $container->make( 'object' );
		$instance2 = $container->make( 'object' );

		$this->assertNotSame( $instance1, $instance2, 'bind() should create new instances, not reuse' );
	}

	/**
	 * Test share() creates shared instance
	 *
	 * share() reuses same instance - but NOT singleton pattern (managed by container)
	 */
	public function test_share_creates_shared_instance() {
		$container = new Container();

		$container->share( 'shared-object', function() {
			return new \stdClass();
		} );

		$instance1 = $container->make( 'shared-object' );
		$instance2 = $container->make( 'shared-object' );

		$this->assertSame( $instance1, $instance2, 'share() should reuse same instance' );
	}

	/**
	 * Test instance() registers existing object
	 */
	public function test_instance_registers_existing_object() {
		$container = new Container();
		$object    = new \stdClass();
		$object->value = 'test';

		$container->instance( 'my-object', $object );

		$retrieved = $container->make( 'my-object' );

		$this->assertSame( $object, $retrieved );
		$this->assertEquals( 'test', $retrieved->value );
	}

	/**
	 * Test make() with container dependency injection
	 *
	 * Closure receives container as parameter for resolving dependencies
	 */
	public function test_make_passes_container_to_closure() {
		$container = new Container();

		$container->bind( 'service', function( $c ) {
			$this->assertInstanceOf( Container::class, $c );
			return 'has-container';
		} );

		$result = $container->make( 'service' );
		$this->assertEquals( 'has-container', $result );
	}

	/**
	 * Test make() throws exception for unbound abstract
	 */
	public function test_make_throws_exception_for_unbound_abstract() {
		$container = new Container();

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'No binding found for [non-existent]' );

		$container->make( 'non-existent' );
	}

	/**
	 * Test NO static methods exist
	 *
	 * Ensures Container has NO static access - dependency injection only
	 */
	public function test_no_static_methods_for_access() {
		$reflection = new \ReflectionClass( Container::class );
		$methods    = $reflection->getMethods( \ReflectionMethod::IS_STATIC );

		// Filter out magic methods and test helpers
		$static_methods = array_filter( $methods, function( $method ) {
			return ! str_starts_with( $method->getName(), '__' );
		} );

		$this->assertEmpty( $static_methods, 'Container should have NO static methods for global access' );
	}

	/**
	 * Test class is NOT final
	 *
	 * Ensures testability and extensibility
	 */
	public function test_class_is_not_final() {
		$reflection = new \ReflectionClass( Container::class );
		$this->assertFalse( $reflection->isFinal(), 'Container should NOT be final for testability' );
	}
}
