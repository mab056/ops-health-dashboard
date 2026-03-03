<?php
/**
 * Integration Test for CheckRunner
 *
 * Integration test with real checks and storage.
 *
 * @package OpsHealthDashboard\Tests\Integration\Services
 */

namespace OpsHealthDashboard\Tests\Integration\Services;

use OpsHealthDashboard\Checks\DatabaseCheck;
use OpsHealthDashboard\Interfaces\CheckInterface;
use OpsHealthDashboard\Services\CheckRunner;
use OpsHealthDashboard\Services\Redaction;
use OpsHealthDashboard\Services\Storage;
use WP_UnitTestCase;

/**
 * Class CheckRunnerTest
 *
 * Integration test for CheckRunner with real WordPress.
 */
class CheckRunnerTest extends WP_UnitTestCase {

	/**
	 * Storage for tests
	 *
	 * @var Storage
	 */
	private $storage;

	/**
	 * CheckRunner with DatabaseCheck already added
	 *
	 * @var CheckRunner
	 */
	private $runner;

	/**
	 * Setup for each test
	 */
	public function setUp(): void {
		parent::setUp();
		$this->storage = new Storage();
		$redaction     = new Redaction();
		$this->runner  = new CheckRunner( $this->storage, $redaction );

		global $wpdb;
		$this->runner->add_check( new DatabaseCheck( $wpdb, $redaction ) );

		// Clean up previous results.
		$this->storage->delete( 'latest_results' );
	}

	/**
	 * Cleanup after each test
	 */
	public function tearDown(): void {
		$this->storage->delete( 'latest_results' );
		parent::tearDown();
	}

	/**
	 * Tests that CheckRunner executes real checks
	 */
	public function test_check_runner_executes_real_checks() {
		$results = $this->runner->run_all();

		$this->assertIsArray( $results );
		$this->assertArrayHasKey( 'database', $results );
		$this->assertArrayHasKey( 'status', $results['database'] );
		$this->assertEquals( 'ok', $results['database']['status'] );
	}

	/**
	 * Tests that CheckRunner saves results to storage
	 */
	public function test_check_runner_saves_results_to_storage() {
		$this->runner->run_all();

		// Retrieve results from storage.
		$stored_results = $this->storage->get( 'latest_results' );

		$this->assertIsArray( $stored_results );
		$this->assertArrayHasKey( 'database', $stored_results );
	}

	/**
	 * Tests that get_latest_results() retrieves saved results
	 */
	public function test_get_latest_results_retrieves_saved_results() {
		$this->runner->run_all();

		// Create a new runner and retrieve the results.
		$redaction  = new Redaction();
		$new_runner = new CheckRunner( $this->storage, $redaction );
		$results    = $new_runner->get_latest_results();

		$this->assertIsArray( $results );
		$this->assertArrayHasKey( 'database', $results );
	}

	/**
	 * Tests that CheckRunner handles multiple executions
	 */
	public function test_check_runner_handles_multiple_executions() {
		// First execution.
		$results1 = $this->runner->run_all();
		$this->assertArrayHasKey( 'database', $results1 );

		// Second execution (should overwrite).
		$results2 = $this->runner->run_all();
		$this->assertArrayHasKey( 'database', $results2 );

		// The saved results should be from the second execution.
		$stored = $this->runner->get_latest_results();
		$this->assertEquals( $results2, $stored );
	}

	/**
	 * Tests that run_all() skips disabled checks
	 */
	public function test_run_all_skips_disabled_check() {
		$this->runner->add_check( new DisabledCheck() );

		$results = $this->runner->run_all();

		$this->assertArrayHasKey( 'database', $results );
		$this->assertArrayNotHasKey( 'disabled', $results );
	}

	/**
	 * Tests that run_all() catches exceptions from checks
	 */
	public function test_run_all_catches_check_exception() {
		$storage   = new Storage();
		$redaction = new Redaction();
		$runner    = new CheckRunner( $storage, $redaction );
		$runner->add_check( new FailingCheck() );

		$results = $runner->run_all();

		$this->assertArrayHasKey( 'failing', $results );
		$this->assertEquals( 'critical', $results['failing']['status'] );
		$this->assertStringContainsString( 'Check exception', $results['failing']['message'] );
	}

	/**
	 * Tests that run_all() redacts exception messages
	 */
	public function test_run_all_redacts_exception_message() {
		$storage   = new Storage();
		$redaction = new Redaction( ABSPATH, WP_CONTENT_DIR );
		$runner    = new CheckRunner( $storage, $redaction );
		$runner->add_check( new FailingCheck() );

		$results = $runner->run_all();

		$this->assertArrayHasKey( 'failing', $results );
		$this->assertStringNotContainsString( ABSPATH, $results['failing']['message'] );
	}

	/**
	 * Tests that clear_results() removes data from storage
	 */
	public function test_clear_results_removes_stored_data() {
		$this->runner->run_all();

		// Verify that results exist.
		$this->assertNotEmpty( $this->runner->get_latest_results() );

		$this->runner->clear_results();

		$this->assertEmpty( $this->runner->get_latest_results() );
	}
}

/**
 * Disabled check for testing
 *
 * Implements CheckInterface with is_enabled() = false.
 */
class DisabledCheck implements CheckInterface {

	/**
	 * Runs the check
	 *
	 * @return array Results.
	 */
	public function run(): array {
		return [
			'status'   => 'ok',
			'message'  => 'Should not run',
			'details'  => [],
			'duration' => 0,
		];
	}

	/**
	 * Check ID
	 *
	 * @return string ID.
	 */
	public function get_id(): string {
		return 'disabled';
	}

	/**
	 * Check name
	 *
	 * @return string Name.
	 */
	public function get_name(): string {
		return 'Disabled Check';
	}

	/**
	 * Disabled check
	 *
	 * @return bool False.
	 */
	public function is_enabled(): bool {
		return false;
	}
}

/**
 * Check that throws exception for testing
 *
 * Implements CheckInterface with run() that throws RuntimeException.
 */
class FailingCheck implements CheckInterface {

	/**
	 * Runs the check (throws exception)
	 *
	 * @return array Never reached.
	 * @throws \RuntimeException Always.
	 */
	public function run(): array {
		throw new \RuntimeException( 'Sensitive error at ' . ABSPATH . 'wp-config.php' );
	}

	/**
	 * Check ID
	 *
	 * @return string ID.
	 */
	public function get_id(): string {
		return 'failing';
	}

	/**
	 * Check name
	 *
	 * @return string Name.
	 */
	public function get_name(): string {
		return 'Failing Check';
	}

	/**
	 * Enabled check
	 *
	 * @return bool True.
	 */
	public function is_enabled(): bool {
		return true;
	}
}
