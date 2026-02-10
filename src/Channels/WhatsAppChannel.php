<?php
/**
 * WhatsApp Alert Channel
 *
 * Canale di alert che invia notifiche via webhook generico per WhatsApp.
 * Compatibile con provider come Twilio, Meta, Vonage.
 *
 * @package OpsHealthDashboard\Channels
 */

namespace OpsHealthDashboard\Channels;

use OpsHealthDashboard\Interfaces\AlertChannelInterface;
use OpsHealthDashboard\Interfaces\HttpClientInterface;
use OpsHealthDashboard\Interfaces\StorageInterface;

/**
 * Class WhatsAppChannel
 *
 * Implementazione del canale WhatsApp per gli alert.
 */
class WhatsAppChannel implements AlertChannelInterface {

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
		return 'whatsapp';
	}

	/**
	 * Ottiene il nome del canale
	 *
	 * @return string
	 */
	public function get_name(): string {
		return __( 'WhatsApp', 'ops-health-dashboard' );
	}

	/**
	 * Verifica se il canale è abilitato
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
	 * Valida un numero di telefono in formato E.164
	 *
	 * Formato accettato: + seguito da 7 a 15 cifre (es. +391234567890).
	 *
	 * @param string $phone Numero di telefono da validare.
	 * @return bool True se valido.
	 */
	private function is_valid_phone( string $phone ): bool {
		return 1 === preg_match( '/^\+[1-9]\d{6,14}$/', $phone );
	}

	/**
	 * Invia un alert via WhatsApp webhook
	 *
	 * @param array $payload Dati dell'alert.
	 * @return array Risultato con chiavi success e error.
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
	 * Formatta il messaggio per WhatsApp
	 *
	 * @param array $payload Dati dell'alert.
	 * @return string Messaggio testuale.
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
	 * Ottiene le impostazioni del canale
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
