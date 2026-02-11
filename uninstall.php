<?php
/**
 * Gestore Disinstallazione Plugin
 *
 * Pulizia completa dei dati del plugin quando viene cancellato
 * tramite la pagina plugin di WordPress admin.
 *
 * @package OpsHealthDashboard
 */

// Verifica che WordPress stia eseguendo la disinstallazione.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Carica l'autoloader.
require_once __DIR__ . '/vendor/autoload.php';

// Esegui la pulizia.
// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
global $wpdb;
( new \OpsHealthDashboard\Core\Uninstaller( $wpdb ) )->uninstall();
