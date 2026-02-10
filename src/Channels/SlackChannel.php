<?php
/**
 * Slack Alert Channel
 *
 * Canale di alert che invia notifiche via Slack Incoming Webhook.
 * Usa il formato Block Kit con colori per lo stato.
 *
 * @package OpsHealthDashboard\Channels
 */

namespace OpsHealthDashboard\Channels;

use OpsHealthDashboard\Interfaces\AlertChannelInterface;
use OpsHealthDashboard\Interfaces\HttpClientInterface;
use OpsHealthDashboard\Interfaces\StorageInterface;

/**
 * Class SlackChannel
 *
 * Implementazione del canale Slack per gli alert.
 */
class SlackChannel implements AlertChannelInterface {

	/**
	 * Mappa colori per lo stato
	 *
	 * @var array
	 */
	const STATUS_COLORS = [
		'critical' => '#FF0000',
		'warning'  => '#FFA500',
		'ok'       => '#36A64F',
	];

	/**
	 * Storage per le impostazioni
	 *
	 * @var StorageInterface
	 */
	private $storage;

	/**
	 * Client HTTP
	 *
	 * @var HttpClientInterface
	 */
	private $http_client;

	/**
	 * Constructor
	 *
	 * @param StorageInterface    $storage     Storage per le impostazioni.
	 * @param HttpClientInterface $http_client Client HTTP sicuro.
	 */
	public function __construct( StorageInterface $storage, HttpClientInterface $http_client ) {
		$this->storage     = $storage;
		$this->http_client = $http_client;
	}

	/**
	 * Ottiene l'identificatore del canale
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'slack';
	}

	/**
	 * Ottiene il nome del canale
	 *
	 * @return string
	 */
	public function get_name(): string {
		return __( 'Slack', 'ops-health-dashboard' );
	}

	/**
	 * Verifica se il canale è abilitato
	 *
	 * @return bool
	 */
	public function is_enabled(): bool {
		$settings = $this->get_channel_settings();
		return ! empty( $settings['enabled'] ) && ! empty( $settings['webhook_url'] );
	}

	/**
	 * Invia un alert via Slack
	 *
	 * @param array $payload Dati dell'alert.
	 * @return array Risultato con chiavi success e error.
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
	 * Formatta il payload per Slack Block Kit
	 *
	 * @param array $payload Dati dell'alert.
	 * @return array Payload Slack.
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
			$text_parts[] = "*Message:* {$message}";
		}
		if ( '' !== $site_name ) {
			$text_parts[] = "*Site:* {$site_name}";
		}
		if ( '' !== $site_url ) {
			$text_parts[] = "*URL:* {$site_url}";
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
	 * Ottiene le impostazioni del canale
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
