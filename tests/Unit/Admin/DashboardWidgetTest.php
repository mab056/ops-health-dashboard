<?php
/**
 * Unit Test per DashboardWidget
 *
 * Test unitario con Brain\Monkey per DashboardWidget.
 *
 * @package OpsHealthDashboard\Tests\Unit\Admin
 */

namespace OpsHealthDashboard\Tests\Unit\Admin;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use OpsHealthDashboard\Admin\DashboardWidget;
use OpsHealthDashboard\Interfaces\CheckRunnerInterface;
use OpsHealthDashboard\Interfaces\StorageInterface;
use PHPUnit\Framework\TestCase;

/**
 * Class DashboardWidgetTest
 *
 * Unit test per DashboardWidget.
 */
class DashboardWidgetTest extends TestCase {
	use MockeryPHPUnitIntegration;

	/**
	 * Setup per ogni test
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	/**
	 * Teardown per ogni test
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Crea un mock di CheckRunnerInterface
	 *
	 * @return \Mockery\MockInterface|CheckRunnerInterface
	 */
	private function create_runner_mock() {
		return Mockery::mock( CheckRunnerInterface::class );
	}

	/**
	 * Crea un mock di StorageInterface
	 *
	 * @param int $last_run_at Timestamp last run (0 = mai eseguito).
	 * @return \Mockery\MockInterface|StorageInterface
	 */
	private function create_storage_mock( int $last_run_at = 0 ) {
		$storage = Mockery::mock( StorageInterface::class );
		$storage->shouldReceive( 'get' )
			->with( 'last_run_at', 0 )
			->andReturn( $last_run_at );
		return $storage;
	}

