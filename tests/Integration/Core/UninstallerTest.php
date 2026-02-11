<?php
/**
 * Integration Test dell'Uninstaller con WordPress Test Suite
 *
 * Test di integrazione reali che verificano la pulizia completa
 * dei dati del plugin dal database WordPress.
 *
 * @package OpsHealthDashboard\Tests\Integration\Core
 */

namespace OpsHealthDashboard\Tests\Integration\Core;

use OpsHealthDashboard\Core\Activator;
use OpsHealthDashboard\Core\Uninstaller;
use WP_UnitTestCase;

/**
 * Class UninstallerTest
 *
 * Integration test per Uninstaller con WordPress reale.
 */
class UninstallerTest extends WP_UnitTestCase {

	/**
	 * Cleanup dopo ogni test
	 */
	public function tearDown(): void {
		delete_option( 'ops_health_activated_at' );
		delete_option( 'ops_health_version' );
		delete_option( 'ops_health_latest_results' );
		delete_option( 'ops_health_alert_settings' );
		delete_option( 'ops_health_alert_log' );
		delete_transient( 'ops_health_cron_check' );
		delete_transient( 'ops_health_admin_notice' );
		delete_transient( 'ops_health_alert_notice' );
		wp_clear_scheduled_hook( 'ops_health_run_checks' );
		parent::tearDown();
	}

	/**
	 * Testa che la classe NON è final
	 */
	public function test_class_is_not_final() {
		$reflection = new \ReflectionClass( Uninstaller::class );
		$this->assertFalse( $reflection->isFinal(), 'Uninstaller should NOT be final' );
	}

	/**
	 * Testa che NON esistono metodi static
	 */
	public function test_no_static_methods() {
		$reflection = new \ReflectionClass( Uninstaller::class );
		$methods    = $reflection->getMethods( \ReflectionMethod::IS_STATIC );

		$static_methods = array_filter(
			$methods,
			function ( $method ) {
				return strpos( $method->getName(), '__' ) !== 0;
			}
		);

		$this->assertEmpty( $static_methods, 'Uninstaller should have NO static methods' );
	}

	/**
	 * Testa che NON esistono proprietà static
	 */
	public function test_no_static_properties() {
		$reflection = new \ReflectionClass( Uninstaller::class );
		$properties = $reflection->getProperties( \ReflectionProperty::IS_STATIC );

		$this->assertEmpty( $properties, 'Uninstaller should have NO static properties' );
	}

	/**
	 * Testa che uninstall() cancella tutte le opzioni del plugin
	 */
	public function test_uninstall_deletes_all_options() {
		global $wpdb;

		// Crea le opzioni del plugin.
		update_option( 'ops_health_activated_at', time() );
		update_option( 'ops_health_version', '0.6.0' );
		update_option( 'ops_health_latest_results', [ 'test' => 'data' ] );
		update_option( 'ops_health_alert_settings', [ 'enabled' => true ] );
		update_option( 'ops_health_alert_log', [ [ 'time' => time() ] ] );

		// Verifica che le opzioni esistano.
		$this->assertNotFalse( get_option( 'ops_health_activated_at' ) );
		$this->assertNotFalse( get_option( 'ops_health_version' ) );
		$this->assertNotFalse( get_option( 'ops_health_latest_results' ) );
		$this->assertNotFalse( get_option( 'ops_health_alert_settings' ) );
		$this->assertNotFalse( get_option( 'ops_health_alert_log' ) );

		// Esegui la disinstallazione.
		$uninstaller = new Uninstaller( $wpdb );
		$uninstaller->uninstall();

		// Verifica che tutte le opzioni siano state cancellate.
		$this->assertFalse( get_option( 'ops_health_activated_at' ), 'ops_health_activated_at should be deleted' );
		$this->assertFalse( get_option( 'ops_health_version' ), 'ops_health_version should be deleted' );
		$this->assertFalse( get_option( 'ops_health_latest_results' ), 'ops_health_latest_results should be deleted' );
		$this->assertFalse( get_option( 'ops_health_alert_settings' ), 'ops_health_alert_settings should be deleted' );
		$this->assertFalse( get_option( 'ops_health_alert_log' ), 'ops_health_alert_log should be deleted' );
	}

