<?php
/**
 * Unit Test per HealthScreen
 *
 * Test unitario con Brain\Monkey per HealthScreen.
 *
 * @package OpsHealthDashboard\Tests\Unit\Admin
 */

namespace OpsHealthDashboard\Tests\Unit\Admin;

use Brain\Monkey\Functions;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use OpsHealthDashboard\Admin\HealthScreen;
use OpsHealthDashboard\Interfaces\CheckRunnerInterface;
use PHPUnit\Framework\TestCase;

/**
 * Class HealthScreenTest
 *
 * Unit test per HealthScreen.
 */
class HealthScreenTest extends TestCase {
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
		unset( $_POST['ops_health_action'], $_POST['_ops_health_nonce'] );
		\Brain\Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Testa che HealthScreen può essere istanziato con dipendenze
	 */
	public function test_health_screen_can_be_instantiated() {
		$runner = Mockery::mock( CheckRunnerInterface::class );
		$screen = new HealthScreen( $runner );

		$this->assertInstanceOf( HealthScreen::class, $screen );
	}

	/**
	 * Testa che render() mostra i risultati dei check
	 */
	public function test_render_shows_check_results() {
		$runner = Mockery::mock( CheckRunnerInterface::class );
		$runner->shouldReceive( 'get_latest_results' )
			->once()
			->andReturn( [
				'database' => [
					'status'  => 'ok',
					'message' => 'Database OK',
					'name'    => 'Database Connection',
					'details' => [],
				],
			] );

		$this->mock_render_functions();

		$screen = new HealthScreen( $runner );

		ob_start();
		$screen->render();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Ops Health Dashboard', $output );
		$this->assertStringContainsString( 'Database Connection', $output );
		$this->assertStringContainsString( 'ops-health-check-ok', $output );
	}

