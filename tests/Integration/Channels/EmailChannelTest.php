<?php
/**
 * Integration Test per EmailChannel
 *
 * Verifica invio alert email con wp_mail reale (interceptato via pre_wp_mail).
 *
 * @package OpsHealthDashboard\Tests\Integration\Channels
 */

namespace OpsHealthDashboard\Tests\Integration\Channels;

use OpsHealthDashboard\Channels\EmailChannel;
use OpsHealthDashboard\Services\Storage;
use WP_UnitTestCase;

/**
 * Class EmailChannelTest
 *
 * Integration test per EmailChannel con WordPress reale.
 */
class EmailChannelTest extends WP_UnitTestCase {

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
	}

	/**
	 * Teardown
	 *
	 * @return void
	 */
	public function tearDown(): void {
		$this->storage->delete( 'alert_settings' );
		remove_all_filters( 'pre_wp_mail' );
		parent::tearDown();
	}

	/**
	 * Crea un payload di test
	 *
	 * @param array $overrides Override dei campi.
	 * @return array Payload alert.
	 */
	private function create_test_payload( array $overrides = [] ): array {
		return array_merge(
			[
				'check_id'        => 'database',
				'check_name'      => 'Database',
				'current_status'  => 'critical',
				'previous_status' => 'ok',
				'message'         => 'Connection failed',
				'site_name'       => 'Test Site',
				'site_url'        => 'https://example.com',
				'is_recovery'     => false,
				'timestamp'       => 1707580800,
			],
			$overrides
		);
	}

	/**
	 * Intercepta wp_mail per catturare argomenti
	 *
	 * @param mixed $captured_mail Variabile per catturare i dati.
	 * @return void
	 */
	private function intercept_wp_mail( &$captured_mail ): void {
		add_filter(
			'pre_wp_mail',
			function ( $null, $atts ) use ( &$captured_mail ) {
				$captured_mail = $atts;
				return true;
			},
			10,
			2
		);
	}

	/**
	 * Testa che la classe NON è final
	 *
	 * @return void
	 */
	public function test_class_is_not_final() {
		$reflection = new \ReflectionClass( EmailChannel::class );
		$this->assertFalse( $reflection->isFinal(), 'Class should NOT be final' );
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

		$this->assertEmpty( $static_methods, 'Class should have NO static methods' );
	}

	/**
	 * Testa che NON esistono proprietà static
	 *
	 * @return void
	 */
	public function test_no_static_properties() {
		$reflection = new \ReflectionClass( EmailChannel::class );
		$properties = $reflection->getProperties( \ReflectionProperty::IS_STATIC );

		$this->assertEmpty( $properties, 'Class should have NO static properties' );
	}

	/**
	 * Testa get_id
	 *
	 * @return void
	 */
	public function test_get_id_returns_email() {
		$channel = new EmailChannel( $this->storage );

		$this->assertEquals( 'email', $channel->get_id() );
	}

	/**
	 * Testa get_name
	 *
	 * @return void
	 */
	public function test_get_name_returns_translated_string() {
		$channel = new EmailChannel( $this->storage );

		$this->assertEquals( 'Email', $channel->get_name() );
	}

	/**
	 * Testa is_enabled con impostazioni valide
	 *
	 * @return void
	 */
	public function test_is_enabled_true_with_enabled_and_recipients() {
		$this->storage->set(
			'alert_settings',
			[
				'email' => [
					'enabled'    => true,
					'recipients' => 'admin@example.com',
				],
			]
		);

		$channel = new EmailChannel( $this->storage );

		$this->assertTrue( $channel->is_enabled() );
	}

	/**
	 * Testa is_enabled quando disabilitato
	 *
	 * @return void
	 */
	public function test_is_enabled_false_when_disabled() {
		$this->storage->set(
			'alert_settings',
			[
				'email' => [
					'enabled'    => false,
					'recipients' => 'admin@example.com',
				],
			]
		);

		$channel = new EmailChannel( $this->storage );

		$this->assertFalse( $channel->is_enabled() );
	}

	/**
	 * Testa is_enabled senza recipients
	 *
	 * @return void
	 */
	public function test_is_enabled_false_without_recipients() {
		$this->storage->set(
			'alert_settings',
			[
				'email' => [
					'enabled'    => true,
					'recipients' => '',
				],
			]
		);

		$channel = new EmailChannel( $this->storage );

		$this->assertFalse( $channel->is_enabled() );
	}

	/**
	 * Testa is_enabled con settings corrotti (non array)
	 *
	 * @return void
	 */
	public function test_is_enabled_false_with_corrupted_settings() {
		$this->storage->set( 'alert_settings', 'not-an-array' );

		$channel = new EmailChannel( $this->storage );

		$this->assertFalse( $channel->is_enabled() );
	}

	/**
	 * Testa send failure quando wp_mail ritorna false
	 *
	 * @return void
	 */
	public function test_send_failure_when_wp_mail_returns_false() {
		$this->storage->set(
			'alert_settings',
			[
				'email' => [
					'enabled'    => true,
					'recipients' => 'admin@example.com',
				],
			]
		);

		add_filter(
			'pre_wp_mail',
			function () {
				return false;
			}
		);

		$channel = new EmailChannel( $this->storage );
		$result  = $channel->send( $this->create_test_payload() );

		$this->assertFalse( $result['success'] );
		$this->assertNotNull( $result['error'] );
	}

	/**
	 * Testa send con successo tramite pre_wp_mail
	 *
	 * @return void
	 */
	public function test_send_success_with_intercepted_email() {
		$this->storage->set(
			'alert_settings',
			[
				'email' => [
					'enabled'    => true,
					'recipients' => 'admin@example.com',
				],
			]
		);

		$captured_mail = null;
		$this->intercept_wp_mail( $captured_mail );

		$channel = new EmailChannel( $this->storage );
		$result  = $channel->send( $this->create_test_payload() );

		$this->assertTrue( $result['success'] );
		$this->assertNull( $result['error'] );
		$this->assertNotNull( $captured_mail );
	}

	/**
	 * Testa che il soggetto contiene il nome del check e lo status
	 *
	 * @return void
	 */
	public function test_send_formats_subject_with_status() {
		$this->storage->set(
			'alert_settings',
			[
				'email' => [
					'enabled'    => true,
					'recipients' => 'admin@example.com',
				],
			]
		);

		$captured_mail = null;
		$this->intercept_wp_mail( $captured_mail );

		$channel = new EmailChannel( $this->storage );
		$channel->send( $this->create_test_payload() );

		$this->assertStringContainsString( '[Ops Health]', $captured_mail['subject'] );
		$this->assertStringContainsString( 'Database', $captured_mail['subject'] );
		$this->assertStringContainsString( 'CRITICAL', $captured_mail['subject'] );
	}

	/**
	 * Testa che il body contiene notice di recovery
	 *
	 * @return void
	 */
	public function test_send_formats_body_with_recovery_notice() {
		$this->storage->set(
			'alert_settings',
			[
				'email' => [
					'enabled'    => true,
					'recipients' => 'admin@example.com',
				],
			]
		);

		$captured_mail = null;
		$this->intercept_wp_mail( $captured_mail );

		$channel = new EmailChannel( $this->storage );
		$channel->send(
			$this->create_test_payload(
				[
					'is_recovery'    => true,
					'current_status' => 'ok',
				]
			)
		);

		$this->assertStringContainsString( 'RECOVERY', $captured_mail['message'] );
	}

	/**
	 * Testa che send ritorna errore quando tutti i recipients sono invalidi
	 *
	 * @return void
	 */
	public function test_send_returns_error_when_all_recipients_invalid() {
		$this->storage->set(
			'alert_settings',
			[
				'email' => [
					'enabled'    => true,
					'recipients' => 'not-an-email, also-not-valid, @invalid',
				],
			]
		);

		$channel = new EmailChannel( $this->storage );
		$result  = $channel->send( $this->create_test_payload() );

		$this->assertFalse( $result['success'] );
		$this->assertStringContainsString( 'No valid email recipients', $result['error'] );
	}

	/**
	 * Testa che send filtra email non valide con is_email() reale
	 *
	 * @return void
	 */
	public function test_send_filters_invalid_recipients_with_real_is_email() {
		$this->storage->set(
			'alert_settings',
			[
				'email' => [
					'enabled'    => true,
					'recipients' => 'valid@example.com, not-an-email, another@test.org',
				],
			]
		);

		$captured_mail = null;
		$this->intercept_wp_mail( $captured_mail );

		$channel = new EmailChannel( $this->storage );
		$channel->send( $this->create_test_payload() );

		// Solo le email valide devono essere incluse.
		$this->assertContains( 'valid@example.com', $captured_mail['to'] );
		$this->assertContains( 'another@test.org', $captured_mail['to'] );
		$this->assertNotContains( 'not-an-email', $captured_mail['to'] );
	}
}
