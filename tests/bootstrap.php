<?php
/**
 * PHPUnit bootstrap file for Ops Health Dashboard tests.
 *
 * Smart bootstrap that loads the appropriate environment:
 * - Brain\Monkey for unit tests (fast, isolated)
 * - WordPress Test Suite for integration tests (real, complete)
 *
 * @package OpsHealthDashboard
 */

// Defines ABSPATH for direct access guards in source files.
// Unit tests do not load WordPress, this is needed to avoid exit() in src/ files.
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/wordpress/' );
}

// Load Composer dependencies.
require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// Determine if we are running integration tests by looking at the PHPUnit suite.
$is_integration_test = false;

// Check command line arguments.
if ( isset( $_SERVER['argv'] ) ) {
	$argv = $_SERVER['argv'];
	$argc = count( $argv );
	for ( $i = 0; $i < $argc; $i++ ) {
		if ( strpos( $argv[ $i ], '--testsuite=integration' ) !== false
			|| strpos( $argv[ $i ], 'tests/Integration' ) !== false
			|| ( $argv[ $i ] === '--testsuite' && isset( $argv[ $i + 1 ] ) && $argv[ $i + 1 ] === 'integration' )
		) {
			$is_integration_test = true;
			break;
		}
	}
}

// If integration tests, load WordPress Test Suite.
if ( $is_integration_test ) {
	// WordPress test suite location.
	$_tests_dir = getenv( 'WP_TESTS_DIR' );
	if ( ! $_tests_dir ) {
		$_tests_dir = '/tmp/wordpress-tests-lib';
	}

	// Verify that the test suite exists.
	if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
		echo "\n";
		echo "ERROR: WordPress Test Suite not found at: $_tests_dir\n";
		echo "Please run: bash bin/install-wp-tests.sh wordpress_test root '' localhost latest\n";
		echo "\n";
		exit( 1 );
	}

	// Provides access to the tests_add_filter() function.
	require_once $_tests_dir . '/includes/functions.php';

	/**
	 * Manually loads the plugin being tested.
	 */
	function _manually_load_plugin() {
		require dirname( __DIR__ ) . '/ops-health-dashboard.php';
	}

	tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

	// Multisite support via environment variable.
	if ( getenv( 'WP_TESTS_MULTISITE' ) ) {
		define( 'WP_TESTS_MULTISITE', true );
	}

	// Start the WP testing environment.
	require $_tests_dir . '/includes/bootstrap.php';
}
// Otherwise, unit tests use only Brain\Monkey (loaded via Composer autoload).
