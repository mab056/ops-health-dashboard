<?php
/**
 * Integration Test per WhatsAppChannel
 *
 * Verifica invio alert WhatsApp con validazione E.164,
 * Storage reale e HTTP interceptato.
 *
 * @package OpsHealthDashboard\Tests\Integration\Channels
 */

namespace OpsHealthDashboard\Tests\Integration\Channels;

use OpsHealthDashboard\Channels\WhatsAppChannel;
use OpsHealthDashboard\Services\Redaction;
use OpsHealthDashboard\Services\Storage;
use OpsHealthDashboard\Tests\Integration\Services\TestableHttpClient;
use WP_UnitTestCase;

/**
 * Class WhatsAppChannelTest
 *
 * Integration test per WhatsAppChannel con WordPress reale.
 */
class WhatsAppChannelTest extends WP_UnitTestCase {

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
		$reflection = new \ReflectionClass( WhatsAppChannel::class );
		$this->assertFalse( $reflection->isFinal(), 'Class should NOT be final' );
	}

	/**
	 * Testa che NON esistono metodi static
	 *
	 * @return void
	 */
	public function test_no_static_methods() {
		$reflection = new \ReflectionClass( WhatsAppChannel::class );
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
		$reflection = new \ReflectionClass( WhatsAppChannel::class );
		$properties = $reflection->getProperties( \ReflectionProperty::IS_STATIC );

		$this->assertEmpty( $properties, 'Class should have NO static properties' );
	}

	/**
	 * Testa get_id
	 *
	 * @return void
	 */
	public function test_get_id_returns_whatsapp() {
		$channel = new WhatsAppChannel( $this->storage, $this->create_http_client() );

		$this->assertEquals( 'whatsapp', $channel->get_id() );
	}

	/**
	 * Testa get_name
	 *
	 * @return void
	 */
	public function test_get_name_returns_translated_string() {
		$channel = new WhatsAppChannel( $this->storage, $this->create_http_client() );

		$this->assertEquals( 'WhatsApp', $channel->get_name() );
	}

	/**
	 * Testa is_enabled con telefono E.164 valido e URL
	 *
	 * @return void
	 */
	public function test_is_enabled_true_with_valid_phone_and_url() {
		$this->storage->set(
			'alert_settings',
			[
				'whatsapp' => [
					'enabled'      => true,
					'webhook_url'  => 'https://api.twilio.com/whatsapp',
					'phone_number' => '+391234567890',
				],
			]
		);

		$channel = new WhatsAppChannel( $this->storage, $this->create_http_client() );

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
				'whatsapp' => [
					'enabled'      => false,
					'webhook_url'  => 'https://api.twilio.com/whatsapp',
					'phone_number' => '+391234567890',
				],
			]
		);

		$channel = new WhatsAppChannel( $this->storage, $this->create_http_client() );

		$this->assertFalse( $channel->is_enabled() );
	}

	/**
	 * Testa is_enabled senza webhook URL
	 *
	 * @return void
	 */
	public function test_is_enabled_false_without_webhook_url() {
		$this->storage->set(
			'alert_settings',
			[
				'whatsapp' => [
					'enabled'      => true,
					'webhook_url'  => '',
					'phone_number' => '+391234567890',
				],
			]
		);

		$channel = new WhatsAppChannel( $this->storage, $this->create_http_client() );

		$this->assertFalse( $channel->is_enabled() );
	}

	/**
	 * Testa is_enabled con telefono non valido (non E.164)
	 *
	 * @return void
	 */
	public function test_is_enabled_false_with_invalid_phone() {
		$this->storage->set(
			'alert_settings',
			[
				'whatsapp' => [
					'enabled'      => true,
					'webhook_url'  => 'https://api.twilio.com/whatsapp',
					'phone_number' => '1234567890',
				],
			]
		);

		$channel = new WhatsAppChannel( $this->storage, $this->create_http_client() );

		$this->assertFalse( $channel->is_enabled() );
	}

	/**
	 * Testa is_enabled senza telefono
	 *
	 * @return void
	 */
	public function test_is_enabled_false_without_phone() {
		$this->storage->set(
			'alert_settings',
			[
				'whatsapp' => [
					'enabled'      => true,
					'webhook_url'  => 'https://api.twilio.com/whatsapp',
					'phone_number' => '',
				],
			]
		);

		$channel = new WhatsAppChannel( $this->storage, $this->create_http_client() );

		$this->assertFalse( $channel->is_enabled() );
	}

	/**
	 * Testa is_enabled con settings corrotti (non array)
	 *
	 * @return void
	 */
	public function test_is_enabled_false_with_corrupted_settings() {
		$this->storage->set( 'alert_settings', 'not-an-array' );

		$channel = new WhatsAppChannel( $this->storage, $this->create_http_client() );

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
				'whatsapp' => [
					'enabled'      => true,
					'webhook_url'  => 'https://api.twilio.com/whatsapp',
					'phone_number' => '+391234567890',
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

		$channel = new WhatsAppChannel( $this->storage, $this->create_http_client() );
		$result  = $channel->send( $this->create_test_payload() );

		$this->assertTrue( $result['success'] );
		$this->assertNull( $result['error'] );
	}

	/**
	 * Testa che send include header Bearer token
	 *
	 * @return void
	 */
	public function test_send_includes_bearer_token_header() {
		$this->storage->set(
			'alert_settings',
			[
				'whatsapp' => [
					'enabled'      => true,
					'webhook_url'  => 'https://api.twilio.com/whatsapp',
					'phone_number' => '+391234567890',
					'api_token'    => 'my-api-token',
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

		$channel = new WhatsAppChannel( $this->storage, $this->create_http_client() );
		$channel->send( $this->create_test_payload() );

		$this->assertArrayHasKey( 'Authorization', $captured_headers );
		$this->assertEquals( 'Bearer my-api-token', $captured_headers['Authorization'] );
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
				'whatsapp' => [
					'enabled'      => true,
					'webhook_url'  => 'https://api.twilio.com/whatsapp',
					'phone_number' => '+391234567890',
				],
			]
		);

		add_filter(
			'pre_http_request',
			function () {
				return new \WP_Error( 'http_request_failed', 'Timeout' );
			}
		);

		$channel = new WhatsAppChannel( $this->storage, $this->create_http_client() );
		$result  = $channel->send( $this->create_test_payload() );

		$this->assertFalse( $result['success'] );
		$this->assertNotNull( $result['error'] );
	}
}
