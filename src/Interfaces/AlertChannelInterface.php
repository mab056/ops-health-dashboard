<?php
/**
 * Alert Channel Interface
 *
 * Contratto per i canali di notifica degli alert.
 * Ogni canale (Email, Slack, Telegram, ecc.) implementa questa interfaccia.
 *
 * @package OpsHealthDashboard\Interfaces
 */

namespace OpsHealthDashboard\Interfaces;

/**
 * Interface AlertChannelInterface
 *
 * Definisce il contratto per l'invio di notifiche attraverso un canale specifico.
 */
interface AlertChannelInterface {

	/**
	 * Ottiene l'identificatore del canale
	 *
	 * @return string ID del canale (es. 'email', 'slack', 'telegram').
	 */
	public function get_id(): string;

	/**
	 * Ottiene il nome visualizzabile del canale
	 *
	 * @return string Nome leggibile.
	 */
	public function get_name(): string;

	/**
	 * Verifica se il canale è abilitato e configurato
	 *
	 * @return bool True se il canale è attivo.
	 */
	public function is_enabled(): bool;

	/**
	 * Invia una notifica di alert
	 *
	 * @param array $payload Dati dell'alert con chiavi:
	 *     check_id, check_name, previous_status, current_status,
	 *     message, details, timestamp, site_url, site_name, is_recovery.
	 * @return array {
	 *     @type bool        $success Se l'invio è riuscito.
	 *     @type string|null $error   Messaggio di errore se fallito.
	 * }
	 */
	public function send( array $payload ): array;
}
