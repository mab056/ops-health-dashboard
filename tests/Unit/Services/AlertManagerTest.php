<?php
/**
 * Test per AlertManager Service
 *
 * Verifica rilevazione cambiamenti di stato, cooldown e dispatch ai canali.
 *
 * @package OpsHealthDashboard\Tests\Unit\Services
 */

namespace OpsHealthDashboard\Tests\Unit\Services;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use OpsHealthDashboard\Interfaces\AlertChannelInterface;
use OpsHealthDashboard\Interfaces\AlertManagerInterface;
use OpsHealthDashboard\Interfaces\RedactionInterface;
use OpsHealthDashboard\Interfaces\StorageInterface;
use OpsHealthDashboard\Services\AlertManager;
use PHPUnit\Framework\TestCase;

/**
 * Class AlertManagerTest
 *
 * Test unitari per il servizio AlertManager.
 */
class AlertManagerTest extends TestCase {

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
	 * @return \Mockery\MockInterface|StorageInterface
	 */
	private function create_storage_mock() {
		$storage = Mockery::mock( StorageInterface::class );
		$storage->shouldReceive( 'get' )
			->with( 'alert_settings', Mockery::any() )
			->andReturn( [ 'cooldown_minutes' => 60 ] )
			->byDefault();
		$storage->shouldReceive( 'get' )
			->with( 'alert_log', Mockery::any() )
			->andReturn( [] )
			->byDefault();
		$storage->shouldReceive( 'set' )->andReturn( true )->byDefault();
		return $storage;
	}

	/**
	 * Crea un mock di RedactionInterface
	 *
	 * @return \Mockery\MockInterface|RedactionInterface
	 */
	private function create_redaction_mock() {
		$redaction = Mockery::mock( RedactionInterface::class );
		$redaction->shouldReceive( 'redact' )
			->andReturnUsing(
				function ( $text ) {
					return $text;
				}
			);
		return $redaction;
	}

	/**
	 * Crea un mock di AlertChannelInterface
	 *
	 * @param bool $enabled Se il canale è abilitato.
	 * @param bool $success Se l'invio ha successo.
	 * @return \Mockery\MockInterface|AlertChannelInterface
	 */
	private function create_channel_mock( bool $enabled = true, bool $success = true ) {
		$channel = Mockery::mock( AlertChannelInterface::class );
		$channel->shouldReceive( 'get_id' )->andReturn( 'test_channel' )->byDefault();
		$channel->shouldReceive( 'get_name' )->andReturn( 'Test Channel' )->byDefault();
		$channel->shouldReceive( 'is_enabled' )->andReturn( $enabled )->byDefault();
		$channel->shouldReceive( 'send' )
			->andReturn( [ 'success' => $success, 'error' => $success ? null : 'Send failed' ] )
			->byDefault();
		return $channel;
	}

	/**
	 * Crea risultati di check per i test
	 *
	 * @param string $status Status del check.
	 * @return array
	 */
	private function create_check_result( string $status ) {
		return [
			'status'   => $status,
			'message'  => "Check is {$status}",
			'name'     => 'Database Connection',
			'details'  => [],
			'duration' => 0.01,
		];
	}

	/**
	 * Configura mock per transient di cooldown
	 *
	 * @param bool $in_cooldown Se il cooldown è attivo.
	 * @return void
	 */
	private function mock_cooldown( bool $in_cooldown = false ) {
		Functions\when( 'get_transient' )
			->justReturn( $in_cooldown ? time() : false );
		Functions\when( 'set_transient' )->justReturn( true );
	}

	/**
	 * Configura mock i18n e utilità
	 *
	 * @return void
	 */
	private function mock_i18n_and_utils() {
		Functions\when( '__' )->returnArg( 1 );
		Functions\when( 'esc_html__' )->returnArg( 1 );
		Functions\when( 'home_url' )->justReturn( 'https://example.com' );
		Functions\when( 'get_bloginfo' )->justReturn( 'Test Site' );
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
		$reflection = new \ReflectionClass( AlertManager::class );
		$this->assertFalse( $reflection->isFinal(), 'AlertManager should NOT be final' );
	}

	/**
	 * Testa che NON esistono metodi static
	 *
	 * @return void
	 */
	public function test_no_static_methods() {
		$reflection = new \ReflectionClass( AlertManager::class );
		$methods    = $reflection->getMethods( \ReflectionMethod::IS_STATIC );

		$static_methods = array_filter(
			$methods,
			function ( $method ) {
				return strpos( $method->getName(), '__' ) !== 0;
			}
		);

		$this->assertEmpty( $static_methods, 'AlertManager should have NO static methods' );
	}

