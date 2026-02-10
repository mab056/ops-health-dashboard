<?php
/**
 * Test per SlackChannel
 *
 * Verifica l'invio di alert via Slack Incoming Webhook con Block Kit.
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
 * Test unitari per il canale di alert Slack.
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
	 * Crea un mock di StorageInterface
	 *
	 * @param array $settings Impostazioni da ritornare.
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
	 * Crea un mock di HttpClientInterface
	 *
	 * @return \Mockery\MockInterface|HttpClientInterface
	 */
	private function create_http_client_mock()
	{
		return Mockery::mock( HttpClientInterface::class );
	}

	/**
	 * Crea un payload di test
	 *
	 * @param array $overrides Chiavi da sovrascrivere.
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
	 * Configura mock i18n
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
	 * Testa che la classe NON e final
	 *
	 * @return void
	 */
	public function test_class_is_not_final()
	{
		$reflection = new \ReflectionClass( SlackChannel::class );
		$this->assertFalse( $reflection->isFinal(), 'SlackChannel should NOT be final' );
	}

	/**
	 * Testa che NON esistono metodi static
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
	 * Testa che NON esistono proprieta static
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
	 * Testa che implementa AlertChannelInterface
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
	 * Testa che get_id ritorna 'slack'
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
	 * Testa che get_name ritorna stringa tradotta
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
	 * Testa is_enabled true con configurazione valida
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
	 * Testa is_enabled false quando disabilitato
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
	 * Testa is_enabled false con webhook_url vuoto
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
	 * Testa is_enabled false senza impostazioni
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
	 * Testa is_enabled false senza chiave slack
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
	 * Testa invio Slack con successo
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
	 * Testa invio Slack con fallimento
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
	 * Testa che il payload Slack per status critical usa colore rosso
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

		// Verifica che il titolo contiene [Alert] e il check name.
		$header_text = $captured_body['blocks'][0]['text']['text'];
		$this->assertStringContainsString( '[Alert]', $header_text );
		$this->assertStringContainsString( 'Database Connection', $header_text );
		$this->assertStringContainsString( 'CRITICAL', $header_text );
	}

	/**
	 * Testa che il payload Slack per recovery usa colore verde e [Recovered]
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

		// Recovery: colore verde.
		$this->assertEquals( '#36A64F', $captured_body['attachments'][0]['color'] );

		// Titolo contiene [Recovered].
		$header_text = $captured_body['blocks'][0]['text']['text'];
		$this->assertStringContainsString( '[Recovered]', $header_text );
		$this->assertStringContainsString( 'OK', $header_text );
	}

	/**
	 * Testa che i caratteri speciali mrkdwn sono escapati nel messaggio
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
	 * Testa che uno status non mappato usa colore grigio di fallback
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
