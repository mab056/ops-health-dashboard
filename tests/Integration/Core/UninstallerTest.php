<?php
/**
 * Integration Test for the Uninstaller with WordPress Test Suite
 *
 * Real integration tests that verify complete cleanup
 * of plugin data from the WordPress database.
 *
 * @package OpsHealthDashboard\Tests\Integration\Core
 */

namespace OpsHealthDashboard\Tests\Integration\Core;

use OpsHealthDashboard\Core\Activator;
use OpsHealthDashboard\Core\Uninstaller;
use WP_UnitTestCase;

/**
 * Class UninstallerTest
 *
 * Integration test for Uninstaller with real WordPress.
 */
class UninstallerTest extends WP_UnitTestCase {

	/**
	 * Cleanup after each test
	 */
	public function tearDown(): void {
		delete_option( 'ops_health_activated_at' );
		delete_option( 'ops_health_version' );
		delete_option( 'ops_health_latest_results' );
		delete_option( 'ops_health_alert_settings' );
		delete_option( 'ops_health_alert_log' );
		delete_transient( 'ops_health_cron_check' );
		delete_transient( 'ops_health_admin_notice' );
		delete_transient( 'ops_health_alert_notice' );
		wp_clear_scheduled_hook( 'ops_health_run_checks' );
		parent::tearDown();
	}

	/**
	 * Tests that the class is NOT final
	 */
	public function test_class_is_not_final() {
		$reflection = new \ReflectionClass( Uninstaller::class );
		$this->assertFalse( $reflection->isFinal(), 'Uninstaller should NOT be final' );
	}

	/**
	 * Tests that NO static methods exist
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
	 * Tests that NO static properties exist
	 */
	public function test_no_static_properties() {
		$reflection = new \ReflectionClass( Uninstaller::class );
		$properties = $reflection->getProperties( \ReflectionProperty::IS_STATIC );

		$this->assertEmpty( $properties, 'Uninstaller should have NO static properties' );
	}

	/**
	 * Tests that uninstall() deletes all plugin options
	 */
	public function test_uninstall_deletes_all_options() {
		global $wpdb;

		// Create plugin options.
		update_option( 'ops_health_activated_at', time() );
		update_option( 'ops_health_version', '0.6.0' );
		update_option( 'ops_health_latest_results', [ 'test' => 'data' ] );
		update_option( 'ops_health_alert_settings', [ 'enabled' => true ] );
		update_option( 'ops_health_alert_log', [ [ 'time' => time() ] ] );

		// Verify that the options exist.
		$this->assertNotFalse( get_option( 'ops_health_activated_at' ) );
		$this->assertNotFalse( get_option( 'ops_health_version' ) );
		$this->assertNotFalse( get_option( 'ops_health_latest_results' ) );
		$this->assertNotFalse( get_option( 'ops_health_alert_settings' ) );
		$this->assertNotFalse( get_option( 'ops_health_alert_log' ) );

		// Run the uninstallation.
		$uninstaller = new Uninstaller( $wpdb );
		$uninstaller->uninstall();

		// Verify that all options have been deleted.
		$this->assertFalse( get_option( 'ops_health_activated_at' ), 'ops_health_activated_at should be deleted' );
		$this->assertFalse( get_option( 'ops_health_version' ), 'ops_health_version should be deleted' );
		$this->assertFalse( get_option( 'ops_health_latest_results' ), 'ops_health_latest_results should be deleted' );
		$this->assertFalse( get_option( 'ops_health_alert_settings' ), 'ops_health_alert_settings should be deleted' );
		$this->assertFalse( get_option( 'ops_health_alert_log' ), 'ops_health_alert_log should be deleted' );
	}

	/**
	 * Tests that uninstall() clears the cron event
	 */
	public function test_uninstall_clears_cron_event() {
		global $wpdb;

		// Schedule the cron hook.
		wp_schedule_event( time(), 'hourly', 'ops_health_run_checks' );
		$this->assertNotFalse( wp_next_scheduled( 'ops_health_run_checks' ) );

		// Run the uninstallation.
		$uninstaller = new Uninstaller( $wpdb );
		$uninstaller->uninstall();

		// Verify that the cron has been cleared.
		$this->assertFalse( wp_next_scheduled( 'ops_health_run_checks' ), 'Cron should be cleared' );
	}

