<?php
/**
 * Plugin Uninstall Handler
 *
 * Complete cleanup of all options, transients, and cron hooks
 * created by the plugin when deleted from WordPress.
 * Supports both single-site and multisite network.
 *
 * @package OpsHealthDashboard\Core
 */

namespace OpsHealthDashboard\Core;

if ( ! defined( 'ABSPATH' ) ) {
	// phpcs:ignore Squiz.Commenting.InlineComment.InvalidEndChar
	// @codeCoverageIgnoreStart
	exit;
	// phpcs:ignore Squiz.Commenting.InlineComment.InvalidEndChar
	// @codeCoverageIgnoreEnd
}

/**
 * Class Uninstaller
 *
 * Removes all plugin data from the database when uninstalled.
 * On multisite, iterates all network blogs.
 */
class Uninstaller {

	/**
	 * $wpdb instance for direct queries
	 *
	 * @var object
	 */
	private $wpdb;

	/**
	 * Constructor
	 *
	 * @param object $wpdb WordPress global $wpdb instance.
	 */
	public function __construct( $wpdb ) {
		$this->wpdb = $wpdb;
	}

	/**
	 * Performs complete plugin cleanup
	 *
	 * On multisite, iterates all network blogs.
	 * On single-site, performs cleanup directly.
	 *
	 * @return void
	 */
	public function uninstall(): void {
		if ( is_multisite() ) {
			$this->uninstall_network();
		} else {
			$this->uninstall_single();
		}
	}

	/**
	 * Multisite network cleanup
	 *
	 * Iterates all network blogs and performs cleanup for each.
	 *
	 * @return void
	 */
	private function uninstall_network(): void {
		$blog_ids = get_sites( [ 'fields' => 'ids' ] );

		foreach ( $blog_ids as $blog_id ) {
			switch_to_blog( $blog_id );
			$this->uninstall_single();
			restore_current_blog();
		}
	}

	/**
	 * Single site cleanup
	 *
	 * Removes options, cron hooks, and transients from the current site database.
	 *
	 * @return void
	 */
	private function uninstall_single(): void {
		$this->delete_options();
		$this->clear_cron();
		$this->delete_transients();
	}

	/**
	 * Deletes all plugin options
	 *
	 * @return void
	 */
	private function delete_options(): void {
		$options = [
			'ops_health_activated_at',
			'ops_health_version',
			'ops_health_latest_results',
			'ops_health_last_run_at',
			'ops_health_alert_settings',
			'ops_health_alert_log',
		];

		foreach ( $options as $option ) {
			delete_option( $option );
		}
	}

	/**
	 * Clears the plugin cron hook
	 *
	 * @return void
	 */
	private function clear_cron(): void {
		wp_clear_scheduled_hook( \OpsHealthDashboard\Services\Scheduler::HOOK_NAME );
	}

	/**
	 * Deletes all plugin transients
	 *
	 * Deletes fixed-name transients with delete_transient()
	 * and dynamic ones (per-check cooldown) via $wpdb query with LIKE.
	 *
	 * @return void
	 */
	private function delete_transients(): void {
		// Fixed-name transients.
		delete_transient( 'ops_health_cron_check' );
		delete_transient( 'ops_health_admin_notice' );
		delete_transient( 'ops_health_alert_notice' );

		// Dynamic cooldown transients (one per check ID).
		// Uses $wpdb->options for the correct table name (respects table prefix).
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
		$this->wpdb->query(
			$this->wpdb->prepare(
				"DELETE FROM {$this->wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				'_transient_ops_health_alert_cooldown_%',
				'_transient_timeout_ops_health_alert_cooldown_%'
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
	}
}
