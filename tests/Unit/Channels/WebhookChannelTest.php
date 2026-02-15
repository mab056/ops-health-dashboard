<?php
/**
 * Test per WebhookChannel
 *
 * Verifica l'invio di alert via webhook HTTP POST con firma HMAC opzionale.
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
 * Test unitari per il canale di alert Webhook.
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
		$reflection = new \ReflectionClass( WebhookChannel::class );
		$this->assertFalse( $reflection->isFinal(), 'WebhookChannel should NOT be final' );
	}

	/**
	 * Testa che NON esistono metodi static
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
	 * Testa che NON esistono proprieta static
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
	 * Testa che implementa AlertChannelInterface
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
	 * Testa che get_id ritorna 'webhook'
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
	 * Testa che get_name ritorna stringa tradotta
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
	 * Testa is_enabled true con configurazione valida
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
	 * Testa is_enabled false quando disabilitato
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
	 * Testa is_enabled false con URL vuoto
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
	 * Testa is_enabled false senza impostazioni
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
	 * Testa is_enabled false senza chiave webhook
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
	 * Testa invio webhook con successo
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
	 * Testa invio webhook con fallimento
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
	 * Testa invio webhook con firma HMAC quando secret configurato
	 *
	 * La firma HMAC deve essere calcolata sullo stesso raw body
	 * passato a HttpClient (stringa pre-serializzata), non su un
	 * json_encode() separato.
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
						// Il body deve essere una stringa pre-serializzata,
						// NON un array da ri-serializzare.
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
	 * Testa invio webhook senza firma quando secret non configurato
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
