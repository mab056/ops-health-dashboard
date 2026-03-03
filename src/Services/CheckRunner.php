<?php
/**
 * Check Runner Service
 *
 * Orchestrator that coordinates the execution of all health checks.
 * Manages execution, aggregation, and storage of results.
 *
 * @package OpsHealthDashboard\Services
 */

namespace OpsHealthDashboard\Services;

if ( ! defined( 'ABSPATH' ) ) {
	// phpcs:ignore Squiz.Commenting.InlineComment.InvalidEndChar
	// @codeCoverageIgnoreStart
	exit;
	// phpcs:ignore Squiz.Commenting.InlineComment.InvalidEndChar
	// @codeCoverageIgnoreEnd
}

use OpsHealthDashboard\Interfaces\CheckInterface;
use OpsHealthDashboard\Interfaces\CheckRunnerInterface;
use OpsHealthDashboard\Interfaces\RedactionInterface;
use OpsHealthDashboard\Interfaces\StorageInterface;

/**
 * Class CheckRunner
 *
 * Service for running and managing health checks.
 */
class CheckRunner implements CheckRunnerInterface {

	/**
	 * Storage for saving results
	 *
	 * @var StorageInterface
	 */
	private $storage;

	/**
	 * Redaction service for sanitizing exception messages
	 *
	 * @var RedactionInterface
	 */
	private $redaction;

	/**
	 * Array of registered checks
	 *
	 * @var CheckInterface[]
	 */
	private $checks = [];

	/**
	 * Constructor
	 *
	 * @param StorageInterface   $storage   Storage for results.
	 * @param RedactionInterface $redaction Sensitive data redaction service.
	 */
	public function __construct( StorageInterface $storage, RedactionInterface $redaction ) {
		$this->storage   = $storage;
		$this->redaction = $redaction;
	}

	/**
	 * Adds a check to the runner
	 *
	 * @param CheckInterface $check Check to add.
	 * @return void
	 */
	public function add_check( CheckInterface $check ): void {
		$this->checks[] = $check;
	}

	/**
	 * Runs all enabled checks
	 *
	 * @return array Associative array with results for each check (key = check ID).
	 */
	public function run_all(): array {
		$results = [];

		foreach ( $this->checks as $check ) {
			// Skip disabled checks.
			if ( ! $check->is_enabled() ) {
				continue;
			}

			$check_id = $check->get_id();

			try {
				$result               = $check->run();
				$result['name']       = $check->get_name();
				$results[ $check_id ] = $result;
			} catch ( \Throwable $e ) {
				$results[ $check_id ] = [
					'status'   => 'critical',
					'message'  => sprintf(
						/* translators: %s: redacted exception message */
						__( 'Check exception: %s', 'ops-health-dashboard' ),
						$this->redaction->redact( $e->getMessage() )
					),
					'name'     => $check->get_name(),
					'details'  => [],
					'duration' => 0,
				];
			}
		}

		// Save results and timestamp to storage.
		$this->storage->set( 'latest_results', $results );
		$this->storage->set( 'last_run_at', time() );

		return $results;
	}

	/**
	 * Clears results from storage
	 *
	 * @return void
	 */
	public function clear_results(): void {
		$this->storage->delete( 'latest_results' );
		$this->storage->delete( 'last_run_at' );
	}

	/**
	 * Gets the latest results from storage
	 *
	 * @return array Latest check results.
	 */
	public function get_latest_results(): array {
		$results = $this->storage->get( 'latest_results', [] );
		return is_array( $results ) ? $results : [];
	}
}
