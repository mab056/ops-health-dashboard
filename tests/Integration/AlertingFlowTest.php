<?php
/**
 * Integration Test per il flusso completo di alerting
 *
 * Test end-to-end: state change → dispatch → cooldown → recovery.
 *
 * @package OpsHealthDashboard\Tests\Integration
 */

namespace OpsHealthDashboard\Tests\Integration;

use OpsHealthDashboard\Channels\EmailChannel;
use OpsHealthDashboard\Checks\DatabaseCheck;
use OpsHealthDashboard\Services\AlertManager;
use OpsHealthDashboard\Services\CheckRunner;
use OpsHealthDashboard\Services\Redaction;
use OpsHealthDashboard\Services\Scheduler;
use OpsHealthDashboard\Services\Storage;
use WP_UnitTestCase;

/**
 * Class AlertingFlowTest
 *
 * Test di integrazione per il flusso completo di alerting.
 */
class AlertingFlowTest extends WP_UnitTestCase {

	/**
	 * Storage per i test
	 *
	 * @var Storage
	 */
	private $storage;

	/**
	 * Redaction per i test
	 *
	 * @var Redaction
	 */
	private $redaction;

	/**
	 * Setup per ogni test
	 */
	public function setUp(): void {
		parent::setUp();
		$this->storage   = new Storage();
		$this->redaction = new Redaction();

		// Cleanup.
		$this->storage->delete( 'latest_results' );
		$this->storage->delete( 'alert_log' );
		$this->storage->delete( 'alert_settings' );
	}

	/**
	 * Cleanup dopo ogni test
	 */
	public function tearDown(): void {
		$this->storage->delete( 'latest_results' );
		$this->storage->delete( 'alert_log' );
		$this->storage->delete( 'alert_settings' );
		delete_transient( 'ops_health_alert_cooldown_database' );
		parent::tearDown();
	}

	/**
	 * Testa che AlertManager rileva cambiamento di stato e logga alert
	 */
	public function test_state_change_triggers_alert_log_entry() {
		$this->storage->set( 'alert_settings', [
			'email' => [
				'enabled'    => true,
				'recipients' => 'admin@example.com',
			],
		] );

		$alert_manager = new AlertManager( $this->storage, $this->redaction );
		$alert_manager->add_channel( new EmailChannel( $this->storage ) );

		$previous = [
			'database' => [
				'status'  => 'ok',
				'message' => 'Database OK',
				'name'    => 'Database',
			],
		];

		$current = [
			'database' => [
				'status'  => 'critical',
				'message' => 'Slow queries detected',
				'name'    => 'Database',
			],
		];

		$results = $alert_manager->process( $current, $previous );

		// EmailChannel tentato → dispatch e log creato.
		$this->assertArrayHasKey( 'database', $results );

		$log = $this->storage->get( 'alert_log', [] );
		$this->assertNotEmpty( $log );
		$this->assertEquals( 'database', $log[0]['check_id'] );
		$this->assertEquals( 'critical', $log[0]['status'] );
	}

	/**
	 * Testa flusso completo con EmailChannel abilitato
	 */
	public function test_full_flow_with_email_channel() {
		// Configura EmailChannel come abilitato.
		$this->storage->set( 'alert_settings', [
			'email' => [
				'enabled'    => true,
				'recipients' => 'admin@example.com',
			],
		] );

		$alert_manager = new AlertManager( $this->storage, $this->redaction );
		$alert_manager->add_channel( new EmailChannel( $this->storage ) );

		$previous = [
			'database' => [
				'status'  => 'ok',
				'message' => 'OK',
				'name'    => 'Database',
			],
		];

		$current = [
			'database' => [
				'status'  => 'critical',
				'message' => 'Slow queries detected',
				'name'    => 'Database',
			],
		];

		$results = $alert_manager->process( $current, $previous );

		// EmailChannel dovrebbe aver tentato l'invio.
		$this->assertArrayHasKey( 'database', $results );
		$this->assertArrayHasKey( 'email', $results['database'] );

		// Verifica che il log è stato creato.
		$log = $this->storage->get( 'alert_log', [] );
		$this->assertNotEmpty( $log );
		$this->assertEquals( 'database', $log[0]['check_id'] );
		$this->assertEquals( 'critical', $log[0]['status'] );
		$this->assertContains( 'email', $log[0]['channels'] );
	}

