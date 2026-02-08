<?php
/**
 * Check Interface
 *
 * Contratto per tutti i check di salute del sistema.
 * Ogni check implementa questa interfaccia per fornire informazioni consistenti.
 *
 * @package OpsHealthDashboard\Interfaces
 */

namespace OpsHealthDashboard\Interfaces;

/**
 * Interface CheckInterface
 *
 * Definisce i metodi per health checks.
 */
interface CheckInterface {

	/**
	 * Esegue il check e ritorna i risultati
	 *
	 * @return array {
	 *     Risultati del check.
	 *
	 *     @type string $status   Stato del check: 'ok', 'warning', 'critical'.
	 *     @type string $message  Messaggio descrittivo del risultato.
	 *     @type array  $details  Dettagli aggiuntivi specifici del check.
	 *     @type float  $duration Durata dell'esecuzione in secondi.
	 * }
	 */
	public function run(): array;

	/**
	 * Ottiene l'ID univoco del check
	 *
	 * @return string ID del check (es: 'database', 'disk', 'redis').
	 */
	public function get_id(): string;

	/**
	 * Ottiene il nome human-readable del check
	 *
	 * @return string Nome del check (es: 'Database Connection', 'Disk Space').
	 */
	public function get_name(): string;

	/**
	 * Verifica se il check è abilitato
	 *
	 * @return bool True se il check è abilitato, false altrimenti.
	 */
	public function is_enabled(): bool;
}
