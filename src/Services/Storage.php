<?php
/**
 * Storage Service
 *
 * Implementazione di StorageInterface che usa WordPress Options API.
 * Wrapper semplice per get_option(), update_option(), delete_option().
 *
 * @package OpsHealthDashboard\Services
 */

namespace OpsHealthDashboard\Services;

use OpsHealthDashboard\Interfaces\StorageInterface;

/**
 * Class Storage
 *
 * Servizio per storage persistente usando WordPress Options API.

 */
class Storage implements StorageInterface {

	/**
	 * Prefisso per le chiavi options
	 *
	 * @var string
	 */
	private $prefix = 'ops_health_';

	/**
	 * Recupera un valore dallo storage
	 *
	 * @param string $key     Chiave da recuperare (senza prefisso).
	 * @param mixed  $default Valore di default se la chiave non esiste.
	 * @return mixed Valore recuperato o default.
	 */
	public function get( string $key, $default = null ) {
		$prefixed_key = $this->get_prefixed_key( $key );
		return get_option( $prefixed_key, $default );
	}

	/**
	 * Salva un valore nello storage
	 *
	 * @param string $key   Chiave da salvare (senza prefisso).
	 * @param mixed  $value Valore da salvare.
	 * @return bool True se il salvataggio ha avuto successo.
	 */
	public function set( string $key, $value ): bool {
		$prefixed_key = $this->get_prefixed_key( $key );
		return update_option( $prefixed_key, $value );
	}

	/**
	 * Cancella un valore dallo storage
	 *
	 * @param string $key Chiave da cancellare (senza prefisso).
	 * @return bool True se la cancellazione ha avuto successo.
	 */
	public function delete( string $key ): bool {
		$prefixed_key = $this->get_prefixed_key( $key );
		return delete_option( $prefixed_key );
	}

	/**
	 * Verifica se una chiave esiste nello storage
	 *
	 * @param string $key Chiave da verificare (senza prefisso).
	 * @return bool True se la chiave esiste.
	 */
	public function has( string $key ): bool {
		$prefixed_key = $this->get_prefixed_key( $key );
		$sentinel     = new \stdClass();
		$value        = get_option( $prefixed_key, $sentinel );
		return $sentinel !== $value;
	}

	/**
	 * Ottiene la chiave con prefisso
	 *
	 * @param string $key Chiave senza prefisso.
	 * @return string Chiave con prefisso.
	 */
	private function get_prefixed_key( string $key ): string {
		return $this->prefix . $key;
	}
}
