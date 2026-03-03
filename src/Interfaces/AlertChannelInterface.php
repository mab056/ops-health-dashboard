<?php
/**
 * Alert Channel Interface
 *
 * Contract for alert notification channels.
 * Each channel (Email, Slack, Telegram, etc.) implements this interface.
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
 * Interface AlertChannelInterface
 *
 * Defines the contract for sending notifications through a specific channel.
 */
interface AlertChannelInterface {

	/**
	 * Gets the channel identifier
	 *
	 * @return string Channel ID (e.g. 'email', 'slack', 'telegram').
	 */
	public function get_id(): string;

	/**
	 * Gets the channel display name
	 *
	 * @return string Human-readable name.
	 */
	public function get_name(): string;

	/**
	 * Checks if the channel is enabled and configured
	 *
	 * @return bool True if the channel is active.
	 */
	public function is_enabled(): bool;

	/**
	 * Sends an alert notification
	 *
	 * @param array $payload Alert data with keys:
	 *     check_id, check_name, previous_status, current_status,
	 *     message, details, timestamp, site_url, site_name, is_recovery.
	 * @return array {
	 *     @type bool        $success Whether the send was successful.
	 *     @type string|null $error   Error message if failed.
	 * }
	 */
	public function send( array $payload ): array;
}