	/**
	 * Testa che render() mostra messaggio "no checks" quando non ci sono risultati
	 */
	public function test_render_shows_no_checks_message_when_empty() {
		$runner = Mockery::mock( CheckRunnerInterface::class );
		$runner->shouldReceive( 'get_latest_results' )
			->once()
			->andReturn( [] );

		$this->mock_render_functions();

		$screen = new HealthScreen( $runner );

		ob_start();
		$screen->render();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'No health checks have been run yet.', $output );
		$this->assertStringContainsString( 'notice-info', $output );
	}

	/**
	 * Testa che render() blocca utenti senza capability
	 */
	public function test_render_blocks_unauthorized_users() {
		$runner = Mockery::mock( CheckRunnerInterface::class );

		Functions\expect( 'current_user_can' )
			->once()
			->with( 'manage_options' )
			->andReturn( false );

		Functions\expect( 'esc_html__' )
			->andReturnUsing( function ( $text ) {
				return $text;
			} );

		// wp_die lancia eccezione per fermare l'esecuzione come in WordPress reale.
		Functions\expect( 'wp_die' )
			->once()
			->with( \Mockery::type( 'string' ) )
			->andReturnUsing( function () {
				throw new \RuntimeException( 'wp_die called' );
			} );

		$screen = new HealthScreen( $runner );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'wp_die called' );
		$screen->render();
	}

	/**
	 * Testa che render() gestisce risultati senza chiavi status/message
	 */
	public function test_render_handles_result_with_missing_keys() {
		$runner = Mockery::mock( CheckRunnerInterface::class );
		$runner->shouldReceive( 'get_latest_results' )
			->once()
			->andReturn( [
				'test_check' => [
					'details' => [],
				],
			] );

		$this->mock_render_functions();

		$screen = new HealthScreen( $runner );

		ob_start();
		$screen->render();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'ops-health-check-unknown', $output );
		$this->assertStringContainsString( 'Test_check', $output );
	}

	/**
	 * Helper: mock comuni per render con bottoni
	 *
	 * @return void
	 */
	private function mock_render_functions() {
		Functions\expect( 'current_user_can' )
			->with( 'manage_options' )
			->andReturn( true );

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
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo '<input type="hidden" name="' . $name . '" value="nonce_token" />';
			} );

		Functions\expect( 'submit_button' )
			->andReturnUsing( function ( $text, $type, $name ) {
				echo '<input type="submit" name="' . $name . '" value="' . $text . '" class="button ' . $type . '" />';
			} );

		Functions\expect( 'get_transient' )
			->andReturn( false );

		Functions\when( '__' )->returnArg();
	}

	/**
	 * Testa che render() contiene il bottone Run Now
	 */
	public function test_render_contains_run_now_button() {
		$runner = Mockery::mock( CheckRunnerInterface::class );
		$runner->shouldReceive( 'get_latest_results' )->andReturn( [] );

		$this->mock_render_functions();

		$screen = new HealthScreen( $runner );

		ob_start();
		$screen->render();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'run_now', $output );
	}

	/**
	 * Testa che render() contiene il bottone Clear Cache
	 */
	public function test_render_contains_clear_cache_button() {
		$runner = Mockery::mock( CheckRunnerInterface::class );
		$runner->shouldReceive( 'get_latest_results' )->andReturn( [] );

		$this->mock_render_functions();

		$screen = new HealthScreen( $runner );

		ob_start();
		$screen->render();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'clear_cache', $output );
	}

	/**
	 * Testa che render() contiene i campi nonce per sicurezza
	 */
	public function test_render_contains_nonce_fields() {
		$runner = Mockery::mock( CheckRunnerInterface::class );
		$runner->shouldReceive( 'get_latest_results' )->andReturn( [] );

		$this->mock_render_functions();

		$screen = new HealthScreen( $runner );

		ob_start();
		$screen->render();
		$output = ob_get_clean();

		$this->assertStringContainsString( '_ops_health_nonce', $output );
	}

	/**
	 * Helper: crea un mock parziale di HealthScreen con do_exit() disabilitato
	 *
	 * @param \Mockery\MockInterface $runner CheckRunner mock.
	 * @return \Mockery\MockInterface
	 */
	private function create_testable_screen( $runner ) {
		$screen = Mockery::mock( HealthScreen::class, [ $runner ] )
			->makePartial()
			->shouldAllowMockingProtectedMethods();

		$screen->shouldReceive( 'do_exit' )->andReturnNull();

		return $screen;
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

		Functions\expect( '__' )
			->andReturnUsing( function ( $text ) {
				return $text;
			} );
	}

	/**
	 * Testa che process_actions() ritorna subito senza POST action
	 */
	public function test_process_actions_returns_early_without_post_action() {
		$runner = Mockery::mock( CheckRunnerInterface::class );
		$runner->shouldNotReceive( 'run_all' );
		$runner->shouldNotReceive( 'clear_results' );

		unset( $_POST['ops_health_action'] );

		$screen = new HealthScreen( $runner );
		$screen->process_actions();

		$this->assertArrayNotHasKey( 'ops_health_action', $_POST );
	}

	/**
	 * Testa che process_actions() ritorna subito con nonce invalido
	 */
	public function test_process_actions_returns_early_with_invalid_nonce() {
		$runner = Mockery::mock( CheckRunnerInterface::class );
		$runner->shouldNotReceive( 'run_all' );
		$runner->shouldNotReceive( 'clear_results' );

		$_POST['ops_health_action'] = 'run_now';
		$_POST['_ops_health_nonce'] = 'invalid_nonce';

		$this->mock_process_functions();

		Functions\expect( 'wp_verify_nonce' )
			->once()
			->andReturn( false );

		$screen = new HealthScreen( $runner );
		$screen->process_actions();

		unset( $_POST['ops_health_action'], $_POST['_ops_health_nonce'] );

		// Mockery verifica shouldNotReceive automaticamente via MockeryPHPUnitIntegration.
		$this->assertInstanceOf( HealthScreen::class, $screen );
	}

	/**
	 * Testa che process_actions() ritorna subito senza capability
	 */
	public function test_process_actions_returns_early_without_capability() {
		$runner = Mockery::mock( CheckRunnerInterface::class );
		$runner->shouldNotReceive( 'run_all' );
		$runner->shouldNotReceive( 'clear_results' );

		$_POST['ops_health_action'] = 'run_now';
		$_POST['_ops_health_nonce'] = 'valid_nonce';

		$this->mock_process_functions();

		Functions\expect( 'wp_verify_nonce' )
			->once()
			->andReturn( 1 );

		Functions\expect( 'current_user_can' )
			->once()
			->with( 'manage_options' )
			->andReturn( false );

		$screen = new HealthScreen( $runner );
		$screen->process_actions();

		// Mockery verifica shouldNotReceive automaticamente via MockeryPHPUnitIntegration.
		$this->assertInstanceOf( HealthScreen::class, $screen );
	}

	/**
	 * Testa che process_actions() esegue run_all con action run_now
	 */
	public function test_process_actions_runs_checks_on_run_now() {
		$runner = Mockery::mock( CheckRunnerInterface::class );
		$runner->shouldReceive( 'run_all' )
			->once()
			->andReturn( [] );

		$_POST['ops_health_action'] = 'run_now';
		$_POST['_ops_health_nonce'] = 'valid_nonce';

		$this->mock_process_functions();

		Functions\expect( 'wp_verify_nonce' )
			->once()
			->andReturn( 1 );

		Functions\expect( 'current_user_can' )
			->once()
			->with( 'manage_options' )
			->andReturn( true );

		Functions\expect( 'set_transient' )
			->once()
			->with( 'ops_health_admin_notice', \Mockery::type( 'string' ), 30 );

		Functions\expect( 'admin_url' )
			->once()
			->andReturn( 'http://example.com/wp-admin/admin.php?page=ops-health-dashboard' );

		Functions\expect( 'wp_safe_redirect' )
			->once()
			->with( 'http://example.com/wp-admin/admin.php?page=ops-health-dashboard' );

		$screen = $this->create_testable_screen( $runner );
		$screen->process_actions();

		// Mockery/Brain\Monkey verificano expectations via MockeryPHPUnitIntegration.
		$this->assertInstanceOf( HealthScreen::class, $screen );
	}

	/**
	 * Testa che process_actions() esegue clear_results con action clear_cache
	 */
	public function test_process_actions_clears_cache_on_clear_cache() {
		$runner = Mockery::mock( CheckRunnerInterface::class );
		$runner->shouldReceive( 'clear_results' )
			->once();

		$_POST['ops_health_action'] = 'clear_cache';
		$_POST['_ops_health_nonce'] = 'valid_nonce';

		$this->mock_process_functions();

		Functions\expect( 'wp_verify_nonce' )
			->once()
			->andReturn( 1 );

		Functions\expect( 'current_user_can' )
			->once()
			->with( 'manage_options' )
			->andReturn( true );

		Functions\expect( 'set_transient' )
			->once()
			->with( 'ops_health_admin_notice', \Mockery::type( 'string' ), 30 );

		Functions\expect( 'admin_url' )
			->once()
			->andReturn( 'http://example.com/wp-admin/admin.php?page=ops-health-dashboard' );

		Functions\expect( 'wp_safe_redirect' )
			->once();

		$screen = $this->create_testable_screen( $runner );
		$screen->process_actions();

		// Mockery/Brain\Monkey verificano expectations via MockeryPHPUnitIntegration.
		$this->assertInstanceOf( HealthScreen::class, $screen );
	}

	/**
	 * Testa che render() mostra notice quando transient presente
	 */
	public function test_render_shows_notice_when_transient_set() {
		$runner = Mockery::mock( CheckRunnerInterface::class );
		$runner->shouldReceive( 'get_latest_results' )
			->once()
			->andReturn( [] );

		Functions\expect( 'current_user_can' )
			->with( 'manage_options' )
			->andReturn( true );

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

		Functions\expect( 'get_transient' )
			->once()
			->with( 'ops_health_admin_notice' )
			->andReturn( 'Test notice message' );

		Functions\expect( 'delete_transient' )
			->once()
			->with( 'ops_health_admin_notice' );

		$screen = new HealthScreen( $runner );

		ob_start();
		$screen->render();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'notice-success', $output );
		$this->assertStringContainsString( 'Test notice message', $output );
	}

	// ─── register_hooks ────────────────────────────────────────────

	/**
	 * Testa che register_hooks registra l'hook admin_enqueue_scripts
	 */
	public function test_register_hooks_adds_admin_enqueue_scripts() {
		$runner = Mockery::mock( CheckRunnerInterface::class );
		$screen = new HealthScreen( $runner );

		$hooks = [];
		Functions\expect( 'add_action' )
			->atLeast()
			->times( 1 )
			->andReturnUsing(
				function ( $hook, $callback ) use ( &$hooks ) {
					$hooks[ $hook ] = $callback;
				}
			);

		$screen->register_hooks();

		$this->assertArrayHasKey( 'admin_enqueue_scripts', $hooks );
		$this->assertSame( [ $screen, 'enqueue_styles' ], $hooks['admin_enqueue_scripts'] );
	}

	// ─── enqueue_styles ────────────────────────────────────────────

	/**
	 * Testa che SCREEN_ID ha il valore corretto
	 */
	public function test_screen_id_constant_value() {
		$this->assertSame(
			'toplevel_page_ops-health-dashboard',
			HealthScreen::SCREEN_ID
		);
	}

	/**
	 * Testa che enqueue_styles carica il CSS sulla schermata health
	 */
	public function test_enqueue_styles_on_health_screen() {
		if ( ! defined( 'OPS_HEALTH_DASHBOARD_FILE' ) ) {
			define( 'OPS_HEALTH_DASHBOARD_FILE', '/fake/ops-health-dashboard.php' );
		}
		if ( ! defined( 'OPS_HEALTH_DASHBOARD_VERSION' ) ) {
			define( 'OPS_HEALTH_DASHBOARD_VERSION', '0.6.0' );
		}

		$screen_obj     = new \stdClass();
		$screen_obj->id = 'toplevel_page_ops-health-dashboard';

		Functions\expect( 'get_current_screen' )
			->once()
			->andReturn( $screen_obj );

		Functions\expect( 'plugin_dir_url' )
			->once()
			->andReturn( 'http://example.com/wp-content/plugins/ops-health-dashboard/' );

		Functions\expect( 'wp_enqueue_style' )
			->once()
			->with(
				'ops-health-dashboard-screen',
				Mockery::type( 'string' ),
				[],
				Mockery::type( 'string' )
			);

		$runner = Mockery::mock( CheckRunnerInterface::class );
		$screen = new HealthScreen( $runner );
		$screen->enqueue_styles();
	}

	/**
	 * Testa che enqueue_styles NON carica il CSS su altre schermate
	 */
	public function test_enqueue_styles_skips_other_screen() {
		$screen_obj     = new \stdClass();
		$screen_obj->id = 'plugins';

		Functions\expect( 'get_current_screen' )
			->once()
			->andReturn( $screen_obj );

		$runner = Mockery::mock( CheckRunnerInterface::class );
		$screen = new HealthScreen( $runner );
		$screen->enqueue_styles();

		$this->assertTrue( true );
	}

	/**
	 * Testa che enqueue_styles gestisce screen null
	 */
	public function test_enqueue_styles_handles_null_screen() {
		Functions\expect( 'get_current_screen' )
			->once()
			->andReturn( null );

		$runner = Mockery::mock( CheckRunnerInterface::class );
		$screen = new HealthScreen( $runner );
		$screen->enqueue_styles();

		$this->assertTrue( true );
	}

	// ─── determine_overall_status ──────────────────────────────────

	/**
	 * Testa che overall status per risultati vuoti è 'unknown'
	 */
	public function test_overall_status_empty_is_unknown() {
		$runner = Mockery::mock( CheckRunnerInterface::class );
		$screen = new HealthScreen( $runner );

		$reflection = new \ReflectionMethod( $screen, 'determine_overall_status' );
		$reflection->setAccessible( true );

		$this->assertEquals( 'unknown', $reflection->invoke( $screen, [] ) );
	}

	/**
	 * Testa che overall status con tutti ok è 'ok'
	 */
	public function test_overall_status_all_ok() {
		$runner = Mockery::mock( CheckRunnerInterface::class );
		$screen = new HealthScreen( $runner );

		$reflection = new \ReflectionMethod( $screen, 'determine_overall_status' );
		$reflection->setAccessible( true );

		$results = [
			'a' => [ 'status' => 'ok' ],
			'b' => [ 'status' => 'ok' ],
		];
		$this->assertEquals( 'ok', $reflection->invoke( $screen, $results ) );
	}

	/**
	 * Testa che overall status worst wins (critical > ok)
	 */
	public function test_overall_status_worst_wins() {
		$runner = Mockery::mock( CheckRunnerInterface::class );
		$screen = new HealthScreen( $runner );

		$reflection = new \ReflectionMethod( $screen, 'determine_overall_status' );
		$reflection->setAccessible( true );

		$results = [
			'a' => [ 'status' => 'ok' ],
			'b' => [ 'status' => 'critical' ],
		];
		$this->assertEquals( 'critical', $reflection->invoke( $screen, $results ) );
	}

	/**
	 * Testa che overall status con status mancante usa default
	 */
	public function test_overall_status_missing_status_field() {
		$runner = Mockery::mock( CheckRunnerInterface::class );
		$screen = new HealthScreen( $runner );

		$reflection = new \ReflectionMethod( $screen, 'determine_overall_status' );
		$reflection->setAccessible( true );

		$results = [
			'a' => [],
			'b' => [ 'status' => 'ok' ],
		];
		$this->assertEquals( 'ok', $reflection->invoke( $screen, $results ) );
	}

	// ─── render (HTML changes) ─────────────────────────────────────

	/**
	 * Testa che render non contiene inline styles
	 */
	public function test_render_has_no_inline_styles() {
		$runner = Mockery::mock( CheckRunnerInterface::class );
		$runner->shouldReceive( 'get_latest_results' )->andReturn( [] );

		$this->mock_render_functions();

		$screen = new HealthScreen( $runner );

		ob_start();
		$screen->render();
		$output = ob_get_clean();

		$this->assertStringNotContainsString( 'style="', $output );
	}

	/**
	 * Testa che render mostra il banner di stato quando ci sono risultati
	 */
	public function test_render_shows_summary_banner_with_results() {
		$runner = Mockery::mock( CheckRunnerInterface::class );
		$runner->shouldReceive( 'get_latest_results' )->andReturn( [
			'database' => [
				'status'  => 'ok',
				'message' => 'OK',
				'name'    => 'Database',
			],
		] );

		$this->mock_render_functions();

		$screen = new HealthScreen( $runner );

		ob_start();
		$screen->render();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'ops-health-summary', $output );
	}

	/**
	 * Testa che render nasconde il banner quando non ci sono risultati
	 */
	public function test_render_hides_summary_when_no_results() {
		$runner = Mockery::mock( CheckRunnerInterface::class );
		$runner->shouldReceive( 'get_latest_results' )->andReturn( [] );

		$this->mock_render_functions();

		$screen = new HealthScreen( $runner );

		ob_start();
		$screen->render();
		$output = ob_get_clean();

		$this->assertStringNotContainsString( 'ops-health-summary', $output );
	}

	// ─── Pattern enforcement ───────────────────────────────────────

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

	/**
	 * Testa che NON esistono proprietà static
	 */
	public function test_no_static_properties() {
		$reflection = new \ReflectionClass( HealthScreen::class );
		$properties = $reflection->getProperties( \ReflectionProperty::IS_STATIC );

		$this->assertEmpty( $properties, 'HealthScreen should have NO static properties' );
	}
}