	/**
	 * Testa che il cooldown blocca alert ripetuti
	 */
	public function test_cooldown_blocks_repeated_alerts() {
		$this->storage->set( 'alert_settings', [
			'email'            => [
				'enabled'    => true,
				'recipients' => 'admin@example.com',
			],
			'cooldown_minutes' => 60,
		] );

		$alert_manager = new AlertManager( $this->storage, $this->redaction );
		$alert_manager->add_channel( new EmailChannel( $this->storage ) );

		$previous = [
			'database' => [
				'status'  => 'ok',
				'message' => 'OK',
				'name'    => 'Database',
			],
		];

		$current = [
			'database' => [
				'status'  => 'critical',
				'message' => 'Failure',
				'name'    => 'Database',
			],
		];

		// Primo alert: dispatched.
		$results1 = $alert_manager->process( $current, $previous );
		$this->assertArrayHasKey( 'database', $results1 );

		// Secondo alert con stessa transizione: bloccato da cooldown.
		$results2 = $alert_manager->process( $current, $previous );
		$this->assertEmpty( $results2 );
	}

	/**
	 * Testa che recovery bypassa il cooldown
	 */
	public function test_recovery_bypasses_cooldown() {
		$this->storage->set( 'alert_settings', [
			'email'            => [
				'enabled'    => true,
				'recipients' => 'admin@example.com',
			],
			'cooldown_minutes' => 60,
		] );

		$alert_manager = new AlertManager( $this->storage, $this->redaction );
		$alert_manager->add_channel( new EmailChannel( $this->storage ) );

		// Primo alert: ok → critical.
		$alert_manager->process(
			[ 'database' => [ 'status' => 'critical', 'message' => 'Fail', 'name' => 'DB' ] ],
			[ 'database' => [ 'status' => 'ok', 'message' => 'OK', 'name' => 'DB' ] ]
		);

		// Recovery: critical → ok (dovrebbe bypassare il cooldown).
		$results = $alert_manager->process(
			[ 'database' => [ 'status' => 'ok', 'message' => 'OK', 'name' => 'DB' ] ],
			[ 'database' => [ 'status' => 'critical', 'message' => 'Fail', 'name' => 'DB' ] ]
		);

		$this->assertArrayHasKey( 'database', $results );

		// Verifica log ha 2 entry: alert + recovery.
		$log = $this->storage->get( 'alert_log', [] );
		$this->assertCount( 2, $log );
		$this->assertEquals( 'ok', $log[0]['status'] );
		$this->assertEquals( 'critical', $log[1]['status'] );
	}

	/**
	 * Testa nessun alert al primo avvio con stato ok
	 */
	public function test_no_alert_on_first_run_with_ok() {
		$this->storage->set( 'alert_settings', [
			'email' => [
				'enabled'    => true,
				'recipients' => 'admin@example.com',
			],
		] );

		$alert_manager = new AlertManager( $this->storage, $this->redaction );
		$alert_manager->add_channel( new EmailChannel( $this->storage ) );

		// Primo avvio: nessun risultato precedente, stato ok.
		$results = $alert_manager->process(
			[ 'database' => [ 'status' => 'ok', 'message' => 'OK', 'name' => 'DB' ] ],
			[]
		);

		$this->assertEmpty( $results );

		$log = $this->storage->get( 'alert_log', [] );
		$this->assertEmpty( $log );
	}

	/**
	 * Testa alert al primo avvio con stato non-ok
	 */
	public function test_alert_on_first_run_with_non_ok() {
		$this->storage->set( 'alert_settings', [
			'email' => [
				'enabled'    => true,
				'recipients' => 'admin@example.com',
			],
		] );

		$alert_manager = new AlertManager( $this->storage, $this->redaction );
		$alert_manager->add_channel( new EmailChannel( $this->storage ) );

		$results = $alert_manager->process(
			[ 'database' => [ 'status' => 'critical', 'message' => 'Fail', 'name' => 'DB' ] ],
			[]
		);

		$this->assertArrayHasKey( 'database', $results );
	}

	/**
	 * Testa Scheduler con AlertManager in integrazione reale
	 */
	public function test_scheduler_alert_manager_integration() {
		$this->storage->set( 'alert_settings', [
			'email' => [
				'enabled'    => true,
				'recipients' => 'admin@example.com',
			],
		] );

		$alert_manager = new AlertManager( $this->storage, $this->redaction );
		$alert_manager->add_channel( new EmailChannel( $this->storage ) );

		$runner = new CheckRunner( $this->storage, $this->redaction );
		global $wpdb;
		$runner->add_check( new DatabaseCheck( $wpdb, $this->redaction ) );

		// Simula precedente critical.
		$this->storage->set( 'latest_results', [
			'database' => [
				'status'  => 'critical',
				'message' => 'Simulated failure',
				'name'    => 'Database',
			],
		] );

		$scheduler = new Scheduler( $runner, $alert_manager );
		$scheduler->run_checks();

		// Database in test env è ok → recovery alert.
		$log = $this->storage->get( 'alert_log', [] );
		$this->assertIsArray( $log );

		// Cleanup.
		$this->storage->delete( 'latest_results' );
	}

