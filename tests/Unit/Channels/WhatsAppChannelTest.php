<?php
/**
 * Test per WhatsAppChannel
 *
 * Verifica l'invio di alert via WhatsApp webhook con Bearer auth opzionale.
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
 * Test unitari per il canale di alert WhatsApp.
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
		$reflection = new \ReflectionClass( WhatsAppChannel::class );
		$this->assertFalse( $reflection->isFinal(), 'WhatsAppChannel should NOT be final' );
	}

	/**
	 * Testa che NON esistono metodi static
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
	 * Testa che NON esistono proprieta static
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
	 * Testa che implementa AlertChannelInterface
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
	 * Testa che get_id ritorna 'whatsapp'
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
	 * Testa che get_name ritorna stringa tradotta
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
	 * Testa is_enabled true con configurazione completa
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
	 * Testa is_enabled false quando disabilitato
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
	 * Testa is_enabled false senza webhook_url
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
	 * Testa is_enabled false senza phone_number
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
	 * Testa is_enabled false senza impostazioni
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
	 * Testa invio WhatsApp con successo incluso phone number nel body
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
	 * Testa invio WhatsApp con fallimento
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
	 * Testa invio WhatsApp con header Bearer auth quando api_token configurato
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
	 * Testa invio WhatsApp senza header auth quando api_token non configurato
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
