<?php
/**
 * Plugin Uninstall Handler
 *
 * Complete cleanup of plugin data when deleted via the WordPress
 * admin plugins page.
 * Supports single-site and multisite network.
 *
 * @package OpsHealthDashboard
 */

// Verify that WordPress is performing the uninstall.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
global $wpdb;

if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	// Autoloader available: use the Uninstaller class (handles multisite).
	require_once __DIR__ . '/vendor/autoload.php';
	( new \OpsHealthDashboard\Core\Uninstaller( $wpdb ) )->uninstall();
} elseif ( is_multisite() ) {
	// Inline multisite fallback: iterate all network blogs.
	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
	$ops_health_blog_ids = get_sites( [ 'fields' => 'ids' ] );
	foreach ( $ops_health_blog_ids as $ops_health_blog_id ) {
		switch_to_blog( $ops_health_blog_id );
		ops_health_uninstall_single_site( $wpdb );
		restore_current_blog();
	}
} else {
	// Inline single-site fallback.
	ops_health_uninstall_single_site( $wpdb );
}

/**
 * Plugin data cleanup for a single site (inline fallback)
 *
 * @param object $wpdb Global $wpdb instance.
 * @return void
 */
function ops_health_uninstall_single_site( $wpdb ) {
	// Options.
	delete_option( 'ops_health_activated_at' );
	delete_option( 'ops_health_version' );
	delete_option( 'ops_health_latest_results' );
	delete_option( 'ops_health_alert_settings' );
	delete_option( 'ops_health_alert_log' );

	// Cron.
	wp_clear_scheduled_hook( 'ops_health_run_checks' );

	// Fixed transients.
	delete_transient( 'ops_health_cron_check' );
	delete_transient( 'ops_health_admin_notice' );
	delete_transient( 'ops_health_alert_notice' );

	// Dynamic cooldown transients.
	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
	// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
			'_transient_ops_health_alert_cooldown_%',
			'_transient_timeout_ops_health_alert_cooldown_%'
		)
	);
	// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared
	// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching
	// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
}
