<?php
/**
 * Integration Test for the complete alerting flow
 *
 * End-to-end test: state change → dispatch → cooldown → recovery.
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
 * Integration test for the complete alerting flow.
 */
class AlertingFlowTest extends WP_UnitTestCase {

	/**
	 * Storage for tests
	 *
	 * @var Storage
	 */
	private $storage;

	/**
	 * Redaction for tests
	 *
	 * @var Redaction
	 */
	private $redaction;

	/**
	 * Setup for each test
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
	 * Cleanup after each test
	 */
	public function tearDown(): void {
		$this->storage->delete( 'latest_results' );
		$this->storage->delete( 'alert_log' );
		$this->storage->delete( 'alert_settings' );
		delete_transient( 'ops_health_alert_cooldown_database' );
		parent::tearDown();
	}

	/**
	 * Tests that AlertManager detects state change and logs alert
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

		// EmailChannel attempted → dispatch and log created.
		$this->assertArrayHasKey( 'database', $results );

		$log = $this->storage->get( 'alert_log', [] );
		$this->assertNotEmpty( $log );
		$this->assertEquals( 'database', $log[0]['check_id'] );
		$this->assertEquals( 'critical', $log[0]['status'] );
	}

	/**
	 * Tests full flow with EmailChannel enabled
	 */
	public function test_full_flow_with_email_channel() {
		// Configure EmailChannel as enabled.
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

		// EmailChannel should have attempted sending.
		$this->assertArrayHasKey( 'database', $results );
		$this->assertArrayHasKey( 'email', $results['database'] );

		// Verify that the log was created.
		$log = $this->storage->get( 'alert_log', [] );
		$this->assertNotEmpty( $log );
		$this->assertEquals( 'database', $log[0]['check_id'] );
		$this->assertEquals( 'critical', $log[0]['status'] );
		$this->assertContains( 'email', $log[0]['channels'] );
	}

	/**
	 * Tests that cooldown blocks repeated alerts
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

		// First alert: dispatched.
		$results1 = $alert_manager->process( $current, $previous );
		$this->assertArrayHasKey( 'database', $results1 );

		// Second alert with same transition: blocked by cooldown.
		$results2 = $alert_manager->process( $current, $previous );
		$this->assertEmpty( $results2 );
	}

	/**
	 * Tests that recovery bypasses cooldown
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

		// First alert: ok → critical.
		$alert_manager->process(
			[ 'database' => [ 'status' => 'critical', 'message' => 'Fail', 'name' => 'DB' ] ],
			[ 'database' => [ 'status' => 'ok', 'message' => 'OK', 'name' => 'DB' ] ]
		);

		// Recovery: critical → ok (should bypass cooldown).
		$results = $alert_manager->process(
			[ 'database' => [ 'status' => 'ok', 'message' => 'OK', 'name' => 'DB' ] ],
			[ 'database' => [ 'status' => 'critical', 'message' => 'Fail', 'name' => 'DB' ] ]
		);

		$this->assertArrayHasKey( 'database', $results );

		// Verify log has 2 entries: alert + recovery.
		$log = $this->storage->get( 'alert_log', [] );
		$this->assertCount( 2, $log );
		$this->assertEquals( 'ok', $log[0]['status'] );
		$this->assertEquals( 'critical', $log[1]['status'] );
	}

	/**
	 * Tests no alert on first run with ok status
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

		// First run: no previous results, ok status.
		$results = $alert_manager->process(
			[ 'database' => [ 'status' => 'ok', 'message' => 'OK', 'name' => 'DB' ] ],
			[]
		);

		$this->assertEmpty( $results );

		$log = $this->storage->get( 'alert_log', [] );
		$this->assertEmpty( $log );
	}

	/**
	 * Tests alert on first run with non-ok status
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
	 * Tests Scheduler with AlertManager in real integration
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

		// Simulate previous critical.
		$this->storage->set( 'latest_results', [
			'database' => [
				'status'  => 'critical',
				'message' => 'Simulated failure',
				'name'    => 'Database',
			],
		] );

		$scheduler = new Scheduler( $runner, $alert_manager );
		$scheduler->run_checks();

		// Database in test env is ok → recovery alert.
		$log = $this->storage->get( 'alert_log', [] );
		$this->assertIsArray( $log );

		// Cleanup.
		$this->storage->delete( 'latest_results' );
	}

	/**
	 * Tests that AlertSettings saves and retrieves real settings
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
	 * Tests alert log capped at 50 entries
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

		// Generate 55 alerts alternating status.
		for ( $i = 0; $i < 55; $i++ ) {
			$status_a = ( 0 === $i % 2 ) ? 'ok' : 'critical';
			$status_b = ( 0 === $i % 2 ) ? 'critical' : 'ok';

			// Clear cooldown to allow sending.
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
	 * Tests that build_payload uses real WordPress home_url() and bloginfo
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

		// The email body should contain home_url() and bloginfo('name').
		$this->assertNotNull( $captured_mail );
		$this->assertStringContainsString( home_url(), $captured_mail['message'] );

		$site_name = get_bloginfo( 'name' );
		if ( ! empty( $site_name ) ) {
			$this->assertStringContainsString( $site_name, $captured_mail['message'] );
		}

		remove_all_filters( 'pre_wp_mail' );
	}

	/**
	 * Tests that cooldown uses DEFAULT_COOLDOWN when minutes is zero
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

		// First alert: ok → critical.
		$alert_manager->process(
			[ 'database' => [ 'status' => 'critical', 'message' => 'Fail', 'name' => 'DB' ] ],
			[ 'database' => [ 'status' => 'ok', 'message' => 'OK', 'name' => 'DB' ] ]
		);

		// Second alert: same transition → blocked by cooldown (DEFAULT_COOLDOWN).
		$results2 = $alert_manager->process(
			[ 'database' => [ 'status' => 'critical', 'message' => 'Fail', 'name' => 'DB' ] ],
			[ 'database' => [ 'status' => 'ok', 'message' => 'OK', 'name' => 'DB' ] ]
		);

		$this->assertEmpty( $results2, 'Cooldown with 0 minutes should use DEFAULT_COOLDOWN' );
	}

	/**
	 * Tests detect_state_changes with previous result missing status key
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

		// Previous result without 'status' key → prev_status = 'unknown'.
		$results = $alert_manager->process(
			[ 'database' => [ 'status' => 'critical', 'message' => 'Fail', 'name' => 'DB' ] ],
			[ 'database' => [ 'message' => 'No status key', 'name' => 'DB' ] ]
		);

		// Change unknown → critical: should trigger alert.
		$this->assertArrayHasKey( 'database', $results );
	}

	/**
	 * Tests log_alert with corrupted alert_log (non-array)
	 */
	public function test_alert_log_handles_corrupted_storage() {
		$this->storage->set( 'alert_settings', [
			'email' => [
				'enabled'    => true,
				'recipients' => 'admin@example.com',
			],
		] );

		// Corrupt the log by setting a non-array value.
		$this->storage->set( 'alert_log', 'corrupted-string' );

		$alert_manager = new AlertManager( $this->storage, $this->redaction );
		$alert_manager->add_channel( new EmailChannel( $this->storage ) );

		$alert_manager->process(
			[ 'database' => [ 'status' => 'critical', 'message' => 'Fail', 'name' => 'DB' ] ],
			[ 'database' => [ 'status' => 'ok', 'message' => 'OK', 'name' => 'DB' ] ]
		);

		// The log should have been rewritten as a valid array.
		$log = $this->storage->get( 'alert_log', [] );
		$this->assertIsArray( $log );
		$this->assertNotEmpty( $log );
	}

