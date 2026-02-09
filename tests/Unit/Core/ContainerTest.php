<?php
/**
 * Test del Container
 *
 * @package OpsHealthDashboard\Tests\Unit\Core
 */

namespace OpsHealthDashboard\Tests\Unit\Core;

use OpsHealthDashboard\Core\Container;
use PHPUnit\Framework\TestCase;

/**
 * Class ContainerTest
 *
 * TDD per la classe Container - NO pattern singleton, usa share() per istanze condivise.
 */
class ContainerTest extends TestCase {

	/**
	 * Testa che il Container possa essere istanziato
	 *
	 * RED: Questo fallirà finché non esiste la classe Container
	 */
	public function test_container_can_be_instantiated() {
		$container = new Container();
		$this->assertInstanceOf( Container::class, $container );
	}

	/**
	 * Testa che bind() registra un binding
	 *
	 * RED: Fallirà finché non esiste il metodo bind()
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
	 * Testa che bind() crea una nuova istanza ogni volta
	 *
	 * Assicura NO pattern singleton - ogni chiamata a make() crea una nuova istanza
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
	 * Testa che share() crea un'istanza condivisa
	 *
	 * share() riusa la stessa istanza - ma NON pattern singleton (gestito dal container)
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
	 * Testa che instance() registra un oggetto esistente
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
	 * Testa che make() con dependency injection del container
	 *
	 * La Closure riceve il container come parametro per risolvere le dipendenze
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
	 * Testa che make() lancia un'eccezione per abstract non legato
	 */
	public function test_make_throws_exception_for_unbound_abstract() {
		$container = new Container();

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'No binding found for [non-existent]' );

		$container->make( 'non-existent' );
	}

	/**
	 * Testa che NON esistono metodi static
	 *
	 * Assicura che Container NON abbia accesso static - solo dependency injection
	 */
	public function test_no_static_methods_for_access() {
		$reflection = new \ReflectionClass( Container::class );
		$methods    = $reflection->getMethods( \ReflectionMethod::IS_STATIC );

		// Filtra i metodi magic e gli helper di test
		$static_methods = array_filter( $methods, function( $method ) {
			return strpos( $method->getName(), '__' ) !== 0;
		} );

		$this->assertEmpty( $static_methods, 'Container should have NO static methods for global access' );
	}

	/**
	 * Testa che NON esistono proprietà static
	 */
	public function test_no_static_properties() {
		$reflection = new \ReflectionClass( Container::class );
		$properties = $reflection->getProperties( \ReflectionProperty::IS_STATIC );

		$this->assertEmpty( $properties, 'Container should have NO static properties' );
	}

	/**
	 * Testa che make() lancia un'eccezione per dipendenze circolari
	 */
	public function test_make_throws_exception_for_circular_dependency() {
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
	 * Testa che la classe NON è final
	 *
	 * Assicura testabilità ed estensibilità
	 */
	public function test_class_is_not_final() {
		$reflection = new \ReflectionClass( Container::class );
		$this->assertFalse( $reflection->isFinal(), 'Container should NOT be final for testability' );
	}
}
