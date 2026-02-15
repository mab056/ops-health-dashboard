<?php
/**
 * Webhook Alert Channel
 *
 * Canale di alert che invia notifiche via webhook generico (HTTP POST JSON).
 * Supporta firma HMAC opzionale per autenticazione.
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
 * Implementazione del canale webhook per gli alert.
 */
class WebhookChannel implements AlertChannelInterface {

	/**
	 * Storage per le impostazioni
	 *
	 * @var StorageInterface
	 */
	private $storage;

	/**
	 * Client HTTP con protezione anti-SSRF
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
		return 'webhook';
	}

	/**
	 * Ottiene il nome del canale
	 *
	 * @return string
	 */
	public function get_name(): string {
		return __( 'Webhook', 'ops-health-dashboard' );
	}

	/**
	 * Verifica se il canale è abilitato
	 *
	 * @return bool
	 */
	public function is_enabled(): bool {
		$settings = $this->get_channel_settings();
		return ! empty( $settings['enabled'] ) && ! empty( $settings['url'] );
	}

	/**
	 * Invia un alert via webhook
	 *
	 * Quando un secret HMAC è configurato, aggiunge un header custom
	 * `X-OpsHealth-Signature` contenente la firma SHA-256 del body JSON.
	 * I consumer possono verificare l'autenticità ricalcolando:
	 * `hash_hmac('sha256', $raw_body, $secret)`.
	 *
	 * @param array $payload Dati dell'alert.
	 * @return array Risultato con chiavi success e error.
	 */
	public function send( array $payload ): array {
		$settings = $this->get_channel_settings();
		$url      = isset( $settings['url'] ) ? $settings['url'] : '';
		$secret   = isset( $settings['secret'] ) ? $settings['secret'] : '';

		// Serializza il body una sola volta per garantire che la firma HMAC
		// corrisponda esattamente ai byte inviati (nessun doppio json_encode).
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
	 * Ottiene le impostazioni del canale
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
