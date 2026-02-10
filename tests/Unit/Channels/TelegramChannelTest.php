<?php
/**
 * Test per TelegramChannel
 *
 * Verifica l'invio di alert via Telegram Bot API con messaggi HTML.
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
 * Test unitari per il canale di alert Telegram.
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
		$reflection = new \ReflectionClass( TelegramChannel::class );
		$this->assertFalse( $reflection->isFinal(), 'TelegramChannel should NOT be final' );
	}

	/**
	 * Testa che NON esistono metodi static
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
	 * Testa che NON esistono proprieta static
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
	 * Testa che implementa AlertChannelInterface
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
	 * Testa che get_id ritorna 'telegram'
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
	 * Testa che get_name ritorna stringa tradotta
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
	 * Testa is_enabled true con configurazione completa
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
	 * Testa is_enabled false quando disabilitato
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
	 * Testa is_enabled false senza bot_token
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
	 * Testa is_enabled false senza chat_id
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
	 * Testa is_enabled false senza impostazioni
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
	 * Testa is_enabled false senza chiave telegram
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
	 * Testa invio Telegram con successo e URL API corretto
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
	 * Testa invio Telegram con fallimento
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
	 * Testa che il messaggio Telegram contiene HTML formattato
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

		// Verifica elementi HTML nel messaggio.
		$this->assertStringContainsString( '<b>', $message );
		$this->assertStringContainsString( '[Alert]', $message );
		$this->assertStringContainsString( 'Database Connection', $message );
		$this->assertStringContainsString( 'CRITICAL', $message );
		$this->assertStringContainsString( 'Connection timeout', $message );
		$this->assertStringContainsString( 'Test Site', $message );
	}

	/**
	 * Testa che caratteri HTML nel payload sono escapati (prevenzione injection)
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
