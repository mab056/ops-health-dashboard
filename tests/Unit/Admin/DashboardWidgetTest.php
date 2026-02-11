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
	 * Configura i mock comuni per il rendering
	 */
	private function mock_render_functions(): void {
		Functions\when( '__' )->returnArg();
		Functions\when( 'esc_html__' )->returnArg();
		Functions\when( 'esc_html' )->returnArg();
		Functions\when( 'esc_attr' )->returnArg();
		Functions\when( 'esc_url' )->returnArg();
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'admin_url' )->justReturn( 'http://example.com/wp-admin/admin.php?page=ops-health-dashboard' );
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
	 * Testa che DashboardWidget si istanzia con CheckRunnerInterface
	 */
	public function test_instantiates_with_runner() {
		$runner = $this->create_runner_mock();
		$widget = new DashboardWidget( $runner );
		$this->assertInstanceOf( DashboardWidget::class, $widget );
	}

	// ─── register_hooks ─────────────────────────────────────────────

	/**
	 * Testa che register_hooks registra l'hook wp_dashboard_setup
	 */
	public function test_register_hooks_adds_dashboard_setup() {
		$runner = $this->create_runner_mock();
		$widget = new DashboardWidget( $runner );

		Functions\expect( 'add_action' )
			->once()
			->with(
				'wp_dashboard_setup',
				Mockery::on(
					function ( $callback ) use ( $widget ) {
						return is_array( $callback )
							&& $callback[0] === $widget
							&& 'add_widget' === $callback[1];
					}
				)
			);

		$widget->register_hooks();
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

		$runner = $this->create_runner_mock();
		$widget = new DashboardWidget( $runner );

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

		$runner = $this->create_runner_mock();
		$widget = new DashboardWidget( $runner );

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

		$runner = $this->create_runner_mock();
		$widget = new DashboardWidget( $runner );

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

		$widget = new DashboardWidget( $runner );

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

		$widget = new DashboardWidget( $runner );

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

		$widget = new DashboardWidget( $runner );

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

		$widget = new DashboardWidget( $runner );

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

		$widget = new DashboardWidget( $runner );

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

		$widget = new DashboardWidget( $runner );

		ob_start();
		$widget->render();
		ob_get_clean();
	}

	// ─── determine_overall_status ───────────────────────────────────

	/**
	 * Testa che overall status per risultati vuoti è 'unknown'
	 */
	public function test_overall_status_empty_is_unknown() {
		$runner = $this->create_runner_mock();
		$widget = new DashboardWidget( $runner );

		$reflection = new \ReflectionMethod( DashboardWidget::class, 'determine_overall_status' );
		$reflection->setAccessible( true );

		$status = $reflection->invoke( $widget, [] );
		$this->assertEquals( 'unknown', $status );
	}

	/**
	 * Testa che overall status prende il peggiore (critical > warning > ok)
	 */
	public function test_overall_status_worst_wins() {
		$runner = $this->create_runner_mock();
		$widget = new DashboardWidget( $runner );

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
		$runner = $this->create_runner_mock();
		$widget = new DashboardWidget( $runner );

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
		$runner = $this->create_runner_mock();
		$widget = new DashboardWidget( $runner );

		$reflection = new \ReflectionMethod( DashboardWidget::class, 'determine_overall_status' );
		$reflection->setAccessible( true );

		$results = [
			'a' => [ 'message' => 'no status' ],
		];
		// Senza status, trattato come unknown → risultato unknown.
		$this->assertEquals( 'unknown', $reflection->invoke( $widget, $results ) );
	}
}
