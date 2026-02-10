<?php
/**
 * Integration Test per SlackChannel
 *
 * Verifica invio alert Slack con Storage reale e HTTP interceptato.
 *
 * @package OpsHealthDashboard\Tests\Integration\Channels
 */

namespace OpsHealthDashboard\Tests\Integration\Channels;

use OpsHealthDashboard\Channels\SlackChannel;
use OpsHealthDashboard\Services\Redaction;
use OpsHealthDashboard\Services\Storage;
use OpsHealthDashboard\Tests\Integration\Services\TestableHttpClient;
use WP_UnitTestCase;

/**
 * Class SlackChannelTest
 *
 * Integration test per SlackChannel con WordPress reale.
 */
class SlackChannelTest extends WP_UnitTestCase {

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
	 * @param array $overrides Override dei campi.
	 * @return array Payload alert.
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
				'timestamp'       => time(),
			],
			$overrides
		);
	}

	/**
	 * Crea HttpClient testabile con IP pubblico
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
		$reflection = new \ReflectionClass( SlackChannel::class );
		$this->assertFalse( $reflection->isFinal(), 'Class should NOT be final' );
	}

	/**
	 * Testa che NON esistono metodi static
	 *
	 * @return void
	 */
	public function test_no_static_methods() {
		$reflection = new \ReflectionClass( SlackChannel::class );
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
		$reflection = new \ReflectionClass( SlackChannel::class );
		$properties = $reflection->getProperties( \ReflectionProperty::IS_STATIC );

		$this->assertEmpty( $properties, 'Class should have NO static properties' );
	}

	/**
	 * Testa get_id
	 *
	 * @return void
	 */
	public function test_get_id_returns_slack() {
		$channel = new SlackChannel( $this->storage, $this->create_http_client() );

		$this->assertEquals( 'slack', $channel->get_id() );
	}

	/**
	 * Testa get_name
	 *
	 * @return void
	 */
	public function test_get_name_returns_translated_string() {
		$channel = new SlackChannel( $this->storage, $this->create_http_client() );

		$this->assertEquals( 'Slack', $channel->get_name() );
	}

	/**
	 * Testa is_enabled con impostazioni valide
	 *
	 * @return void
	 */
	public function test_is_enabled_true_with_valid_settings() {
		$this->storage->set(
			'alert_settings',
			[
				'slack' => [
					'enabled'     => true,
					'webhook_url' => 'https://hooks.slack.com/services/T00/B00/xxx',
				],
			]
		);

		$channel = new SlackChannel( $this->storage, $this->create_http_client() );

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
				'slack' => [
					'enabled'     => false,
					'webhook_url' => 'https://hooks.slack.com/services/T00/B00/xxx',
				],
			]
		);

		$channel = new SlackChannel( $this->storage, $this->create_http_client() );

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
				'slack' => [
					'enabled'     => true,
					'webhook_url' => '',
				],
			]
		);

		$channel = new SlackChannel( $this->storage, $this->create_http_client() );

		$this->assertFalse( $channel->is_enabled() );
	}

	/**
	 * Testa is_enabled senza impostazioni
	 *
	 * @return void
	 */
	public function test_is_enabled_false_without_settings() {
		$channel = new SlackChannel( $this->storage, $this->create_http_client() );

		$this->assertFalse( $channel->is_enabled() );
	}

	/**
	 * Testa send con successo e payload Block Kit
	 *
	 * @return void
	 */
	public function test_send_success_with_intercepted_http() {
		$this->storage->set(
			'alert_settings',
			[
				'slack' => [
					'enabled'     => true,
					'webhook_url' => 'https://hooks.slack.com/services/T00/B00/xxx',
				],
			]
		);

		$captured_args = null;

		add_filter(
			'pre_http_request',
			function ( $preempt, $args ) use ( &$captured_args ) {
				$captured_args = $args;
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

		$channel = new SlackChannel( $this->storage, $this->create_http_client() );
		$result  = $channel->send( $this->create_test_payload() );

		$this->assertTrue( $result['success'] );
		$this->assertNull( $result['error'] );

		// Verifica struttura Block Kit nel body inviato.
		$body = json_decode( $captured_args['body'], true );
		$this->assertArrayHasKey( 'blocks', $body );
		$this->assertArrayHasKey( 'attachments', $body );
		$this->assertEquals( '#FF0000', $body['attachments'][0]['color'] );
	}

	/**
	 * Testa is_enabled con settings corrotti (non array)
	 *
	 * @return void
	 */
	public function test_is_enabled_false_with_corrupted_settings() {
		$this->storage->set( 'alert_settings', 'not-an-array' );

		$channel = new SlackChannel( $this->storage, $this->create_http_client() );

		$this->assertFalse( $channel->is_enabled() );
	}

	/**
	 * Testa send con recovery title
	 *
	 * @return void
	 */
	public function test_send_formats_recovery_title() {
		$this->storage->set(
			'alert_settings',
			[
				'slack' => [
					'enabled'     => true,
					'webhook_url' => 'https://hooks.slack.com/services/T00/B00/xxx',
				],
			]
		);

		$captured_body = null;

		add_filter(
			'pre_http_request',
			function ( $preempt, $args ) use ( &$captured_body ) {
				$captured_body = json_decode( $args['body'], true );
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

		$channel = new SlackChannel( $this->storage, $this->create_http_client() );
		$channel->send(
			$this->create_test_payload(
				[
					'is_recovery'    => true,
					'current_status' => 'ok',
				]
			)
		);

		// Verifica che il titolo contiene [Recovered].
		$header_text = $captured_body['blocks'][0]['text']['text'];
		$this->assertStringContainsString( 'Recovered', $header_text );
	}

	/**
	 * Testa send con status sconosciuto usa colore fallback
	 *
	 * @return void
	 */
	public function test_send_uses_fallback_color_for_unknown_status() {
		$this->storage->set(
			'alert_settings',
			[
				'slack' => [
					'enabled'     => true,
					'webhook_url' => 'https://hooks.slack.com/services/T00/B00/xxx',
				],
			]
		);

		$captured_body = null;

		add_filter(
			'pre_http_request',
			function ( $preempt, $args ) use ( &$captured_body ) {
				$captured_body = json_decode( $args['body'], true );
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

		$channel = new SlackChannel( $this->storage, $this->create_http_client() );
		$channel->send( $this->create_test_payload( [ 'current_status' => 'unknown' ] ) );

		$this->assertEquals( '#808080', $captured_body['attachments'][0]['color'] );
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
				'slack' => [
					'enabled'     => true,
					'webhook_url' => 'https://hooks.slack.com/services/T00/B00/xxx',
				],
			]
		);

		add_filter(
			'pre_http_request',
			function () {
				return new \WP_Error( 'http_request_failed', 'Connection refused' );
			}
		);

		$channel = new SlackChannel( $this->storage, $this->create_http_client() );
		$result  = $channel->send( $this->create_test_payload() );

		$this->assertFalse( $result['success'] );
		$this->assertNotNull( $result['error'] );
	}

	/**
	 * Testa che send usa il colore corretto per ogni status
	 *
	 * @return void
	 */
	public function test_send_formats_color_by_status() {
		$this->storage->set(
			'alert_settings',
			[
				'slack' => [
					'enabled'     => true,
					'webhook_url' => 'https://hooks.slack.com/services/T00/B00/xxx',
				],
			]
		);

		$captured_body = null;

		add_filter(
			'pre_http_request',
			function ( $preempt, $args ) use ( &$captured_body ) {
				$captured_body = json_decode( $args['body'], true );
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

		$channel = new SlackChannel( $this->storage, $this->create_http_client() );

		// Test warning color.
		$channel->send( $this->create_test_payload( [ 'current_status' => 'warning' ] ) );
		$this->assertEquals( '#FFA500', $captured_body['attachments'][0]['color'] );

		// Test ok color.
		$channel->send( $this->create_test_payload( [ 'current_status' => 'ok' ] ) );
		$this->assertEquals( '#36A64F', $captured_body['attachments'][0]['color'] );
	}
}
