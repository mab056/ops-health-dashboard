<?php
/**
 * Disk Space Check
 *
 * Verifies available disk space with configurable thresholds.
 * Graceful degradation: if disk_free_space/disk_total_space are disabled.
 *
 * @package OpsHealthDashboard\Checks
 */

namespace OpsHealthDashboard\Checks;

if ( ! defined( 'ABSPATH' ) ) {
	// phpcs:ignore Squiz.Commenting.InlineComment.InvalidEndChar
	// @codeCoverageIgnoreStart
	exit;
	// phpcs:ignore Squiz.Commenting.InlineComment.InvalidEndChar
	// @codeCoverageIgnoreEnd
}

use OpsHealthDashboard\Interfaces\CheckInterface;
use OpsHealthDashboard\Interfaces\RedactionInterface;

/**
 * Class DiskCheck
 *
 * Disk space check with percentage thresholds.
 */
class DiskCheck implements CheckInterface {

	/**
	 * Percentage threshold for warning (below this value → warning)
	 *
	 * @var int
	 */
	const WARNING_THRESHOLD = 20;

	/**
	 * Percentage threshold for critical (below this value → critical)
	 *
	 * @var int
	 */
	const CRITICAL_THRESHOLD = 10;

	/**
	 * Sensitive data redaction service
	 *
	 * @var RedactionInterface
	 */
	private $redaction;

	/**
	 * Constructor
	 *
	 * @param RedactionInterface $redaction Sensitive data redaction service.
	 */
	public function __construct( RedactionInterface $redaction ) {
		$this->redaction = $redaction;
	}

	/**
	 * Runs the disk space check
	 *
	 * @return array Check results.
	 */
	public function run(): array {
		$start = microtime( true );

		$path  = $this->get_disk_path();
		$free  = $this->get_free_space( $path );
		$total = $this->get_total_space( $path );

		if ( false === $free || false === $total || $total <= 0 ) {
			return $this->build_result(
				'warning',
				__( 'Unable to determine disk space', 'ops-health-dashboard' ),
				[ 'path' => $this->redaction->redact( $path ) ],
				microtime( true ) - $start
			);
		}

		$free_percent = ( $free / $total ) * 100;

		if ( $free_percent < self::CRITICAL_THRESHOLD ) {
			$status = 'critical';
		} elseif ( $free_percent < self::WARNING_THRESHOLD ) {
			$status = 'warning';
		} else {
			$status = 'ok';
		}

		$message = sprintf(
			/* translators: 1: free space, 2: total space, 3: free percent */
			__( '%1$s free of %2$s (%3$.1f%%)', 'ops-health-dashboard' ),
			size_format( (int) $free ),
			size_format( (int) $total ),
			$free_percent
		);

		return $this->build_result(
			$status,
			$message,
			[
				'free_bytes'   => $free,
				'total_bytes'  => $total,
				'free_percent' => round( $free_percent, 1 ),
				'path'         => $this->redaction->redact( $path ),
			],
			microtime( true ) - $start
		);
	}

	/**
	 * Gets the check ID
	 *
	 * @return string Check ID.
	 */
	public function get_id(): string {
		return 'disk';
	}

	/**
	 * Gets the check name
	 *
	 * @return string Check name.
	 */
	public function get_name(): string {
		return __( 'Disk Space', 'ops-health-dashboard' );
	}

	/**
	 * Checks if the check is enabled
	 *
	 * Returns false if disk_free_space or disk_total_space are
	 * disabled via php.ini disable_functions.
	 *
	 * @return bool True if enabled.
	 */
	public function is_enabled(): bool {
		return function_exists( 'disk_free_space' ) && function_exists( 'disk_total_space' );
	}

	/**
	 * Gets the disk path to check
	 *
	 * @return string WordPress root path.
	 */
	protected function get_disk_path(): string {
		return defined( 'ABSPATH' ) ? ABSPATH : '/';
	}

	/**
	 * Gets free disk space
	 *
	 * @param string $path Path to check.
	 * @return float|false Free space in bytes or false on error.
	 */
	protected function get_free_space( string $path ) {
		return disk_free_space( $path );
	}

	/**
	 * Gets total disk space
	 *
	 * @param string $path Path to check.
	 * @return float|false Total space in bytes or false on error.
	 */
	protected function get_total_space( string $path ) {
		return disk_total_space( $path );
	}

	/**
	 * Builds the standard result array
	 *
	 * @param string $status   Check status.
	 * @param string $message  Descriptive message.
	 * @param array  $details  Additional details.
	 * @param float  $duration Execution duration.
	 * @return array Formatted result.
	 */
	private function build_result( string $status, string $message, array $details, float $duration ): array {
		return [
			'status'   => $status,
			'message'  => $message,
			'details'  => $details,
			'duration' => $duration,
		];
	}
}