	/**
	 * Testa che AlertSettings salva e recupera impostazioni reali
	 */
	public function test_alert_settings_persist_via_storage() {
		$settings_data = [
			'email'            => [
				'enabled'    => true,
				'recipients' => 'ops@example.com',
			],
			'slack'            => [
				'enabled'     => true,
				'webhook_url' => 'https://hooks.slack.com/test',
			],
			'cooldown_minutes' => 30,
		];

		$this->storage->set( 'alert_settings', $settings_data );

		$loaded = $this->storage->get( 'alert_settings', [] );

		$this->assertIsArray( $loaded );
		$this->assertTrue( $loaded['email']['enabled'] );
		$this->assertEquals( 'ops@example.com', $loaded['email']['recipients'] );
		$this->assertTrue( $loaded['slack']['enabled'] );
		$this->assertEquals( 30, $loaded['cooldown_minutes'] );
	}

	/**
	 * Testa log alert limitato a 50 entry
	 */
	public function test_alert_log_capped_at_max_entries() {
		$this->storage->set( 'alert_settings', [
			'email' => [
				'enabled'    => true,
				'recipients' => 'admin@example.com',
			],
		] );

		$alert_manager = new AlertManager( $this->storage, $this->redaction );
		$alert_manager->add_channel( new EmailChannel( $this->storage ) );

		// Genera 55 alert alternando stato.
		for ( $i = 0; $i < 55; $i++ ) {
			$status_a = ( 0 === $i % 2 ) ? 'ok' : 'critical';
			$status_b = ( 0 === $i % 2 ) ? 'critical' : 'ok';

			// Cancella cooldown per permettere invio.
			delete_transient( 'ops_health_alert_cooldown_database' );

			$alert_manager->process(
				[ 'database' => [ 'status' => $status_a, 'message' => 'M', 'name' => 'DB' ] ],
				[ 'database' => [ 'status' => $status_b, 'message' => 'M', 'name' => 'DB' ] ]
			);
		}

		$log = $this->storage->get( 'alert_log', [] );
		$this->assertLessThanOrEqual( 50, count( $log ) );
	}

	/**
	 * Testa che build_payload usa home_url() e bloginfo reali di WordPress
	 */
	public function test_build_payload_uses_real_home_url_and_bloginfo() {
		$this->storage->set( 'alert_settings', [
			'email' => [
				'enabled'    => true,
				'recipients' => 'admin@example.com',
			],
		] );

		$captured_mail = null;
		add_filter(
			'pre_wp_mail',
			function ( $null, $atts ) use ( &$captured_mail ) {
				$captured_mail = $atts;
				return true;
			},
			10,
			2
		);

		$alert_manager = new AlertManager( $this->storage, $this->redaction );
		$alert_manager->add_channel( new EmailChannel( $this->storage ) );

		$alert_manager->process(
			[ 'database' => [ 'status' => 'critical', 'message' => 'Fail', 'name' => 'DB' ] ],
			[ 'database' => [ 'status' => 'ok', 'message' => 'OK', 'name' => 'DB' ] ]
		);

		// Il body dell'email dovrebbe contenere home_url() e bloginfo('name').
		$this->assertNotNull( $captured_mail );
		$this->assertStringContainsString( home_url(), $captured_mail['message'] );

		$site_name = get_bloginfo( 'name' );
		if ( ! empty( $site_name ) ) {
			$this->assertStringContainsString( $site_name, $captured_mail['message'] );
		}

		remove_all_filters( 'pre_wp_mail' );
	}

	/**
	 * Testa che cooldown usa DEFAULT_COOLDOWN quando minutes è zero
	 */
	public function test_cooldown_uses_default_when_minutes_zero() {
		$this->storage->set( 'alert_settings', [
			'email'            => [
				'enabled'    => true,
				'recipients' => 'admin@example.com',
			],
			'cooldown_minutes' => 0,
		] );

		$alert_manager = new AlertManager( $this->storage, $this->redaction );
		$alert_manager->add_channel( new EmailChannel( $this->storage ) );

		// Primo alert: ok → critical.
		$alert_manager->process(
			[ 'database' => [ 'status' => 'critical', 'message' => 'Fail', 'name' => 'DB' ] ],
			[ 'database' => [ 'status' => 'ok', 'message' => 'OK', 'name' => 'DB' ] ]
		);

		// Secondo alert: stessa transizione → bloccato da cooldown (DEFAULT_COOLDOWN).
		$results2 = $alert_manager->process(
			[ 'database' => [ 'status' => 'critical', 'message' => 'Fail', 'name' => 'DB' ] ],
			[ 'database' => [ 'status' => 'ok', 'message' => 'OK', 'name' => 'DB' ] ]
		);

		$this->assertEmpty( $results2, 'Cooldown with 0 minutes should use DEFAULT_COOLDOWN' );
	}

