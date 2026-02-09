<?php
/**
 * Integration Test del Plugin con WordPress Test Suite
 *
 * Verifica che Plugin funzioni con WordPress reale
 * (hook registration, idempotenza, container access).
 *
 * @package OpsHealthDashboard\Tests\Integration\Core
 */

namespace OpsHealthDashboard\Tests\Integration\Core;

use OpsHealthDashboard\Core\Container;
use OpsHealthDashboard\Core\Plugin;
use WP_UnitTestCase;

use function OpsHealthDashboard\bootstrap;

/**
 * Class PluginTest
 *
 * Integration test per Plugin.
 */
class PluginTest extends WP_UnitTestCase {

	/**
	 * Testa che Plugin può essere istanziato
	 */
	public function test_plugin_can_be_instantiated() {
		$plugin = bootstrap();
		$this->assertInstanceOf( Plugin::class, $plugin );
	}

	/**
	 * Testa che get_container() ritorna il Container
	 */
	public function test_get_container_returns_container() {
		$plugin = bootstrap();
		$this->assertInstanceOf( Container::class, $plugin->get_container() );
	}

	/**
	 * Testa che init() registra l'hook admin_menu
	 */
	public function test_init_registers_admin_menu_hook() {
		$plugin = bootstrap();

		// Rimuovi eventuali hook pre-registrati.
		remove_all_actions( 'admin_menu' );

		$plugin->init();

		$this->assertGreaterThan(
			0,
			has_action( 'admin_menu' ),
			'admin_menu hook should be registered after init()'
		);
	}

	/**
	 * Testa che init() registra l'hook per il cron
	 */
	public function test_init_registers_cron_hook() {
		$plugin = bootstrap();

		// Rimuovi eventuali hook pre-registrati.
		remove_all_actions( 'ops_health_run_checks' );

		$plugin->init();

		$this->assertGreaterThan(
			0,
			has_action( 'ops_health_run_checks' ),
			'ops_health_run_checks hook should be registered after init()'
		);
	}

	/**
	 * Testa che init() registra il filtro cron_schedules
	 */
	public function test_init_registers_cron_schedules_filter() {
		$plugin = bootstrap();

		// Rimuovi eventuali filtri pre-registrati.
		remove_all_filters( 'cron_schedules' );

		$plugin->init();

		$this->assertGreaterThan(
			0,
			has_filter( 'cron_schedules' ),
			'cron_schedules filter should be registered after init()'
		);
	}

	/**
	 * Testa che init() è idempotente — hooks registrati una sola volta
	 */
	public function test_init_is_idempotent() {
		$plugin = bootstrap();

		remove_all_actions( 'admin_menu' );

		$plugin->init();
		$priority_first = has_action( 'admin_menu' );

		$plugin->init();
		$priority_second = has_action( 'admin_menu' );

		// La priorità non cambia: non ha aggiunto hook doppi.
		$this->assertEquals( $priority_first, $priority_second, 'init() should be idempotent' );
	}

	/**
	 * Testa che la classe NON è final
	 */
	public function test_class_is_not_final() {
		$reflection = new \ReflectionClass( Plugin::class );
		$this->assertFalse( $reflection->isFinal(), 'Plugin should NOT be final' );
	}

	/**
	 * Testa che NON esistono metodi static
	 */
	public function test_no_static_methods() {
		$reflection = new \ReflectionClass( Plugin::class );
		$methods    = $reflection->getMethods( \ReflectionMethod::IS_STATIC );

		$static_methods = array_filter(
			$methods,
			function ( $method ) {
				return strpos( $method->getName(), '__' ) !== 0;
			}
		);

		$this->assertEmpty( $static_methods, 'Plugin should have NO static methods' );
	}

	/**
	 * Testa che NON esistono proprietà static
	 */
	public function test_no_static_properties() {
		$reflection = new \ReflectionClass( Plugin::class );
		$properties = $reflection->getProperties( \ReflectionProperty::IS_STATIC );

		$this->assertEmpty( $properties, 'Plugin should have NO static properties' );
	}
}
