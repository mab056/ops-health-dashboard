<?php
/**
 * Alert Manager Service
 *
 * Central alert orchestrator. Detects state changes,
 * manages cooldown, and dispatches to notification channels.
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

use OpsHealthDashboard\Interfaces\AlertChannelInterface;
use OpsHealthDashboard\Interfaces\AlertManagerInterface;
use OpsHealthDashboard\Interfaces\RedactionInterface;
use OpsHealthDashboard\Interfaces\StorageInterface;

/**
 * Class AlertManager
 *
 * Manages state change detection and alert dispatch.
 */
class AlertManager implements AlertManagerInterface {

	/**
	 * Default cooldown in seconds (60 minutes)
	 *
	 * @var int
	 */
	const DEFAULT_COOLDOWN = 3600;

	/**
	 * Maximum number of entries in the alert log
	 *
	 * @var int
	 */
	const MAX_LOG_ENTRIES = 50;

	/**
	 * Prefix for cooldown transients
	 *
	 * @var string
	 */
	const COOLDOWN_TRANSIENT_PREFIX = 'ops_health_alert_cooldown_';

	/**
	 * Status: check ok
	 *
	 * @var string
	 */
	const STATUS_OK = 'ok';

	/**
	 * Status: check warning
	 *
	 * @var string
	 */
	const STATUS_WARNING = 'warning';

	/**
	 * Status: check critical
	 *
	 * @var string
	 */
	const STATUS_CRITICAL = 'critical';

	/**
	 * Status: unknown
	 *
	 * @var string
	 */
	const STATUS_UNKNOWN = 'unknown';

	/**
	 * Storage for reading/saving state and log
	 *
	 * @var StorageInterface
	 */
	private $storage;

	/**
	 * Redaction service
	 *
	 * @var RedactionInterface
	 */
	private $redaction;

	/**
	 * Registered notification channels
	 *
	 * @var AlertChannelInterface[]
	 */
	private $channels = [];

	/**
	 * Constructor
	 *
	 * @param StorageInterface   $storage   Storage for state and log.
	 * @param RedactionInterface $redaction Redaction service.
	 */
	public function __construct( StorageInterface $storage, RedactionInterface $redaction ) {
		$this->storage   = $storage;
		$this->redaction = $redaction;
	}

	/**
	 * Adds a notification channel
	 *
	 * @param AlertChannelInterface $channel Channel to add.
	 * @return void
	 */
	public function add_channel( AlertChannelInterface $channel ): void {
		$this->channels[] = $channel;
	}

	/**
	 * Processes results and sends alerts on state changes
	 *
	 * @param array $current_results  Current results from run_all().
	 * @param array $previous_results Previous results.
	 * @return array Array of alert dispatch results.
	 */
	public function process( array $current_results, array $previous_results ): array {
		$all_results   = [];
		$changes       = $this->detect_state_changes( $current_results, $previous_results );
		$cooldown_secs = $this->get_cooldown_seconds();

		foreach ( $changes as $check_id => $change ) {
			$is_recovery = self::STATUS_OK === $change['current_status'];

			// Recovery bypasses cooldown.
			if ( ! $is_recovery && $this->is_in_cooldown( $check_id ) ) {
				continue;
			}

			$payload = $this->build_payload( $check_id, $change );

			// Set cooldown BEFORE dispatch to prevent alert spam
			// even if all channels fail.
			if ( ! $is_recovery ) {
				$this->set_cooldown( $check_id, $cooldown_secs );
			}

			$send_results = $this->dispatch_to_channels( $payload );

			if ( ! empty( $send_results ) ) {
				$all_results[ $check_id ] = $send_results;

				$this->log_alert( $payload, $send_results );
			}
		}

		return $all_results;
	}

	/**
	 * Detects state changes between current and previous results
	 *
	 * @param array $current  Current results.
	 * @param array $previous Previous results.
	 * @return array Detected changes (check_id => data).
	 */
	private function detect_state_changes( array $current, array $previous ): array {
		$changes = [];

		foreach ( $current as $check_id => $result ) {
			$current_status = isset( $result['status'] ) ? $result['status'] : self::STATUS_UNKNOWN;

			if ( isset( $previous[ $check_id ] ) ) {
				$prev_status = isset( $previous[ $check_id ]['status'] )
					? $previous[ $check_id ]['status']
					: self::STATUS_UNKNOWN;
			} else {
				// First run: alert only if not ok.
				$prev_status = null;
			}

			// No change: skip.
			if ( $current_status === $prev_status ) {
				continue;
			}

			// First run with ok: no alert.
			if ( null === $prev_status && self::STATUS_OK === $current_status ) {
				continue;
			}

			$changes[ $check_id ] = [
				'current_status'  => $current_status,
				'previous_status' => $prev_status,
				'result'          => $result,
			];
		}

		return $changes;
	}

