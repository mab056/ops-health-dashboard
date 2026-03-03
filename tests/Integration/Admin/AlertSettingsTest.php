<?php
/**
 * Integration Test for AlertSettings Admin Page
 *
 * Verifies form processing, nonce, capability, sanitization and render
 * with real WordPress.
 *
 * @package OpsHealthDashboard\Tests\Integration\Admin
 */

namespace OpsHealthDashboard\Tests\Integration\Admin;

use OpsHealthDashboard\Admin\AlertSettings;
use OpsHealthDashboard\Services\Storage;
use WP_UnitTestCase;

/**
 * Testable subclass of AlertSettings
 *
 * Override of do_exit() to avoid exit() in tests.
 */
class TestableAlertSettings extends AlertSettings {

	/**
	 * Override do_exit for testability
	 *
	 * @return void
	 */
	protected function do_exit(): void {
		// No-op for testability.
	}
}

/**
 * Class AlertSettingsTest
 *
 * Integration test for AlertSettings with real WordPress.
 */
class AlertSettingsTest extends WP_UnitTestCase {

	/**
	 * Real storage
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
	 * Creates testable instance
	 *
	 * @return TestableAlertSettings
	 */
	private function create_screen(): TestableAlertSettings {
		return new TestableAlertSettings( $this->storage );
	}

