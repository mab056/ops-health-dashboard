<?php
/**
 * Integration Test per AlertSettings Admin Page
 *
 * Verifica form processing, nonce, capability, sanitizzazione e render
 * con WordPress reale.
 *
 * @package OpsHealthDashboard\Tests\Integration\Admin
 */

namespace OpsHealthDashboard\Tests\Integration\Admin;

use OpsHealthDashboard\Admin\AlertSettings;
use OpsHealthDashboard\Services\Storage;
use WP_UnitTestCase;

/**
 * Sottoclasse testabile di AlertSettings
 *
 * Override di do_exit() per evitare exit() nei test.
 */
class TestableAlertSettings extends AlertSettings {

	/**
	 * Override do_exit per testabilità
	 *
	 * @return void
	 */
	protected function do_exit(): void {
		// No-op per testabilità.
	}
}

/**
 * Class AlertSettingsTest
 *
 * Integration test per AlertSettings con WordPress reale.
 */
class AlertSettingsTest extends WP_UnitTestCase {

	/**
	 * Storage reale
	 *
	 * @var Storage
	 */
	private $storage;

	/**
	 * Setup
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		$this->storage = new Storage();
		$this->storage->delete( 'alert_settings' );
		delete_transient( 'ops_health_alert_notice' );
		$_POST = [];
	}

	/**
	 * Teardown
	 *
	 * @return void
	 */
	public function tearDown(): void {
		$this->storage->delete( 'alert_settings' );
		delete_transient( 'ops_health_alert_notice' );
		$_POST = [];
		remove_all_filters( 'wp_redirect' );
		parent::tearDown();
	}

	/**
	 * Crea istanza testabile
	 *
	 * @return TestableAlertSettings
	 */
	private function create_screen(): TestableAlertSettings {
		return new TestableAlertSettings( $this->storage );
	}

