<?php
/**
 * Unit Test per AlertSettings
 *
 * Test unitario con Brain\Monkey per AlertSettings.
 *
 * @package OpsHealthDashboard\Tests\Unit\Admin
 */

namespace OpsHealthDashboard\Tests\Unit\Admin;

use Brain\Monkey\Functions;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use OpsHealthDashboard\Admin\AlertSettings;
use OpsHealthDashboard\Interfaces\StorageInterface;
use PHPUnit\Framework\TestCase;

/**
 * Class AlertSettingsTest
 *
 * Unit test per AlertSettings.
 */
class AlertSettingsTest extends TestCase {
	use MockeryPHPUnitIntegration;

	/**
	 * Setup per ogni test
	 */
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();
	}

	/**
	 * Teardown dopo ogni test
	 */
	protected function tearDown(): void {
		unset(
			$_POST['ops_health_alert_action'],
			$_POST['_ops_health_alert_nonce'],
			$_POST['email_enabled'],
			$_POST['email_recipients'],
			$_POST['webhook_enabled'],
			$_POST['webhook_url'],
			$_POST['webhook_secret'],
			$_POST['slack_enabled'],
			$_POST['slack_webhook_url'],
			$_POST['telegram_enabled'],
			$_POST['telegram_bot_token'],
			$_POST['telegram_chat_id'],
			$_POST['whatsapp_enabled'],
			$_POST['whatsapp_webhook_url'],
			$_POST['whatsapp_phone_number'],
			$_POST['whatsapp_api_token'],
			$_POST['cooldown_minutes']
		);
		\Brain\Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Testa che AlertSettings può essere istanziato
	 */
	public function test_can_be_instantiated() {
		$storage  = Mockery::mock( StorageInterface::class );
		$settings = new AlertSettings( $storage );

		$this->assertInstanceOf( AlertSettings::class, $settings );
	}

	// ─── register_hooks ─────────────────────────────────────────────

	/**
	 * Testa che register_hooks registra l'hook admin_enqueue_scripts
	 */
	public function test_register_hooks_adds_admin_enqueue_scripts() {
		$storage  = Mockery::mock( StorageInterface::class );
		$settings = new AlertSettings( $storage );

		$hooks = [];
		Functions\expect( 'add_action' )
			->atLeast()
			->times( 1 )
			->andReturnUsing(
				function ( $hook, $callback ) use ( &$hooks ) {
					$hooks[ $hook ] = $callback;
				}
			);

		$settings->register_hooks();

		$this->assertArrayHasKey( 'admin_enqueue_scripts', $hooks );
		$this->assertSame( [ $settings, 'enqueue_assets' ], $hooks['admin_enqueue_scripts'] );
	}

	// ─── enqueue_assets ─────────────────────────────────────────────

	/**
	 * Testa che enqueue_assets carica CSS e JS sulla schermata alert settings
	 */
	public function test_enqueue_assets_on_alert_settings_screen() {
		if ( ! defined( 'OPS_HEALTH_DASHBOARD_FILE' ) ) {
			define( 'OPS_HEALTH_DASHBOARD_FILE', '/fake/ops-health-dashboard.php' );
		}
		if ( ! defined( 'OPS_HEALTH_DASHBOARD_VERSION' ) ) {
			define( 'OPS_HEALTH_DASHBOARD_VERSION', '0.6.0' );
		}

		$screen     = new \stdClass();
		$screen->id = AlertSettings::SCREEN_ID;

		Functions\expect( 'current_user_can' )
			->once()
			->with( 'manage_options' )
			->andReturn( true );

		Functions\expect( 'get_current_screen' )
			->once()
			->andReturn( $screen );

		Functions\expect( 'plugin_dir_url' )
			->andReturn( 'http://example.com/wp-content/plugins/ops-health-dashboard/' );

		Functions\expect( 'wp_enqueue_style' )
			->once()
			->with(
				'ops-health-alert-settings',
				Mockery::type( 'string' ),
				[],
				Mockery::type( 'string' )
			);

		Functions\expect( 'wp_enqueue_script' )
			->once()
			->with(
				'ops-health-alert-settings',
				Mockery::type( 'string' ),
				[],
				Mockery::type( 'string' ),
				true
			);

		$storage  = Mockery::mock( StorageInterface::class );
		$settings = new AlertSettings( $storage );
		$settings->enqueue_assets();
	}

	/**
	 * Testa che enqueue_assets NON carica per utenti non admin
	 */
	public function test_enqueue_assets_skips_for_non_admin() {
		Functions\expect( 'current_user_can' )
			->once()
			->with( 'manage_options' )
			->andReturn( false );

		$storage  = Mockery::mock( StorageInterface::class );
		$settings = new AlertSettings( $storage );
		$settings->enqueue_assets();

		$this->assertInstanceOf( AlertSettings::class, $settings );
	}

	/**
	 * Testa che enqueue_assets NON carica su schermate diverse
	 */
	public function test_enqueue_assets_skips_on_other_screens() {
		$screen     = new \stdClass();
		$screen->id = 'plugins';

		Functions\expect( 'current_user_can' )
			->once()
			->with( 'manage_options' )
			->andReturn( true );

		Functions\expect( 'get_current_screen' )
			->once()
			->andReturn( $screen );

		$storage  = Mockery::mock( StorageInterface::class );
		$settings = new AlertSettings( $storage );
		$settings->enqueue_assets();

		$this->assertInstanceOf( AlertSettings::class, $settings );
	}

	/**
	 * Testa che enqueue_assets gestisce screen null
	 */
	public function test_enqueue_assets_handles_null_screen() {
		Functions\expect( 'current_user_can' )
			->once()
			->with( 'manage_options' )
			->andReturn( true );

		Functions\expect( 'get_current_screen' )
			->once()
			->andReturn( null );

		$storage  = Mockery::mock( StorageInterface::class );
		$settings = new AlertSettings( $storage );
		$settings->enqueue_assets();

		$this->assertInstanceOf( AlertSettings::class, $settings );
	}

	// ─── render: collapsible sections ──────────────────────────────

	/**
	 * Testa che render mostra sezioni collassabili con details/summary
	 */
	public function test_render_shows_collapsible_sections() {
		$storage = Mockery::mock( StorageInterface::class );
		$storage->shouldReceive( 'get' )
			->with( 'alert_settings', [] )
			->andReturn( [] );

		$this->mock_render_functions();

		$settings = new AlertSettings( $storage );

		ob_start();
		$settings->render();
		$output = ob_get_clean();

		$this->assertStringContainsString( '<details', $output );
		$this->assertStringContainsString( '<summary>', $output );
		$this->assertStringContainsString( 'ops-health-alert-section', $output );
	}

	/**
	 * Testa che canale abilitato ha sezione aperta e badge Enabled
	 */
	public function test_render_enabled_channel_has_open_section_and_badge() {
		$storage = Mockery::mock( StorageInterface::class );
		$storage->shouldReceive( 'get' )
			->with( 'alert_settings', [] )
			->andReturn( [
				'email' => [
					'enabled'    => true,
					'recipients' => 'test@example.com',
				],
			] );

		$this->mock_render_functions();

		$settings = new AlertSettings( $storage );

		ob_start();
		$settings->render();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'open', $output );
		$this->assertStringContainsString( 'ops-health-alert-status-enabled', $output );
		$this->assertStringContainsString( 'Enabled', $output );
	}

	/**
	 * Testa che canale disabilitato ha badge Disabled senza open
	 */
	public function test_render_disabled_channel_has_disabled_badge() {
		$storage = Mockery::mock( StorageInterface::class );
		$storage->shouldReceive( 'get' )
			->with( 'alert_settings', [] )
			->andReturn( [] );

		$this->mock_render_functions();

		$settings = new AlertSettings( $storage );

		ob_start();
		$settings->render();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'ops-health-alert-status-disabled', $output );
		$this->assertStringContainsString( 'Disabled', $output );
	}

	/**
	 * Testa che ogni canale ha un ID univoco sulla sezione details
	 */
	public function test_render_each_channel_has_unique_id() {
		$storage = Mockery::mock( StorageInterface::class );
		$storage->shouldReceive( 'get' )
			->with( 'alert_settings', [] )
			->andReturn( [] );

		$this->mock_render_functions();

		$settings = new AlertSettings( $storage );

		ob_start();
		$settings->render();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'id="alert-email"', $output );
		$this->assertStringContainsString( 'id="alert-webhook"', $output );
		$this->assertStringContainsString( 'id="alert-slack"', $output );
		$this->assertStringContainsString( 'id="alert-telegram"', $output );
		$this->assertStringContainsString( 'id="alert-whatsapp"', $output );
	}

	/**
	 * Testa che General (cooldown) è fuori dalle sezioni collassabili
	 */
	public function test_render_general_section_is_not_collapsible() {
		$storage = Mockery::mock( StorageInterface::class );
		$storage->shouldReceive( 'get' )
			->with( 'alert_settings', [] )
			->andReturn( [] );

		$this->mock_render_functions();

		$settings = new AlertSettings( $storage );

		ob_start();
		$settings->render();
		$output = ob_get_clean();

		// General è prima delle sezioni details e non wrappato in details.
		$general_pos = strpos( $output, 'General' );
		$first_details_pos = strpos( $output, '<details' );
		$this->assertNotFalse( $general_pos );
		$this->assertNotFalse( $first_details_pos );
		$this->assertLessThan( $first_details_pos, $general_pos );
	}

	// ─── process_actions ────────────────────────────────────────────

	/**
	 * Testa che process_actions() ritorna subito senza POST action
	 */
	public function test_process_actions_returns_early_without_post_action() {
		$storage = Mockery::mock( StorageInterface::class );
		$storage->shouldNotReceive( 'set' );

		unset( $_POST['ops_health_alert_action'] );

		$settings = new AlertSettings( $storage );
		$settings->process_actions();

		$this->assertInstanceOf( AlertSettings::class, $settings );
	}

	/**
	 * Testa che process_actions() ritorna subito con nonce invalido
	 */
	public function test_process_actions_returns_early_with_invalid_nonce() {
		$storage = Mockery::mock( StorageInterface::class );
		$storage->shouldNotReceive( 'set' );

		$_POST['ops_health_alert_action'] = 'save';
		$_POST['_ops_health_alert_nonce'] = 'invalid';

		$this->mock_process_functions();

		Functions\expect( 'wp_verify_nonce' )
			->once()
			->andReturn( false );

		$settings = new AlertSettings( $storage );
		$settings->process_actions();

		$this->assertInstanceOf( AlertSettings::class, $settings );
	}

	/**
	 * Testa che process_actions() ritorna subito senza capability
	 */
	public function test_process_actions_returns_early_without_capability() {
		$storage = Mockery::mock( StorageInterface::class );
		$storage->shouldNotReceive( 'set' );

		$_POST['ops_health_alert_action'] = 'save';
		$_POST['_ops_health_alert_nonce'] = 'valid';

		$this->mock_process_functions();

		Functions\expect( 'wp_verify_nonce' )
			->once()
			->andReturn( 1 );

		Functions\expect( 'current_user_can' )
			->once()
			->with( 'manage_options' )
			->andReturn( false );

		$settings = new AlertSettings( $storage );
		$settings->process_actions();

		$this->assertInstanceOf( AlertSettings::class, $settings );
	}

	/**
	 * Testa che process_actions() salva le impostazioni email
	 */
	public function test_process_actions_saves_email_settings() {
		$storage = Mockery::mock( StorageInterface::class );
		$storage->shouldReceive( 'get' )->with( 'alert_settings', [] )->andReturn( [] );

		$_POST['ops_health_alert_action'] = 'save';
		$_POST['_ops_health_alert_nonce'] = 'valid';
		$_POST['email_enabled']           = '1';
		$_POST['email_recipients']        = 'admin@example.com, ops@example.com';

		$this->mock_save_functions();

		$storage->shouldReceive( 'set' )
			->once()
			->with(
				'alert_settings',
				Mockery::on( function ( $data ) {
					return isset( $data['email']['enabled'] )
						&& true === $data['email']['enabled']
						&& 'admin@example.com, ops@example.com' === $data['email']['recipients'];
				} )
			);

		$screen = $this->create_testable_settings( $storage );
		$screen->process_actions();

		$this->assertInstanceOf( AlertSettings::class, $screen );
	}

	/**
	 * Testa che process_actions() salva le impostazioni webhook
	 */
	public function test_process_actions_saves_webhook_settings() {
		$storage = Mockery::mock( StorageInterface::class );
		$storage->shouldReceive( 'get' )->with( 'alert_settings', [] )->andReturn( [] );

		$_POST['ops_health_alert_action'] = 'save';
		$_POST['_ops_health_alert_nonce'] = 'valid';
		$_POST['webhook_enabled']         = '1';
		$_POST['webhook_url']             = 'https://hooks.example.com/alert';
		$_POST['webhook_secret']          = 'my-secret-key';

		$this->mock_save_functions();

		$storage->shouldReceive( 'set' )
			->once()
			->with(
				'alert_settings',
				Mockery::on( function ( $data ) {
					return isset( $data['webhook']['enabled'] )
						&& true === $data['webhook']['enabled']
						&& 'https://hooks.example.com/alert' === $data['webhook']['url']
						&& 'my-secret-key' === $data['webhook']['secret'];
				} )
			);

		$screen = $this->create_testable_settings( $storage );
		$screen->process_actions();

		$this->assertInstanceOf( AlertSettings::class, $screen );
	}

	/**
	 * Testa che process_actions() salva le impostazioni Slack
	 */
	public function test_process_actions_saves_slack_settings() {
		$storage = Mockery::mock( StorageInterface::class );
		$storage->shouldReceive( 'get' )->with( 'alert_settings', [] )->andReturn( [] );

		$_POST['ops_health_alert_action'] = 'save';
		$_POST['_ops_health_alert_nonce'] = 'valid';
		$_POST['slack_enabled']           = '1';
		$_POST['slack_webhook_url']       = 'https://hooks.slack.com/services/T/B/X';

		$this->mock_save_functions();

		$storage->shouldReceive( 'set' )
			->once()
			->with(
				'alert_settings',
				Mockery::on( function ( $data ) {
					return isset( $data['slack']['enabled'] )
						&& true === $data['slack']['enabled']
						&& 'https://hooks.slack.com/services/T/B/X' === $data['slack']['webhook_url'];
				} )
			);

		$screen = $this->create_testable_settings( $storage );
		$screen->process_actions();

		$this->assertInstanceOf( AlertSettings::class, $screen );
	}

	/**
	 * Testa che process_actions() salva le impostazioni Telegram
	 */
	public function test_process_actions_saves_telegram_settings() {
		$storage = Mockery::mock( StorageInterface::class );
		$storage->shouldReceive( 'get' )->with( 'alert_settings', [] )->andReturn( [] );

		$_POST['ops_health_alert_action'] = 'save';
		$_POST['_ops_health_alert_nonce'] = 'valid';
		$_POST['telegram_enabled']        = '1';
		$_POST['telegram_bot_token']      = '123456:ABC-DEF';
		$_POST['telegram_chat_id']        = '-100123456';

		$this->mock_save_functions();

		$storage->shouldReceive( 'set' )
			->once()
			->with(
				'alert_settings',
				Mockery::on( function ( $data ) {
					return isset( $data['telegram']['enabled'] )
						&& true === $data['telegram']['enabled']
						&& '123456:ABC-DEF' === $data['telegram']['bot_token']
						&& '-100123456' === $data['telegram']['chat_id'];
				} )
			);

		$screen = $this->create_testable_settings( $storage );
		$screen->process_actions();

		$this->assertInstanceOf( AlertSettings::class, $screen );
	}

	/**
	 * Testa che process_actions() salva le impostazioni WhatsApp
	 */
	public function test_process_actions_saves_whatsapp_settings() {
		$storage = Mockery::mock( StorageInterface::class );
		$storage->shouldReceive( 'get' )->with( 'alert_settings', [] )->andReturn( [] );

		$_POST['ops_health_alert_action']  = 'save';
		$_POST['_ops_health_alert_nonce']  = 'valid';
		$_POST['whatsapp_enabled']         = '1';
		$_POST['whatsapp_webhook_url']     = 'https://api.twilio.com/whatsapp';
		$_POST['whatsapp_phone_number']    = '+391234567890';
		$_POST['whatsapp_api_token']       = 'token-xyz';

		$this->mock_save_functions();

		$storage->shouldReceive( 'set' )
			->once()
			->with(
				'alert_settings',
				Mockery::on( function ( $data ) {
					return isset( $data['whatsapp']['enabled'] )
						&& true === $data['whatsapp']['enabled']
						&& 'https://api.twilio.com/whatsapp' === $data['whatsapp']['webhook_url']
						&& '+391234567890' === $data['whatsapp']['phone_number']
						&& 'token-xyz' === $data['whatsapp']['api_token'];
				} )
			);

		$screen = $this->create_testable_settings( $storage );
		$screen->process_actions();

		$this->assertInstanceOf( AlertSettings::class, $screen );
	}

	/**
	 * Testa che process_actions() salva il cooldown
	 */
	public function test_process_actions_saves_cooldown_minutes() {
		$storage = Mockery::mock( StorageInterface::class );
		$storage->shouldReceive( 'get' )->with( 'alert_settings', [] )->andReturn( [] );

		$_POST['ops_health_alert_action'] = 'save';
		$_POST['_ops_health_alert_nonce'] = 'valid';
		$_POST['cooldown_minutes']        = '30';

		$this->mock_save_functions();

		$storage->shouldReceive( 'set' )
			->once()
			->with(
				'alert_settings',
				Mockery::on( function ( $data ) {
					return isset( $data['cooldown_minutes'] )
						&& 30 === $data['cooldown_minutes'];
				} )
			);

		$screen = $this->create_testable_settings( $storage );
		$screen->process_actions();

		$this->assertInstanceOf( AlertSettings::class, $screen );
	}

	/**
	 * Testa che cooldown negativo viene convertito in valore assoluto (absint)
	 */
	public function test_process_actions_absint_negative_cooldown() {
		$storage = Mockery::mock( StorageInterface::class );
		$storage->shouldReceive( 'get' )->with( 'alert_settings', [] )->andReturn( [] );

		$_POST['ops_health_alert_action'] = 'save';
		$_POST['_ops_health_alert_nonce'] = 'valid';
		$_POST['cooldown_minutes']        = '-5';

		$this->mock_save_functions();

		$storage->shouldReceive( 'set' )
			->once()
			->with(
				'alert_settings',
				Mockery::on( function ( $data ) {
					// absint(-5) = 5.
					return isset( $data['cooldown_minutes'] )
						&& 5 === $data['cooldown_minutes'];
				} )
			);

		$screen = $this->create_testable_settings( $storage );
		$screen->process_actions();

		$this->assertInstanceOf( AlertSettings::class, $screen );
	}

	/**
	 * Testa che canale disabilitato ha enabled=false
	 */
	public function test_process_actions_disabled_channel_has_false_enabled() {
		$storage = Mockery::mock( StorageInterface::class );
		$storage->shouldReceive( 'get' )->with( 'alert_settings', [] )->andReturn( [] );

		$_POST['ops_health_alert_action'] = 'save';
		$_POST['_ops_health_alert_nonce'] = 'valid';
		// Non imposta email_enabled → deve essere false.

		$this->mock_save_functions();

		$storage->shouldReceive( 'set' )
			->once()
			->with(
				'alert_settings',
				Mockery::on( function ( $data ) {
					return isset( $data['email'] )
						&& false === $data['email']['enabled'];
				} )
			);

		$screen = $this->create_testable_settings( $storage );
		$screen->process_actions();

		$this->assertInstanceOf( AlertSettings::class, $screen );
	}

	/**
	 * Testa che process_actions() reindirizza dopo il salvataggio
	 */
	public function test_process_actions_redirects_after_save() {
		$storage = Mockery::mock( StorageInterface::class );
		$storage->shouldReceive( 'get' )->with( 'alert_settings', [] )->andReturn( [] );
		$storage->shouldReceive( 'set' )->once();

		$_POST['ops_health_alert_action'] = 'save';
		$_POST['_ops_health_alert_nonce'] = 'valid';

		$this->mock_process_functions();

		Functions\expect( 'wp_verify_nonce' )->andReturn( 1 );
		Functions\expect( 'current_user_can' )->andReturn( true );
		Functions\expect( '__' )->andReturnUsing( function ( $t ) {
			return $t;
		} );

		Functions\expect( 'esc_url_raw' )
			->andReturnUsing( function ( $url ) {
				return $url;
			} );

		Functions\expect( 'set_transient' )
			->once()
			->with( 'ops_health_alert_notice', Mockery::type( 'string' ), 30 );

		Functions\expect( 'admin_url' )
			->once()
			->with( 'admin.php?page=ops-health-alert-settings' )
			->andReturn( 'http://example.com/wp-admin/admin.php?page=ops-health-alert-settings' );

		Functions\expect( 'wp_safe_redirect' )
			->once()
			->with( 'http://example.com/wp-admin/admin.php?page=ops-health-alert-settings' );

		$screen = $this->create_testable_settings( $storage );
		$screen->process_actions();

		$this->assertInstanceOf( AlertSettings::class, $screen );
	}

	/**
	 * Testa che render() blocca utenti senza capability
	 */
	public function test_render_blocks_unauthorized_users() {
		$storage = Mockery::mock( StorageInterface::class );

		Functions\expect( 'current_user_can' )
			->once()
			->with( 'manage_options' )
			->andReturn( false );

		Functions\expect( 'esc_html__' )
			->andReturnUsing( function ( $text ) {
				return $text;
			} );

		Functions\expect( 'wp_die' )
			->once()
			->with( Mockery::type( 'string' ) )
			->andReturnUsing( function () {
				throw new \RuntimeException( 'wp_die called' );
			} );

		$settings = new AlertSettings( $storage );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'wp_die called' );
		$settings->render();
	}

	/**
	 * Testa che render() mostra il form con i campi
	 */
	public function test_render_shows_form_fields() {
		$storage = Mockery::mock( StorageInterface::class );
		$storage->shouldReceive( 'get' )
			->with( 'alert_settings', [] )
			->andReturn( [] );

		$this->mock_render_functions();

		$settings = new AlertSettings( $storage );

		ob_start();
		$settings->render();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Alert Settings', $output );
		$this->assertStringContainsString( 'email_enabled', $output );
		$this->assertStringContainsString( 'email_recipients', $output );
		$this->assertStringContainsString( 'webhook_enabled', $output );
		$this->assertStringContainsString( 'webhook_url', $output );
		$this->assertStringContainsString( 'slack_enabled', $output );
		$this->assertStringContainsString( 'telegram_enabled', $output );
		$this->assertStringContainsString( 'whatsapp_enabled', $output );
		$this->assertStringContainsString( 'cooldown_minutes', $output );
	}

	/**
	 * Testa che render() mostra i valori correnti delle impostazioni
	 */
	public function test_render_shows_current_settings() {
		$storage = Mockery::mock( StorageInterface::class );
		$storage->shouldReceive( 'get' )
			->with( 'alert_settings', [] )
			->andReturn( [
				'email' => [
					'enabled'    => true,
					'recipients' => 'test@example.com',
				],
				'cooldown_minutes' => 45,
			] );

		$this->mock_render_functions();

		$settings = new AlertSettings( $storage );

		ob_start();
		$settings->render();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'test@example.com', $output );
		$this->assertStringContainsString( '45', $output );
	}

	/**
	 * Testa che render() contiene campo nonce
	 */
	public function test_render_contains_nonce_field() {
		$storage = Mockery::mock( StorageInterface::class );
		$storage->shouldReceive( 'get' )
			->with( 'alert_settings', [] )
			->andReturn( [] );

		$this->mock_render_functions();

		$settings = new AlertSettings( $storage );

		ob_start();
		$settings->render();
		$output = ob_get_clean();

		$this->assertStringContainsString( '_ops_health_alert_nonce', $output );
	}

	/**
	 * Testa che render() mostra notice quando transient presente
	 */
	public function test_render_shows_notice_when_transient_set() {
		$storage = Mockery::mock( StorageInterface::class );
		$storage->shouldReceive( 'get' )
			->with( 'alert_settings', [] )
			->andReturn( [] );

		Functions\expect( 'current_user_can' )
			->with( 'manage_options' )
			->andReturn( true );

		Functions\when( '__' )->returnArg();

		Functions\expect( 'esc_html__' )
			->andReturnUsing( function ( $text ) {
				return $text;
			} );

		Functions\expect( 'esc_html' )
			->andReturnUsing( function ( $text ) {
				return $text;
			} );

		Functions\expect( 'esc_attr' )
			->andReturnUsing( function ( $text ) {
				return $text;
			} );

		Functions\expect( 'wp_nonce_field' )
			->andReturnUsing( function ( $action, $name ) {
				echo '<input type="hidden" name="' . $name . '" value="nonce_token" />';
			} );

		Functions\expect( 'submit_button' )
			->andReturnUsing( function ( $text, $type, $name ) {
				echo '<input type="submit" name="' . $name . '" value="' . $text . '" />';
			} );

		Functions\expect( 'checked' )
			->andReturn( '' );

		Functions\expect( 'get_transient' )
			->once()
			->with( 'ops_health_alert_notice' )
			->andReturn( 'Settings saved.' );

		Functions\expect( 'delete_transient' )
			->once()
			->with( 'ops_health_alert_notice' );

		$settings = new AlertSettings( $storage );

		ob_start();
		$settings->render();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'notice-success', $output );
		$this->assertStringContainsString( 'Settings saved.', $output );
	}

	/**
	 * Testa che render() gestisce settings non-array dallo storage
	 */
	public function test_render_handles_non_array_settings() {
		$storage = Mockery::mock( StorageInterface::class );
		$storage->shouldReceive( 'get' )
			->with( 'alert_settings', [] )
			->andReturn( 'invalid' );

		$this->mock_render_functions();

		$settings = new AlertSettings( $storage );

		ob_start();
		$settings->render();
		$output = ob_get_clean();

		// Deve comunque renderizzare senza errori.
		$this->assertStringContainsString( 'Alert Settings', $output );
	}

	/**
	 * Testa che render() NON inserisce segreti nel DOM (value="")
	 */
	public function test_render_does_not_prefill_password_fields() {
		$storage = Mockery::mock( StorageInterface::class );
		$storage->shouldReceive( 'get' )
			->with( 'alert_settings', [] )
			->andReturn( [
				'webhook'  => [
					'enabled' => true,
					'url'     => 'https://hooks.example.com',
					'secret'  => 'super-secret-key',
				],
				'telegram' => [
					'enabled'   => true,
					'bot_token' => '123456:ABC-DEF-TOKEN',
					'chat_id'   => '-100123',
				],
				'whatsapp' => [
					'enabled'      => true,
					'webhook_url'  => 'https://wa.example.com',
					'phone_number' => '+391234567890',
					'api_token'    => 'wa-secret-token',
				],
			] );

		$this->mock_render_functions();

		$settings = new AlertSettings( $storage );

		ob_start();
		$settings->render();
		$output = ob_get_clean();

		// Actual secret values must NOT appear in output.
		$this->assertStringNotContainsString( 'super-secret-key', $output );
		$this->assertStringNotContainsString( '123456:ABC-DEF-TOKEN', $output );
		$this->assertStringNotContainsString( 'wa-secret-token', $output );

		// Non-secret values should still be present.
		$this->assertStringContainsString( 'https://hooks.example.com', $output );
		$this->assertStringContainsString( '-100123', $output );
	}

	/**
	 * Testa che build_settings_from_post gestisce existing settings corrotti (non array)
	 */
	public function test_process_actions_handles_corrupted_existing_settings() {
		$storage = Mockery::mock( StorageInterface::class );
		$storage->shouldReceive( 'get' )
			->with( 'alert_settings', [] )
			->andReturn( 'corrupted-string' );

		$_POST['ops_health_alert_action'] = 'save';
		$_POST['_ops_health_alert_nonce'] = 'valid';
		$_POST['email_enabled']           = '1';
		$_POST['email_recipients']        = 'admin@example.com';

		$this->mock_save_functions();

		$storage->shouldReceive( 'set' )
			->once()
			->with(
				'alert_settings',
				Mockery::on( function ( $data ) {
					return isset( $data['email']['enabled'] )
						&& true === $data['email']['enabled']
						&& 'admin@example.com' === $data['email']['recipients'];
				} )
			);

		$screen = $this->create_testable_settings( $storage );
		$screen->process_actions();

		$this->assertInstanceOf( AlertSettings::class, $screen );
	}

	/**
	 * Testa che campo password vuoto preserva il segreto esistente
	 */
	public function test_process_actions_preserves_existing_secret_when_post_empty() {
		$storage = Mockery::mock( StorageInterface::class );
		$storage->shouldReceive( 'get' )
			->with( 'alert_settings', [] )
			->andReturn( [
				'webhook'  => [
					'enabled' => true,
					'url'     => 'https://hooks.example.com',
					'secret'  => 'existing-secret',
				],
				'telegram' => [
					'enabled'   => true,
					'bot_token' => 'existing-bot-token',
					'chat_id'   => '-100',
				],
				'whatsapp' => [
					'enabled'      => true,
					'webhook_url'  => 'https://wa.example.com',
					'phone_number' => '+391234567890',
					'api_token'    => 'existing-wa-token',
				],
			] );

		$_POST['ops_health_alert_action']  = 'save';
		$_POST['_ops_health_alert_nonce']  = 'valid';
		$_POST['webhook_enabled']          = '1';
		$_POST['webhook_url']              = 'https://hooks.example.com';
		$_POST['webhook_secret']           = '';
		$_POST['telegram_enabled']         = '1';
		$_POST['telegram_bot_token']       = '';
		$_POST['telegram_chat_id']         = '-100';
		$_POST['whatsapp_enabled']         = '1';
		$_POST['whatsapp_webhook_url']     = 'https://wa.example.com';
		$_POST['whatsapp_phone_number']    = '+391234567890';
		$_POST['whatsapp_api_token']       = '';

		$this->mock_save_functions();

		$storage->shouldReceive( 'set' )
			->once()
			->with(
				'alert_settings',
				Mockery::on( function ( $data ) {
					return 'existing-secret' === $data['webhook']['secret']
						&& 'existing-bot-token' === $data['telegram']['bot_token']
						&& 'existing-wa-token' === $data['whatsapp']['api_token'];
				} )
			);

		$screen = $this->create_testable_settings( $storage );
		$screen->process_actions();

		$this->assertInstanceOf( AlertSettings::class, $screen );
	}

	// ─── Help tabs ───────────────────────────────────────────────

	/**
	 * Testa che add_help_tabs registra 3 tab
	 */
	public function test_add_help_tabs_registers_three_tabs() {
		$storage = Mockery::mock( StorageInterface::class );

		$screen_mock = Mockery::mock( 'WP_Screen' );
		$screen_mock->shouldReceive( 'add_help_tab' )->times( 3 );
		$screen_mock->shouldReceive( 'set_help_sidebar' )->once();

		Functions\expect( 'get_current_screen' )->once()->andReturn( $screen_mock );
		Functions\when( '__' )->returnArg();
		Functions\expect( 'esc_url' )->andReturnUsing( function ( $url ) {
			return $url;
		} );
		Functions\expect( 'admin_url' )->andReturnUsing( function ( $path ) {
			return 'http://example.com/wp-admin/' . $path;
		} );

		$settings = new AlertSettings( $storage );
		$settings->add_help_tabs();

		$this->assertInstanceOf( AlertSettings::class, $settings );
	}

	/**
	 * Testa che add_help_tabs usa gli ID corretti
	 */
	public function test_add_help_tabs_tab_ids_are_correct() {
		$storage = Mockery::mock( StorageInterface::class );

		$captured_ids = [];
		$screen_mock  = Mockery::mock( 'WP_Screen' );
		$screen_mock->shouldReceive( 'add_help_tab' )
			->times( 3 )
			->andReturnUsing( function ( $args ) use ( &$captured_ids ) {
				$captured_ids[] = $args['id'];
			} );
		$screen_mock->shouldReceive( 'set_help_sidebar' )->once();

		Functions\expect( 'get_current_screen' )->once()->andReturn( $screen_mock );
		Functions\when( '__' )->returnArg();
		Functions\expect( 'esc_url' )->andReturnUsing( function ( $url ) {
			return $url;
		} );
		Functions\expect( 'admin_url' )->andReturnUsing( function ( $path ) {
			return 'http://example.com/wp-admin/' . $path;
		} );

		$settings = new AlertSettings( $storage );
		$settings->add_help_tabs();

		$this->assertSame(
			[ 'ops_health_alert_overview', 'ops_health_alert_channels', 'ops_health_alert_config' ],
			$captured_ids
		);
	}

	/**
	 * Testa che add_help_tabs imposta la sidebar con GitHub e Health Dashboard
	 */
	public function test_add_help_tabs_sets_sidebar() {
		$storage = Mockery::mock( StorageInterface::class );

		$captured_sidebar = '';
		$screen_mock      = Mockery::mock( 'WP_Screen' );
		$screen_mock->shouldReceive( 'add_help_tab' )->times( 3 );
		$screen_mock->shouldReceive( 'set_help_sidebar' )
			->once()
			->andReturnUsing( function ( $html ) use ( &$captured_sidebar ) {
				$captured_sidebar = $html;
			} );

		Functions\expect( 'get_current_screen' )->once()->andReturn( $screen_mock );
		Functions\when( '__' )->returnArg();
		Functions\expect( 'esc_url' )->andReturnUsing( function ( $url ) {
			return $url;
		} );
		Functions\expect( 'admin_url' )->andReturnUsing( function ( $path ) {
			return 'http://example.com/wp-admin/' . $path;
		} );

		$settings = new AlertSettings( $storage );
		$settings->add_help_tabs();

		$this->assertStringContainsString( 'github.com', $captured_sidebar );
		$this->assertStringContainsString( 'ops-health-dashboard', $captured_sidebar );
	}

	/**
	 * Testa che add_help_tabs gestisce screen null senza errori
	 */
	public function test_add_help_tabs_handles_null_screen() {
		$storage = Mockery::mock( StorageInterface::class );

		Functions\expect( 'get_current_screen' )->once()->andReturn( null );

		$settings = new AlertSettings( $storage );
		$settings->add_help_tabs();

		$this->assertInstanceOf( AlertSettings::class, $settings );
	}

	// ─── Pattern enforcement ───────────────────────────────────────

	/**
	 * Testa che la classe NON è final
	 */
	public function test_class_is_not_final() {
		$reflection = new \ReflectionClass( AlertSettings::class );
		$this->assertFalse( $reflection->isFinal(), 'AlertSettings should NOT be final' );
	}

	/**
	 * Testa che NON esistono metodi static
	 */
	public function test_no_static_methods() {
		$reflection = new \ReflectionClass( AlertSettings::class );
		$methods    = $reflection->getMethods( \ReflectionMethod::IS_STATIC );

		$static_methods = array_filter(
			$methods,
			function ( $method ) {
				return strpos( $method->getName(), '__' ) !== 0;
			}
		);

		$this->assertEmpty( $static_methods, 'AlertSettings should have NO static methods' );
	}

	/**
	 * Testa che NON esistono proprietà static
	 */
	public function test_no_static_properties() {
		$reflection = new \ReflectionClass( AlertSettings::class );
		$properties = $reflection->getProperties( \ReflectionProperty::IS_STATIC );

		$this->assertEmpty( $properties, 'AlertSettings should have NO static properties' );
	}

	/**
	 * Helper: mock comuni per process_actions
	 *
	 * @return void
	 */
	private function mock_process_functions() {
		Functions\expect( 'sanitize_text_field' )
			->andReturnUsing( function ( $text ) {
				return $text;
			} );

		Functions\expect( 'wp_unslash' )
			->andReturnUsing( function ( $text ) {
				return $text;
			} );
	}

	/**
	 * Helper: mock completi per salvataggio
	 *
	 * @return void
	 */
	private function mock_save_functions() {
		$this->mock_process_functions();

		Functions\expect( 'wp_verify_nonce' )->andReturn( 1 );
		Functions\expect( 'current_user_can' )->andReturn( true );

		Functions\expect( '__' )->andReturnUsing( function ( $t ) {
			return $t;
		} );

		Functions\expect( 'esc_url_raw' )
			->andReturnUsing( function ( $url ) {
				return $url;
			} );

		Functions\expect( 'absint' )
			->andReturnUsing( function ( $val ) {
				return abs( (int) $val );
			} );

		Functions\expect( 'set_transient' )->andReturn( true );
		Functions\expect( 'admin_url' )->andReturn( 'http://example.com/wp-admin/admin.php?page=ops-health-alert-settings' );
		Functions\expect( 'wp_safe_redirect' )->andReturn( null );
	}

	/**
	 * Helper: crea mock parziale con do_exit() disabilitato
	 *
	 * @param \Mockery\MockInterface $storage Storage mock.
	 * @return \Mockery\MockInterface
	 */
	private function create_testable_settings( $storage ) {
		$screen = Mockery::mock( AlertSettings::class, [ $storage ] )
			->makePartial()
			->shouldAllowMockingProtectedMethods();

		$screen->shouldReceive( 'do_exit' )->andReturnNull();

		return $screen;
	}

	/**
	 * Helper: mock comuni per render
	 *
	 * @return void
	 */
	private function mock_render_functions() {
		Functions\expect( 'current_user_can' )
			->with( 'manage_options' )
			->andReturn( true );

		Functions\when( '__' )->returnArg();

		Functions\expect( 'esc_html__' )
			->andReturnUsing( function ( $text ) {
				return $text;
			} );

		Functions\expect( 'esc_html' )
			->andReturnUsing( function ( $text ) {
				return $text;
			} );

		Functions\expect( 'esc_attr' )
			->andReturnUsing( function ( $text ) {
				return $text;
			} );

		Functions\expect( 'wp_nonce_field' )
			->andReturnUsing( function ( $action, $name ) {
				echo '<input type="hidden" name="' . $name . '" value="nonce_token" />';
			} );

		Functions\expect( 'submit_button' )
			->andReturnUsing( function ( $text, $type, $name ) {
				echo '<input type="submit" name="' . $name . '" value="' . $text . '" />';
			} );

		Functions\expect( 'checked' )
			->andReturn( '' );

		Functions\expect( 'get_transient' )
			->andReturn( false );
	}
}
