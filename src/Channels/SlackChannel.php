<?php
/**
 * Slack Alert Channel
 *
 * Alert channel that sends notifications via Slack Incoming Webhook.
 * Uses Block Kit format with status colors.
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
 * Class SlackChannel
 *
 * Slack channel implementation for alerts.
 */
class SlackChannel implements AlertChannelInterface {

	/**
	 * Status color map
	 *
	 * @var array
	 */
	const STATUS_COLORS = [
		'critical' => '#FF0000',
		'warning'  => '#FFA500',
		'ok'       => '#36A64F',
	];

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
		return 'slack';
	}

	/**
	 * Gets the channel name
	 *
	 * @return string
	 */
	public function get_name(): string {
		return __( 'Slack', 'ops-health-dashboard' );
	}

	/**
	 * Checks if the channel is enabled
	 *
	 * @return bool
	 */
	public function is_enabled(): bool {
		$settings = $this->get_channel_settings();
		return ! empty( $settings['enabled'] ) && ! empty( $settings['webhook_url'] );
	}

	/**
	 * Sends an alert via Slack
	 *
	 * @param array $payload Alert data.
	 * @return array Result with success and error keys.
	 */
	public function send( array $payload ): array {
		$settings    = $this->get_channel_settings();
		$webhook_url = isset( $settings['webhook_url'] ) ? $settings['webhook_url'] : '';

		$slack_payload = $this->format_slack_payload( $payload );
		$result        = $this->http_client->post( $webhook_url, $slack_payload );

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
	 * Escapes special mrkdwn characters for Slack
	 *
	 * Prevents mrkdwn formatting injection in user values.
	 *
	 * @param string $text Text to escape.
	 * @return string Escaped text.
	 */
	private function escape_mrkdwn( string $text ): string {
		return str_replace(
			[ '&', '<', '>', '*', '_', '~', '`' ],
			[ '&amp;', '&lt;', '&gt;', '\*', '\_', '\~', '\`' ],
			$text
		);
	}

	/**
	 * Formats the payload for Slack Block Kit
	 *
	 * @param array $payload Alert data.
	 * @return array Slack payload.
	 */
	private function format_slack_payload( array $payload ): array {
		$check_name  = $payload['check_name'] ?? $payload['check_id'] ?? 'Unknown';
		$status      = $payload['current_status'] ?? 'unknown';
		$message     = $payload['message'] ?? '';
		$site_name   = $payload['site_name'] ?? '';
		$site_url    = $payload['site_url'] ?? '';
		$is_recovery = ! empty( $payload['is_recovery'] );

		$title = $is_recovery
			? sprintf( '[Recovered] %s: %s', $check_name, strtoupper( $status ) )
			: sprintf( '[Alert] %s: %s', $check_name, strtoupper( $status ) );

		$color = isset( self::STATUS_COLORS[ $status ] )
			? self::STATUS_COLORS[ $status ]
			: '#808080';

		$text_parts = [];
		if ( '' !== $message ) {
			$text_parts[] = '*Message:* ' . $this->escape_mrkdwn( $message );
		}
		if ( '' !== $site_name ) {
			$text_parts[] = '*Site:* ' . $this->escape_mrkdwn( $site_name );
		}
		if ( '' !== $site_url ) {
			$text_parts[] = '*URL:* ' . $this->escape_mrkdwn( $site_url );
		}

		return [
			'blocks'      => [
				[
					'type' => 'header',
					'text' => [
						'type' => 'plain_text',
						'text' => $title,
					],
				],
				[
					'type' => 'section',
					'text' => [
						'type' => 'mrkdwn',
						'text' => implode( "\n", $text_parts ),
					],
				],
			],
			'attachments' => [
				[ 'color' => $color ],
			],
		];
	}

	/**
	 * Gets the channel settings
	 *
	 * @return array
	 */
	private function get_channel_settings(): array {
		$settings = $this->storage->get( 'alert_settings', [] );

		if ( ! is_array( $settings ) || ! isset( $settings['slack'] ) ) {
			return [];
		}

		return is_array( $settings['slack'] ) ? $settings['slack'] : [];
	}
}