	/**
	 * Testa che NON esistono proprietà static
	 *
	 * @return void
	 */
	public function test_no_static_properties() {
		$reflection = new \ReflectionClass( AlertManager::class );
		$properties = $reflection->getProperties( \ReflectionProperty::IS_STATIC );

		$this->assertEmpty( $properties, 'AlertManager should have NO static properties' );
	}

	/**
	 * Testa che implementa AlertManagerInterface
	 *
	 * @return void
	 */
	public function test_implements_interface() {
		$this->assertInstanceOf(
			AlertManagerInterface::class,
			new AlertManager(
				$this->create_storage_mock(),
				$this->create_redaction_mock()
			)
		);
	}

	// ---------------------------------------------------
	// add_channel()
	// ---------------------------------------------------

	/**
	 * Testa add_channel aggiunge un canale
	 *
	 * @return void
	 */
	public function test_add_channel() {
		$manager = new AlertManager(
			$this->create_storage_mock(),
			$this->create_redaction_mock()
		);
		$channel = $this->create_channel_mock();

		// Non deve lanciare eccezioni.
		$manager->add_channel( $channel );
		$this->assertInstanceOf( AlertManager::class, $manager );
	}

	// ---------------------------------------------------
	// process() - Nessun cambiamento
	// ---------------------------------------------------

	/**
	 * Testa process senza cambiamenti di stato (ok->ok)
	 *
	 * @return void
	 */
	public function test_process_no_state_change_returns_empty() {
		$manager = new AlertManager(
			$this->create_storage_mock(),
			$this->create_redaction_mock()
		);

		$channel = $this->create_channel_mock();
		$channel->shouldNotReceive( 'send' );
		$manager->add_channel( $channel );

		$this->mock_cooldown( false );
		$this->mock_i18n_and_utils();

		$current  = [ 'database' => $this->create_check_result( 'ok' ) ];
		$previous = [ 'database' => $this->create_check_result( 'ok' ) ];

		$result = $manager->process( $current, $previous );
		$this->assertEmpty( $result );
	}

	/**
	 * Testa process senza cambiamenti di stato (critical->critical)
	 *
	 * @return void
	 */
	public function test_process_no_change_critical_to_critical() {
		$manager = new AlertManager(
			$this->create_storage_mock(),
			$this->create_redaction_mock()
		);

		$channel = $this->create_channel_mock();
		$channel->shouldNotReceive( 'send' );
		$manager->add_channel( $channel );

		$this->mock_cooldown( false );
		$this->mock_i18n_and_utils();

		$current  = [ 'database' => $this->create_check_result( 'critical' ) ];
		$previous = [ 'database' => $this->create_check_result( 'critical' ) ];

		$result = $manager->process( $current, $previous );
		$this->assertEmpty( $result );
	}

	// ---------------------------------------------------
	// process() - Rilevazione cambiamenti
	// ---------------------------------------------------

	/**
	 * Testa process rileva ok->critical e invia alert
	 *
	 * @return void
	 */
	public function test_process_detects_ok_to_critical() {
		$manager = new AlertManager(
			$this->create_storage_mock(),
			$this->create_redaction_mock()
		);

		$channel = $this->create_channel_mock( true, true );
		$channel->shouldReceive( 'send' )->once()->andReturn(
			[ 'success' => true, 'error' => null ]
		);
		$manager->add_channel( $channel );

		$this->mock_cooldown( false );
		$this->mock_i18n_and_utils();

		$current  = [ 'database' => $this->create_check_result( 'critical' ) ];
		$previous = [ 'database' => $this->create_check_result( 'ok' ) ];

		$result = $manager->process( $current, $previous );
		$this->assertNotEmpty( $result );
	}

	/**
	 * Testa process rileva ok->warning
	 *
	 * @return void
	 */
	public function test_process_detects_ok_to_warning() {
		$manager = new AlertManager(
			$this->create_storage_mock(),
			$this->create_redaction_mock()
		);

		$channel = $this->create_channel_mock( true, true );
		$channel->shouldReceive( 'send' )->once()->andReturn(
			[ 'success' => true, 'error' => null ]
		);
		$manager->add_channel( $channel );

		$this->mock_cooldown( false );
		$this->mock_i18n_and_utils();

		$current  = [ 'database' => $this->create_check_result( 'warning' ) ];
		$previous = [ 'database' => $this->create_check_result( 'ok' ) ];

		$result = $manager->process( $current, $previous );
		$this->assertNotEmpty( $result );
	}

