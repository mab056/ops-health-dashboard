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
		delete_option( 'ops_health_results' );

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