	/**
	 * Tests that uninstall() deletes fixed transients
	 */
	public function test_uninstall_deletes_fixed_transients() {
		global $wpdb;

		// Create the transients.
		set_transient( 'ops_health_cron_check', '1', HOUR_IN_SECONDS );
		set_transient( 'ops_health_admin_notice', 'done', 30 );
		set_transient( 'ops_health_alert_notice', 'saved', 30 );

		// Verify that the transients exist.
		$this->assertNotFalse( get_transient( 'ops_health_cron_check' ) );
		$this->assertNotFalse( get_transient( 'ops_health_admin_notice' ) );
		$this->assertNotFalse( get_transient( 'ops_health_alert_notice' ) );

		// Run the uninstallation.
		$uninstaller = new Uninstaller( $wpdb );
		$uninstaller->uninstall();

		// Verify that the transients have been deleted.
		$this->assertFalse( get_transient( 'ops_health_cron_check' ), 'ops_health_cron_check should be deleted' );
		$this->assertFalse( get_transient( 'ops_health_admin_notice' ), 'ops_health_admin_notice should be deleted' );
		$this->assertFalse( get_transient( 'ops_health_alert_notice' ), 'ops_health_alert_notice should be deleted' );
	}

	/**
	 * Tests that uninstall() deletes dynamic cooldown transients
	 */
	public function test_uninstall_deletes_cooldown_transients() {
		global $wpdb;

		// Create cooldown transients for different checks.
		set_transient( 'ops_health_alert_cooldown_database', '1', 3600 );
		set_transient( 'ops_health_alert_cooldown_error_log', '1', 3600 );
		set_transient( 'ops_health_alert_cooldown_redis', '1', 3600 );
		set_transient( 'ops_health_alert_cooldown_disk', '1', 3600 );
		set_transient( 'ops_health_alert_cooldown_versions', '1', 3600 );

		// Verify that the transients exist.
		$this->assertNotFalse( get_transient( 'ops_health_alert_cooldown_database' ) );
		$this->assertNotFalse( get_transient( 'ops_health_alert_cooldown_redis' ) );

		// Run the uninstallation.
		$uninstaller = new Uninstaller( $wpdb );
		$uninstaller->uninstall();

		// Flush object cache: $wpdb->query() bypasses the WP cache layer.
		wp_cache_flush();

		// Verify that the cooldown transients have been deleted.
		$this->assertFalse( get_transient( 'ops_health_alert_cooldown_database' ), 'database cooldown should be deleted' );
		$this->assertFalse( get_transient( 'ops_health_alert_cooldown_error_log' ), 'error_log cooldown should be deleted' );
		$this->assertFalse( get_transient( 'ops_health_alert_cooldown_redis' ), 'redis cooldown should be deleted' );
		$this->assertFalse( get_transient( 'ops_health_alert_cooldown_disk' ), 'disk cooldown should be deleted' );
		$this->assertFalse( get_transient( 'ops_health_alert_cooldown_versions' ), 'versions cooldown should be deleted' );
	}

	/**
	 * Tests that uninstall() is safe when no data exists
	 */
	public function test_uninstall_is_safe_when_no_data_exists() {
		global $wpdb;

		// Explicit cleanup to avoid residuals from other tests.
		delete_option( 'ops_health_activated_at' );
		wp_clear_scheduled_hook( 'ops_health_run_checks' );
		delete_transient( 'ops_health_cron_check' );

		// Verify pre-condition: no plugin data.
		$this->assertFalse( get_option( 'ops_health_activated_at' ) );
		$this->assertFalse( wp_next_scheduled( 'ops_health_run_checks' ) );
		$this->assertFalse( get_transient( 'ops_health_cron_check' ) );

		// Should not generate errors.
		$uninstaller = new Uninstaller( $wpdb );
		$uninstaller->uninstall();

		// Post-condition: everything still false (no errors generated).
		$this->assertFalse( get_option( 'ops_health_activated_at' ) );
	}