	/**
	 * Tests that dispatch skips disabled channels
	 */
	public function test_dispatch_skips_disabled_channels() {
		// Email disabled, but channel registered.
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

		// No enabled channels → no dispatch results.
		$this->assertEmpty( $results );
	}

	/**
	 * Tests that log_alert redacts errors from failed channels
	 */
	public function test_alert_log_redacts_channel_errors() {
		$this->storage->set( 'alert_settings', [
			'email' => [
				'enabled'    => true,
				'recipients' => 'admin@example.com',
			],
		] );

		// Force wp_mail to fail.
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
	 * Tests that dispatch_to_channels catches Throwable from a channel that throws exception
	 */
	public function test_dispatch_catches_throwable_from_channel() {
		$this->storage->set( 'alert_settings', [
			'email' => [
				'enabled'    => true,
				'recipients' => 'admin@example.com',
			],
		] );

		$alert_manager = new AlertManager( $this->storage, $this->redaction );
		$alert_manager->add_channel( new ThrowingChannel() );
		$alert_manager->add_channel( new EmailChannel( $this->storage ) );

		// Intercept wp_mail to prevent real sending.
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

		$results = $alert_manager->process(
			[ 'database' => [ 'status' => 'critical', 'message' => 'Fail', 'name' => 'DB' ] ],
			[ 'database' => [ 'status' => 'ok', 'message' => 'OK', 'name' => 'DB' ] ]
		);

		// ThrowingChannel should have failed with the exception message.
		$this->assertArrayHasKey( 'database', $results );
		$this->assertArrayHasKey( 'throwing', $results['database'] );
		$this->assertFalse( $results['database']['throwing']['success'] );
		$this->assertEquals( 'Simulated channel failure', $results['database']['throwing']['error'] );

		// EmailChannel should still work (per-channel isolation).
		$this->assertArrayHasKey( 'email', $results['database'] );
		$this->assertTrue( $results['database']['email']['success'] );

		// The log should contain both channels.
		$log = $this->storage->get( 'alert_log', [] );
		$this->assertNotEmpty( $log );
		$this->assertContains( 'throwing', $log[0]['channels'] );
		$this->assertContains( 'email', $log[0]['channels'] );

		remove_all_filters( 'pre_wp_mail' );
	}

	/**
	 * Tests that no state change does not generate alert
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

/**
 * Alert channel that throws exception to test per-channel isolation.
 */
class ThrowingChannel implements \OpsHealthDashboard\Interfaces\AlertChannelInterface {

	/**
	 * Gets the channel identifier
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'throwing';
	}

	/**
	 * Gets the channel name
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'Throwing';
	}

	/**
	 * Always enabled for testing purposes
	 *
	 * @return bool
	 */
	public function is_enabled(): bool {
		return true;
	}

	/**
	 * Always throws exception
	 *
	 * @param array $payload Alert data.
	 * @return array Never reached.
	 * @throws \RuntimeException Always.
	 */
	public function send( array $payload ): array {
		throw new \RuntimeException( 'Simulated channel failure' );
	}
}
