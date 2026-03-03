<?php
/**
 * Test for WhatsAppChannel
 *
 * Verifies sending alerts via WhatsApp webhook with optional Bearer auth.
 *
 * @package OpsHealthDashboard\Tests\Unit\Channels
 */

namespace OpsHealthDashboard\Tests\Unit\Channels;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use OpsHealthDashboard\Channels\WhatsAppChannel;
use OpsHealthDashboard\Interfaces\AlertChannelInterface;
use OpsHealthDashboard\Interfaces\HttpClientInterface;
use OpsHealthDashboard\Interfaces\StorageInterface;
use PHPUnit\Framework\TestCase;

/**
 * Class WhatsAppChannelTest
 *
 * Unit tests for the WhatsApp alert channel.
 */
class WhatsAppChannelTest extends TestCase
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
		$reflection = new \ReflectionClass( WhatsAppChannel::class );
		$this->assertFalse( $reflection->isFinal(), 'WhatsAppChannel should NOT be final' );
	}

	/**
	 * Tests that there are NO static methods
	 *
	 * @return void
	 */
	public function test_no_static_methods()
	{
		$reflection = new \ReflectionClass( WhatsAppChannel::class );
		$methods    = $reflection->getMethods( \ReflectionMethod::IS_STATIC );

		$static_methods = array_filter(
			$methods,
			function ( $method ) {
				return strpos( $method->getName(), '__' ) !== 0;
			}
		);

		$this->assertEmpty( $static_methods, 'WhatsAppChannel should have NO static methods' );
	}

	/**
	 * Tests that there are NO static properties
	 *
	 * @return void
	 */
	public function test_no_static_properties()
	{
		$reflection = new \ReflectionClass( WhatsAppChannel::class );
		$properties = $reflection->getProperties( \ReflectionProperty::IS_STATIC );

		$this->assertEmpty( $properties, 'WhatsAppChannel should have NO static properties' );
	}

	/**
	 * Tests that implements AlertChannelInterface
	 *
	 * @return void
	 */
	public function test_implements_interface()
	{
		$channel = new WhatsAppChannel(
			$this->create_storage_mock(),
			$this->create_http_client_mock()
		);
		$this->assertInstanceOf( AlertChannelInterface::class, $channel );
	}

	// ---------------------------------------------------
	// get_id() / get_name()
	// ---------------------------------------------------

	/**
	 * Tests that get_id returns 'whatsapp'
	 *
	 * @return void
	 */
	public function test_get_id_returns_whatsapp()
	{
		$channel = new WhatsAppChannel(
			$this->create_storage_mock(),
			$this->create_http_client_mock()
		);
		$this->assertEquals( 'whatsapp', $channel->get_id() );
	}

	/**
	 * Tests that get_name returns translated string
	 *
	 * @return void
	 */
	public function test_get_name_returns_translated_string()
	{
		$this->mock_i18n();
		$channel = new WhatsAppChannel(
			$this->create_storage_mock(),
			$this->create_http_client_mock()
		);
		$this->assertEquals( 'WhatsApp', $channel->get_name() );
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
			'whatsapp' => [
				'enabled'      => true,
				'webhook_url'  => 'https://api.twilio.com/whatsapp/send',
				'phone_number' => '+1234567890',
			],
		];
		$channel  = new WhatsAppChannel(
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
			'whatsapp' => [
				'enabled'      => false,
				'webhook_url'  => 'https://api.twilio.com/whatsapp/send',
				'phone_number' => '+1234567890',
			],
		];
		$channel  = new WhatsAppChannel(
			$this->create_storage_mock( $settings ),
			$this->create_http_client_mock()
		);
		$this->assertFalse( $channel->is_enabled() );
	}

	/**
	 * Tests is_enabled returns false without webhook_url
	 *
	 * @return void
	 */
	public function test_is_enabled_returns_false_without_webhook_url()
	{
		$settings = [
			'whatsapp' => [
				'enabled'      => true,
				'phone_number' => '+1234567890',
			],
		];
		$channel  = new WhatsAppChannel(
			$this->create_storage_mock( $settings ),
			$this->create_http_client_mock()
		);
		$this->assertFalse( $channel->is_enabled() );
	}

	/**
	 * Tests is_enabled returns false without phone_number
	 *
	 * @return void
	 */
	public function test_is_enabled_returns_false_without_phone_number()
	{
		$settings = [
			'whatsapp' => [
				'enabled'     => true,
				'webhook_url' => 'https://api.twilio.com/whatsapp/send',
			],
		];
		$channel  = new WhatsAppChannel(
			$this->create_storage_mock( $settings ),
			$this->create_http_client_mock()
		);
		$this->assertFalse( $channel->is_enabled() );
	}

	/**
	 * Tests is_enabled returns false with non-E.164 phone_number
	 *
	 * @return void
	 */
	public function test_is_enabled_returns_false_with_invalid_phone_format()
	{
		$settings = [
			'whatsapp' => [
				'enabled'      => true,
				'webhook_url'  => 'https://api.twilio.com/whatsapp/send',
				'phone_number' => '1234567890',
			],
		];
		$channel  = new WhatsAppChannel(
			$this->create_storage_mock( $settings ),
			$this->create_http_client_mock()
		);
		$this->assertFalse( $channel->is_enabled() );
	}

	/**
	 * Tests is_enabled returns false with too short phone_number
	 *
	 * @return void
	 */
	public function test_is_enabled_returns_false_with_short_phone()
	{
		$settings = [
			'whatsapp' => [
				'enabled'      => true,
				'webhook_url'  => 'https://api.twilio.com/whatsapp/send',
				'phone_number' => '+12345',
			],
		];
		$channel  = new WhatsAppChannel(
			$this->create_storage_mock( $settings ),
			$this->create_http_client_mock()
		);
		$this->assertFalse( $channel->is_enabled() );
	}

	/**
	 * Tests is_enabled returns false with phone_number starting with +0
	 *
	 * @return void
	 */
	public function test_is_enabled_returns_false_with_zero_country_code()
	{
		$settings = [
			'whatsapp' => [
				'enabled'      => true,
				'webhook_url'  => 'https://api.twilio.com/whatsapp/send',
				'phone_number' => '+0123456789',
			],
		];
		$channel  = new WhatsAppChannel(
			$this->create_storage_mock( $settings ),
			$this->create_http_client_mock()
		);
		$this->assertFalse( $channel->is_enabled() );
	}

	/**
	 * Tests is_enabled returns true with long E.164 phone_number (15 digits)
	 *
	 * @return void
	 */
	public function test_is_enabled_returns_true_with_max_length_phone()
	{
		$settings = [
			'whatsapp' => [
				'enabled'      => true,
				'webhook_url'  => 'https://api.twilio.com/whatsapp/send',
				'phone_number' => '+123456789012345',
			],
		];
		$channel  = new WhatsAppChannel(
			$this->create_storage_mock( $settings ),
			$this->create_http_client_mock()
		);
		$this->assertTrue( $channel->is_enabled() );
	}

	/**
	 * Tests is_enabled returns false with too long phone_number (16 digits)
	 *
	 * @return void
	 */
	public function test_is_enabled_returns_false_with_too_long_phone()
	{
		$settings = [
			'whatsapp' => [
				'enabled'      => true,
				'webhook_url'  => 'https://api.twilio.com/whatsapp/send',
				'phone_number' => '+1234567890123456',
			],
		];
		$channel  = new WhatsAppChannel(
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
		$channel = new WhatsAppChannel(
			$this->create_storage_mock( [] ),
			$this->create_http_client_mock()
		);
		$this->assertFalse( $channel->is_enabled() );
	}

	// ---------------------------------------------------
	// send()
	// ---------------------------------------------------

	/**
	 * Tests WhatsApp send with success including phone number in body
	 *
	 * @return void
	 */
	public function test_send_success_with_phone_number()
	{
		$webhook_url  = 'https://api.twilio.com/whatsapp/send';
		$phone_number = '+1234567890';
		$settings     = [
			'whatsapp' => [
				'enabled'      => true,
				'webhook_url'  => $webhook_url,
				'phone_number' => $phone_number,
			],
		];

		$http_client = $this->create_http_client_mock();
		$http_client->shouldReceive( 'post' )
			->once()
			->with(
				$webhook_url,
				Mockery::on(
					function ( $body ) use ( $phone_number ) {
						return isset( $body['to'] )
							&& $body['to'] === $phone_number
							&& isset( $body['body'] )
							&& is_string( $body['body'] );
					}
				),
				Mockery::type( 'array' )
			)
			->andReturn(
				[
					'success' => true,
					'code'    => 200,
					'body'    => '{"status":"sent"}',
					'error'   => null,
				]
			);

		$channel = new WhatsAppChannel(
			$this->create_storage_mock( $settings ),
			$http_client
		);
		$payload = $this->create_test_payload();
		$result  = $channel->send( $payload );

		$this->assertTrue( $result['success'] );
		$this->assertNull( $result['error'] );
	}

	/**
	 * Tests WhatsApp send with failure
	 *
	 * @return void
	 */
	public function test_send_failure()
	{
		$settings = [
			'whatsapp' => [
				'enabled'      => true,
				'webhook_url'  => 'https://api.twilio.com/whatsapp/send',
				'phone_number' => '+1234567890',
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
					'error'   => 'Authentication failed',
				]
			);

		$channel = new WhatsAppChannel(
			$this->create_storage_mock( $settings ),
			$http_client
		);
		$payload = $this->create_test_payload();
		$result  = $channel->send( $payload );

		$this->assertFalse( $result['success'] );
		$this->assertEquals( 'Authentication failed', $result['error'] );
	}

	/**
	 * Tests WhatsApp send with Bearer auth header when api_token is configured
	 *
	 * @return void
	 */
	public function test_send_with_bearer_auth()
	{
		$api_token = 'my-secret-api-token';
		$settings  = [
			'whatsapp' => [
				'enabled'      => true,
				'webhook_url'  => 'https://api.twilio.com/whatsapp/send',
				'phone_number' => '+1234567890',
				'api_token'    => $api_token,
			],
		];

		$http_client = $this->create_http_client_mock();
		$http_client->shouldReceive( 'post' )
			->once()
			->with(
				Mockery::any(),
				Mockery::any(),
				Mockery::on(
					function ( $headers ) use ( $api_token ) {
						return isset( $headers['Authorization'] )
							&& $headers['Authorization'] === 'Bearer ' . $api_token;
					}
				)
			)
			->andReturn(
				[
					'success' => true,
					'code'    => 200,
					'body'    => '{"status":"sent"}',
					'error'   => null,
				]
			);

		$channel = new WhatsAppChannel(
			$this->create_storage_mock( $settings ),
			$http_client
		);
		$payload = $this->create_test_payload();
		$result  = $channel->send( $payload );

		$this->assertTrue( $result['success'] );
	}

	/**
	 * Tests WhatsApp send without auth header when api_token is not configured
	 *
	 * @return void
	 */
	public function test_send_without_auth()
	{
		$settings = [
			'whatsapp' => [
				'enabled'      => true,
				'webhook_url'  => 'https://api.twilio.com/whatsapp/send',
				'phone_number' => '+1234567890',
			],
		];

		$http_client = $this->create_http_client_mock();
		$http_client->shouldReceive( 'post' )
			->once()
			->with(
				Mockery::any(),
				Mockery::any(),
				Mockery::on(
					function ( $headers ) {
						return ! isset( $headers['Authorization'] );
					}
				)
			)
			->andReturn(
				[
					'success' => true,
					'code'    => 200,
					'body'    => '{"status":"sent"}',
					'error'   => null,
				]
			);

		$channel = new WhatsAppChannel(
			$this->create_storage_mock( $settings ),
			$http_client
		);
		$payload = $this->create_test_payload();
		$result  = $channel->send( $payload );

		$this->assertTrue( $result['success'] );
		$this->assertNull( $result['error'] );
	}
}