	/**
	 * Tests that uninstall() preserves non-plugin options
	 */
	public function test_uninstall_preserves_non_plugin_options() {
		global $wpdb;

		// Create a non-plugin option and a plugin option.
		update_option( 'some_other_plugin_option', 'keep_me' );
		update_option( 'ops_health_version', '0.6.0' );

		// Run the uninstallation.
		$uninstaller = new Uninstaller( $wpdb );
		$uninstaller->uninstall();

		// The plugin option is deleted, the other preserved.
		$this->assertFalse( get_option( 'ops_health_version' ) );
		$this->assertEquals( 'keep_me', get_option( 'some_other_plugin_option' ) );

		// Cleanup.
		delete_option( 'some_other_plugin_option' );
	}

	/**
	 * Tests that uninstall() preserves non-plugin transients
	 */
	public function test_uninstall_preserves_non_plugin_transients() {
		global $wpdb;

		// Create a non-plugin transient and a plugin transient.
		set_transient( 'some_other_transient', 'keep_me', 3600 );
		set_transient( 'ops_health_cron_check', '1', 3600 );

		// Run the uninstallation.
		$uninstaller = new Uninstaller( $wpdb );
		$uninstaller->uninstall();

		// The plugin transient is deleted, the other preserved.
		$this->assertFalse( get_transient( 'ops_health_cron_check' ) );
		$this->assertEquals( 'keep_me', get_transient( 'some_other_transient' ) );

		// Cleanup.
		delete_transient( 'some_other_transient' );
	}

	/**
	 * Tests the full activate → uninstall cycle
	 */
	public function test_full_activate_then_uninstall_cycle() {
		global $wpdb;

		if ( ! defined( 'OPS_HEALTH_DASHBOARD_VERSION' ) ) {
			define( 'OPS_HEALTH_DASHBOARD_VERSION', '0.6.0' );
		}

		// Activate the plugin.
		$activator = new Activator();
		$activator->activate();

		// Verify that the activation data exists.
		$this->assertNotFalse( get_option( 'ops_health_activated_at' ) );
		$this->assertNotFalse( get_option( 'ops_health_version' ) );
		$this->assertNotFalse( wp_next_scheduled( 'ops_health_run_checks' ) );

		// Uninstall.
		$uninstaller = new Uninstaller( $wpdb );
		$uninstaller->uninstall();

		// Verify complete cleanup.
		$this->assertFalse( get_option( 'ops_health_activated_at' ) );
		$this->assertFalse( get_option( 'ops_health_version' ) );
		$this->assertFalse( wp_next_scheduled( 'ops_health_run_checks' ) );
	}

	// ---------------------------------------------------
	// Multisite support (requires WP_TESTS_MULTISITE=1)
	// ---------------------------------------------------

