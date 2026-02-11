<?php
/**
 * Gestore Disinstallazione Plugin
 *
 * Pulizia completa di tutte le opzioni, transient e cron hook
 * creati dal plugin quando viene cancellato da WordPress.
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
 * Rimuove tutti i dati del plugin dal database quando viene disinstallato.
 */
class Uninstaller {

	/**
	 * Istanza di $wpdb per query dirette
	 *
	 * @var object
	 */
	private $wpdb;

	/**
	 * Costruttore
	 *
	 * @param object $wpdb Istanza globale $wpdb di WordPress.
	 */
	public function __construct( $wpdb ) {
		$this->wpdb = $wpdb;
	}

	/**
	 * Esegue la pulizia completa del plugin
	 *
	 * Rimuove opzioni, cron hook e transient dal database.
	 *
	 * @return void
	 */
	public function uninstall(): void {
		$this->delete_options();
		$this->clear_cron();
		$this->delete_transients();
	}

	/**
	 * Cancella tutte le opzioni del plugin
	 *
	 * @return void
	 */
	private function delete_options(): void {
		$options = [
			'ops_health_activated_at',
			'ops_health_version',
			'ops_health_latest_results',
			'ops_health_alert_settings',
			'ops_health_alert_log',
		];

		foreach ( $options as $option ) {
			delete_option( $option );
		}
	}

	/**
	 * Cancella il cron hook del plugin
	 *
	 * @return void
	 */
	private function clear_cron(): void {
		wp_clear_scheduled_hook( 'ops_health_run_checks' );
	}

	/**
	 * Cancella tutti i transient del plugin
	 *
	 * Cancella i transient a nome fisso con delete_transient()
	 * e quelli dinamici (cooldown per-check) via query $wpdb con LIKE.
	 *
	 * @return void
	 */
	private function delete_transients(): void {
		// Transient a nome fisso.
		delete_transient( 'ops_health_cron_check' );
		delete_transient( 'ops_health_admin_notice' );
		delete_transient( 'ops_health_alert_notice' );

		// Transient di cooldown dinamici (uno per check ID).
		// Usa $wpdb->options per il nome tabella corretto (rispetta table prefix).
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