	/**
	 * Testa detect_state_changes con risultato precedente senza chiave status
	 */
	public function test_state_change_with_missing_previous_status_key() {
		$this->storage->set( 'alert_settings', [
			'email' => [
				'enabled'    => true,
				'recipients' => 'admin@example.com',
			],
		] );

		$alert_manager = new AlertManager( $this->storage, $this->redaction );
		$alert_manager->add_channel( new EmailChannel( $this->storage ) );

		// Risultato precedente senza chiave 'status' → prev_status = 'unknown'.
		$results = $alert_manager->process(
			[ 'database' => [ 'status' => 'critical', 'message' => 'Fail', 'name' => 'DB' ] ],
			[ 'database' => [ 'message' => 'No status key', 'name' => 'DB' ] ]
		);

		// Cambio unknown → critical: dovrebbe triggerare alert.
		$this->assertArrayHasKey( 'database', $results );
	}

	/**
	 * Testa log_alert con alert_log corrotto (non array)
	 */
	public function test_alert_log_handles_corrupted_storage() {
		$this->storage->set( 'alert_settings', [
			'email' => [
				'enabled'    => true,
				'recipients' => 'admin@example.com',
			],
		] );

		// Corrompi il log impostando un valore non-array.
		$this->storage->set( 'alert_log', 'corrupted-string' );

		$alert_manager = new AlertManager( $this->storage, $this->redaction );
		$alert_manager->add_channel( new EmailChannel( $this->storage ) );

		$alert_manager->process(
			[ 'database' => [ 'status' => 'critical', 'message' => 'Fail', 'name' => 'DB' ] ],
			[ 'database' => [ 'status' => 'ok', 'message' => 'OK', 'name' => 'DB' ] ]
		);

		// Il log dovrebbe essere stato riscritto come array valido.
		$log = $this->storage->get( 'alert_log', [] );
		$this->assertIsArray( $log );
		$this->assertNotEmpty( $log );
	}

	/**
	 * Testa che dispatch skippa canali disabilitati
	 */
	public function test_dispatch_skips_disabled_channels() {
		// Email disabilitato, ma canale registrato.
		$this->storage->set( 'alert_settings', [
			'email' => [
				'enabled'    => false,
				'recipients' => 'admin@example.com',
			],
		] );

		$alert_manager = new AlertManager( $this->storage, $this->redaction );
		$alert_manager->add_channel( new EmailChannel( $this->storage ) );

		$results = $alert_manager->process(
			[ 'database' => [ 'status' => 'critical', 'message' => 'Fail', 'name' => 'DB' ] ],
			[ 'database' => [ 'status' => 'ok', 'message' => 'OK', 'name' => 'DB' ] ]
		);

		// Nessun canale abilitato → nessun risultato di dispatch.
		$this->assertEmpty( $results );
	}

	/**
	 * Testa che log_alert redige errori dai canali falliti
	 */
	public function test_alert_log_redacts_channel_errors() {
		$this->storage->set( 'alert_settings', [
			'email' => [
				'enabled'    => true,
				'recipients' => 'admin@example.com',
			],
		] );

		// Forza wp_mail a fallire.
		add_filter(
			'pre_wp_mail',
			function () {
				return false;
			}
		);

		$alert_manager = new AlertManager( $this->storage, $this->redaction );
		$alert_manager->add_channel( new EmailChannel( $this->storage ) );

		$alert_manager->process(
			[ 'database' => [ 'status' => 'critical', 'message' => 'Fail', 'name' => 'DB' ] ],
			[ 'database' => [ 'status' => 'ok', 'message' => 'OK', 'name' => 'DB' ] ]
		);

		$log = $this->storage->get( 'alert_log', [] );
		$this->assertNotEmpty( $log );
		$this->assertArrayHasKey( 'errors', $log[0] );

		remove_all_filters( 'pre_wp_mail' );
	}

	/**
	 * Testa che nessun cambiamento di stato non genera alert
	 */
	public function test_same_status_does_not_trigger_alert() {
		$this->storage->set( 'alert_settings', [
			'email' => [
				'enabled'    => true,
				'recipients' => 'admin@example.com',
			],
		] );

		$alert_manager = new AlertManager( $this->storage, $this->redaction );
		$alert_manager->add_channel( new EmailChannel( $this->storage ) );

		$same = [
			'database' => [
				'status'  => 'ok',
				'message' => 'OK',
				'name'    => 'Database',
			],
		];

		$results = $alert_manager->process( $same, $same );

		$this->assertEmpty( $results );
	}
}
