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
use OpsHealthDashboard\Interfaces\AlertManagerInterface;
use OpsHealthDashboard\Interfaces\CheckRunnerInterface;
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
		$runner    = Mockery::mock( CheckRunnerInterface::class );
		$scheduler = new Scheduler( $runner );

		$this->assertInstanceOf( Scheduler::class, $scheduler );
	}

	/**
	 * Testa che schedule() registra un evento cron se non esiste
	 */
	public function test_schedule_registers_cron_event() {
		$runner = Mockery::mock( CheckRunnerInterface::class );

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
		$runner = Mockery::mock( CheckRunnerInterface::class );

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
		$runner = Mockery::mock( CheckRunnerInterface::class );

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
		$runner = Mockery::mock( CheckRunnerInterface::class );

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
		$runner = Mockery::mock( CheckRunnerInterface::class );

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
		$runner = Mockery::mock( CheckRunnerInterface::class );

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
		$runner = Mockery::mock( CheckRunnerInterface::class );
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
	 * Testa che register_hooks() registra hook e non ri-schedula se cron presente
	 */
	public function test_register_hooks_registers_action_and_filter() {
		if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
			define( 'HOUR_IN_SECONDS', 3600 );
		}

		$runner = Mockery::mock( CheckRunnerInterface::class );

		Functions\expect( 'add_action' )
			->once()
			->with(
				'ops_health_run_checks',
				Mockery::type( 'array' ),
				10
			);

		Functions\expect( 'add_filter' )
			->once()
			->with(
				'cron_schedules',
				Mockery::type( 'array' )
			);

		// Throttle scaduto: esegue il check.
		Functions\expect( 'get_transient' )
			->once()
			->with( 'ops_health_cron_check' )
			->andReturn( false );

		// Cron già schedulato: nessuna ri-schedulazione.
		Functions\expect( 'wp_next_scheduled' )
			->once()
			->with( 'ops_health_run_checks' )
			->andReturn( time() + 900 );

		Functions\expect( 'wp_schedule_event' )->never();

		Functions\expect( 'set_transient' )
			->once()
			->with( 'ops_health_cron_check', 1, 3600 );

		$scheduler = new Scheduler( $runner );
		$scheduler->register_hooks();

		$this->assertInstanceOf( Scheduler::class, $scheduler );
	}

	/**
	 * Testa che register_hooks() ri-schedula il cron se mancante (self-healing)
	 */
	public function test_register_hooks_reschedules_when_cron_missing() {
		if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
			define( 'HOUR_IN_SECONDS', 3600 );
		}

		$runner = Mockery::mock( CheckRunnerInterface::class );

		Functions\expect( 'add_action' )
			->once()
			->with(
				'ops_health_run_checks',
				Mockery::type( 'array' ),
				10
			);

		Functions\expect( 'add_filter' )
			->once()
			->with( 'cron_schedules', Mockery::type( 'array' ) );

		// Throttle scaduto: esegue il check.
		Functions\expect( 'get_transient' )
			->once()
			->with( 'ops_health_cron_check' )
			->andReturn( false );

		// Cron mancante.
		Functions\expect( 'wp_next_scheduled' )
			->once()
			->with( 'ops_health_run_checks' )
			->andReturn( false );

		// Deve ri-schedulare.
		Functions\expect( 'wp_schedule_event' )
			->once()
			->with(
				Mockery::type( 'int' ),
				'every_15_minutes',
				'ops_health_run_checks'
			)
			->andReturn( true );

		Functions\expect( 'set_transient' )
			->once()
			->with( 'ops_health_cron_check', 1, 3600 );

		$scheduler = new Scheduler( $runner );
		$scheduler->register_hooks();

		$this->assertInstanceOf( Scheduler::class, $scheduler );
	}

	/**
	 * Testa che register_hooks() non ri-schedula quando il throttle è attivo
	 */
	public function test_register_hooks_skips_self_healing_when_throttled() {
		$runner = Mockery::mock( CheckRunnerInterface::class );

		Functions\expect( 'add_action' )
			->once()
			->with(
				'ops_health_run_checks',
				Mockery::type( 'array' ),
				10
			);

		Functions\expect( 'add_filter' )
			->once()
			->with( 'cron_schedules', Mockery::type( 'array' ) );

		// Throttle attivo: skip self-healing.
		Functions\expect( 'get_transient' )
			->once()
			->with( 'ops_health_cron_check' )
			->andReturn( 1 );

		// schedule() non dovrebbe essere chiamato.
		Functions\expect( 'wp_next_scheduled' )->never();
		Functions\expect( 'wp_schedule_event' )->never();
		Functions\expect( 'set_transient' )->never();

		$scheduler = new Scheduler( $runner );
		$scheduler->register_hooks();

		$this->assertInstanceOf( Scheduler::class, $scheduler );
	}

	/**
	 * Testa che add_custom_cron_interval() aggiunge l'intervallo personalizzato
	 */
	public function test_add_custom_cron_interval_adds_schedule() {
		if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
			define( 'MINUTE_IN_SECONDS', 60 );
		}

		$runner    = Mockery::mock( CheckRunnerInterface::class );
		$scheduler = new Scheduler( $runner );

		Functions\expect( '__' )
			->andReturnUsing( function ( $text ) {
				return $text;
			} );

		$schedules = $scheduler->add_custom_cron_interval( [] );

		$this->assertArrayHasKey( 'every_15_minutes', $schedules );
		$this->assertEquals( 900, $schedules['every_15_minutes']['interval'] );
		$this->assertArrayHasKey( 'display', $schedules['every_15_minutes'] );
	}

	/**
	 * Testa che add_custom_cron_interval() non sovrascrive intervallo esistente
	 */
	public function test_add_custom_cron_interval_does_not_overwrite_existing() {
		$runner    = Mockery::mock( CheckRunnerInterface::class );
		$scheduler = new Scheduler( $runner );

		$existing = [
			'every_15_minutes' => [
				'interval' => 999,
				'display'  => 'Custom',
			],
		];

		$schedules = $scheduler->add_custom_cron_interval( $existing );

		$this->assertEquals( 999, $schedules['every_15_minutes']['interval'] );
	}

	/**
	 * Testa che Scheduler può essere istanziato con AlertManager opzionale
	 */
	public function test_scheduler_can_be_instantiated_with_alert_manager() {
		$runner        = Mockery::mock( CheckRunnerInterface::class );
		$alert_manager = Mockery::mock( AlertManagerInterface::class );
		$scheduler     = new Scheduler( $runner, $alert_manager );

		$this->assertInstanceOf( Scheduler::class, $scheduler );
	}

	/**
	 * Testa che run_checks() chiama alert_manager->process() con risultati corretti
	 */
	public function test_run_checks_calls_alert_manager_process() {
		$previous = [
			'database' => [
				'status'  => 'ok',
				'message' => 'OK',
			],
		];

		$current = [
			'database' => [
				'status'  => 'critical',
				'message' => 'Slow query',
			],
		];

		$runner = Mockery::mock( CheckRunnerInterface::class );
		$runner->shouldReceive( 'get_latest_results' )
			->once()
			->andReturn( $previous );
		$runner->shouldReceive( 'run_all' )
			->once()
			->andReturn( $current );

		$alert_manager = Mockery::mock( AlertManagerInterface::class );
		$alert_manager->shouldReceive( 'process' )
			->once()
			->with( $current, $previous )
			->andReturn( [] );

		$scheduler = new Scheduler( $runner, $alert_manager );
		$scheduler->run_checks();

		// Mockery verifica automaticamente.
		$this->assertInstanceOf( Scheduler::class, $scheduler );
	}

	/**
	 * Testa che run_checks() senza alert manager funziona normalmente
	 */
	public function test_run_checks_without_alert_manager_does_not_call_process() {
		$runner = Mockery::mock( CheckRunnerInterface::class );
		$runner->shouldReceive( 'run_all' )
			->once()
			->andReturn( [
				'database' => [
					'status'  => 'ok',
					'message' => 'OK',
				],
			] );

		// Senza alert manager, non deve chiamare get_latest_results.
		$runner->shouldNotReceive( 'get_latest_results' );

		$scheduler = new Scheduler( $runner );
		$scheduler->run_checks();

		$this->assertInstanceOf( Scheduler::class, $scheduler );
	}

	/**
	 * Testa che run_checks() passa array vuoto come previous al primo avvio
	 */
	public function test_run_checks_passes_empty_previous_on_first_run() {
		$current = [
			'database' => [
				'status'  => 'ok',
				'message' => 'OK',
			],
		];

		$runner = Mockery::mock( CheckRunnerInterface::class );
		$runner->shouldReceive( 'get_latest_results' )
			->once()
			->andReturn( [] );
		$runner->shouldReceive( 'run_all' )
			->once()
			->andReturn( $current );

		$alert_manager = Mockery::mock( AlertManagerInterface::class );
		$alert_manager->shouldReceive( 'process' )
			->once()
			->with( $current, [] )
			->andReturn( [] );

		$scheduler = new Scheduler( $runner, $alert_manager );
		$scheduler->run_checks();

		$this->assertInstanceOf( Scheduler::class, $scheduler );
	}

	/**
	 * Testa che run_checks() legge previous PRIMA di run_all()
	 */
	public function test_run_checks_reads_previous_before_run_all() {
		$call_order = [];

		$runner = Mockery::mock( CheckRunnerInterface::class );
		$runner->shouldReceive( 'get_latest_results' )
			->once()
			->andReturnUsing( function () use ( &$call_order ) {
				$call_order[] = 'get_latest_results';
				return [];
			} );
		$runner->shouldReceive( 'run_all' )
			->once()
			->andReturnUsing( function () use ( &$call_order ) {
				$call_order[] = 'run_all';
				return [ 'db' => [ 'status' => 'ok' ] ];
			} );

		$alert_manager = Mockery::mock( AlertManagerInterface::class );
		$alert_manager->shouldReceive( 'process' )
			->once()
			->andReturn( [] );

		$scheduler = new Scheduler( $runner, $alert_manager );
		$scheduler->run_checks();

		$this->assertSame( [ 'get_latest_results', 'run_all' ], $call_order );
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