	/**
	 * Configura utente admin
	 *
	 * @return void
	 */
	private function set_admin_user(): void {
		$user_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );
	}

	/**
	 * Configura POST con nonce valido e azione
	 *
	 * @param array $extra Dati POST aggiuntivi.
	 * @return void
	 */
	private function set_valid_post( array $extra = [] ): void {
		$_POST = array_merge(
			[
				'ops_health_alert_action' => 'save',
				'_ops_health_alert_nonce' => wp_create_nonce( 'ops_health_alert_settings' ),
			],
			$extra
		);
	}

	/**
	 * Testa che la classe NON è final
	 *
	 * @return void
	 */
	public function test_class_is_not_final() {
		$reflection = new \ReflectionClass( AlertSettings::class );
		$this->assertFalse( $reflection->isFinal(), 'Class should NOT be final' );
	}

	/**
	 * Testa che NON esistono metodi static
	 *
	 * @return void
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

		$this->assertEmpty( $static_methods, 'Class should have NO static methods' );
	}

	/**
	 * Testa che NON esistono proprietà static
	 *
	 * @return void
	 */
	public function test_no_static_properties() {
		$reflection = new \ReflectionClass( AlertSettings::class );
		$properties = $reflection->getProperties( \ReflectionProperty::IS_STATIC );

		$this->assertEmpty( $properties, 'Class should have NO static properties' );
	}

	/**
	 * Testa che process_actions ritorna senza POST action
	 *
	 * @return void
	 */
	public function test_process_actions_returns_early_without_post_action() {
		$screen = $this->create_screen();
		$screen->process_actions();

		$saved = $this->storage->get( 'alert_settings', null );
		$this->assertNull( $saved );
	}

	/**
	 * Testa che process_actions ritorna con nonce invalido
	 *
	 * @return void
	 */
	public function test_process_actions_returns_early_with_invalid_nonce() {
		$this->set_admin_user();

		$_POST['ops_health_alert_action']  = 'save';
		$_POST['_ops_health_alert_nonce']  = 'invalid-nonce';

		$screen = $this->create_screen();
		$screen->process_actions();

		$saved = $this->storage->get( 'alert_settings', null );
		$this->assertNull( $saved );
	}

	/**
	 * Testa che process_actions ritorna senza capability
	 *
	 * @return void
	 */
	public function test_process_actions_returns_early_without_capability() {
		$user_id = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $user_id );

		$this->set_valid_post();

		$screen = $this->create_screen();
		$screen->process_actions();

		$saved = $this->storage->get( 'alert_settings', null );
		$this->assertNull( $saved );
	}

	/**
	 * Testa che process_actions salva tutte le impostazioni dei canali
	 *
	 * @return void
	 */
	public function test_process_actions_saves_all_channel_settings() {
		$this->set_admin_user();

		$this->set_valid_post(
			[
				'email_enabled'         => '1',
				'email_recipients'      => 'admin@example.com, ops@example.com',
				'webhook_enabled'       => '1',
				'webhook_url'           => 'https://hooks.example.com/webhook',
				'webhook_secret'        => 'my-secret',
				'slack_enabled'         => '1',
				'slack_webhook_url'     => 'https://hooks.slack.com/services/T00/B00/xxx',
				'telegram_enabled'      => '1',
				'telegram_bot_token'    => '123456:ABC',
				'telegram_chat_id'      => '-100123',
				'whatsapp_enabled'      => '1',
				'whatsapp_webhook_url'  => 'https://api.twilio.com/whatsapp',
				'whatsapp_phone_number' => '+391234567890',
				'whatsapp_api_token'    => 'wa-token',
				'cooldown_minutes'      => '30',
			]
		);

		add_filter( 'wp_redirect', '__return_false' );

		$screen = $this->create_screen();
		$screen->process_actions();

		$saved = $this->storage->get( 'alert_settings', [] );

		$this->assertTrue( $saved['email']['enabled'] );
		$this->assertEquals( 'admin@example.com, ops@example.com', $saved['email']['recipients'] );
		$this->assertTrue( $saved['webhook']['enabled'] );
		$this->assertNotEmpty( $saved['webhook']['url'] );
		$this->assertEquals( 'my-secret', $saved['webhook']['secret'] );
		$this->assertTrue( $saved['slack']['enabled'] );
		$this->assertTrue( $saved['telegram']['enabled'] );
		$this->assertEquals( '123456:ABC', $saved['telegram']['bot_token'] );
		$this->assertEquals( '-100123', $saved['telegram']['chat_id'] );
		$this->assertTrue( $saved['whatsapp']['enabled'] );
		$this->assertEquals( '+391234567890', $saved['whatsapp']['phone_number'] );
		$this->assertEquals( 30, $saved['cooldown_minutes'] );
	}

	/**
	 * Testa che process_actions sanitizza URL con esc_url_raw reale
	 *
	 * @return void
	 */
	public function test_process_actions_sanitizes_urls_with_real_esc_url_raw() {
		$this->set_admin_user();

		$this->set_valid_post(
			[
				'webhook_url'          => 'javascript:alert(1)',
				'slack_webhook_url'    => 'ftp://evil.com',
				'whatsapp_webhook_url' => 'https://valid.example.com/api',
			]
		);

		add_filter( 'wp_redirect', '__return_false' );

		$screen = $this->create_screen();
		$screen->process_actions();

		$saved = $this->storage->get( 'alert_settings', [] );

		// esc_url_raw rimuove schemi non sicuri.
		$this->assertStringNotContainsString( 'javascript', $saved['webhook']['url'] );
		$this->assertEquals( 'https://valid.example.com/api', $saved['whatsapp']['webhook_url'] );
	}

	/**
	 * Testa che process_actions converte cooldown con absint reale
	 *
	 * @return void
	 */
	public function test_process_actions_converts_cooldown_with_real_absint() {
		$this->set_admin_user();

		$this->set_valid_post( [ 'cooldown_minutes' => '-5' ] );

		add_filter( 'wp_redirect', '__return_false' );

		$screen = $this->create_screen();
		$screen->process_actions();

		$saved = $this->storage->get( 'alert_settings', [] );

		// absint(-5) restituisce 5.
		$this->assertEquals( 5, $saved['cooldown_minutes'] );
	}

	/**
	 * Testa che process_actions imposta il transient di successo
	 *
	 * @return void
	 */
	public function test_process_actions_sets_success_transient() {
		$this->set_admin_user();

		$this->set_valid_post();

		add_filter( 'wp_redirect', '__return_false' );

		$screen = $this->create_screen();
		$screen->process_actions();

		$notice = get_transient( 'ops_health_alert_notice' );
		$this->assertNotFalse( $notice );
		$this->assertStringContainsString( 'saved', $notice );
	}

	/**
	 * Testa che canali disabilitati hanno enabled=false
	 *
	 * @return void
	 */
	public function test_process_actions_disabled_channels_saved_as_false() {
		$this->set_admin_user();

		// Non inviamo checkbox enabled per nessun canale.
		$this->set_valid_post();

		add_filter( 'wp_redirect', '__return_false' );

		$screen = $this->create_screen();
		$screen->process_actions();

		$saved = $this->storage->get( 'alert_settings', [] );

		$this->assertFalse( $saved['email']['enabled'] );
		$this->assertFalse( $saved['webhook']['enabled'] );
		$this->assertFalse( $saved['slack']['enabled'] );
		$this->assertFalse( $saved['telegram']['enabled'] );
		$this->assertFalse( $saved['whatsapp']['enabled'] );
	}

	/**
	 * Testa che render output il form per admin
	 *
	 * @return void
	 */
	public function test_render_outputs_form_for_admin() {
		$this->set_admin_user();

		$screen = $this->create_screen();

		ob_start();
		$screen->render();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Alert Settings', $output );
		$this->assertStringContainsString( '<form', $output );
		$this->assertStringContainsString( 'email_enabled', $output );
		$this->assertStringContainsString( 'webhook_enabled', $output );
		$this->assertStringContainsString( 'slack_enabled', $output );
		$this->assertStringContainsString( 'telegram_enabled', $output );
		$this->assertStringContainsString( 'whatsapp_enabled', $output );
		$this->assertStringContainsString( 'cooldown_minutes', $output );
	}

	/**
	 * Testa che render mostra i valori salvati
	 *
	 * @return void
	 */
	public function test_render_shows_saved_values_from_storage() {
		$this->set_admin_user();

		$this->storage->set(
			'alert_settings',
			[
				'email'    => [
					'enabled'    => true,
					'recipients' => 'test@example.com',
				],
				'telegram' => [
					'enabled'   => true,
					'bot_token' => 'token123',
					'chat_id'   => '-999',
				],
			]
		);

		$screen = $this->create_screen();

		ob_start();
		$screen->render();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'test@example.com', $output );
		$this->assertStringContainsString( 'token123', $output );
		$this->assertStringContainsString( '-999', $output );
	}

	/**
	 * Testa che render mostra la notice e la cancella
	 *
	 * @return void
	 */
	public function test_render_shows_notice_and_deletes_transient() {
		$this->set_admin_user();

		set_transient( 'ops_health_alert_notice', 'Alert settings saved.', 30 );

		$screen = $this->create_screen();

		ob_start();
		$screen->render();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Alert settings saved.', $output );
		$this->assertStringContainsString( 'notice-success', $output );

		// Il transient deve essere cancellato dopo il render.
		$this->assertFalse( get_transient( 'ops_health_alert_notice' ) );
	}

	/**
	 * Testa che render nega accesso senza capability
	 *
	 * @return void
	 */
	public function test_render_denies_access_without_capability() {
		$user_id = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $user_id );

		$screen = $this->create_screen();

		$this->expectException( 'WPDieException' );
		$screen->render();
	}

	/**
	 * Testa che render gestisce settings corrotti (non array)
	 *
	 * @return void
	 */
	public function test_render_handles_corrupted_settings() {
		$this->set_admin_user();

		// Corrompi le impostazioni.
		$this->storage->set( 'alert_settings', 'corrupted-string' );

		$screen = $this->create_screen();

		ob_start();
		$screen->render();
		$output = ob_get_clean();

		// Deve renderizzare il form senza errori.
		$this->assertStringContainsString( '<form', $output );
	}
}
