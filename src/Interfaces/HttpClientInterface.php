<?php
/**
 * HTTP Client Interface
 *
 * Contratto per un client HTTP sicuro con protezione anti-SSRF.
 * Tutte le richieste HTTP in uscita DEVONO passare attraverso questa interfaccia.
 *
 * @package OpsHealthDashboard\Interfaces
 */

namespace OpsHealthDashboard\Interfaces;

/**
 * Interface HttpClientInterface
 *
 * Definisce il contratto per richieste HTTP sicure con validazione anti-SSRF.
 */
interface HttpClientInterface {

	/**
	 * Valida se un URL è sicuro per richieste in uscita (anti-SSRF)
	 *
	 * Verifica schema, risoluzione DNS, range IP privati e porte consentite.
	 *
	 * @param string $url URL da validare.
	 * @return bool True se l'URL è sicuro.
	 */
	public function is_safe_url( string $url ): bool;

	/**
	 * Invia una richiesta HTTP POST
	 *
	 * L'URL DEVE passare la validazione is_safe_url() prima dell'invio.
	 *
	 * @param string $url     URL di destinazione.
	 * @param array  $body    Dati del corpo della richiesta.
	 * @param array  $headers Header HTTP aggiuntivi.
	 * @return array {
	 *     @type bool        $success    Se la richiesta è andata a buon fine.
	 *     @type int         $code       Codice di risposta HTTP.
	 *     @type string      $body       Corpo della risposta.
	 *     @type string|null $error      Messaggio di errore se fallita.
	 * }
	 */
	public function post( string $url, array $body, array $headers = [] ): array;
}
