<?php
/**
 * Test per EmailChannel
 *
 * Verifica l'invio di alert via email con wp_mail().
 *
 * @package OpsHealthDashboard\Tests\Unit\Channels
 */

namespace OpsHealthDashboard\Tests\Unit\Channels;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use OpsHealthDashboard\Channels\EmailChannel;
use OpsHealthDashboard\Interfaces\AlertChannelInterface;
use OpsHealthDashboard\Interfaces\StorageInterface;
use PHPUnit\Framework\TestCase;

/**
 * Class EmailChannelTest
 *
 * Test unitari per il canale di alert Email.
 */
class EmailChannelTest extends TestCase {

	use MockeryPHPUnitIntegration;

	/**
	 * Setup Brain\Monkey
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	/**
	 * Teardown Brain\Monkey
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Crea un mock di StorageInterface
	 *
	 * @param array $settings Impostazioni da ritornare.
	 * @return \Mockery\MockInterface|StorageInterface
	 */
	private function create_storage_mock( array $settings = [] ) {
		$storage = Mockery::mock( StorageInterface::class );
		$storage->shouldReceive( 'get' )
			->with( 'alert_settings', Mockery::any() )
			->andReturn( $settings );
		return $storage;
	}

	/**
	 * Crea un payload di test
	 *
	 * @param array $overrides Chiavi da sovrascrivere.
	 * @return array
	 */
	private function create_test_payload( array $overrides = [] ) {
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
	private function mock_i18n() {
		Functions\when( '__' )->returnArg( 1 );
		Functions\when( 'esc_html__' )->returnArg( 1 );
	}

	/**
	 * Configura mock is_email che valida formato email
	 *
	 * @return void
	 */
	private function mock_is_email() {
		Functions\when( 'is_email' )->alias(
			function ( $email ) {
				return (bool) filter_var( $email, FILTER_VALIDATE_EMAIL );
			}
		);
	}

	// ---------------------------------------------------
	// Pattern enforcement
	// ---------------------------------------------------

	/**
	 * Testa che la classe NON è final
	 *
	 * @return void
	 */
	public function test_class_is_not_final() {
		$reflection = new \ReflectionClass( EmailChannel::class );
		$this->assertFalse( $reflection->isFinal(), 'EmailChannel should NOT be final' );
	}

	/**
	 * Testa che NON esistono metodi static
	 *
	 * @return void
	 */
	public function test_no_static_methods() {
		$reflection = new \ReflectionClass( EmailChannel::class );
		$methods    = $reflection->getMethods( \ReflectionMethod::IS_STATIC );

		$static_methods = array_filter(
			$methods,
			function ( $method ) {
				return strpos( $method->getName(), '__' ) !== 0;
			}
		);

		$this->assertEmpty( $static_methods, 'EmailChannel should have NO static methods' );
	}

	/**
	 * Testa che NON esistono proprietà static
	 *
	 * @return void
	 */
	public function test_no_static_properties() {
		$reflection = new \ReflectionClass( EmailChannel::class );
		$properties = $reflection->getProperties( \ReflectionProperty::IS_STATIC );

		$this->assertEmpty( $properties, 'EmailChannel should have NO static properties' );
	}

	/**
	 * Testa che implementa AlertChannelInterface
	 *
	 * @return void
	 */
	public function test_implements_interface() {
		$storage = $this->create_storage_mock();
		$this->assertInstanceOf(
			AlertChannelInterface::class,
			new EmailChannel( $storage )
		);
	}

	// ---------------------------------------------------
	// get_id() / get_name()
	// ---------------------------------------------------

	/**
	 * Testa che get_id ritorna 'email'
	 *
	 * @return void
	 */
	public function test_get_id_returns_email() {
		$channel = new EmailChannel( $this->create_storage_mock() );
		$this->assertEquals( 'email', $channel->get_id() );
	}

	/**
	 * Testa che get_name ritorna stringa tradotta
	 *
	 * @return void
	 */
	public function test_get_name_returns_translated_string() {
		$this->mock_i18n();
		$channel = new EmailChannel( $this->create_storage_mock() );
		$this->assertEquals( 'Email', $channel->get_name() );
	}

	// ---------------------------------------------------
	// is_enabled()
	// ---------------------------------------------------

	/**
	 * Testa is_enabled true con configurazione valida
	 *
	 * @return void
	 */
	public function test_is_enabled_returns_true_when_configured() {
		$settings = [
			'email' => [
				'enabled'    => true,
				'recipients' => 'admin@example.com',
			],
		];
		$channel  = new EmailChannel( $this->create_storage_mock( $settings ) );
		$this->assertTrue( $channel->is_enabled() );
	}

	/**
	 * Testa is_enabled false quando disabilitato
	 *
	 * @return void
	 */
	public function test_is_enabled_returns_false_when_disabled() {
		$settings = [
			'email' => [
				'enabled'    => false,
				'recipients' => 'admin@example.com',
			],
		];
		$channel  = new EmailChannel( $this->create_storage_mock( $settings ) );
		$this->assertFalse( $channel->is_enabled() );
	}

	/**
	 * Testa is_enabled false con recipients vuoti
	 *
	 * @return void
	 */
	public function test_is_enabled_returns_false_with_empty_recipients() {
		$settings = [
			'email' => [
				'enabled'    => true,
				'recipients' => '',
			],
		];
		$channel  = new EmailChannel( $this->create_storage_mock( $settings ) );
		$this->assertFalse( $channel->is_enabled() );
	}

	/**
	 * Testa is_enabled false senza impostazioni
	 *
	 * @return void
	 */
	public function test_is_enabled_returns_false_without_settings() {
		$channel = new EmailChannel( $this->create_storage_mock( [] ) );
		$this->assertFalse( $channel->is_enabled() );
	}

	/**
	 * Testa is_enabled false senza chiave email
	 *
	 * @return void
	 */
	public function test_is_enabled_returns_false_without_email_key() {
		$settings = [ 'slack' => [ 'enabled' => true ] ];
		$channel  = new EmailChannel( $this->create_storage_mock( $settings ) );
		$this->assertFalse( $channel->is_enabled() );
	}

	// ---------------------------------------------------
	// send()
	// ---------------------------------------------------

	/**
	 * Testa invio email con successo
	 *
	 * @return void
	 */
	public function test_send_success() {
		$settings = [
			'email' => [
				'enabled'    => true,
				'recipients' => 'admin@example.com',
			],
		];

		$channel = new EmailChannel( $this->create_storage_mock( $settings ) );
		$payload = $this->create_test_payload();

		$this->mock_i18n();
		$this->mock_is_email();

		Functions\expect( 'wp_mail' )
			->once()
			->with(
				Mockery::on(
					function ( $to ) {
						return is_array( $to )
							&& in_array( 'admin@example.com', $to, true );
					}
				),
				Mockery::type( 'string' ),
				Mockery::type( 'string' )
			)
			->andReturn( true );

		$result = $channel->send( $payload );

		$this->assertTrue( $result['success'] );
		$this->assertNull( $result['error'] );
	}

	/**
	 * Testa invio email con fallimento wp_mail
	 *
	 * @return void
	 */
	public function test_send_failure() {
		$settings = [
			'email' => [
				'enabled'    => true,
				'recipients' => 'admin@example.com',
			],
		];

		$channel = new EmailChannel( $this->create_storage_mock( $settings ) );
		$payload = $this->create_test_payload();

		$this->mock_i18n();
		$this->mock_is_email();

		Functions\expect( 'wp_mail' )
			->once()
			->andReturn( false );

		$result = $channel->send( $payload );

		$this->assertFalse( $result['success'] );
		$this->assertNotNull( $result['error'] );
	}

	/**
	 * Testa che il subject contiene il nome del check e lo status
	 *
	 * @return void
	 */
	public function test_send_subject_contains_check_and_status() {
		$settings = [
			'email' => [
				'enabled'    => true,
				'recipients' => 'ops@example.com',
			],
		];

		$channel = new EmailChannel( $this->create_storage_mock( $settings ) );
		$payload = $this->create_test_payload();

		$this->mock_i18n();
		$this->mock_is_email();

		$captured_subject = null;

		Functions\expect( 'wp_mail' )
			->once()
			->with(
				Mockery::any(),
				Mockery::on(
					function ( $subject ) use ( &$captured_subject ) {
						$captured_subject = $subject;
						return true;
					}
				),
				Mockery::any()
			)
			->andReturn( true );

		$channel->send( $payload );

		$this->assertStringContainsString( 'Database Connection', $captured_subject );
		$this->assertStringContainsString( 'critical', strtolower( $captured_subject ) );
	}

	/**
	 * Testa che il body contiene tutti i campi del payload
	 *
	 * @return void
	 */
	public function test_send_body_contains_payload_fields() {
		$settings = [
			'email' => [
				'enabled'    => true,
				'recipients' => 'ops@example.com',
			],
		];

		$channel = new EmailChannel( $this->create_storage_mock( $settings ) );
		$payload = $this->create_test_payload();

		$this->mock_i18n();
		$this->mock_is_email();

		$captured_body = null;

		Functions\expect( 'wp_mail' )
			->once()
			->with(
				Mockery::any(),
				Mockery::any(),
				Mockery::on(
					function ( $body ) use ( &$captured_body ) {
						$captured_body = $body;
						return true;
					}
				)
			)
			->andReturn( true );

		$channel->send( $payload );

		$this->assertStringContainsString( 'Database Connection', $captured_body );
		$this->assertStringContainsString( 'critical', strtolower( $captured_body ) );
		$this->assertStringContainsString( 'Connection timeout', $captured_body );
		$this->assertStringContainsString( 'https://example.com', $captured_body );
	}

	/**
	 * Testa invio recovery alert
	 *
	 * @return void
	 */
	public function test_send_recovery_alert() {
		$settings = [
			'email' => [
				'enabled'    => true,
				'recipients' => 'ops@example.com',
			],
		];

		$channel = new EmailChannel( $this->create_storage_mock( $settings ) );
		$payload = $this->create_test_payload(
			[
				'current_status'  => 'ok',
				'previous_status' => 'critical',
				'is_recovery'     => true,
				'message'         => 'All checks passed',
			]
		);

		$this->mock_i18n();
		$this->mock_is_email();

		$captured_subject = null;

		Functions\expect( 'wp_mail' )
			->once()
			->with(
				Mockery::any(),
				Mockery::on(
					function ( $subject ) use ( &$captured_subject ) {
						$captured_subject = $subject;
						return true;
					}
				),
				Mockery::any()
			)
			->andReturn( true );

		$channel->send( $payload );

		$this->assertStringContainsString( 'ok', strtolower( $captured_subject ) );
	}

	/**
	 * Testa invio con destinatari multipli
	 *
	 * @return void
	 */
	public function test_send_multiple_recipients() {
		$settings = [
			'email' => [
				'enabled'    => true,
				'recipients' => 'admin@example.com, ops@example.com',
			],
		];

		$channel = new EmailChannel( $this->create_storage_mock( $settings ) );
		$payload = $this->create_test_payload();

		$this->mock_i18n();
		$this->mock_is_email();

		$captured_to = null;

		Functions\expect( 'wp_mail' )
			->once()
			->with(
				Mockery::on(
					function ( $to ) use ( &$captured_to ) {
						$captured_to = $to;
						return true;
					}
				),
				Mockery::any(),
				Mockery::any()
			)
			->andReturn( true );

		$channel->send( $payload );

		$this->assertIsArray( $captured_to );
		$this->assertCount( 2, $captured_to );
		$this->assertContains( 'admin@example.com', $captured_to );
		$this->assertContains( 'ops@example.com', $captured_to );
	}

	/**
	 * Testa che indirizzi email non validi sono filtrati da parse_recipients
	 *
	 * @return void
	 */
	public function test_send_filters_invalid_email_addresses() {
		$settings = [
			'email' => [
				'enabled'    => true,
				'recipients' => 'valid@example.com, not-an-email, also@example.com',
			],
		];

		$channel = new EmailChannel( $this->create_storage_mock( $settings ) );
		$payload = $this->create_test_payload();

		$this->mock_i18n();
		$this->mock_is_email();

		$captured_to = null;

		Functions\expect( 'wp_mail' )
			->once()
			->with(
				Mockery::on(
					function ( $to ) use ( &$captured_to ) {
						$captured_to = $to;
						return true;
					}
				),
				Mockery::any(),
				Mockery::any()
			)
			->andReturn( true );

		$channel->send( $payload );

		$this->assertIsArray( $captured_to );
		$this->assertCount( 2, $captured_to );
		$this->assertContains( 'valid@example.com', $captured_to );
		$this->assertContains( 'also@example.com', $captured_to );
		$this->assertNotContains( 'not-an-email', $captured_to );
	}
}
