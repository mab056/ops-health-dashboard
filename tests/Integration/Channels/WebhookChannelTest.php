<?php
/**
 * Integration Test per WebhookChannel
 *
 * Verifica invio alert webhook con HMAC, Storage reale e HTTP interceptato.
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
 * Integration test per WebhookChannel con WordPress reale.
 */
class WebhookChannelTest extends WP_UnitTestCase {

	/**
	 * Storage reale
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
	 * Crea un payload di test
	 *
	 * @return array Payload alert.
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
	 * Crea HttpClient testabile
	 *
	 * @return TestableHttpClient
	 */
	private function create_http_client(): TestableHttpClient {
		return new TestableHttpClient( new Redaction(), '93.184.216.34' );
	}

	/**
	 * Testa che la classe NON è final
	 *
	 * @return void
	 */
	public function test_class_is_not_final() {
		$reflection = new \ReflectionClass( WebhookChannel::class );
		$this->assertFalse( $reflection->isFinal(), 'Class should NOT be final' );
	}

	/**
	 * Testa che NON esistono metodi static
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
	 * Testa che NON esistono proprietà static
	 *
	 * @return void
	 */
	public function test_no_static_properties() {
		$reflection = new \ReflectionClass( WebhookChannel::class );
		$properties = $reflection->getProperties( \ReflectionProperty::IS_STATIC );

		$this->assertEmpty( $properties, 'Class should have NO static properties' );
	}

	/**
	 * Testa get_id
	 *
	 * @return void
	 */
	public function test_get_id_returns_webhook() {
		$channel = new WebhookChannel( $this->storage, $this->create_http_client() );

		$this->assertEquals( 'webhook', $channel->get_id() );
	}

	/**
	 * Testa get_name
	 *
	 * @return void
	 */
	public function test_get_name_returns_translated_string() {
		$channel = new WebhookChannel( $this->storage, $this->create_http_client() );

		$this->assertEquals( 'Webhook', $channel->get_name() );
	}

	/**
	 * Testa is_enabled con URL configurato
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
	 * Testa is_enabled quando disabilitato
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
	 * Testa is_enabled senza URL
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
	 * Testa is_enabled con settings corrotti (non array)
	 *
	 * @return void
	 */
	public function test_is_enabled_false_with_corrupted_settings() {
		$this->storage->set( 'alert_settings', 'not-an-array' );

		$channel = new WebhookChannel( $this->storage, $this->create_http_client() );

		$this->assertFalse( $channel->is_enabled() );
	}

	/**
	 * Testa send con successo
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
	 * Testa che send include header HMAC quando secret è configurato
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

		// Verifica che la firma corrisponda.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
		$expected_signature = hash_hmac( 'sha256', json_encode( $payload ), $secret );
		$this->assertEquals( $expected_signature, $captured_headers['X-OpsHealth-Signature'] );
	}

	/**
	 * Testa send con errore HTTP
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
	 * Testa che send non include header signature senza secret
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
