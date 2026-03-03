<?php
/**
 * Integration Test for TelegramChannel
 *
 * Verifies Telegram alert sending with real Storage and intercepted HTTP.
 *
 * @package OpsHealthDashboard\Tests\Integration\Channels
 */

namespace OpsHealthDashboard\Tests\Integration\Channels;

use OpsHealthDashboard\Channels\TelegramChannel;
use OpsHealthDashboard\Services\Redaction;
use OpsHealthDashboard\Services\Storage;
use OpsHealthDashboard\Tests\Integration\Services\TestableHttpClient;
use WP_UnitTestCase;

/**
 * Class TelegramChannelTest
 *
 * Integration test for TelegramChannel with real WordPress.
 */
class TelegramChannelTest extends WP_UnitTestCase {

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
				'timestamp'       => time(),
			],
			$overrides
		);
	}

	/**
	 * Creates testable HttpClient with public IP
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
		$reflection = new \ReflectionClass( TelegramChannel::class );
		$this->assertFalse( $reflection->isFinal(), 'Class should NOT be final' );
	}

	/**
	 * Verifies that NO static methods exist
	 *
	 * @return void
	 */
	public function test_no_static_methods() {
		$reflection = new \ReflectionClass( TelegramChannel::class );
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
		$reflection = new \ReflectionClass( TelegramChannel::class );
		$properties = $reflection->getProperties( \ReflectionProperty::IS_STATIC );

		$this->assertEmpty( $properties, 'Class should have NO static properties' );
	}

	/**
	 * Tests get_id
	 *
	 * @return void
	 */
	public function test_get_id_returns_telegram() {
		$channel = new TelegramChannel( $this->storage, $this->create_http_client() );

		$this->assertEquals( 'telegram', $channel->get_id() );
	}

	/**
	 * Tests get_name
	 *
	 * @return void
	 */
	public function test_get_name_returns_translated_string() {
		$channel = new TelegramChannel( $this->storage, $this->create_http_client() );

		$this->assertEquals( 'Telegram', $channel->get_name() );
	}

	/**
	 * Tests is_enabled with bot_token and chat_id
	 *
	 * @return void
	 */
	public function test_is_enabled_true_with_bot_token_and_chat_id() {
		$this->storage->set(
			'alert_settings',
			[
				'telegram' => [
					'enabled'   => true,
					'bot_token' => '123456:ABC-DEF',
					'chat_id'   => '-100123456',
				],
			]
		);

		$channel = new TelegramChannel( $this->storage, $this->create_http_client() );

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
				'telegram' => [
					'enabled'   => false,
					'bot_token' => '123456:ABC',
					'chat_id'   => '-100',
				],
			]
		);

		$channel = new TelegramChannel( $this->storage, $this->create_http_client() );

		$this->assertFalse( $channel->is_enabled() );
	}

	/**
	 * Tests is_enabled without bot_token
	 *
	 * @return void
	 */
	public function test_is_enabled_false_without_bot_token() {
		$this->storage->set(
			'alert_settings',
			[
				'telegram' => [
					'enabled'   => true,
					'bot_token' => '',
					'chat_id'   => '-100',
				],
			]
		);

		$channel = new TelegramChannel( $this->storage, $this->create_http_client() );

		$this->assertFalse( $channel->is_enabled() );
	}

	/**
	 * Tests is_enabled without chat_id
	 *
	 * @return void
	 */
	public function test_is_enabled_false_without_chat_id() {
		$this->storage->set(
			'alert_settings',
			[
				'telegram' => [
					'enabled'   => true,
					'bot_token' => '123456:ABC',
					'chat_id'   => '',
				],
			]
		);

		$channel = new TelegramChannel( $this->storage, $this->create_http_client() );

		$this->assertFalse( $channel->is_enabled() );
	}

	/**
	 * Tests is_enabled with corrupted settings (non-array)
	 *
	 * @return void
	 */
	public function test_is_enabled_false_with_corrupted_settings() {
		$this->storage->set( 'alert_settings', 'not-an-array' );

		$channel = new TelegramChannel( $this->storage, $this->create_http_client() );

		$this->assertFalse( $channel->is_enabled() );
	}

	/**
	 * Tests send with correct Telegram API URL
	 *
	 * @return void
	 */
	public function test_send_success_posts_to_correct_api_url() {
		$this->storage->set(
			'alert_settings',
			[
				'telegram' => [
					'enabled'   => true,
					'bot_token' => '123456:ABC-DEF',
					'chat_id'   => '-100123456',
				],
			]
		);

		$captured_url = null;

		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) use ( &$captured_url ) {
				$captured_url = $url;
				return [
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
					'body'     => '{"ok":true}',
				];
			},
			10,
			3
		);

		$channel = new TelegramChannel( $this->storage, $this->create_http_client() );
		$result  = $channel->send( $this->create_test_payload() );

		$this->assertTrue( $result['success'] );
		$this->assertStringContainsString( '/bot123456:ABC-DEF/sendMessage', $captured_url );
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
				'telegram' => [
					'enabled'   => true,
					'bot_token' => '123456:ABC',
					'chat_id'   => '-100',
				],
			]
		);

		add_filter(
			'pre_http_request',
			function () {
				return new \WP_Error( 'http_request_failed', 'Timeout' );
			}
		);

		$channel = new TelegramChannel( $this->storage, $this->create_http_client() );
		$result  = $channel->send( $this->create_test_payload() );

		$this->assertFalse( $result['success'] );
		$this->assertNotNull( $result['error'] );
	}

	/**
	 * Tests that the body contains HTML parse_mode and chat_id
	 *
	 * @return void
	 */
	public function test_send_body_contains_html_parse_mode() {
		$this->storage->set(
			'alert_settings',
			[
				'telegram' => [
					'enabled'   => true,
					'bot_token' => '123456:ABC',
					'chat_id'   => '-100999',
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
					'body'     => '{"ok":true}',
				];
			},
			10,
			2
		);

		$channel = new TelegramChannel( $this->storage, $this->create_http_client() );
		$channel->send( $this->create_test_payload() );

		$this->assertEquals( 'HTML', $captured_body['parse_mode'] );
		$this->assertEquals( '-100999', $captured_body['chat_id'] );
		$this->assertStringContainsString( '<b>', $captured_body['text'] );
	}
}
