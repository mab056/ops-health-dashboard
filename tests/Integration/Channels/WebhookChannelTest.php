<?php
/**
 * Integration Test for WebhookChannel
 *
 * Verifies webhook alert sending with HMAC, real Storage and intercepted HTTP.
 *
 * @package OpsHealthDashboard\Tests\Integration\Channels
 */

namespace OpsHealthDashboard\Tests\Integration\Channels;

use OpsHealthDashboard\Channels\WebhookChannel;
use OpsHealthDashboard\Services\Redaction;
use OpsHealthDashboard\Services\Storage;
use OpsHealthDashboard\Tests\Integration\Services\TestableHttpClient;
use WP_UnitTestCase;

/**
 * Class WebhookChannelTest
 *
 * Integration test for WebhookChannel with real WordPress.
 */
class WebhookChannelTest extends WP_UnitTestCase {

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
		remove_all_filters( 'pre_http_request' );
		parent::tearDown();
	}

	/**
	 * Creates a test payload
	 *
	 * @return array Alert payload.
	 */
	private function create_test_payload(): array {
		return [
			'check_id'        => 'database',
			'check_name'      => 'Database',
			'current_status'  => 'critical',
			'previous_status' => 'ok',
			'message'         => 'Connection failed',
			'site_name'       => 'Test Site',
			'site_url'        => 'https://example.com',
			'is_recovery'     => false,
			'timestamp'       => time(),
		];
	}

	/**
	 * Creates testable HttpClient
	 *
	 * @return TestableHttpClient
	 */
	private function create_http_client(): TestableHttpClient {
		return new TestableHttpClient( new Redaction(), '93.184.216.34' );
	}

	/**
	 * Verifies that the class is NOT final
	 *
	 * @return void
	 */
	public function test_class_is_not_final() {
		$reflection = new \ReflectionClass( WebhookChannel::class );
		$this->assertFalse( $reflection->isFinal(), 'Class should NOT be final' );
	}

	/**
	 * Verifies that NO static methods exist
	 *
	 * @return void
	 */
	public function test_no_static_methods() {
		$reflection = new \ReflectionClass( WebhookChannel::class );
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
		$reflection = new \ReflectionClass( WebhookChannel::class );
		$properties = $reflection->getProperties( \ReflectionProperty::IS_STATIC );

		$this->assertEmpty( $properties, 'Class should have NO static properties' );
	}

	/**
	 * Tests get_id
	 *
	 * @return void
	 */
	public function test_get_id_returns_webhook() {
		$channel = new WebhookChannel( $this->storage, $this->create_http_client() );

		$this->assertEquals( 'webhook', $channel->get_id() );
	}

	/**
	 * Tests get_name
	 *
	 * @return void
	 */
	public function test_get_name_returns_translated_string() {
		$channel = new WebhookChannel( $this->storage, $this->create_http_client() );

		$this->assertEquals( 'Webhook', $channel->get_name() );
	}

	/**
	 * Tests is_enabled with configured URL
	 *
	 * @return void
	 */
	public function test_is_enabled_true_with_url() {
		$this->storage->set(
			'alert_settings',
			[
				'webhook' => [
					'enabled' => true,
					'url'     => 'https://hooks.example.com/webhook',
				],
			]
		);

		$channel = new WebhookChannel( $this->storage, $this->create_http_client() );

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
				'webhook' => [
					'enabled' => false,
					'url'     => 'https://hooks.example.com/webhook',
				],
			]
		);

		$channel = new WebhookChannel( $this->storage, $this->create_http_client() );

		$this->assertFalse( $channel->is_enabled() );
	}

	/**
	 * Tests is_enabled without URL
	 *
	 * @return void
	 */
	public function test_is_enabled_false_without_url() {
		$this->storage->set(
			'alert_settings',
			[
				'webhook' => [
					'enabled' => true,
					'url'     => '',
				],
			]
		);

		$channel = new WebhookChannel( $this->storage, $this->create_http_client() );

		$this->assertFalse( $channel->is_enabled() );
	}

	/**
	 * Tests is_enabled with corrupted settings (non-array)
	 *
	 * @return void
	 */
	public function test_is_enabled_false_with_corrupted_settings() {
		$this->storage->set( 'alert_settings', 'not-an-array' );

		$channel = new WebhookChannel( $this->storage, $this->create_http_client() );

		$this->assertFalse( $channel->is_enabled() );
	}

	/**
	 * Tests send success
	 *
	 * @return void
	 */
	public function test_send_success_with_intercepted_http() {
		$this->storage->set(
			'alert_settings',
			[
				'webhook' => [
					'enabled' => true,
					'url'     => 'https://hooks.example.com/webhook',
				],
			]
		);

		add_filter(
			'pre_http_request',
			function () {
				return [
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
					'body'     => '{"ok":true}',
				];
			}
		);

		$channel = new WebhookChannel( $this->storage, $this->create_http_client() );
		$result  = $channel->send( $this->create_test_payload() );

		$this->assertTrue( $result['success'] );
		$this->assertNull( $result['error'] );
	}

	/**
	 * Tests that send includes HMAC header when secret is configured
	 *
	 * @return void
	 */
	public function test_send_includes_hmac_signature_when_secret_set() {
		$secret = 'my-webhook-secret';

		$this->storage->set(
			'alert_settings',
			[
				'webhook' => [
					'enabled' => true,
					'url'     => 'https://hooks.example.com/webhook',
					'secret'  => $secret,
				],
			]
		);

		$captured_headers = null;

		add_filter(
			'pre_http_request',
			function ( $preempt, $args ) use ( &$captured_headers ) {
				$captured_headers = $args['headers'];
				return [
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
					'body'     => 'ok',
				];
			},
			10,
			2
		);

		$payload = $this->create_test_payload();
		$channel = new WebhookChannel( $this->storage, $this->create_http_client() );
		$channel->send( $payload );

		$this->assertArrayHasKey( 'X-OpsHealth-Signature', $captured_headers );

		// Verify that the signature matches.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
		$expected_signature = hash_hmac( 'sha256', json_encode( $payload ), $secret );
		$this->assertEquals( $expected_signature, $captured_headers['X-OpsHealth-Signature'] );
	}

	/**
	 * Tests send with HTTP error
	 *
	 * @return void
	 */
	public function test_send_failure_returns_error() {
		$this->storage->set(
			'alert_settings',
			[
				'webhook' => [
					'enabled' => true,
					'url'     => 'https://hooks.example.com/webhook',
				],
			]
		);

		add_filter(
			'pre_http_request',
			function () {
				return new \WP_Error( 'http_request_failed', 'Timeout' );
			}
		);

		$channel = new WebhookChannel( $this->storage, $this->create_http_client() );
		$result  = $channel->send( $this->create_test_payload() );

		$this->assertFalse( $result['success'] );
		$this->assertNotNull( $result['error'] );
	}

	/**
	 * Tests that send does not include signature header without secret
	 *
	 * @return void
	 */
	public function test_send_no_signature_when_no_secret() {
		$this->storage->set(
			'alert_settings',
			[
				'webhook' => [
					'enabled' => true,
					'url'     => 'https://hooks.example.com/webhook',
					'secret'  => '',
				],
			]
		);

		$captured_headers = null;

		add_filter(
			'pre_http_request',
			function ( $preempt, $args ) use ( &$captured_headers ) {
				$captured_headers = $args['headers'];
				return [
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
					'body'     => 'ok',
				];
			},
			10,
			2
		);

		$channel = new WebhookChannel( $this->storage, $this->create_http_client() );
		$channel->send( $this->create_test_payload() );

		$this->assertArrayNotHasKey( 'X-OpsHealth-Signature', $captured_headers );
	}
}
