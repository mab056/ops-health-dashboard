<?php
/**
 * Integration Test dell'Activator con WordPress Test Suite
 *
 * Test di integrazione reali che verificano l'interazione con WordPress.
 *
 * @package OpsHealthDashboard\Tests\Integration\Core
 */

namespace OpsHealthDashboard\Tests\Integration\Core;

use OpsHealthDashboard\Core\Activator;
use WP_UnitTestCase;

/**
 * Class ActivatorTest
 *
 * Integration test per Activator con WordPress reale.
 */
class ActivatorTest extends WP_UnitTestCase {

	/**
	 * Cleanup dopo ogni test
	 */
	public function tearDown(): void {
		delete_option( 'ops_health_activated_at' );
		delete_option( 'ops_health_version' );
		wp_clear_scheduled_hook( 'ops_health_run_checks' );
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
	 * Testa che activate() imposta realmente il timestamp di attivazione
	 */
	public function test_activate_sets_timestamp() {
		if ( ! defined( 'OPS_HEALTH_DASHBOARD_VERSION' ) ) {
			define( 'OPS_HEALTH_DASHBOARD_VERSION', '0.4.1' );
		}

		$activator = new Activator();

		delete_option( 'ops_health_activated_at' );

		$activator->activate();

		$timestamp = get_option( 'ops_health_activated_at' );
		$this->assertNotFalse( $timestamp, 'Timestamp dovrebbe essere creato' );
		$this->assertIsNumeric( $timestamp, 'Timestamp dovrebbe essere numerico' );
		$this->assertGreaterThan( 0, $timestamp, 'Timestamp dovrebbe essere > 0' );
	}

	/**
	 * Testa che activate() imposta la versione del plugin
	 */
	public function test_activate_sets_version() {
		if ( ! defined( 'OPS_HEALTH_DASHBOARD_VERSION' ) ) {
			define( 'OPS_HEALTH_DASHBOARD_VERSION', '0.4.1' );
		}

		$activator = new Activator();

		delete_option( 'ops_health_version' );

		$activator->activate();

		$version = get_option( 'ops_health_version' );
		$this->assertNotFalse( $version, 'Versione dovrebbe essere creata' );
		$this->assertEquals( OPS_HEALTH_DASHBOARD_VERSION, $version, 'Versione dovrebbe matchare la costante' );
	}

	/**
	 * Testa che activate() schedula il cron event
	 */
	public function test_activate_schedules_cron_event() {
		if ( ! defined( 'OPS_HEALTH_DASHBOARD_VERSION' ) ) {
			define( 'OPS_HEALTH_DASHBOARD_VERSION', '0.4.1' );
		}

		wp_clear_scheduled_hook( 'ops_health_run_checks' );

		$activator = new Activator();
		$activator->activate();

		$timestamp = wp_next_scheduled( 'ops_health_run_checks' );
		$this->assertNotFalse( $timestamp, 'Cron dovrebbe essere schedulato dopo activate()' );
	}

	/**
	 * Testa che deactivate() cancella il cron corretto
	 */
	public function test_deactivate_clears_scheduled_hooks() {
		// Schedule il hook corretto.
		wp_schedule_event( time(), 'hourly', 'ops_health_run_checks' );

		$timestamp = wp_next_scheduled( 'ops_health_run_checks' );
		$this->assertNotFalse( $timestamp, 'Cron dovrebbe essere schedulato' );

		$activator = new Activator();
		$activator->deactivate();

		$timestamp = wp_next_scheduled( 'ops_health_run_checks' );
		$this->assertFalse( $timestamp, 'Cron dovrebbe essere cancellato dopo deactivate()' );
	}

	/**
	 * Testa che deactivate() non genera errori se nessun cron è schedulato
	 */
	public function test_deactivate_runs_without_errors_when_no_cron() {
		wp_clear_scheduled_hook( 'ops_health_run_checks' );

		$activator = new Activator();
		$activator->deactivate();

		$this->assertFalse( wp_next_scheduled( 'ops_health_run_checks' ) );
	}

	/**
	 * Testa che activate() registra l'intervallo custom in cron_schedules
	 *
	 * Copre le righe 41-44 della closure in activate().
	 */
	public function test_activate_registers_custom_cron_interval() {
		if ( ! defined( 'OPS_HEALTH_DASHBOARD_VERSION' ) ) {
			define( 'OPS_HEALTH_DASHBOARD_VERSION', '0.4.1' );
		}

		// Rimuovi tutti i filtri cron_schedules per assicurarsi che l'intervallo non esista.
		remove_all_filters( 'cron_schedules' );
		wp_clear_scheduled_hook( 'ops_health_run_checks' );

		$activator = new Activator();
		$activator->activate();

		$schedules = wp_get_schedules();
		$this->assertArrayHasKey( 'every_15_minutes', $schedules );
		$this->assertEquals( 15 * MINUTE_IN_SECONDS, $schedules['every_15_minutes']['interval'] );
	}

	/**
	 * Testa che activate() non duplica il cron se già schedulato
	 */
	public function test_activate_does_not_duplicate_cron_event() {
		if ( ! defined( 'OPS_HEALTH_DASHBOARD_VERSION' ) ) {
			define( 'OPS_HEALTH_DASHBOARD_VERSION', '0.4.1' );
		}

		// Pre-schedula il cron.
		wp_schedule_event( time(), 'hourly', 'ops_health_run_checks' );
		$original_timestamp = wp_next_scheduled( 'ops_health_run_checks' );
		$this->assertNotFalse( $original_timestamp );

		$activator = new Activator();
		$activator->activate();

		// Il cron deve esistere ancora (non duplicato).
		$after_timestamp = wp_next_scheduled( 'ops_health_run_checks' );
		$this->assertNotFalse( $after_timestamp );

		// Il timestamp originale deve essere preservato (non ri-schedulato).
		$this->assertEquals( $original_timestamp, $after_timestamp );
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
