<?php
/**
 * Test for WebhookChannel
 *
 * Verifies sending alerts via webhook HTTP POST with optional HMAC signature.
 *
 * @package OpsHealthDashboard\Tests\Unit\Channels
 */

namespace OpsHealthDashboard\Tests\Unit\Channels;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use OpsHealthDashboard\Channels\WebhookChannel;
use OpsHealthDashboard\Interfaces\AlertChannelInterface;
use OpsHealthDashboard\Interfaces\HttpClientInterface;
use OpsHealthDashboard\Interfaces\StorageInterface;
use PHPUnit\Framework\TestCase;

/**
 * Class WebhookChannelTest
 *
 * Unit tests for the Webhook alert channel.
 */
class WebhookChannelTest extends TestCase
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
		$reflection = new \ReflectionClass( WebhookChannel::class );
		$this->assertFalse( $reflection->isFinal(), 'WebhookChannel should NOT be final' );
	}

	/**
	 * Tests that there are NO static methods
	 *
	 * @return void
	 */
	public function test_no_static_methods()
	{
		$reflection = new \ReflectionClass( WebhookChannel::class );
		$methods    = $reflection->getMethods( \ReflectionMethod::IS_STATIC );

		$static_methods = array_filter(
			$methods,
			function ( $method ) {
				return strpos( $method->getName(), '__' ) !== 0;
			}
		);

		$this->assertEmpty( $static_methods, 'WebhookChannel should have NO static methods' );
	}

	/**
	 * Tests that there are NO static properties
	 *
	 * @return void
	 */
	public function test_no_static_properties()
	{
		$reflection = new \ReflectionClass( WebhookChannel::class );
		$properties = $reflection->getProperties( \ReflectionProperty::IS_STATIC );

		$this->assertEmpty( $properties, 'WebhookChannel should have NO static properties' );
	}

	/**
	 * Tests that implements AlertChannelInterface
	 *
	 * @return void
	 */
	public function test_implements_interface()
	{
		$channel = new WebhookChannel(
			$this->create_storage_mock(),
			$this->create_http_client_mock()
		);
		$this->assertInstanceOf( AlertChannelInterface::class, $channel );
	}

	// ---------------------------------------------------
	// get_id() / get_name()
	// ---------------------------------------------------

	/**
	 * Tests that get_id returns 'webhook'
	 *
	 * @return void
	 */
	public function test_get_id_returns_webhook()
	{
		$channel = new WebhookChannel(
			$this->create_storage_mock(),
			$this->create_http_client_mock()
		);
		$this->assertEquals( 'webhook', $channel->get_id() );
	}

	/**
	 * Tests that get_name returns translated string
	 *
	 * @return void
	 */
	public function test_get_name_returns_translated_string()
	{
		$this->mock_i18n();
		$channel = new WebhookChannel(
			$this->create_storage_mock(),
			$this->create_http_client_mock()
		);
		$this->assertEquals( 'Webhook', $channel->get_name() );
	}

	// ---------------------------------------------------
	// is_enabled()
	// ---------------------------------------------------

	/**
	 * Tests is_enabled returns true with valid configuration
	 *
	 * @return void
	 */
	public function test_is_enabled_returns_true_when_configured()
	{
		$settings = [
			'webhook' => [
				'enabled' => true,
				'url'     => 'https://hooks.example.com/alert',
			],
		];
		$channel  = new WebhookChannel(
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
			'webhook' => [
				'enabled' => false,
				'url'     => 'https://hooks.example.com/alert',
			],
		];
		$channel  = new WebhookChannel(
			$this->create_storage_mock( $settings ),
			$this->create_http_client_mock()
		);
		$this->assertFalse( $channel->is_enabled() );
	}

	/**
	 * Tests is_enabled returns false with empty URL
	 *
	 * @return void
	 */
	public function test_is_enabled_returns_false_with_empty_url()
	{
		$settings = [
			'webhook' => [
				'enabled' => true,
				'url'     => '',
			],
		];
		$channel  = new WebhookChannel(
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
		$channel = new WebhookChannel(
			$this->create_storage_mock( [] ),
			$this->create_http_client_mock()
		);
		$this->assertFalse( $channel->is_enabled() );
	}

	/**
	 * Tests is_enabled returns false without webhook key
	 *
	 * @return void
	 */
	public function test_is_enabled_returns_false_without_webhook_key()
	{
		$settings = [ 'email' => [ 'enabled' => true ] ];
		$channel  = new WebhookChannel(
			$this->create_storage_mock( $settings ),
			$this->create_http_client_mock()
		);
		$this->assertFalse( $channel->is_enabled() );
	}

	// ---------------------------------------------------
	// send()
	// ---------------------------------------------------

	/**
	 * Tests webhook send with success
	 *
	 * @return void
	 */
	public function test_send_success()
	{
		$settings = [
			'webhook' => [
				'enabled' => true,
				'url'     => 'https://hooks.example.com/alert',
			],
		];

		$http_client = $this->create_http_client_mock();
		$http_client->shouldReceive( 'post' )
			->once()
			->with(
				'https://hooks.example.com/alert',
				Mockery::type( 'string' ),
				Mockery::type( 'array' )
			)
			->andReturn(
				[
					'success' => true,
					'code'    => 200,
					'body'    => 'ok',
					'error'   => null,
				]
			);

		$channel = new WebhookChannel(
			$this->create_storage_mock( $settings ),
			$http_client
		);
		$payload = $this->create_test_payload();
		$result  = $channel->send( $payload );

		$this->assertTrue( $result['success'] );
		$this->assertNull( $result['error'] );
	}

	/**
	 * Tests webhook send with failure
	 *
	 * @return void
	 */
	public function test_send_failure()
	{
		$settings = [
			'webhook' => [
				'enabled' => true,
				'url'     => 'https://hooks.example.com/alert',
			],
		];

		$http_client = $this->create_http_client_mock();
		$http_client->shouldReceive( 'post' )
			->once()
			->with(
				'https://hooks.example.com/alert',
				Mockery::type( 'string' ),
				Mockery::type( 'array' )
			)
			->andReturn(
				[
					'success' => false,
					'code'    => 0,
					'body'    => '',
					'error'   => 'Connection refused',
				]
			);

		$channel = new WebhookChannel(
			$this->create_storage_mock( $settings ),
			$http_client
		);
		$payload = $this->create_test_payload();
		$result  = $channel->send( $payload );

		$this->assertFalse( $result['success'] );
		$this->assertEquals( 'Connection refused', $result['error'] );
	}

	/**
	 * Tests webhook send with HMAC signature when secret is configured
	 *
	 * The HMAC signature must be calculated on the same raw body
	 * passed to HttpClient (pre-serialized string), not on a
	 * separate json_encode().
	 *
	 * @return void
	 */
	public function test_send_with_hmac_signature()
	{
		$secret   = 'my-webhook-secret';
		$settings = [
			'webhook' => [
				'enabled' => true,
				'url'     => 'https://hooks.example.com/alert',
				'secret'  => $secret,
			],
		];

		$payload  = $this->create_test_payload();
		$raw_body = json_encode( $payload );
		$expected_signature = hash_hmac( 'sha256', $raw_body, $secret );

		$http_client = $this->create_http_client_mock();
		$http_client->shouldReceive( 'post' )
			->once()
			->with(
				'https://hooks.example.com/alert',
				Mockery::on(
					function ( $body ) use ( $raw_body ) {
						// The body must be a pre-serialized string,
						// NOT an array to be re-serialized.
						return is_string( $body ) && $body === $raw_body;
					}
				),
				Mockery::on(
					function ( $headers ) use ( $expected_signature ) {
						return isset( $headers['X-OpsHealth-Signature'] )
							&& $headers['X-OpsHealth-Signature'] === $expected_signature;
					}
				)
			)
			->andReturn(
				[
					'success' => true,
					'code'    => 200,
					'body'    => 'ok',
					'error'   => null,
				]
			);

		$channel = new WebhookChannel(
			$this->create_storage_mock( $settings ),
			$http_client
		);
		$result = $channel->send( $payload );

		$this->assertTrue( $result['success'] );
	}

	/**
	 * Tests webhook send without signature when secret is not configured
	 *
	 * @return void
	 */
	public function test_send_without_signature()
	{
		$settings = [
			'webhook' => [
				'enabled' => true,
				'url'     => 'https://hooks.example.com/alert',
			],
		];

		$http_client = $this->create_http_client_mock();
		$http_client->shouldReceive( 'post' )
			->once()
			->with(
				'https://hooks.example.com/alert',
				Mockery::type( 'string' ),
				Mockery::on(
					function ( $headers ) {
						return ! isset( $headers['X-OpsHealth-Signature'] );
					}
				)
			)
			->andReturn(
				[
					'success' => true,
					'code'    => 200,
					'body'    => 'ok',
					'error'   => null,
				]
			);

		$channel = new WebhookChannel(
			$this->create_storage_mock( $settings ),
			$http_client
		);
		$payload = $this->create_test_payload();
		$result  = $channel->send( $payload );

		$this->assertTrue( $result['success'] );
		$this->assertNull( $result['error'] );
	}
}
