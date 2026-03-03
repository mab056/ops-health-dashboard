<?php
/**
 * Email Alert Channel
 *
 * Alert channel that sends notifications via email using wp_mail().
 *
 * @package OpsHealthDashboard\Channels
 */

namespace OpsHealthDashboard\Channels;

if ( ! defined( 'ABSPATH' ) ) {
	// phpcs:ignore Squiz.Commenting.InlineComment.InvalidEndChar
	// @codeCoverageIgnoreStart
	exit;
	// phpcs:ignore Squiz.Commenting.InlineComment.InvalidEndChar
	// @codeCoverageIgnoreEnd
}

use OpsHealthDashboard\Interfaces\AlertChannelInterface;
use OpsHealthDashboard\Interfaces\StorageInterface;

/**
 * Class EmailChannel
 *
 * Email channel implementation for alerts.
 */
class EmailChannel implements AlertChannelInterface {

	/**
	 * Storage for reading channel settings
	 *
	 * @var StorageInterface
	 */
	private $storage;

	/**
	 * Constructor
	 *
	 * @param StorageInterface $storage Storage for settings.
	 */
	public function __construct( StorageInterface $storage ) {
		$this->storage = $storage;
	}

	/**
	 * Gets the channel identifier
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'email';
	}

	/**
	 * Gets the channel display name
	 *
	 * @return string
	 */
	public function get_name(): string {
		return __( 'Email', 'ops-health-dashboard' );
	}

	/**
	 * Checks if the channel is enabled and configured
	 *
	 * @return bool True if enabled with configured recipients.
	 */
	public function is_enabled(): bool {
		$settings = $this->get_channel_settings();

		if ( empty( $settings['enabled'] ) ) {
			return false;
		}

		if ( empty( $settings['recipients'] ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Sends an alert notification via email
	 *
	 * @param array $payload Alert data.
	 * @return array Result with success and error keys.
	 */
	public function send( array $payload ): array {
		$settings   = $this->get_channel_settings();
		$recipients = $this->parse_recipients( $settings['recipients'] ?? '' );

		if ( empty( $recipients ) ) {
			return [
				'success' => false,
				'error'   => __( 'No valid email recipients configured', 'ops-health-dashboard' ),
			];
		}

		$subject = $this->format_subject( $payload );
		$body    = $this->format_body( $payload );

		$sent = wp_mail( $recipients, $subject, $body );

		if ( ! $sent ) {
			return [
				'success' => false,
				'error'   => __( 'wp_mail() failed to send email', 'ops-health-dashboard' ),
			];
		}

		return [
			'success' => true,
			'error'   => null,
		];
	}

	/**
	 * Gets the email channel settings
	 *
	 * @return array Channel settings.
	 */
	private function get_channel_settings(): array {
		$settings = $this->storage->get( 'alert_settings', [] );

		if ( ! is_array( $settings ) || ! isset( $settings['email'] ) ) {
			return [];
		}

		return is_array( $settings['email'] ) ? $settings['email'] : [];
	}

	/**
	 * Converts recipients string to array
	 *
	 * @param string $recipients Comma-separated recipients.
	 * @return array Array of email addresses.
	 */
	private function parse_recipients( string $recipients ): array {
		$list    = explode( ',', $recipients );
		$trimmed = array_map( 'trim', $list );
		return array_values(
			array_filter(
				$trimmed,
				function ( $email ) {
					return '' !== $email && is_email( $email );
				}
			)
		);
	}

	/**
	 * Formats the email subject
	 *
	 * @param array $payload Alert data.
	 * @return string Formatted subject.
	 */
	private function format_subject( array $payload ): string {
		$check_name = $payload['check_name'] ?? $payload['check_id'] ?? 'Unknown';
		$status     = $payload['current_status'] ?? 'unknown';

		return sprintf(
			/* translators: 1: check name, 2: status */
			__( '[Ops Health] %1$s: %2$s', 'ops-health-dashboard' ),
			$check_name,
			strtoupper( $status )
		);
	}

	/**
	 * Formats the email body
	 *
	 * @param array $payload Alert data.
	 * @return string Formatted body.
	 */
	private function format_body( array $payload ): string {
		$check_name      = $payload['check_name'] ?? $payload['check_id'] ?? 'Unknown';
		$current_status  = $payload['current_status'] ?? 'unknown';
		$previous_status = $payload['previous_status'] ?? 'none';
		$message         = $payload['message'] ?? '';
		$site_url        = $payload['site_url'] ?? '';
		$site_name       = $payload['site_name'] ?? '';
		$is_recovery     = ! empty( $payload['is_recovery'] );
		$timestamp       = $payload['timestamp'] ?? time();

		$lines   = [];
		$lines[] = sprintf(
			/* translators: %s: check name */
			__( 'Check: %s', 'ops-health-dashboard' ),
			$check_name
		);
		$lines[] = sprintf(
			/* translators: %s: previous status */
			__( 'Previous Status: %s', 'ops-health-dashboard' ),
			strtoupper( $previous_status )
		);
		$lines[] = sprintf(
			/* translators: %s: current status */
			__( 'Current Status: %s', 'ops-health-dashboard' ),
			strtoupper( $current_status )
		);
		$lines[] = sprintf(
			/* translators: %s: message */
			__( 'Message: %s', 'ops-health-dashboard' ),
			$message
		);
		$lines[] = '';

		if ( $is_recovery ) {
			$lines[] = __( 'This is a RECOVERY alert.', 'ops-health-dashboard' );
			$lines[] = '';
		}

		$lines[] = sprintf(
			/* translators: %s: timestamp */
			__( 'Timestamp: %s', 'ops-health-dashboard' ),
			gmdate( 'Y-m-d H:i:s', $timestamp ) . ' UTC'
		);

		if ( '' !== $site_name ) {
			$lines[] = sprintf(
				/* translators: %s: site name */
				__( 'Site: %s', 'ops-health-dashboard' ),
				$site_name
			);
		}

		if ( '' !== $site_url ) {
			$lines[] = sprintf(
				/* translators: %s: site URL */
				__( 'URL: %s', 'ops-health-dashboard' ),
				$site_url
			);
		}

		return implode( "\n", $lines );
	}
}
