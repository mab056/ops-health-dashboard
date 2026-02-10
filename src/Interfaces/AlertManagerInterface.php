<?php
/**
 * Alert Manager Interface
 *
 * Contratto per l'orchestratore degli alert.
 * Gestisce la rilevazione dei cambiamenti di stato,
 * il cooldown e il dispatch ai canali.
 *
 * @package OpsHealthDashboard\Interfaces
 */

namespace OpsHealthDashboard\Interfaces;

/**
 * Interface AlertManagerInterface
 *
 * Definisce il contratto per la gestione centralizzata degli alert.
 */
interface AlertManagerInterface {

	/**
	 * Aggiunge un canale di notifica
	 *
	 * @param AlertChannelInterface $channel Canale da aggiungere.
	 * @return void
	 */
	public function add_channel( AlertChannelInterface $channel ): void;

	/**
	 * Processa i risultati dei check e invia alert sui cambiamenti di stato
	 *
	 * Confronta i risultati correnti con i precedenti, rileva transizioni di stato,
	 * verifica i cooldown e invia notifiche ai canali abilitati.
	 *
	 * @param array $current_results  Risultati correnti da run_all().
	 * @param array $previous_results Risultati precedenti (può essere vuoto al primo avvio).
	 * @return array Array dei risultati di invio alert.
	 */
	public function process( array $current_results, array $previous_results ): array;
}
