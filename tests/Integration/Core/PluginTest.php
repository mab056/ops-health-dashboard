<?php
/**
 * Integration Test for the Plugin with WordPress Test Suite
 *
 * Verifies that Plugin works with real WordPress
 * (hook registration, idempotency, container access).
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
 * Integration test for Plugin.
 */
class PluginTest extends WP_UnitTestCase {

	/**
	 * Tests that Plugin can be instantiated
	 */
	public function test_plugin_can_be_instantiated() {
		$plugin = bootstrap();
		$this->assertInstanceOf( Plugin::class, $plugin );
	}

	/**
	 * Tests that get_container() returns the Container
	 */
	public function test_get_container_returns_container() {
		$plugin = bootstrap();
		$this->assertInstanceOf( Container::class, $plugin->get_container() );
	}

	/**
	 * Tests that init() registers the admin_menu hook
	 */
	public function test_init_registers_admin_menu_hook() {
		$plugin = bootstrap();

		// Remove any pre-registered hooks.
		remove_all_actions( 'admin_menu' );

		$plugin->init();

		$this->assertGreaterThan(
			0,
			has_action( 'admin_menu' ),
			'admin_menu hook should be registered after init()'
		);
	}

	/**
	 * Tests that init() registers the cron hook
	 */
	public function test_init_registers_cron_hook() {
		$plugin = bootstrap();

		// Remove any pre-registered hooks.
		remove_all_actions( 'ops_health_run_checks' );

		$plugin->init();

		$this->assertGreaterThan(
			0,
			has_action( 'ops_health_run_checks' ),
			'ops_health_run_checks hook should be registered after init()'
		);
	}

	/**
	 * Tests that init() registers the cron_schedules filter
	 */
	public function test_init_registers_cron_schedules_filter() {
		$plugin = bootstrap();

		// Remove any pre-registered filters.
		remove_all_filters( 'cron_schedules' );

		$plugin->init();

		$this->assertGreaterThan(
			0,
			has_filter( 'cron_schedules' ),
			'cron_schedules filter should be registered after init()'
		);
	}

	/**
	 * Tests that init() is idempotent — hooks registered only once
	 */
	public function test_init_is_idempotent() {
		$plugin = bootstrap();

		remove_all_actions( 'admin_menu' );

		$plugin->init();
		$priority_first = has_action( 'admin_menu' );

		$plugin->init();
		$priority_second = has_action( 'admin_menu' );

		// The priority does not change: no duplicate hooks added.
		$this->assertEquals( $priority_first, $priority_second, 'init() should be idempotent' );
	}

	/**
	 * Tests that init() registers the admin_enqueue_scripts hook
	 */
	public function test_init_registers_admin_enqueue_scripts_hook() {
		$plugin = bootstrap();

		remove_all_actions( 'admin_enqueue_scripts' );

		$plugin->init();

		$this->assertGreaterThan(
			0,
			has_action( 'admin_enqueue_scripts' ),
			'admin_enqueue_scripts hook should be registered after init()'
		);
	}

	/**
	 * Tests that the class is NOT final
	 */
	public function test_class_is_not_final() {
		$reflection = new \ReflectionClass( Plugin::class );
		$this->assertFalse( $reflection->isFinal(), 'Plugin should NOT be final' );
	}

	/**
	 * Tests that NO static methods exist
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
	 * Tests that NO static properties exist
	 */
	public function test_no_static_properties() {
		$reflection = new \ReflectionClass( Plugin::class );
		$properties = $reflection->getProperties( \ReflectionProperty::IS_STATIC );

		$this->assertEmpty( $properties, 'Plugin should have NO static properties' );
	}
}