	/**
	 * Testa che uninstall() cancella il cron event
	 */
	public function test_uninstall_clears_cron_event() {
		global $wpdb;

		// Schedula il cron hook.
		wp_schedule_event( time(), 'hourly', 'ops_health_run_checks' );
		$this->assertNotFalse( wp_next_scheduled( 'ops_health_run_checks' ) );

		// Esegui la disinstallazione.
		$uninstaller = new Uninstaller( $wpdb );
		$uninstaller->uninstall();

		// Verifica che il cron sia stato cancellato.
		$this->assertFalse( wp_next_scheduled( 'ops_health_run_checks' ), 'Cron should be cleared' );
	}

	/**
	 * Testa che uninstall() cancella i transient fissi
	 */
	public function test_uninstall_deletes_fixed_transients() {
		global $wpdb;

		// Crea i transient.
		set_transient( 'ops_health_cron_check', '1', HOUR_IN_SECONDS );
		set_transient( 'ops_health_admin_notice', 'done', 30 );
		set_transient( 'ops_health_alert_notice', 'saved', 30 );

		// Verifica che i transient esistano.
		$this->assertNotFalse( get_transient( 'ops_health_cron_check' ) );
		$this->assertNotFalse( get_transient( 'ops_health_admin_notice' ) );
		$this->assertNotFalse( get_transient( 'ops_health_alert_notice' ) );

		// Esegui la disinstallazione.
		$uninstaller = new Uninstaller( $wpdb );
		$uninstaller->uninstall();

		// Verifica che i transient siano stati cancellati.
		$this->assertFalse( get_transient( 'ops_health_cron_check' ), 'ops_health_cron_check should be deleted' );
		$this->assertFalse( get_transient( 'ops_health_admin_notice' ), 'ops_health_admin_notice should be deleted' );
		$this->assertFalse( get_transient( 'ops_health_alert_notice' ), 'ops_health_alert_notice should be deleted' );
	}

	/**
	 * Testa che uninstall() cancella i transient di cooldown dinamici
	 */
	public function test_uninstall_deletes_cooldown_transients() {
		global $wpdb;

		// Crea transient di cooldown per diversi check.
		set_transient( 'ops_health_alert_cooldown_database', '1', 3600 );
		set_transient( 'ops_health_alert_cooldown_error_log', '1', 3600 );
		set_transient( 'ops_health_alert_cooldown_redis', '1', 3600 );
		set_transient( 'ops_health_alert_cooldown_disk', '1', 3600 );
		set_transient( 'ops_health_alert_cooldown_versions', '1', 3600 );

		// Verifica che i transient esistano.
		$this->assertNotFalse( get_transient( 'ops_health_alert_cooldown_database' ) );
		$this->assertNotFalse( get_transient( 'ops_health_alert_cooldown_redis' ) );

		// Esegui la disinstallazione.
		$uninstaller = new Uninstaller( $wpdb );
		$uninstaller->uninstall();

		// Flush object cache: $wpdb->query() bypassa il layer cache di WP.
		wp_cache_flush();

		// Verifica che i transient di cooldown siano stati cancellati.
		$this->assertFalse( get_transient( 'ops_health_alert_cooldown_database' ), 'database cooldown should be deleted' );
		$this->assertFalse( get_transient( 'ops_health_alert_cooldown_error_log' ), 'error_log cooldown should be deleted' );
		$this->assertFalse( get_transient( 'ops_health_alert_cooldown_redis' ), 'redis cooldown should be deleted' );
		$this->assertFalse( get_transient( 'ops_health_alert_cooldown_disk' ), 'disk cooldown should be deleted' );
		$this->assertFalse( get_transient( 'ops_health_alert_cooldown_versions' ), 'versions cooldown should be deleted' );
	}

