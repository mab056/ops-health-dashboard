<?php
/**
 * Test dell'Activator
 *
 * @package OpsHealthDashboard\Tests\Unit\Core
 */

namespace OpsHealthDashboard\Tests\Unit\Core;

use OpsHealthDashboard\Core\Activator;
use WP_UnitTestCase;

/**
 * Class ActivatorTest
 *
 * TDD per l'attivazione/disattivazione del plugin.
 */
class ActivatorTest extends WP_UnitTestCase {

	/**
	 * Testa che Activator può essere istanziato
	 *
	 * RED: Fallirà finché non esiste Activator
	 */
	public function test_activator_can_be_instantiated() {
		$activator = new Activator();
		$this->assertInstanceOf( Activator::class, $activator );
	}

	/**
	 * Testa che il metodo activate() esiste
	 */
	public function test_activate_method_exists() {
		$activator = new Activator();
		$this->assertTrue( method_exists( $activator, 'activate' ) );
	}

	/**
	 * Testa che il metodo deactivate() esiste
	 */
	public function test_deactivate_method_exists() {
		$activator = new Activator();
		$this->assertTrue( method_exists( $activator, 'deactivate' ) );
	}

	/**
	 * Testa che activate() imposta il timestamp di attivazione
	 */
	public function test_activate_sets_timestamp() {
		$activator = new Activator();

		delete_option( 'ops_health_activated_at' );

		$activator->activate();

		$timestamp = get_option( 'ops_health_activated_at' );
		$this->assertNotFalse( $timestamp );
		$this->assertIsNumeric( $timestamp );
	}

	/**
	 * Testa che activate() imposta la versione del plugin
	 */
	public function test_activate_sets_version() {
		$activator = new Activator();

		delete_option( 'ops_health_version' );

		$activator->activate();

		$version = get_option( 'ops_health_version' );
		$this->assertNotFalse( $version );
	}

	/**
	 * Testa che deactivate() può essere eseguito senza errori
	 */
	public function test_deactivate_runs_without_errors() {
		$activator = new Activator();

		// Non dovrebbe lanciare alcuna eccezione.
		$activator->deactivate();

		$this->assertTrue( true );
	}

	/**
	 * Testa che la classe NON è final
	 */
	public function test_class_is_not_final() {
		$reflection = new \ReflectionClass( Activator::class );
		$this->assertFalse( $reflection->isFinal(), 'Activator should NOT be final' );
	}

	/**
	 * Testa che NON esistono metodi static
	 */
	public function test_no_static_methods() {
		$reflection = new \ReflectionClass( Activator::class );
		$methods    = $reflection->getMethods( \ReflectionMethod::IS_STATIC );

		$static_methods = array_filter( $methods, function( $method ) {
			return ! str_starts_with( $method->getName(), '__' );
		} );

		$this->assertEmpty( $static_methods, 'Activator should have NO static methods' );
	}
}
