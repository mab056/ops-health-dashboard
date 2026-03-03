<?php
/**
 * Check Interface
 *
 * Contract for all system health checks.
 * Every check implements this interface to provide consistent information.
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
 * Interface CheckInterface
 *
 * Defines the methods for health checks.
 */
interface CheckInterface {

	/**
	 * Runs the check and returns the results
	 *
	 * @return array {
	 *     Check results.
	 *
	 *     @type string $status   Check status: 'ok', 'warning', 'critical'.
	 *     @type string $message  Descriptive result message.
	 *     @type array  $details  Check-specific additional details.
	 *     @type float  $duration Execution duration in seconds.
	 * }
	 */
	public function run(): array;

	/**
	 * Gets the unique check ID
	 *
	 * @return string ID del check (es: 'database', 'disk', 'redis').
	 */
	public function get_id(): string;

	/**
	 * Gets the human-readable check name
	 *
	 * @return string Nome del check (es: 'Database Connection', 'Disk Space').
	 */
	public function get_name(): string;

	/**
	 * Checks if the check is enabled
	 *
	 * @return bool True if the check is enabled, false otherwise.
	 */
	public function is_enabled(): bool;
}
