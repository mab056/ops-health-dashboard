<?php
/**
 * Gestore Disinstallazione Plugin
 *
 * Pulizia completa dei dati del plugin quando viene cancellato
 * tramite la pagina plugin di WordPress admin.
 * Supporta single-site e multisite network.
 *
 * @package OpsHealthDashboard
 */

// Verifica che WordPress stia eseguendo la disinstallazione.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
global $wpdb;

if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	// Autoloader disponibile: usa la classe Uninstaller (gestisce multisite).
	require_once __DIR__ . '/vendor/autoload.php';
	( new \OpsHealthDashboard\Core\Uninstaller( $wpdb ) )->uninstall();
} elseif ( is_multisite() ) {
	// Fallback inline multisite: itera su tutti i blog della rete.
	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
	$ops_health_blog_ids = get_sites( [ 'fields' => 'ids' ] );
	foreach ( $ops_health_blog_ids as $ops_health_blog_id ) {
		switch_to_blog( $ops_health_blog_id );
		ops_health_uninstall_single_site( $wpdb );
		restore_current_blog();
	}
} else {
	// Fallback inline single-site.
	ops_health_uninstall_single_site( $wpdb );
}

/**
 * Pulizia dati plugin per un singolo sito (fallback inline)
 *
 * @param object $wpdb Istanza globale $wpdb.
 * @return void
 */
function ops_health_uninstall_single_site( $wpdb ) {
	// Opzioni.
	delete_option( 'ops_health_activated_at' );
	delete_option( 'ops_health_version' );
	delete_option( 'ops_health_latest_results' );
	delete_option( 'ops_health_alert_settings' );
	delete_option( 'ops_health_alert_log' );

	// Cron.
	wp_clear_scheduled_hook( 'ops_health_run_checks' );

	// Transient fissi.
	delete_transient( 'ops_health_cron_check' );
	delete_transient( 'ops_health_admin_notice' );
	delete_transient( 'ops_health_alert_notice' );

	// Transient di cooldown dinamici.
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
