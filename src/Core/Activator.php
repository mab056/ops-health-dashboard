<?php
/**
 * Plugin Activation/Deactivation Handler
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

use OpsHealthDashboard\Services\Scheduler;

/**
 * Class Activator
 *
 * Manages plugin activation and deactivation.
 */
class Activator {

	/**
	 * Plugin activation handler
	 *
	 * Configures the plugin on first activation.
	 *
	 * @return void
	 */
	public function activate(): void {
		if ( ! get_option( 'ops_health_activated_at' ) ) {
			update_option( 'ops_health_activated_at', time() );
		}

		if ( defined( 'OPS_HEALTH_DASHBOARD_VERSION' ) ) {
			update_option( 'ops_health_version', OPS_HEALTH_DASHBOARD_VERSION );
		}

		// Registers the custom interval and schedules the cron.
		// Note: filter duplicated with Scheduler::register_hooks().
		// Required here because Scheduler is not yet bootstrapped during activation.
		add_filter(
			'cron_schedules',
			function ( $schedules ) {
				if ( ! isset( $schedules[ Scheduler::INTERVAL ] ) ) {
					$schedules[ Scheduler::INTERVAL ] = [
						'interval' => 15 * MINUTE_IN_SECONDS,
						'display'  => __( 'Every 15 minutes', 'ops-health-dashboard' ),
					];
				}
				return $schedules;
			}
		);

		if ( ! wp_next_scheduled( Scheduler::HOOK_NAME ) ) {
			wp_schedule_event( time(), Scheduler::INTERVAL, Scheduler::HOOK_NAME );
		}
	}

	/**
	 * Plugin deactivation handler
	 *
	 * Cleanup on deactivation (but preserves data).
	 *
	 * @return void
	 */
	public function deactivate(): void {
		wp_clear_scheduled_hook( Scheduler::HOOK_NAME );
	}
}