	/**
	 * Tests that on multisite uninstall() cleans all blogs in the network
	 */
	public function test_uninstall_on_multisite_cleans_all_sites() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Requires multisite.' );
		}

		global $wpdb;

		// Create additional blogs in the network.
		$blog_id_2 = self::factory()->blog->create();
		$blog_id_3 = self::factory()->blog->create();

		$blogs = [ get_current_blog_id(), $blog_id_2, $blog_id_3 ];

		// Set plugin data on each blog.
		foreach ( $blogs as $blog_id ) {
			switch_to_blog( $blog_id );
			update_option( 'ops_health_activated_at', time() );
			update_option( 'ops_health_version', '0.6.0' );
			update_option( 'ops_health_latest_results', [ 'test' => 'data' ] );
			update_option( 'ops_health_alert_settings', [ 'enabled' => true ] );
			update_option( 'ops_health_alert_log', [ [ 'time' => time() ] ] );
			set_transient( 'ops_health_cron_check', '1', HOUR_IN_SECONDS );
			wp_schedule_event( time(), 'hourly', 'ops_health_run_checks' );
			restore_current_blog();
		}

		// Run the uninstallation.
		$uninstaller = new Uninstaller( $wpdb );
		$uninstaller->uninstall();

		// Verify cleanup on each blog.
		foreach ( $blogs as $blog_id ) {
			switch_to_blog( $blog_id );
			$this->assertFalse( get_option( 'ops_health_activated_at' ), "Blog $blog_id: activated_at should be deleted" );
			$this->assertFalse( get_option( 'ops_health_version' ), "Blog $blog_id: version should be deleted" );
			$this->assertFalse( get_option( 'ops_health_latest_results' ), "Blog $blog_id: latest_results should be deleted" );
			$this->assertFalse( get_option( 'ops_health_alert_settings' ), "Blog $blog_id: alert_settings should be deleted" );
			$this->assertFalse( get_option( 'ops_health_alert_log' ), "Blog $blog_id: alert_log should be deleted" );
			$this->assertFalse( get_transient( 'ops_health_cron_check' ), "Blog $blog_id: cron_check transient should be deleted" );
			$this->assertFalse( wp_next_scheduled( 'ops_health_run_checks' ), "Blog $blog_id: cron should be cleared" );
			restore_current_blog();
		}
	}

	/**
	 * Tests that on multisite cooldown transients are deleted on all blogs
	 */
	public function test_uninstall_on_multisite_cleans_cooldown_transients() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Requires multisite.' );
		}

		global $wpdb;

		$blog_id_2 = self::factory()->blog->create();

		$blogs = [ get_current_blog_id(), $blog_id_2 ];

		// Set cooldown transients on each blog.
		foreach ( $blogs as $blog_id ) {
			switch_to_blog( $blog_id );
			set_transient( 'ops_health_alert_cooldown_database', '1', 3600 );
			set_transient( 'ops_health_alert_cooldown_redis', '1', 3600 );
			restore_current_blog();
		}

		// Run the uninstallation.
		$uninstaller = new Uninstaller( $wpdb );
		$uninstaller->uninstall();

		// Verify cleanup on each blog.
		foreach ( $blogs as $blog_id ) {
			switch_to_blog( $blog_id );
			wp_cache_flush();
			$this->assertFalse( get_transient( 'ops_health_alert_cooldown_database' ), "Blog $blog_id: database cooldown should be deleted" );
			$this->assertFalse( get_transient( 'ops_health_alert_cooldown_redis' ), "Blog $blog_id: redis cooldown should be deleted" );
			restore_current_blog();
		}
	}

	/**
	 * Tests that on multisite other plugin options are preserved
	 */
	public function test_uninstall_on_multisite_preserves_non_plugin_data() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Requires multisite.' );
		}

		global $wpdb;

		$blog_id_2 = self::factory()->blog->create();

		// Set plugin data and another plugin's data on both blogs.
		$blogs = [ get_current_blog_id(), $blog_id_2 ];
		foreach ( $blogs as $blog_id ) {
			switch_to_blog( $blog_id );
			update_option( 'ops_health_version', '0.6.0' );
			update_option( 'other_plugin_option', 'keep_me' );
			restore_current_blog();
		}

		// Run the uninstallation.
		$uninstaller = new Uninstaller( $wpdb );
		$uninstaller->uninstall();

		// Verify that only plugin data is deleted.
		foreach ( $blogs as $blog_id ) {
			switch_to_blog( $blog_id );
			$this->assertFalse( get_option( 'ops_health_version' ), "Blog $blog_id: plugin option should be deleted" );
			$this->assertEquals( 'keep_me', get_option( 'other_plugin_option' ), "Blog $blog_id: other plugin option should be preserved" );
			delete_option( 'other_plugin_option' );
			restore_current_blog();
		}
	}

	/**
	 * Tests that transients with custom cooldown prefix are deleted
	 */
	public function test_uninstall_deletes_cooldown_with_custom_check_ids() {
		global $wpdb;

		// Simulate a custom check with ID different from the 5 standard ones.
		set_transient( 'ops_health_alert_cooldown_custom_check', '1', 3600 );

		$this->assertNotFalse( get_transient( 'ops_health_alert_cooldown_custom_check' ) );

		// Run the uninstallation.
		$uninstaller = new Uninstaller( $wpdb );
		$uninstaller->uninstall();

		// Flush object cache: $wpdb->query() bypasses the WP cache layer.
		wp_cache_flush();

		// Even the custom transient with the correct prefix is deleted.
		$this->assertFalse( get_transient( 'ops_health_alert_cooldown_custom_check' ), 'Custom cooldown transient should be deleted' );
	}
}
