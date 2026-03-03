<?php
/**
 * Database Check
 *
 * Verifies the WordPress database connection and performance.
 * Executes a simple query (SELECT 1) and measures response time.
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
 * Class DatabaseCheck
 *
 * WordPress database health check.
 */
class DatabaseCheck implements CheckInterface {

	/**
	 * Slow query threshold in seconds
	 *
	 * @var float
	 */
	const SLOW_QUERY_THRESHOLD = 0.5;

	/**
	 * Injected wpdb instance
	 *
	 * @var \wpdb
	 */
	private $wpdb;

	/**
	 * Sensitive data redaction service
	 *
	 * @var RedactionInterface
	 */
	private $redaction;

	/**
	 * Constructor
	 *
	 * @param \wpdb              $wpdb      wpdb instance for queries.
	 * @param RedactionInterface $redaction Sensitive data redaction service.
	 */
	public function __construct( \wpdb $wpdb, RedactionInterface $redaction ) {
		$this->wpdb      = $wpdb;
		$this->redaction = $redaction;
	}

	/**
	 * Runs the database check
	 *
	 * @return array Check results.
	 */
	public function run(): array {
		$start = microtime( true );

		// Executes a simple query to test the connection.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $this->wpdb->query( 'SELECT 1' );

		$duration = microtime( true ) - $start;

		// Verify the result.
		if ( false === $result || ! empty( $this->wpdb->last_error ) ) {
			$error_msg = ! empty( $this->wpdb->last_error )
				? $this->redaction->redact( $this->wpdb->last_error )
				: __( 'Unknown error', 'ops-health-dashboard' );
			return [
				'status'   => 'critical',
				'message'  => __( 'Database connection failed', 'ops-health-dashboard' ),
				'details'  => [
					'error' => $error_msg,
				],
				'duration' => $duration,
			];
		}

		// Query performance check.
		$status = 'ok';
		if ( $duration > self::SLOW_QUERY_THRESHOLD ) {
			$status = 'warning';
		}

		return [
			'status'   => $status,
			'message'  => __( 'Database connection healthy', 'ops-health-dashboard' ),
			'details'  => [
				'query_time' => round( $duration * 1000, 2 ) . 'ms',
			],
			'duration' => $duration,
		];
	}

	/**
	 * Gets the check ID
	 *
	 * @return string Check ID.
	 */
	public function get_id(): string {
		return 'database';
	}

	/**
	 * Gets the check name
	 *
	 * @return string Check name.
	 */
	public function get_name(): string {
		return __( 'Database Connection', 'ops-health-dashboard' );
	}

	/**
	 * Checks if the check is enabled
	 *
	 * @return bool True if enabled.
	 */
	public function is_enabled(): bool {
		return true;
	}
}
