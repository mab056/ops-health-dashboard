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
	 * Testa che activate() imposta il timestamp e schedula il cron
	 */
	public function test_activate_sets_timestamp_and_schedules_cron() {
		if ( ! defined( 'OPS_HEALTH_DASHBOARD_VERSION' ) ) {
			define( 'OPS_HEALTH_DASHBOARD_VERSION', '0.3.0' );
		}

		if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
			define( 'MINUTE_IN_SECONDS', 60 );
		}

		$activator = new Activator();

		Functions\expect( 'get_option' )
			->once()
			->with( 'ops_health_activated_at' )
			->andReturn( false );

		Functions\expect( 'update_option' )
			->once()
			->with( 'ops_health_activated_at', \Mockery::type( 'int' ) );

		Functions\expect( 'update_option' )
			->once()
			->with( 'ops_health_version', '0.3.0' );

		Functions\expect( '__' )
			->andReturnUsing( function ( $text ) {
				return $text;
			} );

		Functions\expect( 'add_filter' )
			->once()
			->with( 'cron_schedules', \Mockery::type( 'Closure' ) );

		Functions\expect( 'wp_next_scheduled' )
			->once()
			->with( 'ops_health_run_checks' )
			->andReturn( false );

		Functions\expect( 'wp_schedule_event' )
			->once()
			->with( \Mockery::type( 'int' ), 'every_15_minutes', 'ops_health_run_checks' );

		$activator->activate();

		$this->assertInstanceOf( Activator::class, $activator );
	}

	/**
	 * Testa che activate() non sovrascrive il timestamp se già presente
	 */
	public function test_activate_does_not_overwrite_existing_timestamp() {
		if ( ! defined( 'OPS_HEALTH_DASHBOARD_VERSION' ) ) {
			define( 'OPS_HEALTH_DASHBOARD_VERSION', '0.3.0' );
		}

		if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
			define( 'MINUTE_IN_SECONDS', 60 );
		}

		$activator = new Activator();

		Functions\expect( 'get_option' )
			->once()
			->with( 'ops_health_activated_at' )
			->andReturn( 1234567890 );

		Functions\expect( 'update_option' )
			->once()
			->with( 'ops_health_version', '0.3.0' );

		Functions\expect( '__' )
			->andReturnUsing( function ( $text ) {
				return $text;
			} );

		Functions\expect( 'add_filter' )
			->once()
			->with( 'cron_schedules', \Mockery::type( 'Closure' ) );

		Functions\expect( 'wp_next_scheduled' )
			->once()
			->with( 'ops_health_run_checks' )
			->andReturn( false );

		Functions\expect( 'wp_schedule_event' )
			->once();

		$activator->activate();

		$this->assertInstanceOf( Activator::class, $activator );
	}

	/**
	 * Testa che activate() non schedula se già schedulato
	 */
	public function test_activate_skips_schedule_if_already_scheduled() {
		if ( ! defined( 'OPS_HEALTH_DASHBOARD_VERSION' ) ) {
			define( 'OPS_HEALTH_DASHBOARD_VERSION', '0.3.0' );
		}

		if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
			define( 'MINUTE_IN_SECONDS', 60 );
		}

		$activator = new Activator();

		Functions\expect( 'get_option' )
			->once()
			->with( 'ops_health_activated_at' )
			->andReturn( false );

		Functions\expect( 'update_option' )
			->with( 'ops_health_activated_at', \Mockery::type( 'int' ) );

		Functions\expect( 'update_option' )
			->with( 'ops_health_version', '0.3.0' );

		Functions\expect( '__' )
			->andReturnUsing( function ( $text ) {
				return $text;
			} );

		Functions\expect( 'add_filter' )
			->once()
			->with( 'cron_schedules', \Mockery::type( 'Closure' ) );

		Functions\expect( 'wp_next_scheduled' )
			->once()
			->with( 'ops_health_run_checks' )
			->andReturn( time() + 900 );

		Functions\expect( 'wp_schedule_event' )->never();

		$activator->activate();

		$this->assertInstanceOf( Activator::class, $activator );
	}

	/**
	 * Testa che deactivate() cancella il cron hook corretto
	 */
	public function test_deactivate_clears_correct_hook() {
		$activator = new Activator();

		Functions\expect( 'wp_clear_scheduled_hook' )
			->once()
			->with( 'ops_health_run_checks' );

		$activator->deactivate();

		$this->assertInstanceOf( Activator::class, $activator );
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
				return strpos( $method->getName(), '__' ) !== 0;
			}
		);

		$this->assertEmpty( $static_methods, 'Activator should have NO static methods' );
	}

	/**
	 * Testa che NON esistono proprietà static
	 */
	public function test_no_static_properties() {
		$reflection = new \ReflectionClass( Activator::class );
		$properties = $reflection->getProperties( \ReflectionProperty::IS_STATIC );

		$this->assertEmpty( $properties, 'Activator should have NO static properties' );
	}
}
