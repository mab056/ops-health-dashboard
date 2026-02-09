<?php
/**
 * Integration Test per HealthScreen
 *
 * Test di integrazione con WordPress admin reale.
 *
 * @package OpsHealthDashboard\Tests\Integration\Admin
 */

namespace OpsHealthDashboard\Tests\Integration\Admin;

use OpsHealthDashboard\Admin\HealthScreen;
use OpsHealthDashboard\Admin\Menu;
use OpsHealthDashboard\Checks\DatabaseCheck;
use OpsHealthDashboard\Services\CheckRunner;
use OpsHealthDashboard\Services\Redaction;
use OpsHealthDashboard\Services\Storage;
use WP_UnitTestCase;

/**
 * Class HealthScreenTest
 *
 * Integration test per HealthScreen con WordPress reale.
 */
class HealthScreenTest extends WP_UnitTestCase {

	/**
	 * Cleanup dopo ogni test
	 */
	public function tearDown(): void {
		unset( $_POST['ops_health_action'], $_POST['_ops_health_nonce'] );
		delete_transient( 'ops_health_admin_notice' );
		delete_option( 'ops_health_latest_results' );
		parent::tearDown();
	}

	/**
	 * Crea un'istanza di HealthScreen con dipendenze reali
	 *
	 * @return HealthScreen
	 */
	private function create_health_screen(): HealthScreen {
		$storage   = new Storage();
		$redaction = new Redaction();
		$runner    = new CheckRunner( $storage, $redaction );
		return new HealthScreen( $runner );
	}

	/**
	 * Crea un TestableHealthScreen con CheckRunner reale
	 *
	 * @return TestableHealthScreen
	 */
	private function create_testable_screen(): TestableHealthScreen {
		$storage   = new Storage();
		$redaction = new Redaction();
		$runner    = new CheckRunner( $storage, $redaction );
		return new TestableHealthScreen( $runner );
	}

	/**
	 * Crea un TestableHealthScreen con DatabaseCheck reale
	 *
	 * @return TestableHealthScreen
	 */
	private function create_testable_screen_with_db_check(): TestableHealthScreen {
		$storage   = new Storage();
		$redaction = new Redaction();
		$runner    = new CheckRunner( $storage, $redaction );

		global $wpdb;
		$runner->add_check( new DatabaseCheck( $wpdb, $redaction ) );

		return new TestableHealthScreen( $runner );
	}

	/**
	 * Testa che render() produce output con capability check
	 */
	public function test_render_outputs_html_for_admin() {
		$user_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );

		$health_screen = $this->create_health_screen();