	/**
	 * Configura i mock comuni per il rendering
	 */
	private function mock_render_functions(): void {
		Functions\when( '__' )->returnArg();
		Functions\when( 'esc_html__' )->returnArg();
		Functions\when( 'esc_html' )->returnArg();
		Functions\when( 'esc_attr' )->returnArg();
		Functions\when( 'esc_url' )->returnArg();
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'admin_url' )->justReturn(
			'http://example.com/wp-admin/admin.php?page=ops-health-dashboard'
		);
		Functions\when( 'human_time_diff' )->justReturn( '3 mins' );
	}

	// ─── Pattern Enforcement ──────────────────────────────────────────

	/**
	 * Testa che la classe NON è final
	 */
	public function test_class_is_not_final() {
		$reflection = new \ReflectionClass( DashboardWidget::class );
		$this->assertFalse( $reflection->isFinal(), 'Class should NOT be final' );
	}

	/**
	 * Testa che NON esistono metodi static
	 */
	public function test_no_static_methods() {
		$reflection = new \ReflectionClass( DashboardWidget::class );
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
	 */
	public function test_no_static_properties() {
		$reflection = new \ReflectionClass( DashboardWidget::class );
		$this->assertEmpty(
			$reflection->getProperties( \ReflectionProperty::IS_STATIC ),
			'Class should have NO static properties'
		);
	}

	// ─── Instantiation ───────────────────────────────────────────────

	/**
	 * Testa che DashboardWidget si istanzia con CheckRunnerInterface e StorageInterface
	 */
	public function test_instantiates_with_runner_and_storage() {
		$runner  = $this->create_runner_mock();
		$storage = $this->create_storage_mock();
		$widget  = new DashboardWidget( $runner, $storage );
		$this->assertInstanceOf( DashboardWidget::class, $widget );
	}

	// ─── register_hooks ─────────────────────────────────────────────

	/**
	 * Testa che register_hooks registra l'hook wp_dashboard_setup
	 */
	public function test_register_hooks_adds_dashboard_setup() {
		$runner  = $this->create_runner_mock();
		$storage = $this->create_storage_mock();
		$widget  = new DashboardWidget( $runner, $storage );

		$hooks = [];
		Functions\expect( 'add_action' )
			->atLeast()
			->times( 1 )
			->andReturnUsing(
				function ( $hook, $callback ) use ( &$hooks ) {
					$hooks[ $hook ] = $callback;
				}
			);

		$widget->register_hooks();

		$this->assertArrayHasKey( 'wp_dashboard_setup', $hooks );
		$this->assertSame( [ $widget, 'add_widget' ], $hooks['wp_dashboard_setup'] );
	}

	/**
	 * Testa che register_hooks registra l'hook admin_enqueue_scripts
	 */
	public function test_register_hooks_adds_admin_enqueue_scripts() {
		$runner  = $this->create_runner_mock();
		$storage = $this->create_storage_mock();
		$widget  = new DashboardWidget( $runner, $storage );

		$hooks = [];
		Functions\expect( 'add_action' )
			->atLeast()
			->times( 1 )
			->andReturnUsing(
				function ( $hook, $callback ) use ( &$hooks ) {
					$hooks[ $hook ] = $callback;
				}
			);

		$widget->register_hooks();

		$this->assertArrayHasKey( 'admin_enqueue_scripts', $hooks );
		$this->assertSame( [ $widget, 'enqueue_styles' ], $hooks['admin_enqueue_scripts'] );
	}

	// ─── enqueue_styles ─────────────────────────────────────────────

	/**
	 * Testa che enqueue_styles carica il CSS sulla dashboard per admin
	 */
	public function test_enqueue_styles_on_dashboard_screen() {
		if ( ! defined( 'OPS_HEALTH_DASHBOARD_FILE' ) ) {
			define( 'OPS_HEALTH_DASHBOARD_FILE', '/fake/ops-health-dashboard.php' );
		}
		if ( ! defined( 'OPS_HEALTH_DASHBOARD_VERSION' ) ) {
			define( 'OPS_HEALTH_DASHBOARD_VERSION', '0.6.0' );
		}

		$screen     = new \stdClass();
		$screen->id = 'dashboard';

		Functions\expect( 'current_user_can' )
			->once()
			->with( 'manage_options' )
			->andReturn( true );

		Functions\expect( 'get_current_screen' )
			->once()
			->andReturn( $screen );

		Functions\expect( 'plugin_dir_url' )
			->once()
			->andReturn( 'http://example.com/wp-content/plugins/ops-health-dashboard/' );

		Functions\expect( 'wp_enqueue_style' )
			->once()
			->with(
				'ops-health-dashboard-widget',
				Mockery::type( 'string' ),
				[],
				Mockery::type( 'string' )
			);

		$runner  = $this->create_runner_mock();
		$storage = $this->create_storage_mock();
		$widget  = new DashboardWidget( $runner, $storage );
		$widget->enqueue_styles();
	}

	/**
	 * Testa che enqueue_styles NON carica il CSS per utenti senza manage_options
	 */
	public function test_enqueue_styles_skips_for_non_admin_user() {
		Functions\expect( 'current_user_can' )
			->once()
			->with( 'manage_options' )
			->andReturn( false );

		$runner  = $this->create_runner_mock();
		$storage = $this->create_storage_mock();
		$widget  = new DashboardWidget( $runner, $storage );
		$widget->enqueue_styles();

		// wp_enqueue_style non deve essere chiamato.
		$this->assertInstanceOf( DashboardWidget::class, $widget );
	}

	/**
	 * Testa che enqueue_styles NON carica il CSS su schermate non-dashboard
	 */
	public function test_enqueue_styles_skips_non_dashboard_screen() {
		$screen     = new \stdClass();
		$screen->id = 'plugins';

		Functions\expect( 'current_user_can' )
			->once()
			->with( 'manage_options' )
			->andReturn( true );

		Functions\expect( 'get_current_screen' )
			->once()
			->andReturn( $screen );

		$runner  = $this->create_runner_mock();
		$storage = $this->create_storage_mock();
		$widget  = new DashboardWidget( $runner, $storage );
		$widget->enqueue_styles();

		// wp_enqueue_style non deve essere chiamato.
		$this->assertInstanceOf( DashboardWidget::class, $widget );
	}

	/**
	 * Testa che enqueue_styles gestisce screen null
	 */
	public function test_enqueue_styles_handles_null_screen() {
		Functions\expect( 'current_user_can' )
			->once()
			->with( 'manage_options' )
			->andReturn( true );

		Functions\expect( 'get_current_screen' )
			->once()
			->andReturn( null );

		$runner  = $this->create_runner_mock();
		$storage = $this->create_storage_mock();
		$widget  = new DashboardWidget( $runner, $storage );
		$widget->enqueue_styles();

		// wp_enqueue_style non deve essere chiamato.
		$this->assertInstanceOf( DashboardWidget::class, $widget );
	}

	// ─── add_widget ─────────────────────────────────────────────────

	/**
	 * Testa che add_widget verifica la capability
	 */
	public function test_add_widget_checks_capability() {
		Functions\when( '__' )->returnArg();
		Functions\expect( 'current_user_can' )
			->once()
			->with( 'manage_options' )
			->andReturn( false );

		$runner  = $this->create_runner_mock();
		$storage = $this->create_storage_mock();
		$widget  = new DashboardWidget( $runner, $storage );

		// Non deve chiamare wp_add_dashboard_widget.
		$widget->add_widget();
	}

	/**
	 * Testa che add_widget aggiunge il widget per utenti autorizzati
	 */
	public function test_add_widget_adds_widget_for_admin() {
		Functions\when( '__' )->returnArg();
		Functions\expect( 'current_user_can' )
			->once()
			->with( 'manage_options' )
			->andReturn( true );

		$runner  = $this->create_runner_mock();
		$storage = $this->create_storage_mock();
		$widget  = new DashboardWidget( $runner, $storage );

		Functions\expect( 'wp_add_dashboard_widget' )
			->once()
			->with(
				'ops_health_dashboard_widget',
				Mockery::type( 'string' ),
				Mockery::on(
					function ( $callback ) use ( $widget ) {
						return is_array( $callback )
							&& $callback[0] === $widget
							&& 'render' === $callback[1];
					}
				)
			);

		$widget->add_widget();
	}

	// ─── render ─────────────────────────────────────────────────────

	/**
	 * Testa che render verifica la capability
	 */
	public function test_render_checks_capability() {
		Functions\expect( 'current_user_can' )
			->once()
			->with( 'manage_options' )
			->andReturn( false );

		$runner  = $this->create_runner_mock();
		$storage = $this->create_storage_mock();
		$widget  = new DashboardWidget( $runner, $storage );

		ob_start();
		$widget->render();
		$output = ob_get_clean();

		$this->assertEmpty( $output );
	}

	/**
	 * Testa che render mostra messaggio quando non ci sono risultati
	 */
	public function test_render_empty_results_message() {
		$this->mock_render_functions();
		$runner = $this->create_runner_mock();
		$runner->shouldReceive( 'get_latest_results' )->andReturn( [] );
		$storage = $this->create_storage_mock();

		$widget = new DashboardWidget( $runner, $storage );

		ob_start();
		$widget->render();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'no health checks', strtolower( $output ) );
	}

	/**
	 * Testa che render mostra lo stato per ogni check
	 */
	public function test_render_shows_check_results() {
		$this->mock_render_functions();
		$runner = $this->create_runner_mock();
		$runner->shouldReceive( 'get_latest_results' )->andReturn(
			[
				'database' => [
					'status'  => 'ok',
					'message' => 'Database healthy',
					'name'    => 'Database',
				],
				'disk'     => [
					'status'  => 'warning',
					'message' => 'Low space',
					'name'    => 'Disk Space',
				],
			]
		);
		$storage = $this->create_storage_mock();

		$widget = new DashboardWidget( $runner, $storage );

		ob_start();
		$widget->render();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Database', $output );
		$this->assertStringContainsString( 'Disk Space', $output );
		$this->assertStringContainsString( 'Ok', $output );
		$this->assertStringContainsString( 'Warning', $output );
	}

	/**
	 * Testa che render mostra lo stato globale Healthy
	 */
	public function test_render_shows_healthy_overall_status() {
		$this->mock_render_functions();
		$runner = $this->create_runner_mock();
		$runner->shouldReceive( 'get_latest_results' )->andReturn(
			[
				'database' => [
					'status'  => 'ok',
					'message' => 'OK',
					'name'    => 'Database',
				],
			]
		);
		$storage = $this->create_storage_mock();

		$widget = new DashboardWidget( $runner, $storage );

		ob_start();
		$widget->render();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Healthy', $output );
	}

	/**
	 * Testa che render mostra lo stato globale Critical (worst wins)
	 */
	public function test_render_shows_critical_overall_status() {
		$this->mock_render_functions();
		$runner = $this->create_runner_mock();
		$runner->shouldReceive( 'get_latest_results' )->andReturn(
			[
				'database' => [
					'status'  => 'ok',
					'message' => 'OK',
					'name'    => 'Database',
				],
				'disk'     => [
					'status'  => 'critical',
					'message' => 'Critical',
					'name'    => 'Disk',
				],
			]
		);
		$storage = $this->create_storage_mock();

		$widget = new DashboardWidget( $runner, $storage );

		ob_start();
		$widget->render();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Critical', $output );
	}

	/**
	 * Testa che render include il link alla dashboard completa
	 */
	public function test_render_includes_dashboard_link() {
		$this->mock_render_functions();
		$runner = $this->create_runner_mock();
		$runner->shouldReceive( 'get_latest_results' )->andReturn(
			[
				'database' => [
					'status'  => 'ok',
					'message' => 'OK',
					'name'    => 'Database',
				],
			]
		);
		$storage = $this->create_storage_mock();

		$widget = new DashboardWidget( $runner, $storage );

		ob_start();
		$widget->render();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'ops-health-dashboard', $output );
	}

	/**
	 * Testa che render usa escaping su tutti gli output
	 */
	public function test_render_uses_escaping() {
		Functions\when( '__' )->returnArg();
		Functions\when( 'esc_html__' )->returnArg();
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'admin_url' )->justReturn( 'http://example.com/' );
		Functions\when( 'human_time_diff' )->justReturn( '3 mins' );

		Functions\expect( 'esc_html' )
			->atLeast()
			->times( 1 )
			->andReturnFirstArg();
		Functions\expect( 'esc_attr' )
			->atLeast()
			->times( 1 )
			->andReturnFirstArg();
		Functions\expect( 'esc_url' )
			->atLeast()
			->times( 1 )
			->andReturnFirstArg();

		$runner = $this->create_runner_mock();
		$runner->shouldReceive( 'get_latest_results' )->andReturn(
			[
				'database' => [
					'status'  => 'ok',
					'message' => 'OK',
					'name'    => 'Database',
				],
			]
		);
		$storage = $this->create_storage_mock();

		$widget = new DashboardWidget( $runner, $storage );

		ob_start();
		$widget->render();
		ob_get_clean();
	}

	/**
	 * Testa che render include la classe CSS footer
	 */
	public function test_render_includes_footer_class() {
		$this->mock_render_functions();
		$runner = $this->create_runner_mock();
		$runner->shouldReceive( 'get_latest_results' )->andReturn(
			[
				'database' => [
					'status'  => 'ok',
					'message' => 'OK',
					'name'    => 'Database',
				],
			]
		);
		$storage = $this->create_storage_mock();

		$widget = new DashboardWidget( $runner, $storage );

		ob_start();
		$widget->render();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'ops-health-widget-footer', $output );
	}

	// ─── determine_overall_status ───────────────────────────────────

	/**
	 * Testa che overall status per risultati vuoti è 'unknown'
	 */
	public function test_overall_status_empty_is_unknown() {
		$runner  = $this->create_runner_mock();
		$storage = $this->create_storage_mock();
		$widget  = new DashboardWidget( $runner, $storage );

		$reflection = new \ReflectionMethod( DashboardWidget::class, 'determine_overall_status' );
		$reflection->setAccessible( true );

		$status = $reflection->invoke( $widget, [] );
		$this->assertEquals( 'unknown', $status );
	}

	/**
	 * Testa che overall status prende il peggiore (critical > warning > ok)
	 */
	public function test_overall_status_worst_wins() {
		$runner  = $this->create_runner_mock();
		$storage = $this->create_storage_mock();
		$widget  = new DashboardWidget( $runner, $storage );

		$reflection = new \ReflectionMethod( DashboardWidget::class, 'determine_overall_status' );
		$reflection->setAccessible( true );

		$results = [
			'a' => [ 'status' => 'ok' ],
			'b' => [ 'status' => 'warning' ],
			'c' => [ 'status' => 'ok' ],
		];
		$this->assertEquals( 'warning', $reflection->invoke( $widget, $results ) );

		$results['d'] = [ 'status' => 'critical' ];
		$this->assertEquals( 'critical', $reflection->invoke( $widget, $results ) );
	}

	/**
	 * Testa che overall status per tutti ok è 'ok'
	 */
	public function test_overall_status_all_ok() {
		$runner  = $this->create_runner_mock();
		$storage = $this->create_storage_mock();
		$widget  = new DashboardWidget( $runner, $storage );

		$reflection = new \ReflectionMethod( DashboardWidget::class, 'determine_overall_status' );
		$reflection->setAccessible( true );

		$results = [
			'a' => [ 'status' => 'ok' ],
			'b' => [ 'status' => 'ok' ],
		];
		$this->assertEquals( 'ok', $reflection->invoke( $widget, $results ) );
	}

	/**
	 * Testa che overall status gestisce risultati senza campo status
	 */
	public function test_overall_status_missing_status_field() {
		$runner  = $this->create_runner_mock();
		$storage = $this->create_storage_mock();
		$widget  = new DashboardWidget( $runner, $storage );

		$reflection = new \ReflectionMethod( DashboardWidget::class, 'determine_overall_status' );
		$reflection->setAccessible( true );

		$results = [
			'a' => [ 'message' => 'no status' ],
		];
		// Senza status, trattato come unknown → risultato unknown.
		$this->assertEquals( 'unknown', $reflection->invoke( $widget, $results ) );
	}

	// ─── Edge Cases Coverage ────────────────────────────────────────

	/**
	 * Testa render con risultato che ha status non riconosciuto (fallback unknown)
	 */
	public function test_render_with_unrecognized_status() {
		$this->mock_render_functions();
		$runner = $this->create_runner_mock();
		$runner->shouldReceive( 'get_latest_results' )->andReturn(
			[
				'custom' => [
					'status'  => 'weird_status',
					'message' => 'Something weird',
					'name'    => 'Custom Check',
				],
			]
		);
		$storage = $this->create_storage_mock();

		$widget = new DashboardWidget( $runner, $storage );

		ob_start();
		$widget->render();
		$output = ob_get_clean();

		// Overall status non riconosciuto → label "Unknown".
		$this->assertStringContainsString( 'Unknown', $output );
		$this->assertStringContainsString( 'Custom Check', $output );
	}

	/**
	 * Testa render con risultato senza campo name (fallback ucfirst check_id)
	 */
	public function test_render_with_missing_name_field() {
		$this->mock_render_functions();

		$runner = $this->create_runner_mock();
		$runner->shouldReceive( 'get_latest_results' )->andReturn(
			[
				'database' => [
					'status'  => 'ok',
					'message' => 'OK',
					// No 'name' key → fallback to ucfirst('database').
				],
			]
		);
		$storage = $this->create_storage_mock();

		$widget = new DashboardWidget( $runner, $storage );

		ob_start();
		$widget->render();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Database', $output );
	}

	/**
	 * Testa overall status con solo status non riconosciuti e ok
	 */
	public function test_overall_status_unrecognized_with_ok() {
		$runner  = $this->create_runner_mock();
		$storage = $this->create_storage_mock();
		$widget  = new DashboardWidget( $runner, $storage );

		$reflection = new \ReflectionMethod( DashboardWidget::class, 'determine_overall_status' );
		$reflection->setAccessible( true );

		$results = [
			'a' => [ 'status' => 'unrecognized' ],
			'b' => [ 'status' => 'ok' ],
		];
		// 'ok' ha priority 1, 'unrecognized' ha priority 0 → ok vince.
		$this->assertEquals( 'ok', $reflection->invoke( $widget, $results ) );
	}

	// ─── v0.6.2: Clickable check names ─────────────────────────────

	/**
	 * Testa che i nomi dei check sono link con ancora alla dashboard
	 */
	public function test_check_names_are_links_with_anchor() {
		$this->mock_render_functions();
		$runner = $this->create_runner_mock();
		$runner->shouldReceive( 'get_latest_results' )->andReturn(
			[
				'database' => [
					'status'  => 'ok',
					'message' => 'OK',
					'name'    => 'Database',
				],
			]
		);
		$storage = $this->create_storage_mock();

		$widget = new DashboardWidget( $runner, $storage );

		ob_start();
		$widget->render();
		$output = ob_get_clean();

		$this->assertStringContainsString( '#check-database', $output );
		$this->assertStringContainsString( '<a href=', $output );
	}

	/**
	 * Testa che ogni check ha link con ancora corretta
	 */
	public function test_each_check_has_correct_anchor() {
		$this->mock_render_functions();
		$runner = $this->create_runner_mock();
		$runner->shouldReceive( 'get_latest_results' )->andReturn(
			[
				'database'  => [
					'status' => 'ok',
					'name'   => 'Database',
				],
				'error_log' => [
					'status' => 'warning',
					'name'   => 'Error Log',
				],
			]
		);
		$storage = $this->create_storage_mock();

		$widget = new DashboardWidget( $runner, $storage );

		ob_start();
		$widget->render();
		$output = ob_get_clean();

		$this->assertStringContainsString( '#check-database', $output );
		$this->assertStringContainsString( '#check-error_log', $output );
	}

	// ─── v0.6.2: Timing display ────────────────────────────────────

	/**
	 * Testa che il timing viene mostrato quando last_run_at è disponibile
	 */
	public function test_timing_shown_when_last_run_at_available() {
		$this->mock_render_functions();
		$runner = $this->create_runner_mock();
		$runner->shouldReceive( 'get_latest_results' )->andReturn(
			[
				'database' => [
					'status' => 'ok',
					'name'   => 'Database',
				],
			]
		);
		$storage = $this->create_storage_mock( time() - 180 );

		$widget = new DashboardWidget( $runner, $storage );

		ob_start();
		$widget->render();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'ops-health-widget-timing', $output );
		$this->assertStringContainsString( 'Last run', $output );
	}

	/**
	 * Testa che il timing NON viene mostrato quando last_run_at è 0
	 */
	public function test_timing_hidden_when_no_last_run() {
		$this->mock_render_functions();
		$runner = $this->create_runner_mock();
		$runner->shouldReceive( 'get_latest_results' )->andReturn(
			[
				'database' => [
					'status' => 'ok',
					'name'   => 'Database',
				],
			]
		);
		$storage = $this->create_storage_mock( 0 );

		$widget = new DashboardWidget( $runner, $storage );

		ob_start();
		$widget->render();
		$output = ob_get_clean();

		$this->assertStringNotContainsString( 'ops-health-widget-timing', $output );
	}

	/**
	 * Testa che il timing include "ago" nel testo
	 */
	public function test_timing_includes_ago_text() {
		$this->mock_render_functions();
		$runner = $this->create_runner_mock();
		$runner->shouldReceive( 'get_latest_results' )->andReturn(
			[
				'database' => [
					'status' => 'ok',
					'name'   => 'Database',
				],
			]
		);
		$storage = $this->create_storage_mock( time() - 300 );

		$widget = new DashboardWidget( $runner, $storage );

		ob_start();
		$widget->render();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'ago', $output );
	}
}
