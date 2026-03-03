<?php
/**
 * Error Log Check
 *
 * Verifies the PHP error log, aggregates by severity, and
 * redacts sensitive data before returning results.
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
 * Class ErrorLogCheck
 *
 * Safe error log summary check.
 */
class ErrorLogCheck implements CheckInterface {

	/**
	 * Sensitive data redaction service
	 *
	 * @var RedactionInterface
	 */
	private $redaction;

	/**
	 * Maximum number of lines to read from the end of the file
	 *
	 * @var int
	 */
	private $max_lines;

	/**
	 * Maximum number of bytes to read from the end of the file
	 *
	 * @var int
	 */
	private $max_bytes;

	/**
	 * Maximum number of samples to include in results
	 *
	 * @var int
	 */
	private $max_samples;

	/**
	 * Constructor
	 *
	 * @param RedactionInterface $redaction   Redaction service.
	 * @param int                $max_lines   Maximum lines to read (default 100).
	 * @param int                $max_bytes   Maximum bytes to read (default 512KB).
	 * @param int                $max_samples Maximum samples to include (default 5).
	 */
	public function __construct(
		RedactionInterface $redaction,
		int $max_lines = 100,
		int $max_bytes = 524288,
		int $max_samples = 5
	) {
		$this->redaction   = $redaction;
		$this->max_lines   = $max_lines;
		$this->max_bytes   = $max_bytes;
		$this->max_samples = $max_samples;
	}