		ob_start();
		$health_screen->render();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Ops Health Dashboard', $output );
		$this->assertStringContainsString( 'wrap', $output );
	}

	/**
	 * Testa che render() mostra messaggio "no checks" quando nessun risultato
	 */
	public function test_render_shows_no_checks_message_when_empty() {
		$user_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );

		// Pulisci eventuali risultati precedenti.
		delete_option( 'ops_health_latest_results' );

		$health_screen = $this->create_health_screen();

		ob_start();
		$health_screen->render();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'No health checks have been run yet', $output );
	}

	/**
	 * Testa che Menu::render_page() delega a HealthScreen::render()
	 */
	public function test_menu_render_page_delegates_to_health_screen() {
		$user_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );

		$health_screen = $this->create_health_screen();
		$menu          = new Menu( $health_screen );

		ob_start();
		$menu->render_page();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Ops Health Dashboard', $output );
	}

	/**
	 * Testa che render() mostra i bottoni d'azione per admin
	 */
	public function test_render_shows_action_buttons_for_admin() {
		$user_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );

		$health_screen = $this->create_health_screen();

		ob_start();
		$health_screen->render();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'run_now', $output );
		$this->assertStringContainsString( 'clear_cache', $output );
		$this->assertStringContainsString( '_ops_health_nonce', $output );
	}

	/**
	 * Testa che process_actions() ritorna subito senza POST action
	 */
	public function test_process_actions_returns_early_without_post() {
		unset( $_POST['ops_health_action'] );

		$screen = $this->create_testable_screen();
		$screen->process_actions();

		// Nessun transient impostato = early return.
		$this->assertFalse( get_transient( 'ops_health_admin_notice' ) );
	}

	/**
	 * Testa che process_actions() ritorna subito con nonce invalido
	 */
	public function test_process_actions_returns_early_with_bad_nonce() {
		$user_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );

		$_POST['ops_health_action'] = 'run_now';
		$_POST['_ops_health_nonce'] = 'invalid_nonce_value';

		$screen = $this->create_testable_screen();
		$screen->process_actions();

		$this->assertFalse( get_transient( 'ops_health_admin_notice' ) );
	}

	/**
	 * Testa che process_actions() ritorna subito senza capability
	 */
	public function test_process_actions_returns_early_without_capability() {
		$user_id = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $user_id );

		$_POST['ops_health_action'] = 'run_now';
		$_POST['_ops_health_nonce'] = wp_create_nonce( 'ops_health_admin_action' );

		$screen = $this->create_testable_screen();
		$screen->process_actions();

		$this->assertFalse( get_transient( 'ops_health_admin_notice' ) );
	}

	/**
	 * Testa che process_actions() esegue run_all con action run_now
	 */
	public function test_process_actions_run_now_executes_and_redirects() {
		$user_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );

		$_POST['ops_health_action'] = 'run_now';
		$_POST['_ops_health_nonce'] = wp_create_nonce( 'ops_health_admin_action' );

		$screen = $this->create_testable_screen_with_db_check();

		// Intercetta wp_redirect per evitare "headers already sent" nei test.
		add_filter( 'wp_redirect', '__return_false' );
		$screen->process_actions();
		remove_filter( 'wp_redirect', '__return_false' );

		$notice = get_transient( 'ops_health_admin_notice' );
		$this->assertNotFalse( $notice );
		$this->assertStringContainsString( 'executed', strtolower( $notice ) );

		// Verifica che i risultati siano stati salvati.
		$results = get_option( 'ops_health_latest_results' );
		$this->assertIsArray( $results );
		$this->assertArrayHasKey( 'database', $results );
	}

	/**
	 * Testa che process_actions() esegue clear_results con action clear_cache
	 */
	public function test_process_actions_clear_cache_clears_and_redirects() {
		$user_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );

		// Prima salva dei risultati.
		update_option( 'ops_health_latest_results', [ 'database' => [ 'status' => 'ok' ] ] );

		$_POST['ops_health_action'] = 'clear_cache';
		$_POST['_ops_health_nonce'] = wp_create_nonce( 'ops_health_admin_action' );

		$screen = $this->create_testable_screen();

		add_filter( 'wp_redirect', '__return_false' );
		$screen->process_actions();
		remove_filter( 'wp_redirect', '__return_false' );

		$notice = get_transient( 'ops_health_admin_notice' );
		$this->assertNotFalse( $notice );
		$this->assertStringContainsString( 'cleared', strtolower( $notice ) );

		// Verifica che i risultati siano stati cancellati.
		$this->assertFalse( get_option( 'ops_health_latest_results' ) );
	}

	/**
	 * Testa che render() mostra notice dal transient
	 */
	public function test_render_shows_notice_from_transient() {
		$user_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );

		set_transient( 'ops_health_admin_notice', 'Test notice message', 30 );

		$screen = $this->create_health_screen();

		ob_start();
		$screen->render();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Test notice message', $output );
		$this->assertStringContainsString( 'notice-success', $output );

		// Transient deve essere cancellato dopo render.
		$this->assertFalse( get_transient( 'ops_health_admin_notice' ) );
	}

	/**
	 * Testa che render() mostra i risultati dei check
	 */
	public function test_render_displays_check_results() {
		$user_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );

		// Salva risultati finti.
		update_option(
			'ops_health_latest_results',
			[
				'database' => [
					'status'  => 'ok',
					'message' => 'Database connection healthy',
					'name'    => 'Database Connection',
				],
			]
		);

		$screen = $this->create_health_screen();

		ob_start();
		$screen->render();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Database Connection', $output );
		$this->assertStringContainsString( 'Database connection healthy', $output );
		$this->assertStringContainsString( 'ops-health-check-ok', $output );
		$this->assertStringNotContainsString( 'No health checks have been run yet', $output );
	}

	/**
	 * Testa che render() usa valori default per chiavi mancanti nei risultati
	 */
	public function test_render_uses_defaults_for_missing_result_keys() {
		$user_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );

		// Salva risultati senza status, message e name.
		update_option(
			'ops_health_latest_results',
			[
				'custom_check' => [],
			]
		);

		$screen = $this->create_health_screen();

		ob_start();
		$screen->render();
		$output = ob_get_clean();

		// Deve usare i default: ucfirst(check_id) per name, 'unknown' per status.
		$this->assertStringContainsString( 'Custom_check', $output );
		$this->assertStringContainsString( 'ops-health-check-unknown', $output );
		$this->assertStringNotContainsString( 'No health checks have been run yet', $output );
	}

	/**
	 * Testa che render() nega l'accesso senza capability manage_options
	 */
	public function test_render_denies_access_without_capability() {
		$user_id = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $user_id );

		$screen = $this->create_health_screen();

		$this->expectException( 'WPDieException' );
		$screen->render();
	}

	/**
	 * Testa che la classe NON è final
	 */
	public function test_class_is_not_final() {
		$reflection = new \ReflectionClass( HealthScreen::class );
		$this->assertFalse( $reflection->isFinal(), 'HealthScreen should NOT be final' );
	}

	/**
	 * Testa che NON esistono metodi static
	 */
	public function test_no_static_methods() {
		$reflection = new \ReflectionClass( HealthScreen::class );
		$methods    = $reflection->getMethods( \ReflectionMethod::IS_STATIC );

		$static_methods = array_filter(
			$methods,
			function ( $method ) {
				return strpos( $method->getName(), '__' ) !== 0;
			}
		);

		$this->assertEmpty( $static_methods, 'HealthScreen should have NO static methods' );
	}
}

/**
 * HealthScreen testabile con do_exit() no-op
 *
 * Evita la chiamata a exit() durante i test di process_actions().
 */
class TestableHealthScreen extends HealthScreen {

	/**
	 * Override do_exit() per evitare exit nei test
	 *
	 * @return void
	 */
	protected function do_exit(): void {
		// No-op per testabilità.
	}
}