	/**
	 * Testa process rileva warning->critical
	 *
	 * @return void
	 */
	public function test_process_detects_warning_to_critical() {
		$manager = new AlertManager(
			$this->create_storage_mock(),
			$this->create_redaction_mock()
		);

		$channel = $this->create_channel_mock( true, true );
		$channel->shouldReceive( 'send' )->once()->andReturn(
			[ 'success' => true, 'error' => null ]
		);
		$manager->add_channel( $channel );

		$this->mock_cooldown( false );
		$this->mock_i18n_and_utils();

		$current  = [ 'database' => $this->create_check_result( 'critical' ) ];
		$previous = [ 'database' => $this->create_check_result( 'warning' ) ];

		$result = $manager->process( $current, $previous );
		$this->assertNotEmpty( $result );
	}

	// ---------------------------------------------------
	// process() - Recovery
	// ---------------------------------------------------

	/**
	 * Testa recovery alert critical->ok
	 *
	 * @return void
	 */
	public function test_process_detects_recovery_critical_to_ok() {
		$manager = new AlertManager(
			$this->create_storage_mock(),
			$this->create_redaction_mock()
		);

		$channel = $this->create_channel_mock( true, true );
		$channel->shouldReceive( 'send' )
			->once()
			->with(
				Mockery::on(
					function ( $payload ) {
						return true === $payload['is_recovery'];
					}
				)
			)
			->andReturn( [ 'success' => true, 'error' => null ] );
		$manager->add_channel( $channel );

		$this->mock_cooldown( false );
		$this->mock_i18n_and_utils();

		$current  = [ 'database' => $this->create_check_result( 'ok' ) ];
		$previous = [ 'database' => $this->create_check_result( 'critical' ) ];

		$result = $manager->process( $current, $previous );
		$this->assertNotEmpty( $result );
	}

	/**
	 * Testa recovery alert warning->ok
	 *
	 * @return void
	 */
	public function test_process_detects_recovery_warning_to_ok() {
		$manager = new AlertManager(
			$this->create_storage_mock(),
			$this->create_redaction_mock()
		);

		$channel = $this->create_channel_mock( true, true );
		$channel->shouldReceive( 'send' )
			->once()
			->with(
				Mockery::on(
					function ( $payload ) {
						return true === $payload['is_recovery'];
					}
				)
			)
			->andReturn( [ 'success' => true, 'error' => null ] );
		$manager->add_channel( $channel );

		$this->mock_cooldown( false );
		$this->mock_i18n_and_utils();

		$current  = [ 'database' => $this->create_check_result( 'ok' ) ];
		$previous = [ 'database' => $this->create_check_result( 'warning' ) ];

		$result = $manager->process( $current, $previous );
		$this->assertNotEmpty( $result );
	}

	// ---------------------------------------------------
	// process() - First run
	// ---------------------------------------------------

	/**
	 * Testa primo avvio con tutti ok non manda alert
	 *
	 * @return void
	 */
	public function test_process_first_run_all_ok_no_alert() {
		$manager = new AlertManager(
			$this->create_storage_mock(),
			$this->create_redaction_mock()
		);

		$channel = $this->create_channel_mock();
		$channel->shouldNotReceive( 'send' );
		$manager->add_channel( $channel );

		$this->mock_cooldown( false );
		$this->mock_i18n_and_utils();

		$current = [ 'database' => $this->create_check_result( 'ok' ) ];

		$result = $manager->process( $current, [] );
		$this->assertEmpty( $result );
	}

	/**
	 * Testa primo avvio con critical manda alert
	 *
	 * @return void
	 */
	public function test_process_first_run_critical_sends_alert() {
		$manager = new AlertManager(
			$this->create_storage_mock(),
			$this->create_redaction_mock()
		);

		$channel = $this->create_channel_mock( true, true );
		$channel->shouldReceive( 'send' )->once()->andReturn(
			[ 'success' => true, 'error' => null ]
		);
		$manager->add_channel( $channel );

		$this->mock_cooldown( false );
		$this->mock_i18n_and_utils();

		$current = [ 'database' => $this->create_check_result( 'critical' ) ];

		$result = $manager->process( $current, [] );
		$this->assertNotEmpty( $result );
	}

