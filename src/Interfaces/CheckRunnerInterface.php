<?php
/**
 * CheckRunner Interface
 *
 * Contratto per il servizio di esecuzione dei check di salute.
 * Consente disaccoppiamento tra CheckRunner e i suoi consumatori.
 *
 * @package OpsHealthDashboard\Interfaces
 */

namespace OpsHealthDashboard\Interfaces;

/**
 * Interface CheckRunnerInterface
 *
 * Definisce i metodi per l'esecuzione e gestione dei check di salute.
 */
interface CheckRunnerInterface {

	/**
	 * Aggiunge un check al runner
	 *
	 * @param CheckInterface $check Check da aggiungere.
	 * @return void
	 */
	public function add_check( CheckInterface $check ): void;

	/**
	 * Esegue tutti i check abilitati
	 *
	 * @return array Array associativo con i risultati per ogni check (chiave = ID check).
	 */
	public function run_all(): array;

	/**
	 * Ottiene gli ultimi risultati dallo storage
	 *
	 * @return array Ultimi risultati dei check.
	 */
	public function get_latest_results(): array;

	/**
	 * Cancella i risultati dallo storage
	 *
	 * @return void
	 */
	public function clear_results(): void;
}
