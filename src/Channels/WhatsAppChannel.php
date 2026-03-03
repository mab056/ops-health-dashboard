<?php
/**
 * WhatsApp Alert Channel
 *
 * Alert channel that sends notifications via generic webhook for WhatsApp.
 * Compatible with providers such as Twilio, Meta, Vonage.
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
use OpsHealthDashboard\Interfaces\HttpClientInterface;
use OpsHealthDashboard\Interfaces\StorageInterface;

/**
 * Class WhatsAppChannel
 *
 * WhatsApp channel implementation for alerts.
 */
class WhatsAppChannel implements AlertChannelInterface {

	/**
	 * Storage for settings
	 *
	 * @var StorageInterface
	 */
	private $storage;

	/**
	 * HTTP client
	 *
	 * @var HttpClientInterface
	 */
	private $http_client;

	/**
	 * Constructor
	 *
	 * @param StorageInterface    $storage     Storage for settings.
	 * @param HttpClientInterface $http_client Secure HTTP client.
	 */
	public function __construct( StorageInterface $storage, HttpClientInterface $http_client ) {
		$this->storage     = $storage;
		$this->http_client = $http_client;
	}

	/**
	 * Gets the channel identifier
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'whatsapp';
	}

	/**
	 * Gets the channel name
	 *
	 * @return string
	 */
	public function get_name(): string {
		return __( 'WhatsApp', 'ops-health-dashboard' );
	}

	/**
	 * Checks if the channel is enabled
	 *
	 * @return bool
	 */
	public function is_enabled(): bool {
		$settings = $this->get_channel_settings();
		return ! empty( $settings['enabled'] )
			&& ! empty( $settings['webhook_url'] )
			&& ! empty( $settings['phone_number'] )
			&& $this->is_valid_phone( $settings['phone_number'] );
	}

	/**
	 * Validates a phone number in E.164 format
	 *
	 * Accepted format: + followed by 7 to 15 digits (e.g. +391234567890).
	 *
	 * @param string $phone Phone number to validate.
	 * @return bool True if valid.
	 */
	private function is_valid_phone( string $phone ): bool {
		return 1 === preg_match( '/^\+[1-9]\d{6,14}$/', $phone );
	}

	/**
	 * Sends an alert via WhatsApp webhook
	 *
	 * @param array $payload Alert data.
	 * @return array Result with success and error keys.
	 */
	public function send( array $payload ): array {
		$settings    = $this->get_channel_settings();
		$webhook_url = isset( $settings['webhook_url'] ) ? $settings['webhook_url'] : '';

		$body = [
			'to'   => isset( $settings['phone_number'] ) ? $settings['phone_number'] : '',
			'body' => $this->format_message( $payload ),
		];

		$headers = [];
		if ( ! empty( $settings['api_token'] ) ) {
			$headers['Authorization'] = 'Bearer ' . $settings['api_token'];
		}

		$result = $this->http_client->post( $webhook_url, $body, $headers );

		if ( ! $result['success'] ) {
			return [
				'success' => false,
				'error'   => $result['error'],
			];
		}

		return [
			'success' => true,
			'error'   => null,
		];
	}

	/**
	 * Formats the message for WhatsApp
	 *
	 * @param array $payload Alert data.
	 * @return string Text message.
	 */
	private function format_message( array $payload ): string {
		$check_name  = $payload['check_name'] ?? $payload['check_id'] ?? 'Unknown';
		$status      = $payload['current_status'] ?? 'unknown';
		$message     = $payload['message'] ?? '';
		$site_url    = $payload['site_url'] ?? '';
		$is_recovery = ! empty( $payload['is_recovery'] );

		$label = $is_recovery ? 'Recovered' : 'Alert';

		$text = sprintf( '[Ops Health] %s - %s: %s', $label, $check_name, strtoupper( $status ) );

		if ( '' !== $message ) {
			$text .= "\n" . $message;
		}

		if ( '' !== $site_url ) {
			$text .= "\n" . $site_url;
		}

		return $text;
	}

	/**
	 * Gets the channel settings
	 *
	 * @return array
	 */
	private function get_channel_settings(): array {
		$settings = $this->storage->get( 'alert_settings', [] );

		if ( ! is_array( $settings ) || ! isset( $settings['whatsapp'] ) ) {
			return [];
		}

		return is_array( $settings['whatsapp'] ) ? $settings['whatsapp'] : [];
	}
}