	// ---------------------------------------------------
	// process() - Cooldown
	// ---------------------------------------------------

	/**
	 * Testa che cooldown impedisce alert ripetuti
	 *
	 * @return void
	 */
	public function test_process_respects_cooldown() {
		$manager = new AlertManager(
			$this->create_storage_mock(),
			$this->create_redaction_mock()
		);

		$channel = $this->create_channel_mock();
		$channel->shouldNotReceive( 'send' );
		$manager->add_channel( $channel );

		$this->mock_cooldown( true );
		$this->mock_i18n_and_utils();

		$current  = [ 'database' => $this->create_check_result( 'critical' ) ];
		$previous = [ 'database' => $this->create_check_result( 'ok' ) ];

		$result = $manager->process( $current, $previous );
		$this->assertEmpty( $result );
	}

	/**
	 * Testa che recovery bypassa il cooldown
	 *
	 * @return void
	 */
	public function test_process_recovery_bypasses_cooldown() {
		$manager = new AlertManager(
			$this->create_storage_mock(),
			$this->create_redaction_mock()
		);

		$channel = $this->create_channel_mock( true, true );
		$channel->shouldReceive( 'send' )->once()->andReturn(
			[ 'success' => true, 'error' => null ]
		);
		$manager->add_channel( $channel );

		$this->mock_cooldown( true );
		$this->mock_i18n_and_utils();

		$current  = [ 'database' => $this->create_check_result( 'ok' ) ];
		$previous = [ 'database' => $this->create_check_result( 'critical' ) ];

		$result = $manager->process( $current, $previous );
		$this->assertNotEmpty( $result );
	}

	// ---------------------------------------------------
	// process() - Canali multipli
	// ---------------------------------------------------

	/**
	 * Testa dispatch a canali multipli abilitati
	 *
	 * @return void
	 */
	public function test_process_sends_to_multiple_channels() {
		$manager = new AlertManager(
			$this->create_storage_mock(),
			$this->create_redaction_mock()
		);

		$channel1 = $this->create_channel_mock( true, true );
		$channel1->shouldReceive( 'get_id' )->andReturn( 'email' );
		$channel1->shouldReceive( 'send' )->once()->andReturn(
			[ 'success' => true, 'error' => null ]
		);

		$channel2 = $this->create_channel_mock( true, true );
		$channel2->shouldReceive( 'get_id' )->andReturn( 'slack' );
		$channel2->shouldReceive( 'send' )->once()->andReturn(
			[ 'success' => true, 'error' => null ]
		);

		$manager->add_channel( $channel1 );
		$manager->add_channel( $channel2 );

		$this->mock_cooldown( false );
		$this->mock_i18n_and_utils();

		$current  = [ 'database' => $this->create_check_result( 'critical' ) ];
		$previous = [ 'database' => $this->create_check_result( 'ok' ) ];

		$result = $manager->process( $current, $previous );
		$this->assertNotEmpty( $result );
	}

	/**
	 * Testa che canali disabilitati sono saltati
	 *
	 * @return void
	 */
	public function test_process_skips_disabled_channels() {
		$manager = new AlertManager(
			$this->create_storage_mock(),
			$this->create_redaction_mock()
		);

		$enabled_channel = $this->create_channel_mock( true, true );
		$enabled_channel->shouldReceive( 'get_id' )->andReturn( 'email' );
		$enabled_channel->shouldReceive( 'send' )->once()->andReturn(
			[ 'success' => true, 'error' => null ]
		);

		$disabled_channel = $this->create_channel_mock( false );
		$disabled_channel->shouldReceive( 'get_id' )->andReturn( 'slack' );
		$disabled_channel->shouldNotReceive( 'send' );

		$manager->add_channel( $enabled_channel );
		$manager->add_channel( $disabled_channel );

		$this->mock_cooldown( false );
		$this->mock_i18n_and_utils();

		$current  = [ 'database' => $this->create_check_result( 'critical' ) ];
		$previous = [ 'database' => $this->create_check_result( 'ok' ) ];

		$result = $manager->process( $current, $previous );
		$this->assertNotEmpty( $result );
	}

