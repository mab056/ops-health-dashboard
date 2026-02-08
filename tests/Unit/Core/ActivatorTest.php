<?php
/**
 * Test dell'Activator
 *
 * @package OpsHealthDashboard\Tests\Unit\Core
 */

namespace OpsHealthDashboard\Tests\Unit\Core;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use OpsHealthDashboard\Core\Activator;
use PHPUnit\Framework\TestCase;

/**
 * Class ActivatorTest
 *
 * TDD per l'attivazione/disattivazione del plugin con Brain\Monkey.
 */
class ActivatorTest extends TestCase {
	use MockeryPHPUnitIntegration;

	/**
	 * Setup del test con Brain\Monkey
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	/**
	 * Teardown del test con Brain\Monkey
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

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
		// Definisce la costante per il test.
		if ( ! defined( 'OPS_HEALTH_DASHBOARD_VERSION' ) ) {
			define( 'OPS_HEALTH_DASHBOARD_VERSION', '0.0.0' );
		}

		$activator = new Activator();

		// Mock delle funzioni WordPress.
		Functions\expect( 'get_option' )
			->once()
			->with( 'ops_health_activated_at' )
			->andReturn( false );

		Functions\expect( 'update_option' )
			->once()
			->with( 'ops_health_activated_at', \Mockery::type( 'int' ) );

		Functions\expect( 'update_option' )
			->once()
			->with( 'ops_health_version', '0.0.0' );

		Functions\expect( 'flush_rewrite_rules' )
			->once();

		$activator->activate();

		$this->assertTrue( true );
	}

	/**
	 * Testa che deactivate() può essere eseguito senza errori
	 */
	public function test_deactivate_runs_without_errors() {
		$activator = new Activator();

		// Mock delle funzioni WordPress.
		Functions\expect( 'wp_clear_scheduled_hook' )
			->once()
			->with( 'ops_health_scheduled_check' );

		Functions\expect( 'flush_rewrite_rules' )
			->once();

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

		$static_methods = array_filter(
			$methods,
			function ( $method ) {
				return ! str_starts_with( $method->getName(), '__' );
			}
		);

		$this->assertEmpty( $static_methods, 'Activator should have NO static methods' );
	}
}