	/**
	 * Testa che uninstall() è sicuro quando non ci sono dati
	 */
	public function test_uninstall_is_safe_when_no_data_exists() {
		global $wpdb;

		// Pulizia esplicita per evitare residui da altri test.
		delete_option( 'ops_health_activated_at' );
		wp_clear_scheduled_hook( 'ops_health_run_checks' );
		delete_transient( 'ops_health_cron_check' );

		// Verifica pre-condizione: nessun dato del plugin.
		$this->assertFalse( get_option( 'ops_health_activated_at' ) );
		$this->assertFalse( wp_next_scheduled( 'ops_health_run_checks' ) );
		$this->assertFalse( get_transient( 'ops_health_cron_check' ) );

		// Non deve generare errori.
		$uninstaller = new Uninstaller( $wpdb );
		$uninstaller->uninstall();

		// Post-condition: tutto ancora false (nessun errore generato).
		$this->assertFalse( get_option( 'ops_health_activated_at' ) );
	}

	/**
	 * Testa che uninstall() preserva le opzioni non del plugin
	 */
	public function test_uninstall_preserves_non_plugin_options() {
		global $wpdb;

		// Crea una opzione non del plugin e una del plugin.
		update_option( 'some_other_plugin_option', 'keep_me' );
		update_option( 'ops_health_version', '0.6.0' );

		// Esegui la disinstallazione.
		$uninstaller = new Uninstaller( $wpdb );
		$uninstaller->uninstall();

		// L'opzione del plugin è cancellata, l'altra preservata.
		$this->assertFalse( get_option( 'ops_health_version' ) );
		$this->assertEquals( 'keep_me', get_option( 'some_other_plugin_option' ) );

		// Cleanup.
		delete_option( 'some_other_plugin_option' );
	}

	/**
	 * Testa che uninstall() preserva i transient non del plugin
	 */
	public function test_uninstall_preserves_non_plugin_transients() {
		global $wpdb;

		// Crea un transient non del plugin e uno del plugin.
		set_transient( 'some_other_transient', 'keep_me', 3600 );
		set_transient( 'ops_health_cron_check', '1', 3600 );

		// Esegui la disinstallazione.
		$uninstaller = new Uninstaller( $wpdb );
		$uninstaller->uninstall();

		// Il transient del plugin è cancellato, l'altro preservato.
		$this->assertFalse( get_transient( 'ops_health_cron_check' ) );
		$this->assertEquals( 'keep_me', get_transient( 'some_other_transient' ) );

		// Cleanup.
		delete_transient( 'some_other_transient' );
	}

	/**
	 * Testa il ciclo completo activate → uninstall
	 */
	public function test_full_activate_then_uninstall_cycle() {
		global $wpdb;

		if ( ! defined( 'OPS_HEALTH_DASHBOARD_VERSION' ) ) {
			define( 'OPS_HEALTH_DASHBOARD_VERSION', '0.6.0' );
		}

		// Attiva il plugin.
		$activator = new Activator();
		$activator->activate();

		// Verifica che i dati di attivazione esistano.
		$this->assertNotFalse( get_option( 'ops_health_activated_at' ) );
		$this->assertNotFalse( get_option( 'ops_health_version' ) );
		$this->assertNotFalse( wp_next_scheduled( 'ops_health_run_checks' ) );

		// Disinstalla.
		$uninstaller = new Uninstaller( $wpdb );
		$uninstaller->uninstall();

		// Verifica pulizia completa.
		$this->assertFalse( get_option( 'ops_health_activated_at' ) );
		$this->assertFalse( get_option( 'ops_health_version' ) );
		$this->assertFalse( wp_next_scheduled( 'ops_health_run_checks' ) );
	}

	/**
	 * Testa che i transient con cooldown prefix custom vengono cancellati
	 */
	public function test_uninstall_deletes_cooldown_with_custom_check_ids() {
		global $wpdb;

		// Simula un check custom con ID diverso dai 5 standard.
		set_transient( 'ops_health_alert_cooldown_custom_check', '1', 3600 );

		$this->assertNotFalse( get_transient( 'ops_health_alert_cooldown_custom_check' ) );

		// Esegui la disinstallazione.
		$uninstaller = new Uninstaller( $wpdb );
		$uninstaller->uninstall();

		// Flush object cache: $wpdb->query() bypassa il layer cache di WP.
		wp_cache_flush();

		// Anche il transient custom con il prefisso corretto viene cancellato.
		$this->assertFalse( get_transient( 'ops_health_alert_cooldown_custom_check' ), 'Custom cooldown transient should be deleted' );
	}
}
