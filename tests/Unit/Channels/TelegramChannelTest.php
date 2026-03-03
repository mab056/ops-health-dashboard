<?php
/**
 * Test for TelegramChannel
 *
 * Verifies sending alerts via Telegram Bot API with HTML messages.
 *
 * @package OpsHealthDashboard\Tests\Unit\Channels
 */

namespace OpsHealthDashboard\Tests\Unit\Channels;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use OpsHealthDashboard\Channels\TelegramChannel;
use OpsHealthDashboard\Interfaces\AlertChannelInterface;
use OpsHealthDashboard\Interfaces\HttpClientInterface;
use OpsHealthDashboard\Interfaces\StorageInterface;
use PHPUnit\Framework\TestCase;

/**
 * Class TelegramChannelTest
 *
 * Unit tests for the Telegram alert channel.
 */
class TelegramChannelTest extends TestCase
{

	use MockeryPHPUnitIntegration;

	/**
	 * Setup Brain\Monkey
	 *
	 * @return void
	 */
	protected function setUp(): void
	{
		parent::setUp();
		Monkey\setUp();
	}

	/**
	 * Teardown Brain\Monkey
	 *
	 * @return void
	 */
	protected function tearDown(): void
	{
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Creates a mock of StorageInterface
	 *
	 * @param array $settings Settings to return.
	 * @return \Mockery\MockInterface|StorageInterface
	 */
	private function create_storage_mock( array $settings = [] )
	{
		$storage = Mockery::mock( StorageInterface::class );
		$storage->shouldReceive( 'get' )
			->with( 'alert_settings', Mockery::any() )
			->andReturn( $settings );
		return $storage;
	}

	/**
	 * Creates a mock of HttpClientInterface
	 *
	 * @return \Mockery\MockInterface|HttpClientInterface
	 */
	private function create_http_client_mock()
	{
		return Mockery::mock( HttpClientInterface::class );
	}

	/**
	 * Creates a test payload
	 *
	 * @param array $overrides Keys to override.
	 * @return array
	 */
	private function create_test_payload( array $overrides = [] )
	{
		return array_merge(
			[
				'check_id'        => 'database',
				'check_name'      => 'Database Connection',
				'previous_status' => 'ok',
				'current_status'  => 'critical',
				'message'         => 'Connection timeout',
				'details'         => [],
				'timestamp'       => 1707579045,
				'site_url'        => 'https://example.com',
				'site_name'       => 'Test Site',
				'is_recovery'     => false,
			],
			$overrides
		);
	}

	/**
	 * Configures i18n mocks
	 *
	 * @return void
	 */
	private function mock_i18n()
	{
		Functions\when( '__' )->returnArg( 1 );
		Functions\when( 'esc_html__' )->returnArg( 1 );
	}

	// ---------------------------------------------------
	// Pattern enforcement
	// ---------------------------------------------------

	/**
	 * Tests that the class is NOT final
	 *
	 * @return void
	 */
	public function test_class_is_not_final()
	{
		$reflection = new \ReflectionClass( TelegramChannel::class );
		$this->assertFalse( $reflection->isFinal(), 'TelegramChannel should NOT be final' );
	}

	/**
	 * Tests that there are NO static methods
	 *
	 * @return void
	 */
	public function test_no_static_methods()
	{
		$reflection = new \ReflectionClass( TelegramChannel::class );
		$methods    = $reflection->getMethods( \ReflectionMethod::IS_STATIC );

		$static_methods = array_filter(
			$methods,
			function ( $method ) {
				return strpos( $method->getName(), '__' ) !== 0;
			}
		);

		$this->assertEmpty( $static_methods, 'TelegramChannel should have NO static methods' );
	}

	/**
	 * Tests that there are NO static properties
	 *
	 * @return void
	 */
	public function test_no_static_properties()
	{
		$reflection = new \ReflectionClass( TelegramChannel::class );
		$properties = $reflection->getProperties( \ReflectionProperty::IS_STATIC );

		$this->assertEmpty( $properties, 'TelegramChannel should have NO static properties' );
	}

	/**
	 * Tests that implements AlertChannelInterface
	 *
	 * @return void
	 */
	public function test_implements_interface()
	{
		$channel = new TelegramChannel(
			$this->create_storage_mock(),
			$this->create_http_client_mock()
		);
		$this->assertInstanceOf( AlertChannelInterface::class, $channel );
	}

	// ---------------------------------------------------
	// get_id() / get_name()
	// ---------------------------------------------------

	/**
	 * Tests that get_id returns 'telegram'
	 *
	 * @return void
	 */
	public function test_get_id_returns_telegram()
	{
		$channel = new TelegramChannel(
			$this->create_storage_mock(),
			$this->create_http_client_mock()
		);
		$this->assertEquals( 'telegram', $channel->get_id() );
	}

	/**
	 * Tests that get_name returns translated string
	 *
	 * @return void
	 */
	public function test_get_name_returns_translated_string()
	{
		$this->mock_i18n();
		$channel = new TelegramChannel(
			$this->create_storage_mock(),
			$this->create_http_client_mock()
		);
		$this->assertEquals( 'Telegram', $channel->get_name() );
	}

	// ---------------------------------------------------
	// is_enabled()
	// ---------------------------------------------------

	/**
	 * Tests is_enabled returns true with complete configuration
	 *
	 * @return void
	 */
	public function test_is_enabled_returns_true_when_configured()
	{
		$settings = [
			'telegram' => [
				'enabled'   => true,
				'bot_token' => '123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11',
				'chat_id'   => '-1001234567890',
			],
		];
		$channel  = new TelegramChannel(
			$this->create_storage_mock( $settings ),
			$this->create_http_client_mock()
		);
		$this->assertTrue( $channel->is_enabled() );
	}

	/**
	 * Tests is_enabled returns false when disabled
	 *
	 * @return void
	 */
	public function test_is_enabled_returns_false_when_disabled()
	{
		$settings = [
			'telegram' => [
				'enabled'   => false,
				'bot_token' => '123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11',
				'chat_id'   => '-1001234567890',
			],
		];
		$channel  = new TelegramChannel(
			$this->create_storage_mock( $settings ),
			$this->create_http_client_mock()
		);
		$this->assertFalse( $channel->is_enabled() );
	}

	/**
	 * Tests is_enabled returns false without bot_token
	 *
	 * @return void
	 */
	public function test_is_enabled_returns_false_without_bot_token()
	{
		$settings = [
			'telegram' => [
				'enabled' => true,
				'chat_id' => '-1001234567890',
			],
		];
		$channel  = new TelegramChannel(
			$this->create_storage_mock( $settings ),
			$this->create_http_client_mock()
		);
		$this->assertFalse( $channel->is_enabled() );
	}

	/**
	 * Tests is_enabled returns false without chat_id
	 *
	 * @return void
	 */
	public function test_is_enabled_returns_false_without_chat_id()
	{
		$settings = [
			'telegram' => [
				'enabled'   => true,
				'bot_token' => '123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11',
			],
		];
		$channel  = new TelegramChannel(
			$this->create_storage_mock( $settings ),
			$this->create_http_client_mock()
		);
		$this->assertFalse( $channel->is_enabled() );
	}

	/**
	 * Tests is_enabled returns false without settings
	 *
	 * @return void
	 */
	public function test_is_enabled_returns_false_without_settings()
	{
		$channel = new TelegramChannel(
			$this->create_storage_mock( [] ),
			$this->create_http_client_mock()
		);
		$this->assertFalse( $channel->is_enabled() );
	}

	/**
	 * Tests is_enabled returns false without telegram key
	 *
	 * @return void
	 */
	public function test_is_enabled_returns_false_without_telegram_key()
	{
		$settings = [ 'email' => [ 'enabled' => true ] ];
		$channel  = new TelegramChannel(
			$this->create_storage_mock( $settings ),
			$this->create_http_client_mock()
		);
		$this->assertFalse( $channel->is_enabled() );
	}

	// ---------------------------------------------------
	// send()
	// ---------------------------------------------------

	/**
	 * Tests Telegram send with success and correct API URL
	 *
	 * @return void
	 */
	public function test_send_success_with_correct_api_url()
	{
		$bot_token    = '123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11';
		$chat_id      = '-1001234567890';
		$expected_url = 'https://api.telegram.org/bot' . $bot_token . '/sendMessage';

		$settings = [
			'telegram' => [
				'enabled'   => true,
				'bot_token' => $bot_token,
				'chat_id'   => $chat_id,
			],
		];

		$http_client = $this->create_http_client_mock();
		$http_client->shouldReceive( 'post' )
			->once()
			->with(
				$expected_url,
				Mockery::on(
					function ( $body ) use ( $chat_id ) {
						return isset( $body['chat_id'] )
							&& $body['chat_id'] === $chat_id
							&& isset( $body['parse_mode'] )
							&& 'HTML' === $body['parse_mode']
							&& isset( $body['text'] )
							&& is_string( $body['text'] );
					}
				)
			)
			->andReturn(
				[
					'success' => true,
					'code'    => 200,
					'body'    => '{"ok":true}',
					'error'   => null,
				]
			);

		$channel = new TelegramChannel(
			$this->create_storage_mock( $settings ),
			$http_client
		);
		$payload = $this->create_test_payload();
		$result  = $channel->send( $payload );

		$this->assertTrue( $result['success'] );
		$this->assertNull( $result['error'] );
	}

	/**
	 * Tests Telegram send with failure
	 *
	 * @return void
	 */
	public function test_send_failure()
	{
		$settings = [
			'telegram' => [
				'enabled'   => true,
				'bot_token' => '123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11',
				'chat_id'   => '-1001234567890',
			],
		];

		$http_client = $this->create_http_client_mock();
		$http_client->shouldReceive( 'post' )
			->once()
			->andReturn(
				[
					'success' => false,
					'code'    => 0,
					'body'    => '',
					'error'   => 'Unauthorized: bot token invalid',
				]
			);

		$channel = new TelegramChannel(
			$this->create_storage_mock( $settings ),
			$http_client
		);
		$payload = $this->create_test_payload();
		$result  = $channel->send( $payload );

		$this->assertFalse( $result['success'] );
		$this->assertEquals( 'Unauthorized: bot token invalid', $result['error'] );
	}

	/**
	 * Tests that the Telegram message contains formatted HTML
	 *
	 * @return void
	 */
	public function test_send_formats_html_message()
	{
		$settings = [
			'telegram' => [
				'enabled'   => true,
				'bot_token' => '123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11',
				'chat_id'   => '-1001234567890',
			],
		];

		$captured_body = null;

		$http_client = $this->create_http_client_mock();
		$http_client->shouldReceive( 'post' )
			->once()
			->with(
				Mockery::any(),
				Mockery::on(
					function ( $body ) use ( &$captured_body ) {
						$captured_body = $body;
						return true;
					}
				)
			)
			->andReturn(
				[
					'success' => true,
					'code'    => 200,
					'body'    => '{"ok":true}',
					'error'   => null,
				]
			);

		$channel = new TelegramChannel(
			$this->create_storage_mock( $settings ),
			$http_client
		);
		$payload = $this->create_test_payload();
		$channel->send( $payload );

		$this->assertNotNull( $captured_body );
		$message = $captured_body['text'];

		// Verify HTML elements in the message.
		$this->assertStringContainsString( '<b>', $message );
		$this->assertStringContainsString( '[Alert]', $message );
		$this->assertStringContainsString( 'Database Connection', $message );
		$this->assertStringContainsString( 'CRITICAL', $message );
		$this->assertStringContainsString( 'Connection timeout', $message );
		$this->assertStringContainsString( 'Test Site', $message );
	}

	/**
	 * Tests that HTML characters in the payload are escaped (injection prevention)
	 *
	 * @return void
	 */
	public function test_send_escapes_html_in_payload_fields()
	{
		$settings = [
			'telegram' => [
				'enabled'   => true,
				'bot_token' => '123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11',
				'chat_id'   => '-1001234567890',
			],
		];

		$captured_body = null;

		$http_client = $this->create_http_client_mock();
		$http_client->shouldReceive( 'post' )
			->once()
			->with(
				Mockery::any(),
				Mockery::on(
					function ( $body ) use ( &$captured_body ) {
						$captured_body = $body;
						return true;
					}
				)
			)
			->andReturn(
				[
					'success' => true,
					'code'    => 200,
					'body'    => '{"ok":true}',
					'error'   => null,
				]
			);

		$channel = new TelegramChannel(
			$this->create_storage_mock( $settings ),
			$http_client
		);

		$payload = $this->create_test_payload(
			[
				'check_name' => '<script>alert("xss")</script>',
				'site_name'  => 'Site & "Stuff"',
			]
		);
		$channel->send( $payload );

		$this->assertNotNull( $captured_body );
		$message = $captured_body['text'];

		// Raw HTML must be escaped.
		$this->assertStringNotContainsString( '<script>', $message );
		$this->assertStringContainsString( '&lt;script&gt;', $message );
		$this->assertStringContainsString( 'Site &amp; &quot;Stuff&quot;', $message );
	}
}
