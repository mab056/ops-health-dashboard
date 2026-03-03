<?php
/**
 * Telegram Alert Channel
 *
 * Alert channel that sends notifications via Telegram Bot API.
 * Uses the sendMessage method with HTML parse_mode.
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
 * Class TelegramChannel
 *
 * Telegram channel implementation for alerts.
 */
class TelegramChannel implements AlertChannelInterface {

	/**
	 * Telegram Bot API base URL
	 *
	 * @var string
	 */
	const API_BASE_URL = 'https://api.telegram.org/bot';

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
		return 'telegram';
	}

	/**
	 * Gets the channel name
	 *
	 * @return string
	 */
	public function get_name(): string {
		return __( 'Telegram', 'ops-health-dashboard' );
	}

	/**
	 * Checks if the channel is enabled
	 *
	 * Requires configured bot_token and chat_id.
	 *
	 * @return bool
	 */
	public function is_enabled(): bool {
		$settings = $this->get_channel_settings();
		return ! empty( $settings['enabled'] )
			&& ! empty( $settings['bot_token'] )
			&& ! empty( $settings['chat_id'] );
	}

	/**
	 * Sends an alert via Telegram Bot API
	 *
	 * @param array $payload Alert data.
	 * @return array Result with success and error keys.
	 */
	public function send( array $payload ): array {
		$settings = $this->get_channel_settings();
		$url      = $this->build_api_url( $settings['bot_token'] ?? '' );

		$body = [
			'chat_id'    => $settings['chat_id'] ?? '',
			'text'       => $this->format_message( $payload ),
			'parse_mode' => 'HTML',
		];

		$result = $this->http_client->post( $url, $body );

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
	 * Builds the Telegram API URL
	 *
	 * @param string $bot_token Bot token.
	 * @return string Full URL for sendMessage.
	 */
	private function build_api_url( string $bot_token ): string {
		return self::API_BASE_URL . $bot_token . '/sendMessage';
	}

	/**
	 * Formats the message for Telegram (HTML)
	 *
	 * @param array $payload Alert data.
	 * @return string HTML message.
	 */
	private function format_message( array $payload ): string {
		$check_name  = $payload['check_name'] ?? $payload['check_id'] ?? 'Unknown';
		$status      = $payload['current_status'] ?? 'unknown';
		$prev_status = $payload['previous_status'] ?? 'none';
		$message     = $payload['message'] ?? '';
		$site_name   = $payload['site_name'] ?? '';
		$is_recovery = ! empty( $payload['is_recovery'] );

		$emoji = $this->get_status_emoji( $status );
		$label = $is_recovery ? 'Recovered' : 'Alert';

		$safe_check_name  = htmlspecialchars( $check_name, ENT_QUOTES, 'UTF-8' );
		$safe_status      = htmlspecialchars( strtoupper( $status ), ENT_QUOTES, 'UTF-8' );
		$safe_prev_status = htmlspecialchars( strtoupper( $prev_status ), ENT_QUOTES, 'UTF-8' );

		$lines   = [];
		$lines[] = "<b>[{$label}] {$safe_check_name}</b>";
		$lines[] = '';
		$lines[] = "{$emoji} <b>Status:</b> {$safe_status}";
		$lines[] = "<b>Previous:</b> {$safe_prev_status}";

		if ( '' !== $message ) {
			$lines[] = '<b>Message:</b> ' . htmlspecialchars( $message, ENT_QUOTES, 'UTF-8' );
		}

		if ( '' !== $site_name ) {
			$safe_site_name = htmlspecialchars( $site_name, ENT_QUOTES, 'UTF-8' );
			$lines[]        = '';
			$lines[]        = "<b>Site:</b> {$safe_site_name}";
		}

		return implode( "\n", $lines );
	}

	/**
	 * Gets the emoji for the status
	 *
	 * @param string $status Check status.
	 * @return string Corresponding emoji.
	 */
	private function get_status_emoji( string $status ): string {
		$map = [
			'critical' => "\xF0\x9F\x94\xB4",
			'warning'  => "\xF0\x9F\x9F\xA1",
			'ok'       => "\xE2\x9C\x85",
		];

		return isset( $map[ $status ] ) ? $map[ $status ] : "\xE2\x9D\x93";
	}

	/**
	 * Gets the channel settings
	 *
	 * @return array
	 */
	private function get_channel_settings(): array {
		$settings = $this->storage->get( 'alert_settings', [] );

		if ( ! is_array( $settings ) || ! isset( $settings['telegram'] ) ) {
			return [];
		}

		return is_array( $settings['telegram'] ) ? $settings['telegram'] : [];
	}
}
