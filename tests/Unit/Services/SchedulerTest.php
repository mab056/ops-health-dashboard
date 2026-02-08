<?php
/**
 * Unit Test per Scheduler
 *
 * Test unitario con Brain\Monkey per Scheduler.
 *
 * @package OpsHealthDashboard\Tests\Unit\Services
 */

namespace OpsHealthDashboard\Tests\Unit\Services;

use Brain\Monkey\Functions;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use OpsHealthDashboard\Services\CheckRunner;
use OpsHealthDashboard\Services\Scheduler;
use PHPUnit\Framework\TestCase;

/**
 * Class SchedulerTest
 *
 * Unit test per Scheduler.
 */
class SchedulerTest extends TestCase {
	use MockeryPHPUnitIntegration;

	/**
	 * Setup per ogni test
	 */
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();
	}

	/**
	 * Teardown dopo ogni test
	 */
	protected function tearDown(): void {
		\Brain\Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Testa che Scheduler può essere istanziato con dipendenze
	 */
	public function test_scheduler_can_be_instantiated() {
		$runner    = Mockery::mock( CheckRunner::class );
		$scheduler = new Scheduler( $runner );

		$this->assertInstanceOf( Scheduler::class, $scheduler );
	}

	/**
	 * Testa che schedule() registra un evento cron se non esiste
	 */
	public function test_schedule_registers_cron_event() {
		$runner = Mockery::mock( CheckRunner::class );

		// Mock wp_next_scheduled ritorna false (non schedulato).
		Functions\expect( 'wp_next_scheduled' )
			->once()
			->with( 'ops_health_run_checks' )
			->andReturn( false );

		// Mock wp_schedule_event per registrare l'evento.
		Functions\expect( 'wp_schedule_event' )
			->once()
			->with(
				Mockery::type( 'int' ),
				'every_15_minutes',
				'ops_health_run_checks'
			)
			->andReturn( true );

		$scheduler = new Scheduler( $runner );
		$scheduler->schedule();

		// Mockery verifica le expectations automaticamente.
		$this->assertInstanceOf( Scheduler::class, $scheduler );
	}

	/**
	 * Testa che schedule() non registra se già schedulato
	 */
	public function test_schedule_skips_if_already_scheduled() {
		$runner = Mockery::mock( CheckRunner::class );

		// Mock wp_next_scheduled ritorna timestamp (già schedulato).
		Functions\expect( 'wp_next_scheduled' )
			->once()
			->with( 'ops_health_run_checks' )
			->andReturn( time() + 900 );

		// wp_schedule_event NON dovrebbe essere chiamato.
		Functions\expect( 'wp_schedule_event' )->never();

		$scheduler = new Scheduler( $runner );
		$scheduler->schedule();

		// Mockery verifica che wp_schedule_event non è stato chiamato.
		$this->assertInstanceOf( Scheduler::class, $scheduler );
	}

	/**
	 * Testa che unschedule() rimuove l'evento cron
	 */
	public function test_unschedule_removes_cron_event() {
		$runner = Mockery::mock( CheckRunner::class );

		$timestamp = time() + 900;

		// Mock wp_next_scheduled ritorna timestamp.
		Functions\expect( 'wp_next_scheduled' )
			->once()
			->with( 'ops_health_run_checks' )
			->andReturn( $timestamp );

		// Mock wp_unschedule_event per rimuovere.
		Functions\expect( 'wp_unschedule_event' )
			->once()
			->with( $timestamp, 'ops_health_run_checks' )
			->andReturn( true );

		$scheduler = new Scheduler( $runner );
		$scheduler->unschedule();

		// Mockery verifica le expectations.
		$this->assertInstanceOf( Scheduler::class, $scheduler );
	}

	/**
	 * Testa che unschedule() non fa nulla se non schedulato
	 */
	public function test_unschedule_does_nothing_when_not_scheduled() {
		$runner = Mockery::mock( CheckRunner::class );

		Functions\expect( 'wp_next_scheduled' )
			->once()
			->with( 'ops_health_run_checks' )
			->andReturn( false );

		Functions\expect( 'wp_unschedule_event' )->never();

		$scheduler = new Scheduler( $runner );
		$scheduler->unschedule();

		$this->assertInstanceOf( Scheduler::class, $scheduler );
	}

	/**
	 * Testa che is_scheduled() ritorna true se schedulato
	 */
	public function test_is_scheduled_returns_true_when_scheduled() {
		$runner = Mockery::mock( CheckRunner::class );

		Functions\expect( 'wp_next_scheduled' )
			->once()
			->with( 'ops_health_run_checks' )
			->andReturn( time() + 900 );

		$scheduler = new Scheduler( $runner );
		$result    = $scheduler->is_scheduled();

		$this->assertTrue( $result );
	}

	/**
	 * Testa che is_scheduled() ritorna false se non schedulato
	 */
	public function test_is_scheduled_returns_false_when_not_scheduled() {
		$runner = Mockery::mock( CheckRunner::class );

		Functions\expect( 'wp_next_scheduled' )
			->once()
			->with( 'ops_health_run_checks' )
			->andReturn( false );

		$scheduler = new Scheduler( $runner );
		$result    = $scheduler->is_scheduled();

		$this->assertFalse( $result );
	}

	/**
	 * Testa che run_checks() esegue i check tramite CheckRunner
	 */
	public function test_run_checks_executes_checks() {
		$runner = Mockery::mock( CheckRunner::class );
		$runner->shouldReceive( 'run_all' )
			->once()
			->andReturn( [
				'database' => [
					'status'  => 'ok',
					'message' => 'OK',
				],
			] );

		$scheduler = new Scheduler( $runner );
		$scheduler->run_checks();

		// Mockery verifica che run_all() è stato chiamato.
		$this->assertInstanceOf( Scheduler::class, $scheduler );
	}

	/**
	 * Testa che register_hooks() registra l'action hook corretto
	 */
	public function test_register_hooks_registers_action() {
		$runner = Mockery::mock( CheckRunner::class );

		Functions\expect( 'add_action' )
			->once()
			->with(
				'ops_health_run_checks',
				Mockery::type( 'array' ),
				10
			);

		$scheduler = new Scheduler( $runner );
		$scheduler->register_hooks();

		// Mockery verifica l'add_action.
		$this->assertInstanceOf( Scheduler::class, $scheduler );
	}

	/**
	 * Testa che la classe NON è final
	 */
	public function test_class_is_not_final() {
		$reflection = new \ReflectionClass( Scheduler::class );
		$this->assertFalse( $reflection->isFinal(), 'Scheduler should NOT be final' );
	}

	/**
	 * Testa che NON esistono metodi static
	 */
	public function test_no_static_methods() {
		$reflection = new \ReflectionClass( Scheduler::class );
		$methods    = $reflection->getMethods( \ReflectionMethod::IS_STATIC );

		$static_methods = array_filter(
			$methods,
			function ( $method ) {
				return strpos( $method->getName(), '__' ) !== 0;
			}
		);

		$this->assertEmpty( $static_methods, 'Scheduler should have NO static methods' );
	}

	/**
	 * Testa che NON esistono proprietà static
	 */
	public function test_no_static_properties() {
		$reflection = new \ReflectionClass( Scheduler::class );
		$properties = $reflection->getProperties( \ReflectionProperty::IS_STATIC );

		$this->assertEmpty( $properties, 'Scheduler should have NO static properties' );
	}
}
