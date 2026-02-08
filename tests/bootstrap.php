<?php
/**
 * File di bootstrap PHPUnit per i test di Ops Health Dashboard.
 *
 * Bootstrap "smart" che carica l'ambiente appropriato:
 * - Brain\Monkey per unit tests (veloce, isolato)
 * - WordPress Test Suite per integration tests (reale, completo)
 *
 * @package OpsHealthDashboard
 */

// Carica le dipendenze di Composer.
require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// Determina se stiamo eseguendo integration tests guardando la suite PHPUnit.
$is_integration_test = false;

// Controlla gli argomenti della riga di comando.
if ( isset( $_SERVER['argv'] ) ) {
	foreach ( $_SERVER['argv'] as $arg ) {
		if ( strpos( $arg, '--testsuite=integration' ) !== false || strpos( $arg, 'tests/Integration' ) !== false ) {
			$is_integration_test = true;
			break;
		}
	}
}

// Se sono integration tests, carica WordPress Test Suite.
if ( $is_integration_test ) {
	// Posizione della test suite di WordPress.
	$_tests_dir = getenv( 'WP_TESTS_DIR' );
	if ( ! $_tests_dir ) {
		$_tests_dir = '/tmp/wordpress-tests-lib';
	}

	// Verifica che la test suite esista.
	if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
		echo "\n";
		echo "ERROR: WordPress Test Suite not found at: $_tests_dir\n";
		echo "Please run: bash bin/install-wp-tests.sh wordpress_test root '' localhost latest\n";
		echo "\n";
		exit( 1 );
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
}
// Altrimenti, unit tests usano solo Brain\Monkey (caricato da Composer autoload).
