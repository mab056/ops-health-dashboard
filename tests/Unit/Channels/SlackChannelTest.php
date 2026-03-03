<?php
/**
 * Test for SlackChannel
 *
 * Verifies sending alerts via Slack Incoming Webhook with Block Kit.
 *
 * @package OpsHealthDashboard\Tests\Unit\Channels
 */

namespace OpsHealthDashboard\Tests\Unit\Channels;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use OpsHealthDashboard\Channels\SlackChannel;
use OpsHealthDashboard\Interfaces\AlertChannelInterface;
use OpsHealthDashboard\Interfaces\HttpClientInterface;
use OpsHealthDashboard\Interfaces\StorageInterface;
use PHPUnit\Framework\TestCase;

/**
 * Class SlackChannelTest
 *
 * Unit tests for the Slack alert channel.
 */
class SlackChannelTest extends TestCase
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
		$reflection = new \ReflectionClass( SlackChannel::class );
		$this->assertFalse( $reflection->isFinal(), 'SlackChannel should NOT be final' );
	}

	/**
	 * Tests that there are NO static methods
	 *
	 * @return void
	 */
	public function test_no_static_methods()
	{
		$reflection = new \ReflectionClass( SlackChannel::class );
		$methods    = $reflection->getMethods( \ReflectionMethod::IS_STATIC );

		$static_methods = array_filter(
			$methods,
			function ( $method ) {
				return strpos( $method->getName(), '__' ) !== 0;
			}
		);

		$this->assertEmpty( $static_methods, 'SlackChannel should have NO static methods' );
	}

	/**
	 * Tests that there are NO static properties
	 *
	 * @return void
	 */
	public function test_no_static_properties()
	{
		$reflection = new \ReflectionClass( SlackChannel::class );
		$properties = $reflection->getProperties( \ReflectionProperty::IS_STATIC );

		$this->assertEmpty( $properties, 'SlackChannel should have NO static properties' );
	}

	/**
	 * Tests that implements AlertChannelInterface
	 *
	 * @return void
	 */
	public function test_implements_interface()
	{
		$channel = new SlackChannel(
			$this->create_storage_mock(),
			$this->create_http_client_mock()
		);
		$this->assertInstanceOf( AlertChannelInterface::class, $channel );
	}

	// ---------------------------------------------------
	// get_id() / get_name()
	// ---------------------------------------------------

	/**
	 * Tests that get_id returns 'slack'
	 *
	 * @return void
	 */
	public function test_get_id_returns_slack()
	{
		$channel = new SlackChannel(
			$this->create_storage_mock(),
			$this->create_http_client_mock()
		);
		$this->assertEquals( 'slack', $channel->get_id() );
	}

	/**
	 * Tests that get_name returns translated string
	 *
	 * @return void
	 */
	public function test_get_name_returns_translated_string()
	{
		$this->mock_i18n();
		$channel = new SlackChannel(
			$this->create_storage_mock(),
			$this->create_http_client_mock()
		);
		$this->assertEquals( 'Slack', $channel->get_name() );
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
			'slack' => [
				'enabled'     => true,
				'webhook_url' => 'https://hooks.slack.com/services/T00/B00/xxx',
			],
		];
		$channel  = new SlackChannel(
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
			'slack' => [
				'enabled'     => false,
				'webhook_url' => 'https://hooks.slack.com/services/T00/B00/xxx',
			],
		];
		$channel  = new SlackChannel(
			$this->create_storage_mock( $settings ),
			$this->create_http_client_mock()
		);
		$this->assertFalse( $channel->is_enabled() );
	}

	/**
	 * Tests is_enabled returns false with empty webhook_url
	 *
	 * @return void
	 */
	public function test_is_enabled_returns_false_with_empty_webhook_url()
	{
		$settings = [
			'slack' => [
				'enabled'     => true,
				'webhook_url' => '',
			],
		];
		$channel  = new SlackChannel(
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
		$channel = new SlackChannel(
			$this->create_storage_mock( [] ),
			$this->create_http_client_mock()
		);
		$this->assertFalse( $channel->is_enabled() );
	}

	/**
	 * Tests is_enabled returns false without slack key
	 *
	 * @return void
	 */
	public function test_is_enabled_returns_false_without_slack_key()
	{
		$settings = [ 'email' => [ 'enabled' => true ] ];
		$channel  = new SlackChannel(
			$this->create_storage_mock( $settings ),
			$this->create_http_client_mock()
		);
		$this->assertFalse( $channel->is_enabled() );
	}

	// ---------------------------------------------------
	// send()
	// ---------------------------------------------------

	/**
	 * Tests Slack send with success
	 *
	 * @return void
	 */
	public function test_send_success()
	{
		$webhook_url = 'https://hooks.slack.com/services/T00/B00/xxx';
		$settings    = [
			'slack' => [
				'enabled'     => true,
				'webhook_url' => $webhook_url,
			],
		];

		$http_client = $this->create_http_client_mock();
		$http_client->shouldReceive( 'post' )
			->once()
			->with(
				$webhook_url,
				Mockery::on(
					function ( $body ) {
						return isset( $body['blocks'] )
							&& isset( $body['attachments'] );
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

		$channel = new SlackChannel(
			$this->create_storage_mock( $settings ),
			$http_client
		);
		$payload = $this->create_test_payload();
		$result  = $channel->send( $payload );

		$this->assertTrue( $result['success'] );
		$this->assertNull( $result['error'] );
	}

	/**
	 * Tests Slack send with failure
	 *
	 * @return void
	 */
	public function test_send_failure()
	{
		$settings = [
			'slack' => [
				'enabled'     => true,
				'webhook_url' => 'https://hooks.slack.com/services/T00/B00/xxx',
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
					'error'   => 'Connection refused',
				]
			);

		$channel = new SlackChannel(
			$this->create_storage_mock( $settings ),
			$http_client
		);
		$payload = $this->create_test_payload();
		$result  = $channel->send( $payload );

		$this->assertFalse( $result['success'] );
		$this->assertEquals( 'Connection refused', $result['error'] );
	}

	/**
	 * Tests that the Slack payload for critical status uses red color
	 *
	 * @return void
	 */
	public function test_send_formats_critical_with_red_color()
	{
		$settings = [
			'slack' => [
				'enabled'     => true,
				'webhook_url' => 'https://hooks.slack.com/services/T00/B00/xxx',
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
					'body'    => 'ok',
					'error'   => null,
				]
			);

		$channel = new SlackChannel(
			$this->create_storage_mock( $settings ),
			$http_client
		);
		$payload = $this->create_test_payload( [ 'current_status' => 'critical' ] );
		$channel->send( $payload );

		$this->assertNotNull( $captured_body );
		$this->assertArrayHasKey( 'attachments', $captured_body );
		$this->assertEquals( '#FF0000', $captured_body['attachments'][0]['color'] );

		// Verify that the title contains [Alert] and the check name.
		$header_text = $captured_body['blocks'][0]['text']['text'];
		$this->assertStringContainsString( '[Alert]', $header_text );
		$this->assertStringContainsString( 'Database Connection', $header_text );
		$this->assertStringContainsString( 'CRITICAL', $header_text );
	}

	/**
	 * Tests that the Slack payload for recovery uses green color and [Recovered]
	 *
	 * @return void
	 */
	public function test_send_formats_recovery_payload()
	{
		$settings = [
			'slack' => [
				'enabled'     => true,
				'webhook_url' => 'https://hooks.slack.com/services/T00/B00/xxx',
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
					'body'    => 'ok',
					'error'   => null,
				]
			);

		$channel = new SlackChannel(
			$this->create_storage_mock( $settings ),
			$http_client
		);
		$payload = $this->create_test_payload(
			[
				'current_status'  => 'ok',
				'previous_status' => 'critical',
				'is_recovery'     => true,
				'message'         => 'All checks passed',
			]
		);
		$channel->send( $payload );

		$this->assertNotNull( $captured_body );

		// Recovery: green color.
		$this->assertEquals( '#36A64F', $captured_body['attachments'][0]['color'] );

		// Title contains [Recovered].
		$header_text = $captured_body['blocks'][0]['text']['text'];
		$this->assertStringContainsString( '[Recovered]', $header_text );
		$this->assertStringContainsString( 'OK', $header_text );
	}

	/**
	 * Tests that mrkdwn special characters are escaped in the message
	 *
	 * @return void
	 */
	public function test_send_escapes_mrkdwn_special_chars()
	{
		$settings = [
			'slack' => [
				'enabled'     => true,
				'webhook_url' => 'https://hooks.slack.com/services/T00/B00/xxx',
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
					'body'    => 'ok',
					'error'   => null,
				]
			);

		$channel = new SlackChannel(
			$this->create_storage_mock( $settings ),
			$http_client
		);
		$payload = $this->create_test_payload(
			[
				'message'   => '*bold* _italic_ ~strike~ `code`',
				'site_name' => 'Site <&> Test',
			]
		);
		$channel->send( $payload );

		$this->assertNotNull( $captured_body );
		$section_text = $captured_body['blocks'][1]['text']['text'];

		// Verify mrkdwn chars are escaped.
		$this->assertStringContainsString( '\*bold\*', $section_text );
		$this->assertStringContainsString( '\_italic\_', $section_text );
		$this->assertStringContainsString( '\~strike\~', $section_text );
		$this->assertStringContainsString( '\`code\`', $section_text );
		$this->assertStringContainsString( '&amp;', $section_text );
		$this->assertStringContainsString( '&lt;', $section_text );
		$this->assertStringContainsString( '&gt;', $section_text );
	}

	/**
	 * Tests that an unmapped status uses gray fallback color
	 *
	 * @return void
	 */
	public function test_send_formats_unknown_status_with_gray_fallback_color()
	{
		$settings = [
			'slack' => [
				'enabled'     => true,
				'webhook_url' => 'https://hooks.slack.com/services/T00/B00/xxx',
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
					'body'    => 'ok',
					'error'   => null,
				]
			);

		$channel = new SlackChannel(
			$this->create_storage_mock( $settings ),
			$http_client
		);
		$payload = $this->create_test_payload( [ 'current_status' => 'unknown' ] );
		$channel->send( $payload );

		$this->assertNotNull( $captured_body );
		$this->assertEquals( '#808080', $captured_body['attachments'][0]['color'] );
	}
}
