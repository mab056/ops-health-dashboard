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
	 *
	 * Test di integrazione con WordPress Options API reale.
	 */
	public function test_activate_sets_timestamp() {
		// Definisce la costante per il test.
		if ( ! defined( 'OPS_HEALTH_DASHBOARD_VERSION' ) ) {
			define( 'OPS_HEALTH_DASHBOARD_VERSION', '0.0.0' );
		}

		$activator = new Activator();

		// Pulisci l'option prima del test.
		delete_option( 'ops_health_activated_at' );

		// Esegui l'attivazione.
		$activator->activate();

		// Verifica che l'option sia stata creata con un timestamp valido.
		$timestamp = get_option( 'ops_health_activated_at' );
		$this->assertNotFalse( $timestamp, 'Timestamp dovrebbe essere creato' );
		$this->assertIsNumeric( $timestamp, 'Timestamp dovrebbe essere numerico' );
		$this->assertGreaterThan( 0, $timestamp, 'Timestamp dovrebbe essere > 0' );

		// Cleanup.
		delete_option( 'ops_health_activated_at' );
	}

	/**
	 * Testa che activate() imposta la versione del plugin
	 *
	 * Test di integrazione con WordPress Options API reale.
	 */
	public function test_activate_sets_version() {
		// Definisce la costante per il test.
		if ( ! defined( 'OPS_HEALTH_DASHBOARD_VERSION' ) ) {
			define( 'OPS_HEALTH_DASHBOARD_VERSION', '0.0.0' );
		}

		$activator = new Activator();

		// Pulisci l'option prima del test.
		delete_option( 'ops_health_version' );

		// Esegui l'attivazione.
		$activator->activate();

		// Verifica che l'option sia stata creata.
		$version = get_option( 'ops_health_version' );
		$this->assertNotFalse( $version, 'Versione dovrebbe essere creata' );
		$this->assertEquals( '0.0.0', $version, 'Versione dovrebbe matchare la costante' );

		// Cleanup.
		delete_option( 'ops_health_version' );
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
	 * Testa che deactivate() cancella realmente i cron job schedulati
	 *
	 * Test di integrazione con WP-Cron reale.
	 */
	public function test_deactivate_clears_scheduled_hooks() {
		// Schedule un hook.
		wp_schedule_event( time(), 'hourly', 'ops_health_scheduled_check' );

		// Verifica che il cron sia schedulato.
		$timestamp = wp_next_scheduled( 'ops_health_scheduled_check' );
		$this->assertNotFalse( $timestamp, 'Cron dovrebbe essere schedulato' );

		// Esegui la disattivazione.
		$activator = new Activator();
		$activator->deactivate();

		// Verifica che il cron sia stato cancellato.
		$timestamp = wp_next_scheduled( 'ops_health_scheduled_check' );
		$this->assertFalse( $timestamp, 'Cron dovrebbe essere cancellato' );
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
