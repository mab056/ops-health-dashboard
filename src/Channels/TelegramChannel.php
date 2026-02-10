<?php
/**
 * Telegram Alert Channel
 *
 * Canale di alert che invia notifiche via Telegram Bot API.
 * Usa il metodo sendMessage con parse_mode HTML.
 *
 * @package OpsHealthDashboard\Channels
 */

namespace OpsHealthDashboard\Channels;

use OpsHealthDashboard\Interfaces\AlertChannelInterface;
use OpsHealthDashboard\Interfaces\HttpClientInterface;
use OpsHealthDashboard\Interfaces\StorageInterface;

/**
 * Class TelegramChannel
 *
 * Implementazione del canale Telegram per gli alert.
 */
class TelegramChannel implements AlertChannelInterface {

	/**
	 * URL base dell'API Telegram Bot
	 *
	 * @var string
	 */
	const API_BASE_URL = 'https://api.telegram.org/bot';

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
		return 'telegram';
	}

	/**
	 * Ottiene il nome del canale
	 *
	 * @return string
	 */
	public function get_name(): string {
		return __( 'Telegram', 'ops-health-dashboard' );
	}

	/**
	 * Verifica se il canale è abilitato
	 *
	 * Richiede bot_token e chat_id configurati.
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
	 * Invia un alert via Telegram Bot API
	 *
	 * @param array $payload Dati dell'alert.
	 * @return array Risultato con chiavi success e error.
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
	 * Costruisce l'URL dell'API Telegram
	 *
	 * @param string $bot_token Token del bot.
	 * @return string URL completo per sendMessage.
	 */
	private function build_api_url( string $bot_token ): string {
		return self::API_BASE_URL . $bot_token . '/sendMessage';
	}

	/**
	 * Formatta il messaggio per Telegram (HTML)
	 *
	 * @param array $payload Dati dell'alert.
	 * @return string Messaggio HTML.
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

		$lines   = [];
		$lines[] = "<b>[{$label}] {$check_name}</b>";
		$lines[] = '';
		$lines[] = "{$emoji} <b>Status:</b> " . strtoupper( $status );
		$lines[] = '<b>Previous:</b> ' . strtoupper( $prev_status );

		if ( '' !== $message ) {
			$lines[] = '<b>Message:</b> ' . htmlspecialchars( $message, ENT_QUOTES, 'UTF-8' );
		}

		if ( '' !== $site_name ) {
			$lines[] = '';
			$lines[] = "<b>Site:</b> {$site_name}";
		}

		return implode( "\n", $lines );
	}

	/**
	 * Ottiene l'emoji per lo status
	 *
	 * @param string $status Status del check.
	 * @return string Emoji corrispondente.
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
	 * Ottiene le impostazioni del canale
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