	/**
	 * Testa che fallimento canale non blocca altri canali
	 *
	 * @return void
	 */
	public function test_process_continues_on_channel_failure() {
		$manager = new AlertManager(
			$this->create_storage_mock(),
			$this->create_redaction_mock()
		);

		$failing_channel = $this->create_channel_mock( true, false );
		$failing_channel->shouldReceive( 'get_id' )->andReturn( 'email' );
		$failing_channel->shouldReceive( 'send' )->once()->andReturn(
			[ 'success' => false, 'error' => 'SMTP error' ]
		);

		$working_channel = $this->create_channel_mock( true, true );
		$working_channel->shouldReceive( 'get_id' )->andReturn( 'slack' );
		$working_channel->shouldReceive( 'send' )->once()->andReturn(
			[ 'success' => true, 'error' => null ]
		);

		$manager->add_channel( $failing_channel );
		$manager->add_channel( $working_channel );

		$this->mock_cooldown( false );
		$this->mock_i18n_and_utils();

		$current  = [ 'database' => $this->create_check_result( 'critical' ) ];
		$previous = [ 'database' => $this->create_check_result( 'ok' ) ];

		$result = $manager->process( $current, $previous );
		$this->assertNotEmpty( $result );
	}

	// ---------------------------------------------------
	// process() - Payload
	// ---------------------------------------------------

	/**
	 * Testa che il payload contiene tutti i campi richiesti
	 *
	 * @return void
	 */
	public function test_process_payload_contains_required_fields() {
		$manager = new AlertManager(
			$this->create_storage_mock(),
			$this->create_redaction_mock()
		);

		$captured_payload = null;

		$channel = $this->create_channel_mock( true, true );
		$channel->shouldReceive( 'send' )
			->once()
			->with(
				Mockery::on(
					function ( $payload ) use ( &$captured_payload ) {
						$captured_payload = $payload;
						return true;
					}
				)
			)
			->andReturn( [ 'success' => true, 'error' => null ] );
		$manager->add_channel( $channel );

		$this->mock_cooldown( false );
		$this->mock_i18n_and_utils();

		$current  = [ 'database' => $this->create_check_result( 'critical' ) ];
		$previous = [ 'database' => $this->create_check_result( 'ok' ) ];

		$manager->process( $current, $previous );

		$this->assertNotNull( $captured_payload );
		$this->assertArrayHasKey( 'check_id', $captured_payload );
		$this->assertArrayHasKey( 'check_name', $captured_payload );
		$this->assertArrayHasKey( 'previous_status', $captured_payload );
		$this->assertArrayHasKey( 'current_status', $captured_payload );
		$this->assertArrayHasKey( 'message', $captured_payload );
		$this->assertArrayHasKey( 'timestamp', $captured_payload );
		$this->assertArrayHasKey( 'site_url', $captured_payload );
		$this->assertArrayHasKey( 'site_name', $captured_payload );
		$this->assertArrayHasKey( 'is_recovery', $captured_payload );
		$this->assertEquals( 'database', $captured_payload['check_id'] );
		$this->assertEquals( 'critical', $captured_payload['current_status'] );
		$this->assertEquals( 'ok', $captured_payload['previous_status'] );
		$this->assertFalse( $captured_payload['is_recovery'] );
	}

	/**
	 * Testa che recovery payload ha is_recovery=true
	 *
	 * @return void
	 */
	public function test_process_recovery_payload_has_flag() {
		$manager = new AlertManager(
			$this->create_storage_mock(),
			$this->create_redaction_mock()
		);

		$captured_payload = null;

		$channel = $this->create_channel_mock( true, true );
		$channel->shouldReceive( 'send' )
			->once()
			->with(
				Mockery::on(
					function ( $payload ) use ( &$captured_payload ) {
						$captured_payload = $payload;
						return true;
					}
				)
			)
			->andReturn( [ 'success' => true, 'error' => null ] );
		$manager->add_channel( $channel );

		$this->mock_cooldown( false );
		$this->mock_i18n_and_utils();

		$current  = [ 'database' => $this->create_check_result( 'ok' ) ];
		$previous = [ 'database' => $this->create_check_result( 'critical' ) ];

		$manager->process( $current, $previous );

		$this->assertNotNull( $captured_payload );
		$this->assertTrue( $captured_payload['is_recovery'] );
	}

	// ---------------------------------------------------
	// process() - Log e storage
	// ---------------------------------------------------

