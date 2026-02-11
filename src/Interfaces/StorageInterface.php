<?php
/**
 * Storage Interface
 *
 * Contratto per servizi di storage. Implementazioni possono usare WordPress
 * Options API, transients, custom tables, o altri meccanismi.
 *
 * @package OpsHealthDashboard\Interfaces
 */

namespace OpsHealthDashboard\Interfaces;

if ( ! defined( 'ABSPATH' ) ) {
	// phpcs:ignore Squiz.Commenting.InlineComment.InvalidEndChar
	// @codeCoverageIgnoreStart
	exit;
	// phpcs:ignore Squiz.Commenting.InlineComment.InvalidEndChar
	// @codeCoverageIgnoreEnd
}

/**
 * Interface StorageInterface
 *
 * Definisce i metodi per operazioni di storage (get, set, delete, has).
 */
interface StorageInterface {

	/**
	 * Recupera un valore dallo storage
	 *
	 * @param string $key     Chiave da recuperare.
	 * @param mixed  $default Valore di default se la chiave non esiste.
	 * @return mixed Valore recuperato o default.
	 */
	public function get( string $key, $default = null );

	/**
	 * Salva un valore nello storage
	 *
	 * @param string $key   Chiave da salvare.
	 * @param mixed  $value Valore da salvare.
	 * @return bool True se il salvataggio ha avuto successo.
	 */
	public function set( string $key, $value ): bool;

	/**
	 * Cancella un valore dallo storage
	 *
	 * @param string $key Chiave da cancellare.
	 * @return bool True se la cancellazione ha avuto successo.
	 */
	public function delete( string $key ): bool;

	/**
	 * Verifica se una chiave esiste nello storage
	 *
	 * @param string $key Chiave da verificare.
	 * @return bool True se la chiave esiste.
	 */
	public function has( string $key ): bool;
}
