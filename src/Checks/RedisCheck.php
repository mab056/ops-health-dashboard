<?php
/**
 * Redis Check
 *
 * Verifies Redis availability and performance.
 * Graceful degradation: Redis is optional, all failures are 'warning'.
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
 * Class RedisCheck
 *
 * Redis health check with extension detection,
 * connection test, and SET/GET/DEL smoke test.
 */
class RedisCheck implements CheckInterface {

	/**
	 * Key used for the smoke test
	 *
	 * @var string
	 */
	const SMOKE_TEST_KEY = 'ops_health_smoke_test';

	/**
	 * Value used for the smoke test
	 *
	 * @var string
	 */
	const SMOKE_TEST_VALUE = 'ops_health_test_value';

	/**
	 * Response time threshold in seconds (100ms)
	 *
	 * @var float
	 */
	const SLOW_THRESHOLD = 0.1;

	/**
	 * Connection timeout in seconds
	 *
	 * @var float
	 */
	const CONNECT_TIMEOUT = 2.0;

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
	 * Runs the Redis check
	 *
	 * @return array Check results.
	 */
	public function run(): array {
		$start = microtime( true );

		// 1. Check Redis extension.
		if ( ! $this->is_extension_loaded() ) {
			return $this->build_result(
				'warning',
				__( 'Redis PHP extension not installed (optional)', 'ops-health-dashboard' ),
				[],
				microtime( true ) - $start
			);
		}

		// 2. Configuration.
		$config = $this->get_redis_config();

		// 3. Connection.
		try {
			$redis = $this->create_redis_instance();
			$redis->connect( $config['host'], $config['port'], self::CONNECT_TIMEOUT );
		} catch ( \Throwable $e ) {
			return $this->build_result(
				'warning',
				__( 'Redis connection failed', 'ops-health-dashboard' ),
				[ 'error' => $this->redaction->redact( $e->getMessage() ) ],
				microtime( true ) - $start
			);
		}

		// 4. Authentication (if configured).
		if ( '' !== $config['password'] ) {
			try {
				$redis->auth( $config['password'] );
			} catch ( \Throwable $e ) {
				$this->close_connection( $redis );
				return $this->build_result(
					'warning',
					__( 'Redis authentication failed', 'ops-health-dashboard' ),
					[ 'error' => $this->redaction->redact( $e->getMessage() ) ],
					microtime( true ) - $start
				);
			}
		}

		// 5. Database selection (if not 0).
		if ( 0 !== $config['database'] ) {
			try {
				$redis->select( $config['database'] );
			} catch ( \Throwable $e ) {
				$this->close_connection( $redis );
				return $this->build_result(
					'warning',
					__( 'Redis database selection failed', 'ops-health-dashboard' ),
					[ 'error' => $this->redaction->redact( $e->getMessage() ) ],
					microtime( true ) - $start
				);
			}
		}

		// 6. SET/GET/DEL smoke test (unique key to avoid race conditions).
		$smoke_key   = self::SMOKE_TEST_KEY . '_' . uniqid( '', true );
		$smoke_start = microtime( true );

		try {
			$set_result = $redis->set( $smoke_key, self::SMOKE_TEST_VALUE );

			if ( false === $set_result ) {
				$this->cleanup_and_close( $redis, $smoke_key );
				return $this->build_result(
					'warning',
					__( 'Redis smoke test failed (SET returned false)', 'ops-health-dashboard' ),
					[],
					microtime( true ) - $start
				);
			}

			$get_result = $redis->get( $smoke_key );

			if ( self::SMOKE_TEST_VALUE !== $get_result ) {
				$this->cleanup_and_close( $redis, $smoke_key );
				return $this->build_result(
					'warning',
					__( 'Redis smoke test failed (GET value mismatch)', 'ops-health-dashboard' ),
					[],
					microtime( true ) - $start
				);
			}

			$redis->del( $smoke_key );
		} catch ( \Throwable $e ) {
			$this->cleanup_and_close( $redis, $smoke_key );
			return $this->build_result(
				'warning',
				__( 'Redis smoke test failed', 'ops-health-dashboard' ),
				[ 'error' => $this->redaction->redact( $e->getMessage() ) ],
				microtime( true ) - $start
			);
		}

		$smoke_duration = microtime( true ) - $smoke_start;

		$this->close_connection( $redis );

		// 7. Evaluate response time.
		$response_time_ms = round( $smoke_duration * 1000, 2 ) . 'ms';
		$redacted_host    = $this->redaction->redact( $config['host'] );

		if ( $smoke_duration > self::SLOW_THRESHOLD ) {
			return $this->build_result(
				'warning',
				__( 'Redis response slow', 'ops-health-dashboard' ),
				[
					'response_time' => $response_time_ms,
					'host'          => $redacted_host,
				],
				microtime( true ) - $start
			);
		}

		// 8. All ok.
		return $this->build_result(
			'ok',
			__( 'Redis connection healthy', 'ops-health-dashboard' ),
			[
				'response_time' => $response_time_ms,
				'host'          => $redacted_host,
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
		return 'redis';
	}

	/**
	 * Gets the check name
	 *
	 * @return string Check name.
	 */
	public function get_name(): string {
		return __( 'Redis Cache', 'ops-health-dashboard' );
	}

	/**
	 * Checks if the check is enabled
	 *
	 * @return bool True if enabled.
	 */
	public function is_enabled(): bool {
		return true;
	}

	/**
	 * Checks if the Redis PHP extension is loaded
	 *
	 * @return bool True if the extension is available.
	 */
	protected function is_extension_loaded(): bool {
		return extension_loaded( 'redis' );
	}

	/**
	 * Creates a new Redis instance
	 *
	 * @return \Redis Redis instance.
	 */
	protected function create_redis_instance(): \Redis {
		return new \Redis();
	}

	/**
	 * Gets the Redis configuration from WordPress constants
	 *
	 * Reads WP_REDIS_HOST, WP_REDIS_PORT, WP_REDIS_PASSWORD, WP_REDIS_DATABASE.
	 *
	 * @return array {
	 *     Redis configuration.
	 *
	 *     @type string $host     Redis host (default 127.0.0.1).
	 *     @type int    $port     Redis port (default 6379).
	 *     @type string $password Redis password (default '').
	 *     @type int    $database Redis database (default 0).
	 * }
	 */
	protected function get_redis_config(): array {
		return [
			'host'     => defined( 'WP_REDIS_HOST' ) ? WP_REDIS_HOST : '127.0.0.1',
			'port'     => defined( 'WP_REDIS_PORT' ) ? (int) WP_REDIS_PORT : 6379,
			'password' => defined( 'WP_REDIS_PASSWORD' ) ? WP_REDIS_PASSWORD : '',
			'database' => defined( 'WP_REDIS_DATABASE' ) ? (int) WP_REDIS_DATABASE : 0,
		];
	}

	/**
	 * Safely closes the Redis connection
	 *
	 * @param \Redis $redis Redis instance.
	 */
	private function close_connection( $redis ): void {
		try {
			$redis->close();
			// phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
		} catch ( \Throwable $e ) {
			// Ignore close errors.
		}
	}

	/**
	 * Cleans up the smoke test key and closes the connection
	 *
	 * @param \Redis $redis     Redis instance.
	 * @param string $smoke_key Smoke test key to delete.
	 */
	private function cleanup_and_close( $redis, string $smoke_key ): void {
		try {
			$redis->del( $smoke_key );
			// phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
		} catch ( \Throwable $e ) {
			// Ignore cleanup errors.
		}
		$this->close_connection( $redis );
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
