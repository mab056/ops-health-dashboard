<?php
/**
 * Integration Test for EmailChannel
 *
 * Verifies email alert sending with real wp_mail (intercepted via pre_wp_mail).
 *
 * @package OpsHealthDashboard\Tests\Integration\Channels
 */

namespace OpsHealthDashboard\Tests\Integration\Channels;

use OpsHealthDashboard\Channels\EmailChannel;
use OpsHealthDashboard\Services\Storage;
use WP_UnitTestCase;

/**
 * Class EmailChannelTest
 *
 * Integration test for EmailChannel with real WordPress.
 */
class EmailChannelTest extends WP_UnitTestCase {

	/**
	 * Real storage
	 *
	 * @var Storage
	 */
	private $storage;

	/**
	 * Setup
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		$this->storage = new Storage();
		$this->storage->delete( 'alert_settings' );
	}

	/**
	 * Teardown
	 *
	 * @return void
	 */
	public function tearDown(): void {
		$this->storage->delete( 'alert_settings' );
		remove_all_filters( 'pre_wp_mail' );
		parent::tearDown();
	}

	/**
	 * Creates a test payload
	 *
	 * @param array $overrides Field overrides.
	 * @return array Alert payload.
	 */
	private function create_test_payload( array $overrides = [] ): array {
		return array_merge(
			[
				'check_id'        => 'database',
				'check_name'      => 'Database',
				'current_status'  => 'critical',
				'previous_status' => 'ok',
				'message'         => 'Connection failed',
				'site_name'       => 'Test Site',
				'site_url'        => 'https://example.com',
				'is_recovery'     => false,
				'timestamp'       => 1707580800,
			],
			$overrides
		);
	}

	/**
	 * Intercepts wp_mail to capture arguments
	 *
	 * @param mixed $captured_mail Variable to capture data.
	 * @return void
	 */
	private function intercept_wp_mail( &$captured_mail ): void {
		add_filter(
			'pre_wp_mail',
			function ( $null, $atts ) use ( &$captured_mail ) {
				$captured_mail = $atts;
				return true;
			},
			10,
			2
		);
	}

	/**
	 * Verifies that the class is NOT final
	 *
	 * @return void
	 */
	public function test_class_is_not_final() {
		$reflection = new \ReflectionClass( EmailChannel::class );
		$this->assertFalse( $reflection->isFinal(), 'Class should NOT be final' );
	}

	/**
	 * Verifies that NO static methods exist
	 *
	 * @return void
	 */
	public function test_no_static_methods() {
		$reflection = new \ReflectionClass( EmailChannel::class );
		$methods    = $reflection->getMethods( \ReflectionMethod::IS_STATIC );

		$static_methods = array_filter(
			$methods,
			function ( $method ) {
				return strpos( $method->getName(), '__' ) !== 0;
			}
		);

		$this->assertEmpty( $static_methods, 'Class should have NO static methods' );
	}

	/**
	 * Verifies that NO static properties exist
	 *
	 * @return void
	 */
	public function test_no_static_properties() {
		$reflection = new \ReflectionClass( EmailChannel::class );
		$properties = $reflection->getProperties( \ReflectionProperty::IS_STATIC );

		$this->assertEmpty( $properties, 'Class should have NO static properties' );
	}

	/**
	 * Tests get_id
	 *
	 * @return void
	 */
	public function test_get_id_returns_email() {
		$channel = new EmailChannel( $this->storage );

		$this->assertEquals( 'email', $channel->get_id() );
	}

	/**
	 * Tests get_name
	 *
	 * @return void
	 */
	public function test_get_name_returns_translated_string() {
		$channel = new EmailChannel( $this->storage );

		$this->assertEquals( 'Email', $channel->get_name() );
	}

	/**
	 * Tests is_enabled with valid settings
	 *
	 * @return void
	 */
	public function test_is_enabled_true_with_enabled_and_recipients() {
		$this->storage->set(
			'alert_settings',
			[
				'email' => [
					'enabled'    => true,
					'recipients' => 'admin@example.com',
				],
			]
		);

		$channel = new EmailChannel( $this->storage );

		$this->assertTrue( $channel->is_enabled() );
	}

	/**
	 * Tests is_enabled when disabled
	 *
	 * @return void
	 */
	public function test_is_enabled_false_when_disabled() {
		$this->storage->set(
			'alert_settings',
			[
				'email' => [
					'enabled'    => false,
					'recipients' => 'admin@example.com',
				],
			]
		);

		$channel = new EmailChannel( $this->storage );

		$this->assertFalse( $channel->is_enabled() );
	}