	/**
	 * Runs the error log check
	 *
	 * @return array Check results.
	 */
	public function run(): array {
		$start = microtime( true );

		// 1. Resolve the log path.
		$log_path = $this->resolve_log_path();

		if ( '' === $log_path ) {
			return $this->build_result(
				'warning',
				__( 'Error log not configured (set WP_DEBUG_LOG or error_log in php.ini)', 'ops-health-dashboard' ),
				[ 'log_path_exists' => false ],
				microtime( true ) - $start
			);
		}

		// 2. Validate the file.
		$validation = $this->validate_log_file( $log_path );

		if ( ! $validation['valid'] ) {
			return $this->build_result(
				$validation['status'],
				$validation['message'],
				[ 'log_path_exists' => true ],
				microtime( true ) - $start
			);
		}

		// 3. Read the last lines.
		$lines = $this->read_tail( $log_path );

		if ( empty( $lines ) ) {
			return $this->build_result(
				'ok',
				__( 'Error log is empty', 'ops-health-dashboard' ),
				[ 'log_path_exists' => true ],
				microtime( true ) - $start
			);
		}

		// 4. Analyze and aggregate.
		$parsed = $this->parse_lines( $lines );

		// 5. Determine the status.
		$status  = $this->determine_status( $parsed['counts'] );
		$message = $this->build_message( $status, $parsed['counts'], count( $lines ) );

		// 6. Collect redacted samples.
		$samples = $this->collect_samples( $parsed['severity_lines'] );

		// 7. Formatted file size.
		$file_size = $this->get_file_size( $log_path );

		return $this->build_result(
			$status,
			$message,
			[
				'log_path_exists' => true,
				'file_size'       => $file_size,
				'lines_analyzed'  => count( $lines ),
				'counts'          => $parsed['counts'],
				'recent_samples'  => $samples,
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
		return 'error_log';
	}

	/**
	 * Gets the check name
	 *
	 * @return string Check name.
	 */
	public function get_name(): string {
		return __( 'Error Log Summary', 'ops-health-dashboard' );
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
	 * Resolves the error log file path
	 *
	 * Checks WP_DEBUG_LOG:
	 * - string = custom path
	 * - true = wp-content/debug.log (standard WordPress behavior)
	 * - false/undefined = fallback to ini_get('error_log')
	 *
	 * @return string Log file path, empty string if not found.
	 */
	protected function resolve_log_path(): string {
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound
		if ( defined( 'WP_DEBUG_LOG' ) ) {
			// WP_DEBUG_LOG can be string path or bool; WP stubs type it as bool only.
			$debug_log = WP_DEBUG_LOG;

			// String = custom path (WP stubs incorrectly type WP_DEBUG_LOG as bool).
			// phpcs:ignore Squiz.Commenting.InlineComment.InvalidEndChar -- PHPStan directive
			// @phpstan-ignore function.impossibleType, booleanAnd.alwaysFalse
			if ( is_string( $debug_log ) && '' !== $debug_log ) {
				return $debug_log; // @codeCoverageIgnore
			}

			// true = WordPress writes to wp-content/debug.log.
			if ( true === $debug_log && defined( 'WP_CONTENT_DIR' ) ) {
				return WP_CONTENT_DIR . '/debug.log'; // @codeCoverageIgnore
			}
		}

		$ini_path = ini_get( 'error_log' );
		if ( is_string( $ini_path ) && '' !== $ini_path ) {
			return $ini_path; // @codeCoverageIgnore
		}

		return '';
	}

	/**
	 * Validates the log file
	 *
	 * Checks existence, readability, and absence of symlinks.
	 *
	 * @param string $path File path to validate.
	 * @return array {
	 *     Validation result.
	 *
	 *     @type bool   $valid   True if the file is valid.
	 *     @type string $status  Status on error.
	 *     @type string $message Message on error.
	 * }
	 */
	protected function validate_log_file( string $path ): array {
		if ( is_link( $path ) ) {
			return [
				'valid'   => false,
				'status'  => 'warning',
				'message' => __( 'Error log path is a symbolic link (skipped for security)', 'ops-health-dashboard' ),
			];
		}

		if ( ! is_file( $path ) ) {
			return [
				'valid'   => false,
				'status'  => 'ok',
				'message' => __(
					'Error log configured but file does not exist yet (no errors logged)',
					'ops-health-dashboard'
				),
			];
		}

		if ( ! is_readable( $path ) ) {
			return [
				'valid'   => false,
				'status'  => 'warning',
				'message' => __( 'Error log file not readable (check permissions)', 'ops-health-dashboard' ),
			];
		}

		return [ 'valid' => true ];
	}

	/**
	 * Reads the last lines of the file
	 *
	 * Uses seek to the end of the file to read only the last max_bytes.
	 *
	 * @param string $path File path.
	 * @return array Array of read lines.
	 */
	protected function read_tail( string $path ): array {
		$file_size = filesize( $path );

		if ( false === $file_size || 0 === $file_size ) {
			return [];
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$handle = fopen( $path, 'r' );

		if ( false === $handle ) {
			return []; // @codeCoverageIgnore
		}

		// Shared lock to prevent reading during writing.
		if ( ! flock( $handle, LOCK_SH ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			fclose( $handle ); // @codeCoverageIgnore
			return []; // @codeCoverageIgnore
		}

		// Seek to the starting position.
		$offset = max( 0, $file_size - $this->max_bytes );
		if ( $offset > 0 ) {
			fseek( $handle, $offset );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread
		$content = fread( $handle, $this->max_bytes );

		flock( $handle, LOCK_UN );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		fclose( $handle );

		if ( false === $content || '' === $content ) {
			return []; // @codeCoverageIgnore
		}

		$lines = explode( "\n", $content );

		// Discard the first line if we seeked (may be partial).
		if ( $offset > 0 && count( $lines ) > 1 ) {
			array_shift( $lines );
		}

		// Remove empty lines from the end.
		while ( ! empty( $lines ) && '' === trim( end( $lines ) ) ) {
			array_pop( $lines );
		}

		// Take only the last max_lines lines.
		if ( count( $lines ) > $this->max_lines ) {
			$lines = array_slice( $lines, -$this->max_lines );
		}

		return array_values( $lines );
	}

	/**
	 * Parses lines and classifies by severity
	 *
	 * @param array $lines Array of log lines.
	 * @return array {
	 *     Analysis results.
	 *
	 *     @type array $counts         Counts by severity.
	 *     @type array $severity_lines Lines indexed by severity.
	 * }
	 */
	private function parse_lines( array $lines ): array {
		$counts = [
			'fatal'      => 0,
			'parse'      => 0,
			'warning'    => 0,
			'notice'     => 0,
			'deprecated' => 0,
			'strict'     => 0,
			'other'      => 0,
		];

		$severity_lines = [
			'critical' => [],
			'warning'  => [],
		];

		foreach ( $lines as $line ) {
			$severity = $this->classify_line( $line );
			if ( isset( $counts[ $severity ] ) ) {
				++$counts[ $severity ];
			}

			// Collect lines for samples.
			if ( 'fatal' === $severity || 'parse' === $severity ) {
				$severity_lines['critical'][] = $line;
			} elseif ( 'warning' === $severity || 'deprecated' === $severity || 'strict' === $severity ) {
				$severity_lines['warning'][] = $line;
			}
		}

		return [
			'counts'         => $counts,
			'severity_lines' => $severity_lines,
		];
	}

	/**
	 * Classifies a single line by severity
	 *
	 * Uses a single preg_match with alternation for efficiency.
	 *
	 * @param string $line Log line.
	 * @return string Severità: fatal, parse, warning, notice, deprecated, strict, other.
	 */
	private function classify_line( string $line ): string {
		$pattern = '/PHP (Fatal error|Core error|Parse error|Warning|Notice|Deprecated|Strict Standards)/i';

		if ( ! preg_match( $pattern, $line, $matches ) ) {
			return 'other';
		}

		$map = [
			'fatal error'      => 'fatal',
			'core error'       => 'fatal',
			'parse error'      => 'parse',
			'warning'          => 'warning',
			'notice'           => 'notice',
			'deprecated'       => 'deprecated',
			'strict standards' => 'strict',
		];

		return $map[ strtolower( $matches[1] ) ] ?? 'other';
	}

	/**
	 * Determines the status based on counts
	 *
	 * @param array $counts Counts by severity.
	 * @return string Status: ok, warning, critical.
	 */
	private function determine_status( array $counts ): string {
		if ( $counts['fatal'] > 0 || $counts['parse'] > 0 ) {
			return 'critical';
		}

		if ( $counts['warning'] > 0 || $counts['deprecated'] > 0 || $counts['strict'] > 0 ) {
			return 'warning';
		}

		return 'ok';
	}

	/**
	 * Builds the status message
	 *
	 * @param string $status      Determined status.
	 * @param array  $counts      Counts by severity.
	 * @param int    $total_lines Total lines analyzed.
	 * @return string Localized message.
	 */
	private function build_message( string $status, array $counts, int $total_lines ): string {
		if ( 'critical' === $status ) {
			return sprintf(
				/* translators: 1: fatal error count, 2: warning count, 3: total lines */
				__( 'Error log: %1$d fatal errors, %2$d warnings in last %3$d lines', 'ops-health-dashboard' ),
				$counts['fatal'] + $counts['parse'],
				$counts['warning'],
				$total_lines
			);
		}

		if ( 'warning' === $status ) {
			return sprintf(
				/* translators: 1: warning count, 2: notice count, 3: total lines */
				__( 'Error log: %1$d warnings, %2$d notices in last %3$d lines', 'ops-health-dashboard' ),
				$counts['warning'] + $counts['deprecated'] + $counts['strict'],
				$counts['notice'],
				$total_lines
			);
		}

		return sprintf(
			/* translators: %d: total lines analyzed */
			__( 'Error log: no significant errors in last %d lines', 'ops-health-dashboard' ),
			$total_lines
		);
	}

	/**
	 * Collects redacted samples for results
	 *
	 * Takes the most recent critical/warning lines and redacts them.
	 *
	 * @param array $severity_lines Lines grouped by severity.
	 * @return array Redacted samples (maximum max_samples).
	 */
	private function collect_samples( array $severity_lines ): array {
		// Priority: critical first, then fill remaining slots with warning.
		$critical  = array_slice( $severity_lines['critical'], -$this->max_samples );
		$remaining = $this->max_samples - count( $critical );
		$warnings  = $remaining > 0
			? array_slice( $severity_lines['warning'], -$remaining )
			: [];
		$samples   = array_merge( $critical, $warnings );

		// Redact all samples.
		return $this->redaction->redact_lines( $samples );
	}

	/**
	 * Gets the formatted file size
	 *
	 * @param string $path File path.
	 * @return string Formatted size.
	 */
	protected function get_file_size( string $path ): string {
		$size = filesize( $path );

		if ( false === $size ) {
			return __( 'Unknown', 'ops-health-dashboard' ); // @codeCoverageIgnore
		}

		return size_format( $size );
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
