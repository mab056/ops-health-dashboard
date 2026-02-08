<?php
/**
 * Test del Plugin
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
 * TDD per la classe principale Plugin con constructor injection (NO singleton).
 */
class PluginTest extends TestCase {

	/**
	 * Testa che Plugin può essere istanziato con Container
	 *
	 * RED: Fallirà finché non esiste la classe Plugin
	 */
	public function test_plugin_can_be_instantiated_with_container() {
		$container = new Container();
		$plugin    = new Plugin( $container );

		$this->assertInstanceOf( Plugin::class, $plugin );
	}

	/**
	 * Testa che Plugin riceve Container tramite constructor injection
	 *
	 * NO pattern singleton - solo dependency injection
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
	 * Testa che init() può essere chiamato senza errori
	 */
	public function test_init_runs_without_errors() {
		$container = new Container();
		$plugin    = new Plugin( $container );

		// Non dovrebbe lanciare eccezioni.
		$plugin->init();

		$this->assertTrue( true );
	}

	/**
	 * Testa che init() può essere chiamato più volte in sicurezza
	 *
	 * Dovrebbe essere idempotente - sicuro da chiamare più volte
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
	 * Testa che la classe NON è final
	 */
	public function test_class_is_not_final() {
		$reflection = new \ReflectionClass( Plugin::class );
		$this->assertFalse( $reflection->isFinal(), 'Plugin should NOT be final' );
	}

	/**
	 * Testa che NON esistono metodi static
	 *
	 * Assicura NO get_instance() o metodi singleton simili
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
	 * Testa che NON esistono proprietà static
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
