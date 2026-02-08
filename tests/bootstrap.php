<?php
/**
 * File di bootstrap PHPUnit per i test di Ops Health Dashboard.
 *
 * @package OpsHealthDashboard
 */

// Carica le dipendenze di Composer.
require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// Posizione della test suite di WordPress.
$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
	$_tests_dir = '/tmp/wordpress-tests-lib';
}

// Fornisce accesso alla funzione tests_add_filter().
require_once $_tests_dir . '/includes/functions.php';

/**
 * Carica manualmente il plugin da testare.
 */
function _manually_load_plugin() {
	require dirname( __DIR__ ) . '/ops-health-dashboard.php';
}

tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

// Avvia l'ambiente di testing di WP.
require $_tests_dir . '/includes/bootstrap.php';