	/**
	 * Testa che il log viene salvato nello storage
	 *
	 * @return void
	 */
	public function test_process_logs_alert_to_storage() {
		$storage = $this->create_storage_mock();
		$storage->shouldReceive( 'set' )
			->with( 'alert_log', Mockery::type( 'array' ) )
			->once()
			->andReturn( true );

		$manager = new AlertManager( $storage, $this->create_redaction_mock() );

		$channel = $this->create_channel_mock( true, true );
		$channel->shouldReceive( 'send' )->once()->andReturn(
			[ 'success' => true, 'error' => null ]
		);
		$manager->add_channel( $channel );

		$this->mock_cooldown( false );
		$this->mock_i18n_and_utils();

		$current  = [ 'database' => $this->create_check_result( 'critical' ) ];
		$previous = [ 'database' => $this->create_check_result( 'ok' ) ];

		$manager->process( $current, $previous );
	}

	/**
	 * Testa che log è limitato a MAX_LOG_ENTRIES
	 *
	 * @return void
	 */
	public function test_process_caps_log_at_max_entries() {
		$existing_log = array_fill( 0, 50, [ 'check_id' => 'old', 'timestamp' => 1 ] );

		$storage = $this->create_storage_mock();
		$storage->shouldReceive( 'get' )
			->with( 'alert_log', Mockery::any() )
			->andReturn( $existing_log );
		$storage->shouldReceive( 'set' )
			->with(
				'alert_log',
				Mockery::on(
					function ( $log ) {
						return is_array( $log ) && count( $log ) <= 50;
					}
				)
			)
			->once()
			->andReturn( true );

		$manager = new AlertManager( $storage, $this->create_redaction_mock() );

		$channel = $this->create_channel_mock( true, true );
		$channel->shouldReceive( 'send' )->once()->andReturn(
			[ 'success' => true, 'error' => null ]
		);
		$manager->add_channel( $channel );

		$this->mock_cooldown( false );
		$this->mock_i18n_and_utils();

		$current  = [ 'database' => $this->create_check_result( 'critical' ) ];
		$previous = [ 'database' => $this->create_check_result( 'ok' ) ];

		$manager->process( $current, $previous );
	}

	// ---------------------------------------------------
	// process() - Edge cases
	// ---------------------------------------------------

	/**
	 * Testa process con risultati vuoti
	 *
	 * @return void
	 */
	public function test_process_empty_current_results() {
		$manager = new AlertManager(
			$this->create_storage_mock(),
			$this->create_redaction_mock()
		);

		$this->mock_i18n_and_utils();

		$result = $manager->process( [], [] );
		$this->assertEmpty( $result );
	}

	/**
	 * Testa cooldown personalizzato da settings
	 *
	 * @return void
	 */
	public function test_process_uses_custom_cooldown_from_settings() {
		$storage = $this->create_storage_mock();
		$storage->shouldReceive( 'get' )
			->with( 'alert_settings', Mockery::any() )
			->andReturn( [ 'cooldown_minutes' => 30 ] );

		$manager = new AlertManager( $storage, $this->create_redaction_mock() );

		$channel = $this->create_channel_mock( true, true );
		$channel->shouldReceive( 'send' )->once()->andReturn(
			[ 'success' => true, 'error' => null ]
		);
		$manager->add_channel( $channel );

		Functions\expect( 'get_transient' )
			->andReturn( false );
		Functions\expect( 'set_transient' )
			->once()
			->with(
				Mockery::type( 'string' ),
				Mockery::any(),
				1800 // 30 minuti in secondi.
			)
			->andReturn( true );

		$this->mock_i18n_and_utils();

		$current  = [ 'database' => $this->create_check_result( 'critical' ) ];
		$previous = [ 'database' => $this->create_check_result( 'ok' ) ];

		$manager->process( $current, $previous );
	}

	/**
	 * Testa che critical->warning genera alert (peggioramento parziale)
	 *
	 * @return void
	 */
	public function test_process_detects_critical_to_warning() {
		$manager = new AlertManager(
			$this->create_storage_mock(),
			$this->create_redaction_mock()
		);

		$channel = $this->create_channel_mock( true, true );
		$channel->shouldReceive( 'send' )->once()->andReturn(
			[ 'success' => true, 'error' => null ]
		);
		$manager->add_channel( $channel );

		$this->mock_cooldown( false );
		$this->mock_i18n_and_utils();

		$current  = [ 'database' => $this->create_check_result( 'warning' ) ];
		$previous = [ 'database' => $this->create_check_result( 'critical' ) ];

		$result = $manager->process( $current, $previous );
		$this->assertNotEmpty( $result );
	}
}
