<?php
/**
 * Plugin Activation/Deactivation Handler
 *
 * @package OpsHealthDashboard\Core
 */

namespace OpsHealthDashboard\Core;

/**
 * Class Activator
 *
 * Handles plugin activation and deactivation.
 * NO singleton, NO static methods, NO final modifier.
 */
class Activator {

	/**
	 * Plugin activation handler
	 *
	 * Sets up plugin on first activation.
	 *
	 * @return void
	 */
	public function activate(): void {
		// Store activation timestamp.
		if ( ! get_option( 'ops_health_activated_at' ) ) {
			update_option( 'ops_health_activated_at', time() );
		}

		// Store plugin version.
		if ( defined( 'OPS_HEALTH_DASHBOARD_VERSION' ) ) {
			update_option( 'ops_health_version', OPS_HEALTH_DASHBOARD_VERSION );
		}

		// Flush rewrite rules if needed.
		flush_rewrite_rules();
	}

	/**
	 * Plugin deactivation handler
	 *
	 * Cleanup on deactivation (but preserve data).
	 *
	 * @return void
	 */
	public function deactivate(): void {
		// Clear scheduled hooks.
		wp_clear_scheduled_hook( 'ops_health_scheduled_check' );

		// Flush rewrite rules.
		flush_rewrite_rules();
	}
}