	/**
	 * Sets up admin user
	 *
	 * @return void
	 */
	private function set_admin_user(): void {
		$user_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );
	}

	/**
	 * Sets up POST with valid nonce and action
	 *
	 * @param array $extra Additional POST data.
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

	// ─── Help tabs ────────────────────────────────────────────────

	/**
	 * Verifies that add_help_tabs registers 3 tabs on the alert settings screen
	 */
	public function test_add_help_tabs_registers_tabs_on_alert_screen() {
		$this->set_admin_user();
		set_current_screen( 'ops-health_page_ops-health-alert-settings' );

		$screen = new AlertSettings( $this->storage );
		$screen->add_help_tabs();

		$wp_screen = get_current_screen();
		$tabs      = $wp_screen->get_help_tabs();

		$tab_ids = array_column( $tabs, 'id' );
		$this->assertContains( 'ops_health_alert_overview', $tab_ids );
		$this->assertContains( 'ops_health_alert_channels', $tab_ids );
		$this->assertContains( 'ops_health_alert_config', $tab_ids );
	}

	/**
	 * Verifies that add_help_tabs sets the sidebar with GitHub link
	 */
	public function test_add_help_tabs_sets_sidebar_on_alert_screen() {
		$this->set_admin_user();
		set_current_screen( 'ops-health_page_ops-health-alert-settings' );

		$screen = new AlertSettings( $this->storage );
		$screen->add_help_tabs();

		$wp_screen = get_current_screen();
		$sidebar   = $wp_screen->get_help_sidebar();

		$this->assertStringContainsString( 'github.com', $sidebar );
	}

	/**
	 * Verifies that the class is NOT final
	 *
	 * @return void
	 */
	public function test_class_is_not_final() {
		$reflection = new \ReflectionClass( AlertSettings::class );
		$this->assertFalse( $reflection->isFinal(), 'Class should NOT be final' );
	}

	/**
	 * Verifies that NO static methods exist
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
	 * Verifies that NO static properties exist
	 *
	 * @return void
	 */
	public function test_no_static_properties() {
		$reflection = new \ReflectionClass( AlertSettings::class );
		$properties = $reflection->getProperties( \ReflectionProperty::IS_STATIC );

		$this->assertEmpty( $properties, 'Class should have NO static properties' );
	}

	/**
	 * Verifies that process_actions returns without POST action
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
	 * Verifies that process_actions returns with invalid nonce
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
	 * Verifies that process_actions returns without capability
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
	 * Verifies that process_actions saves all channel settings
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
	 * Verifies that process_actions sanitizes URLs with real esc_url_raw
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

		// esc_url_raw removes unsafe schemes.
		$this->assertStringNotContainsString( 'javascript', $saved['webhook']['url'] );
		$this->assertEquals( 'https://valid.example.com/api', $saved['whatsapp']['webhook_url'] );
	}

	/**
	 * Verifies that process_actions converts cooldown with real absint
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

		// absint(-5) returns 5.
		$this->assertEquals( 5, $saved['cooldown_minutes'] );
	}

	/**
	 * Verifies that process_actions sets the success transient
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
	 * Verifies that disabled channels have enabled=false
	 *
	 * @return void
	 */
	public function test_process_actions_disabled_channels_saved_as_false() {
		$this->set_admin_user();

		// Do not send enabled checkbox for any channel.
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
	 * Verifies that render outputs the form for admin
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
	 * Verifies that render shows saved values
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
		// Secret (bot_token) must NOT appear in DOM — only placeholder.
		$this->assertStringNotContainsString( 'token123', $output );
		$this->assertStringContainsString( '********', $output );
		$this->assertStringContainsString( '-999', $output );
	}

	/**
	 * Verifies that render shows the notice and deletes it
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

		// The transient must be deleted after render.
		$this->assertFalse( get_transient( 'ops_health_alert_notice' ) );
	}

	/**
	 * Verifies that render denies access without capability
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
	 * Verifies that process_actions preserves existing secrets when password fields are empty
	 *
	 * @return void
	 */
	public function test_process_actions_preserves_existing_secrets_when_empty() {
		$this->set_admin_user();

		// Save settings with existing secrets.
		$this->storage->set(
			'alert_settings',
			[
				'webhook'  => [
					'enabled' => true,
					'url'     => 'https://hooks.example.com/webhook',
					'secret'  => 'existing-webhook-secret',
				],
				'telegram' => [
					'enabled'   => true,
					'bot_token' => 'existing-bot-token',
					'chat_id'   => '-100123',
				],
				'whatsapp' => [
					'enabled'      => true,
					'webhook_url'  => 'https://api.twilio.com/whatsapp',
					'phone_number' => '+391234567890',
					'api_token'    => 'existing-wa-token',
				],
			]
		);

		// Submit POST with empty password fields (simulates submit without changing password).
		$this->set_valid_post(
			[
				'webhook_enabled'       => '1',
				'webhook_url'           => 'https://hooks.example.com/webhook',
				'webhook_secret'        => '',
				'telegram_enabled'      => '1',
				'telegram_bot_token'    => '',
				'telegram_chat_id'      => '-100123',
				'whatsapp_enabled'      => '1',
				'whatsapp_webhook_url'  => 'https://api.twilio.com/whatsapp',
				'whatsapp_phone_number' => '+391234567890',
				'whatsapp_api_token'    => '',
			]
		);

		add_filter( 'wp_redirect', '__return_false' );

		$screen = $this->create_screen();
		$screen->process_actions();

		$saved = $this->storage->get( 'alert_settings', [] );

		// Existing secrets must be preserved.
		$this->assertEquals( 'existing-webhook-secret', $saved['webhook']['secret'] );
		$this->assertEquals( 'existing-bot-token', $saved['telegram']['bot_token'] );
		$this->assertEquals( 'existing-wa-token', $saved['whatsapp']['api_token'] );
	}

	/**
	 * Verifies that build_settings_from_post handles corrupted settings (non-array)
	 *
	 * @return void
	 */
	public function test_process_actions_handles_corrupted_existing_settings() {
		$this->set_admin_user();

		// Corrupt existing settings with a non-array value.
		$this->storage->set( 'alert_settings', 'corrupted-string' );

		$this->set_valid_post(
			[
				'email_enabled'    => '1',
				'email_recipients' => 'admin@example.com',
			]
		);

		add_filter( 'wp_redirect', '__return_false' );

		$screen = $this->create_screen();
		$screen->process_actions();

		$saved = $this->storage->get( 'alert_settings', [] );

		// Must save correctly despite previously corrupted data.
		$this->assertIsArray( $saved );
		$this->assertTrue( $saved['email']['enabled'] );
		$this->assertEquals( 'admin@example.com', $saved['email']['recipients'] );
	}

	/**
	 * Verifies that render handles corrupted settings (non-array)
	 *
	 * @return void
	 */
	public function test_render_handles_corrupted_settings() {
		$this->set_admin_user();

		// Corrupt settings.
		$this->storage->set( 'alert_settings', 'corrupted-string' );

		$screen = $this->create_screen();

		ob_start();
		$screen->render();
		$output = ob_get_clean();

		// Must render the form without errors.
		$this->assertStringContainsString( '<form', $output );
	}
}
