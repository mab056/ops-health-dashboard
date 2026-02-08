<?php
/**
 * Activator Test
 *
 * @package OpsHealthDashboard\Tests\Unit\Core
 */

namespace OpsHealthDashboard\Tests\Unit\Core;

use OpsHealthDashboard\Core\Activator;
use WP_UnitTestCase;

/**
 * Class ActivatorTest
 *
 * TDD for plugin activation/deactivation.
 */
class ActivatorTest extends WP_UnitTestCase {

	/**
	 * Test Activator can be instantiated
	 *
	 * RED: Will fail until Activator exists
	 */
	public function test_activator_can_be_instantiated() {
		$activator = new Activator();
		$this->assertInstanceOf( Activator::class, $activator );
	}

	/**
	 * Test activate() method exists
	 */
	public function test_activate_method_exists() {
		$activator = new Activator();
		$this->assertTrue( method_exists( $activator, 'activate' ) );
	}

	/**
	 * Test deactivate() method exists
	 */
	public function test_deactivate_method_exists() {
		$activator = new Activator();
		$this->assertTrue( method_exists( $activator, 'deactivate' ) );
	}

	/**
	 * Test activate() sets activation timestamp
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
	 * Test activate() sets plugin version
	 */
	public function test_activate_sets_version() {
		$activator = new Activator();

		delete_option( 'ops_health_version' );

		$activator->activate();

		$version = get_option( 'ops_health_version' );
		$this->assertNotFalse( $version );
	}

	/**
	 * Test deactivate() can run without errors
	 */
	public function test_deactivate_runs_without_errors() {
		$activator = new Activator();

		// Should not throw any exception.
		$activator->deactivate();

		$this->assertTrue( true );
	}

	/**
	 * Test class is NOT final
	 */
	public function test_class_is_not_final() {
		$reflection = new \ReflectionClass( Activator::class );
		$this->assertFalse( $reflection->isFinal(), 'Activator should NOT be final' );
	}

	/**
	 * Test NO static methods
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
