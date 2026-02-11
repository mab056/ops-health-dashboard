<?php
/**
 * Test dell'Uninstaller
 *
 * @package OpsHealthDashboard\Tests\Unit\Core
 */

namespace OpsHealthDashboard\Tests\Unit\Core;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use OpsHealthDashboard\Core\Uninstaller;
use PHPUnit\Framework\TestCase;

/**
 * Class UninstallerTest
 *
 * TDD per la disinstallazione del plugin con Brain\Monkey.
 */
class UninstallerTest extends TestCase {
	use MockeryPHPUnitIntegration;

	/**
	 * Setup del test con Brain\Monkey
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	/**
	 * Teardown del test con Brain\Monkey
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Crea un mock di $wpdb per i test
	 *
	 * @return object Mock $wpdb.
	 */
	private function create_wpdb_mock() {
		$wpdb          = \Mockery::mock( 'wpdb' );
		$wpdb->options = 'wp_options';
		return $wpdb;
	}

	/**
	 * Testa che la classe NON è final
	 */
	public function test_class_is_not_final() {
		$reflection = new \ReflectionClass( Uninstaller::class );
		$this->assertFalse( $reflection->isFinal(), 'Uninstaller should NOT be final' );
	}

	/**
	 * Testa che NON esistono metodi static
	 */
	public function test_no_static_methods() {
		$reflection = new \ReflectionClass( Uninstaller::class );
		$methods    = $reflection->getMethods( \ReflectionMethod::IS_STATIC );

		$static_methods = array_filter(
			$methods,
			function ( $method ) {
				return strpos( $method->getName(), '__' ) !== 0;
			}
		);

		$this->assertEmpty( $static_methods, 'Uninstaller should have NO static methods' );
	}

	/**
	 * Testa che NON esistono proprietà static
	 */
	public function test_no_static_properties() {
		$reflection = new \ReflectionClass( Uninstaller::class );
		$properties = $reflection->getProperties( \ReflectionProperty::IS_STATIC );

		$this->assertEmpty( $properties, 'Uninstaller should have NO static properties' );
	}

	/**
	 * Testa che Uninstaller può essere istanziato con $wpdb
	 */
	public function test_uninstaller_can_be_instantiated() {
		$wpdb        = $this->create_wpdb_mock();
		$uninstaller = new Uninstaller( $wpdb );
		$this->assertInstanceOf( Uninstaller::class, $uninstaller );
	}

	/**
	 * Testa che uninstall() cancella tutte le opzioni del plugin
	 */
	public function test_uninstall_deletes_all_options() {
		$wpdb = $this->create_wpdb_mock();

		$expected_options = [
			'ops_health_activated_at',
			'ops_health_version',
			'ops_health_latest_results',
			'ops_health_alert_settings',
			'ops_health_alert_log',
		];

		foreach ( $expected_options as $option ) {
			Functions\expect( 'delete_option' )
				->once()
				->with( $option );
		}

		Functions\expect( 'wp_clear_scheduled_hook' )
			->once()
			->with( 'ops_health_run_checks' );

		Functions\expect( 'delete_transient' )
			->times( 3 );

		$wpdb->shouldReceive( 'prepare' )
			->once()
			->andReturn( "DELETE FROM wp_options WHERE option_name LIKE '_transient_ops_health_alert_cooldown_%' OR option_name LIKE '_transient_timeout_ops_health_alert_cooldown_%'" );

		$wpdb->shouldReceive( 'query' )
			->once();

		$uninstaller = new Uninstaller( $wpdb );
		$uninstaller->uninstall();
	}

	/**
	 * Testa che uninstall() cancella il cron hook corretto
	 */
	public function test_uninstall_clears_cron_hook() {
		$wpdb = $this->create_wpdb_mock();

		Functions\expect( 'delete_option' )->times( 5 );

		Functions\expect( 'wp_clear_scheduled_hook' )
			->once()
			->with( 'ops_health_run_checks' );

		Functions\expect( 'delete_transient' )->times( 3 );

		$wpdb->shouldReceive( 'prepare' )->once()->andReturn( '' );
		$wpdb->shouldReceive( 'query' )->once();

		$uninstaller = new Uninstaller( $wpdb );
		$uninstaller->uninstall();
	}

	/**
	 * Testa che uninstall() cancella i transient fissi
	 */
	public function test_uninstall_deletes_fixed_transients() {
		$wpdb = $this->create_wpdb_mock();

		Functions\expect( 'delete_option' )->times( 5 );
		Functions\expect( 'wp_clear_scheduled_hook' )->once();

		$expected_transients = [
			'ops_health_cron_check',
			'ops_health_admin_notice',
			'ops_health_alert_notice',
		];

		foreach ( $expected_transients as $transient ) {
			Functions\expect( 'delete_transient' )
				->once()
				->with( $transient );
		}

		$wpdb->shouldReceive( 'prepare' )->once()->andReturn( '' );
		$wpdb->shouldReceive( 'query' )->once();

		$uninstaller = new Uninstaller( $wpdb );
		$uninstaller->uninstall();
	}

