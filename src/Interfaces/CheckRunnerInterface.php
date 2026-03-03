<?php
/**
 * CheckRunner Interface
 *
 * Contract for the health check execution service.
 * Enables decoupling between CheckRunner and its consumers.
 *
 * @package OpsHealthDashboard\Interfaces
 */

namespace OpsHealthDashboard\Interfaces;

if ( ! defined( 'ABSPATH' ) ) {
	// phpcs:ignore Squiz.Commenting.InlineComment.InvalidEndChar
	// @codeCoverageIgnoreStart
	exit;
	// phpcs:ignore Squiz.Commenting.InlineComment.InvalidEndChar
	// @codeCoverageIgnoreEnd
}

/**
 * Interface CheckRunnerInterface
 *
 * Defines the methods for running and managing health checks.
 */
interface CheckRunnerInterface {

	/**
	 * Adds a check to the runner
	 *
	 * @param CheckInterface $check Check to add.
	 * @return void
	 */
	public function add_check( CheckInterface $check ): void;

	/**
	 * Runs all enabled checks
	 *
	 * @return array Associative array with results for each check (key = check ID).
	 */
	public function run_all(): array;

	/**
	 * Gets the latest results from storage
	 *
	 * @return array Latest check results.
	 */
	public function get_latest_results(): array;

	/**
	 * Clears results from storage
	 *
	 * @return void
	 */
	public function clear_results(): void;
}