	/**
	 * Tests is_enabled without recipients
	 *
	 * @return void
	 */
	public function test_is_enabled_false_without_recipients() {
		$this->storage->set(
			'alert_settings',
			[
				'email' => [
					'enabled'    => true,
					'recipients' => '',
				],
			]
		);

		$channel = new EmailChannel( $this->storage );

		$this->assertFalse( $channel->is_enabled() );
	}

	/**
	 * Tests is_enabled with corrupted settings (non-array)
	 *
	 * @return void
	 */
	public function test_is_enabled_false_with_corrupted_settings() {
		$this->storage->set( 'alert_settings', 'not-an-array' );

		$channel = new EmailChannel( $this->storage );

		$this->assertFalse( $channel->is_enabled() );
	}

	/**
	 * Tests send failure when wp_mail returns false
	 *
	 * @return void
	 */
	public function test_send_failure_when_wp_mail_returns_false() {
		$this->storage->set(
			'alert_settings',
			[
				'email' => [
					'enabled'    => true,
					'recipients' => 'admin@example.com',
				],
			]
		);

		add_filter(
			'pre_wp_mail',
			function () {
				return false;
			}
		);

		$channel = new EmailChannel( $this->storage );
		$result  = $channel->send( $this->create_test_payload() );

		$this->assertFalse( $result['success'] );
		$this->assertNotNull( $result['error'] );
	}

	/**
	 * Tests send success via pre_wp_mail
	 *
	 * @return void
	 */
	public function test_send_success_with_intercepted_email() {
		$this->storage->set(
			'alert_settings',
			[
				'email' => [
					'enabled'    => true,
					'recipients' => 'admin@example.com',
				],
			]
		);

		$captured_mail = null;
		$this->intercept_wp_mail( $captured_mail );

		$channel = new EmailChannel( $this->storage );
		$result  = $channel->send( $this->create_test_payload() );

		$this->assertTrue( $result['success'] );
		$this->assertNull( $result['error'] );
		$this->assertNotNull( $captured_mail );
	}

	/**
	 * Tests that the subject contains the check name and status
	 *
	 * @return void
	 */
	public function test_send_formats_subject_with_status() {
		$this->storage->set(
			'alert_settings',
			[
				'email' => [
					'enabled'    => true,
					'recipients' => 'admin@example.com',
				],
			]
		);

		$captured_mail = null;
		$this->intercept_wp_mail( $captured_mail );

		$channel = new EmailChannel( $this->storage );
		$channel->send( $this->create_test_payload() );

		$this->assertStringContainsString( '[Ops Health]', $captured_mail['subject'] );
		$this->assertStringContainsString( 'Database', $captured_mail['subject'] );
		$this->assertStringContainsString( 'CRITICAL', $captured_mail['subject'] );
	}

	/**
	 * Tests that the body contains a recovery notice
	 *
	 * @return void
	 */
	public function test_send_formats_body_with_recovery_notice() {
		$this->storage->set(
			'alert_settings',
			[
				'email' => [
					'enabled'    => true,
					'recipients' => 'admin@example.com',
				],
			]
		);

		$captured_mail = null;
		$this->intercept_wp_mail( $captured_mail );

		$channel = new EmailChannel( $this->storage );
		$channel->send(
			$this->create_test_payload(
				[
					'is_recovery'    => true,
					'current_status' => 'ok',
				]
			)
		);

		$this->assertStringContainsString( 'RECOVERY', $captured_mail['message'] );
	}

	/**
	 * Tests that send returns error when all recipients are invalid
	 *
	 * @return void
	 */
	public function test_send_returns_error_when_all_recipients_invalid() {
		$this->storage->set(
			'alert_settings',
			[
				'email' => [
					'enabled'    => true,
					'recipients' => 'not-an-email, also-not-valid, @invalid',
				],
			]
		);

		$channel = new EmailChannel( $this->storage );
		$result  = $channel->send( $this->create_test_payload() );

		$this->assertFalse( $result['success'] );
		$this->assertStringContainsString( 'No valid email recipients', $result['error'] );
	}

	/**
	 * Tests that send filters invalid emails with real is_email()
	 *
	 * @return void
	 */
	public function test_send_filters_invalid_recipients_with_real_is_email() {
		$this->storage->set(
			'alert_settings',
			[
				'email' => [
					'enabled'    => true,
					'recipients' => 'valid@example.com, not-an-email, another@test.org',
				],
			]
		);

		$captured_mail = null;
		$this->intercept_wp_mail( $captured_mail );

		$channel = new EmailChannel( $this->storage );
		$channel->send( $this->create_test_payload() );

		// Only valid emails must be included.
		$this->assertContains( 'valid@example.com', $captured_mail['to'] );
		$this->assertContains( 'another@test.org', $captured_mail['to'] );
		$this->assertNotContains( 'not-an-email', $captured_mail['to'] );
	}
}