	/**
	 * Checks if a check is in cooldown
	 *
	 * @param string $check_id Check ID.
	 * @return bool True if in cooldown.
	 */
	private function is_in_cooldown( string $check_id ): bool {
		$transient_key = self::COOLDOWN_TRANSIENT_PREFIX . $check_id;
		return false !== get_transient( $transient_key );
	}

	/**
	 * Sets the cooldown for a check
	 *
	 * @param string $check_id Check ID.
	 * @param int    $seconds  Duration in seconds.
	 * @return void
	 */
	private function set_cooldown( string $check_id, int $seconds ): void {
		$transient_key = self::COOLDOWN_TRANSIENT_PREFIX . $check_id;
		set_transient( $transient_key, time(), $seconds );
	}

	/**
	 * Gets the configured cooldown in seconds
	 *
	 * @return int Cooldown seconds.
	 */
	private function get_cooldown_seconds(): int {
		$settings = $this->storage->get( 'alert_settings', [] );

		if ( is_array( $settings ) && isset( $settings['cooldown_minutes'] ) ) {
			$minutes = (int) $settings['cooldown_minutes'];
			if ( $minutes > 0 ) {
				return $minutes * 60;
			}
		}

		return self::DEFAULT_COOLDOWN;
	}

	/**
	 * Builds the alert payload
	 *
	 * @param string $check_id Check ID.
	 * @param array  $change   Change data.
	 * @return array Structured payload.
	 */
	private function build_payload( string $check_id, array $change ): array {
		$result          = $change['result'];
		$current_status  = $change['current_status'];
		$previous_status = $change['previous_status'];

		return [
			'check_id'        => $check_id,
			'check_name'      => isset( $result['name'] ) ? $result['name'] : $check_id,
			'previous_status' => $previous_status,
			'current_status'  => $current_status,
			'message'         => isset( $result['message'] ) ? $result['message'] : '',
			'details'         => isset( $result['details'] ) ? $result['details'] : [],
			'timestamp'       => time(),
			'site_url'        => home_url(),
			'site_name'       => get_bloginfo( 'name' ),
			'is_recovery'     => self::STATUS_OK === $current_status,
		];
	}

	/**
	 * Sends the payload to all enabled channels
	 *
	 * @param array $payload Alert payload.
	 * @return array Dispatch results for each channel.
	 */
	private function dispatch_to_channels( array $payload ): array {
		$results = [];

		foreach ( $this->channels as $channel ) {
			if ( ! $channel->is_enabled() ) {
				continue;
			}

			try {
				$results[ $channel->get_id() ] = $channel->send( $payload );
			} catch ( \Throwable $e ) {
				$results[ $channel->get_id() ] = [
					'success' => false,
					'error'   => $e->getMessage(),
				];
			}
		}

		return $results;
	}

	/**
	 * Logs the alert (limited to MAX_LOG_ENTRIES)
	 *
	 * @param array $payload Alert payload.
	 * @param array $results Dispatch results.
	 * @return void
	 */
	private function log_alert( array $payload, array $results ): void {
		$log = $this->storage->get( 'alert_log', [] );

		if ( ! is_array( $log ) ) {
			$log = [];
		}

		// Redact any error messages from channels.
		$redacted_errors = [];
		foreach ( $results as $channel_id => $r ) {
			if ( ! empty( $r['error'] ) ) {
				$redacted_errors[ $channel_id ] = $this->redaction->redact( $r['error'] );
			}
		}

		$entry = [
			'check_id'  => $payload['check_id'],
			'status'    => $payload['current_status'],
			'channels'  => array_keys( $results ),
			'errors'    => $redacted_errors,
			'success'   => ! empty(
				array_filter(
					$results,
					function ( $r ) {
						return ! empty( $r['success'] );
					}
				)
			),
			'timestamp' => $payload['timestamp'],
		];

		array_unshift( $log, $entry );

		// Limit to MAX_LOG_ENTRIES.
		$log = array_slice( $log, 0, self::MAX_LOG_ENTRIES );

		$this->storage->set( 'alert_log', $log );
	}
}
