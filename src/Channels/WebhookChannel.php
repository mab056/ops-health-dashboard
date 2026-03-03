<?php
/**
 * Webhook Alert Channel
 *
 * Alert channel that sends notifications via generic webhook (HTTP POST JSON).
 * Supports optional HMAC signature for authentication.
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
 * Class WebhookChannel
 *
 * Webhook channel implementation for alerts.
 */
class WebhookChannel implements AlertChannelInterface {

	/**
	 * Storage for settings
	 *
	 * @var StorageInterface
	 */
	private $storage;

	/**
	 * HTTP client with anti-SSRF protection
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
		return 'webhook';
	}

	/**
	 * Gets the channel name
	 *
	 * @return string
	 */
	public function get_name(): string {
		return __( 'Webhook', 'ops-health-dashboard' );
	}

	/**
	 * Checks if the channel is enabled
	 *
	 * @return bool
	 */
	public function is_enabled(): bool {
		$settings = $this->get_channel_settings();
		return ! empty( $settings['enabled'] ) && ! empty( $settings['url'] );
	}

	/**
	 * Sends an alert via webhook
	 *
	 * When an HMAC secret is configured, adds a custom `X-OpsHealth-Signature`
	 * header containing the SHA-256 signature of the JSON body. Consumers can
	 * verify authenticity by recalculating:
	 * `hash_hmac('sha256', $raw_body, $secret)`.
	 *
	 * @param array $payload Alert data.
	 * @return array Result with success and error keys.
	 */
	public function send( array $payload ): array {
		$settings = $this->get_channel_settings();
		$url      = isset( $settings['url'] ) ? $settings['url'] : '';
		$secret   = isset( $settings['secret'] ) ? $settings['secret'] : '';

		// Serializes the body once to ensure the HMAC signature matches the
		// exact bytes sent (no double json_encode).
		// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
		$raw_body = json_encode( $payload );

		$headers = [];
		if ( '' !== $secret ) {
			$signature                        = hash_hmac( 'sha256', $raw_body, $secret );
			$headers['X-OpsHealth-Signature'] = $signature;
		}

		$result = $this->http_client->post( $url, $raw_body, $headers );

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
	 * Gets the channel settings
	 *
	 * @return array
	 */
	private function get_channel_settings(): array {
		$settings = $this->storage->get( 'alert_settings', [] );

		if ( ! is_array( $settings ) || ! isset( $settings['webhook'] ) ) {
			return [];
		}

		return is_array( $settings['webhook'] ) ? $settings['webhook'] : [];
	}
}