	/**
	 * Testa che uninstall() cancella i transient di cooldown via $wpdb
	 */
	public function test_uninstall_deletes_cooldown_transients_via_wpdb() {
		$wpdb = $this->create_wpdb_mock();

		Functions\expect( 'delete_option' )->times( 5 );
		Functions\expect( 'wp_clear_scheduled_hook' )->once();
		Functions\expect( 'delete_transient' )->times( 3 );

		$expected_sql = "DELETE FROM wp_options WHERE option_name LIKE %s OR option_name LIKE %s";

		$wpdb->shouldReceive( 'prepare' )
			->once()
			->with(
				$expected_sql,
				'_transient_ops_health_alert_cooldown_%',
				'_transient_timeout_ops_health_alert_cooldown_%'
			)
			->andReturn( 'prepared_query' );

		$wpdb->shouldReceive( 'query' )
			->once()
			->with( 'prepared_query' );

		$uninstaller = new Uninstaller( $wpdb );
		$uninstaller->uninstall();
	}

	/**
	 * Testa che uninstall() usa $wpdb->prepare per prevenire SQL injection
	 */
	public function test_uninstall_uses_wpdb_prepare() {
		$wpdb = $this->create_wpdb_mock();

		Functions\expect( 'delete_option' )->times( 5 );
		Functions\expect( 'wp_clear_scheduled_hook' )->once();
		Functions\expect( 'delete_transient' )->times( 3 );

		$wpdb->shouldReceive( 'prepare' )
			->once()
			->with(
				\Mockery::type( 'string' ),
				\Mockery::type( 'string' ),
				\Mockery::type( 'string' )
			)
			->andReturn( 'safe_query' );

		$wpdb->shouldReceive( 'query' )
			->once()
			->with( 'safe_query' );

		$uninstaller = new Uninstaller( $wpdb );
		$uninstaller->uninstall();
	}

	/**
	 * Testa che uninstall() usa la tabella corretta da $wpdb->options
	 */
	public function test_uninstall_targets_correct_table() {
		$wpdb          = \Mockery::mock( 'wpdb' );
		$wpdb->options = 'custom_prefix_options';

		Functions\expect( 'delete_option' )->times( 5 );
		Functions\expect( 'wp_clear_scheduled_hook' )->once();
		Functions\expect( 'delete_transient' )->times( 3 );

		$wpdb->shouldReceive( 'prepare' )
			->once()
			->with(
				\Mockery::on(
					function ( $sql ) {
						return strpos( $sql, 'custom_prefix_options' ) !== false;
					}
				),
				\Mockery::type( 'string' ),
				\Mockery::type( 'string' )
			)
			->andReturn( 'prepared_query' );

		$wpdb->shouldReceive( 'query' )->once();

		$uninstaller = new Uninstaller( $wpdb );
		$uninstaller->uninstall();
	}

	/**
	 * Testa che uninstall() è sicuro se chiamato più volte
	 */
	public function test_uninstall_is_safe_when_called_twice() {
		$wpdb = $this->create_wpdb_mock();

		Functions\expect( 'delete_option' )->times( 10 );
		Functions\expect( 'wp_clear_scheduled_hook' )->times( 2 );
		Functions\expect( 'delete_transient' )->times( 6 );

		$wpdb->shouldReceive( 'prepare' )->times( 2 )->andReturn( '' );
		$wpdb->shouldReceive( 'query' )->times( 2 );

		$uninstaller = new Uninstaller( $wpdb );
		$uninstaller->uninstall();
		$uninstaller->uninstall();
	}

	/**
	 * Testa che il metodo uninstall() esiste e è pubblico
	 */
	public function test_uninstall_method_is_public() {
		$reflection = new \ReflectionClass( Uninstaller::class );
		$method     = $reflection->getMethod( 'uninstall' );
		$this->assertTrue( $method->isPublic(), 'uninstall() should be public' );
	}

	/**
	 * Testa che il costruttore accetta $wpdb come parametro
	 */
	public function test_constructor_accepts_wpdb_parameter() {
		$reflection  = new \ReflectionClass( Uninstaller::class );
		$constructor = $reflection->getConstructor();

		$this->assertNotNull( $constructor, 'Constructor should exist' );
		$this->assertCount( 1, $constructor->getParameters(), 'Constructor should accept 1 parameter' );
		$this->assertEquals( 'wpdb', $constructor->getParameters()[0]->getName() );
	}
}
