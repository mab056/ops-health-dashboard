<?php
/**
 * Integration Test per TelegramChannel
 *
 * Verifica invio alert Telegram con Storage reale e HTTP interceptato.
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
 * Integration test per TelegramChannel con WordPress reale.
 */
class TelegramChannelTest extends WP_UnitTestCase {

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
		$reflection = new \ReflectionClass( TelegramChannel::class );
		$this->assertFalse( $reflection->isFinal(), 'Class should NOT be final' );
	}

	/**
	 * Testa che NON esistono metodi static
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
	 * Testa che NON esistono proprietà static
	 *
	 * @return void
	 */
	public function test_no_static_properties() {
		$reflection = new \ReflectionClass( TelegramChannel::class );
		$properties = $reflection->getProperties( \ReflectionProperty::IS_STATIC );

		$this->assertEmpty( $properties, 'Class should have NO static properties' );
	}

	/**
	 * Testa get_id
	 *
	 * @return void
	 */
	public function test_get_id_returns_telegram() {
		$channel = new TelegramChannel( $this->storage, $this->create_http_client() );

		$this->assertEquals( 'telegram', $channel->get_id() );
	}

	/**
	 * Testa get_name
	 *
	 * @return void
	 */
	public function test_get_name_returns_translated_string() {
		$channel = new TelegramChannel( $this->storage, $this->create_http_client() );

		$this->assertEquals( 'Telegram', $channel->get_name() );
	}

	/**
	 * Testa is_enabled con bot_token e chat_id
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
	 * Testa is_enabled quando disabilitato
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
	 * Testa is_enabled senza bot_token
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
	 * Testa is_enabled senza chat_id
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
	 * Testa is_enabled con settings corrotti (non array)
	 *
	 * @return void
	 */
	public function test_is_enabled_false_with_corrupted_settings() {
		$this->storage->set( 'alert_settings', 'not-an-array' );

		$channel = new TelegramChannel( $this->storage, $this->create_http_client() );

		$this->assertFalse( $channel->is_enabled() );
	}

	/**
	 * Testa send con URL API Telegram corretto
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
	 * Testa send con errore HTTP
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
	 * Testa che il body contiene parse_mode HTML e chat_id
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
