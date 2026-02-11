<?php
/**
 * HTTP Client Service
 *
 * Client HTTP sicuro con protezione anti-SSRF per tutte le richieste in uscita.
 * Valida schema, risoluzione DNS, range IP e porte prima di ogni richiesta.
 *
 * @package OpsHealthDashboard\Services
 */

namespace OpsHealthDashboard\Services;

if ( ! defined( 'ABSPATH' ) ) {
	// phpcs:ignore Squiz.Commenting.InlineComment.InvalidEndChar
	// @codeCoverageIgnoreStart
	exit;
	// phpcs:ignore Squiz.Commenting.InlineComment.InvalidEndChar
	// @codeCoverageIgnoreEnd
}

use OpsHealthDashboard\Interfaces\HttpClientInterface;
use OpsHealthDashboard\Interfaces\RedactionInterface;

/**
 * Class HttpClient
 *
 * Implementazione del client HTTP con protezione anti-SSRF.
 */
class HttpClient implements HttpClientInterface {

	/**
	 * Timeout per le richieste HTTP in secondi
	 *
	 * @var int
	 */
	const TIMEOUT = 5;

	/**
	 * Schemi URL consentiti
	 *
	 * @var array
	 */
	const ALLOWED_SCHEMES = [ 'http', 'https' ];

	/**
	 * Porte consentite
	 *
	 * @var array
	 */
	const ALLOWED_PORTS = [ 80, 443 ];

	/**
	 * Servizio di redazione per messaggi di errore
	 *
	 * @var RedactionInterface
	 */
	private $redaction;

	/**
	 * Constructor
	 *
	 * @param RedactionInterface $redaction Servizio di redazione dati sensibili.
	 */
	public function __construct( RedactionInterface $redaction ) {
		$this->redaction = $redaction;
	}

	/**
	 * Valida se un URL è sicuro per richieste in uscita (anti-SSRF)
	 *
	 * @param string $url URL da validare.
	 * @return bool True se l'URL è sicuro.
	 */
	public function is_safe_url( string $url ): bool {
		return null !== $this->validate_and_resolve( $url );
	}

	/**
	 * Invia una richiesta HTTP POST
	 *
	 * Usa DNS pinning via CURLOPT_RESOLVE per prevenire attacchi
	 * TOCTOU/DNS rebinding tra validazione e richiesta effettiva.
	 *
	 * @param string $url     URL di destinazione.
	 * @param array  $body    Dati del corpo della richiesta.
	 * @param array  $headers Header HTTP aggiuntivi.
	 * @return array Risultato con chiavi success, code, body, error.
	 */
	public function post( string $url, array $body, array $headers = [] ): array {
		$resolved = $this->validate_and_resolve( $url );

		if ( null === $resolved ) {
			return [
				'success' => false,
				'code'    => 0,
				'body'    => '',
				'error'   => __( 'Unsafe URL detected', 'ops-health-dashboard' ),
			];
		}

		// Pin DNS to prevent TOCTOU/DNS rebinding.
		$pin = $this->create_dns_pin(
			$resolved['host'],
			$resolved['ip'],
			$resolved['port']
		);
		add_action( 'http_api_curl', $pin );

		$args = [
			// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- wp_json_encode non disponibile in unit test.
			'body'        => json_encode( $body ),
			'headers'     => array_merge(
				[ 'Content-Type' => 'application/json' ],
				$headers
			),
			'timeout'     => self::TIMEOUT,
			'redirection' => 0,
			'blocking'    => true,
		];

		$response = wp_remote_post( $url, $args );

		remove_action( 'http_api_curl', $pin );

		if ( is_wp_error( $response ) ) {
			return [
				'success' => false,
				'code'    => 0,
				'body'    => '',
				'error'   => sprintf(
					/* translators: %s: redacted error message */
					__( 'HTTP request failed: %s', 'ops-health-dashboard' ),
					$this->redaction->redact( $response->get_error_message() )
				),
			];
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		if ( $code < 200 || $code >= 300 ) {
			return [
				'success' => false,
				'code'    => $code,
				'body'    => $body,
				'error'   => sprintf(
					/* translators: %d: HTTP status code */
					__( 'HTTP request returned status %d', 'ops-health-dashboard' ),
					$code
				),
			];
		}

		return [
			'success' => true,
			'code'    => $code,
			'body'    => $body,
			'error'   => null,
		];
	}

	/**
	 * Valida URL e risolve DNS, ritorna dati di connessione
	 *
	 * Esegue tutte le verifiche anti-SSRF (schema, host, porta, DNS, IP privato)
	 * e ritorna i dati necessari per il DNS pinning.
	 *
	 * @param string $url URL da validare.
	 * @return array|null Array con host, ip, port se valido, null altrimenti.
	 */
	private function validate_and_resolve( string $url ) {
		if ( '' === $url ) {
			return null;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- Usa parse_url nativa per evitare dipendenza WP.
		$parts = parse_url( $url );

		if ( false === $parts || ! is_array( $parts ) ) {
			return null;
		}

		// Verifica schema.
		$scheme = isset( $parts['scheme'] ) ? strtolower( $parts['scheme'] ) : '';
		if ( ! in_array( $scheme, self::ALLOWED_SCHEMES, true ) ) {
			return null;
		}

		// Verifica host presente.
		if ( ! isset( $parts['host'] ) || '' === $parts['host'] ) {
			return null;
		}

		// Determina porta (default basato su schema).
		$port = isset( $parts['port'] )
			? (int) $parts['port']
			: ( 'https' === $scheme ? 443 : 80 );

		if ( ! in_array( $port, self::ALLOWED_PORTS, true ) ) {
			return null;
		}

		// Risolvi hostname e verifica IP.
		$ip = $this->resolve_host( $parts['host'] );

		// gethostbyname ritorna l'hostname se non riesce a risolvere.
		if ( $ip === $parts['host'] && ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			return null;
		}

		// Verifica che l'IP non sia privato.
		if ( $this->is_private_ip( $ip ) ) {
			return null;
		}

		return [
			'host' => $parts['host'],
			'ip'   => $ip,
			'port' => $port,
		];
	}

	/**
	 * Crea una closure per il DNS pinning via CURLOPT_RESOLVE
	 *
	 * Forza cURL a usare l'IP già validato, prevenendo DNS rebinding.
	 * Estratto in metodo protetto per testabilità (Reflection pattern).
	 *
	 * @param string $host Hostname da pinnare.
	 * @param string $ip   IP risolto e validato.
	 * @param int    $port Porta di destinazione.
	 * @return \Closure Closure da passare a add_action('http_api_curl').
	 */
	protected function create_dns_pin( string $host, string $ip, int $port ): \Closure {
		return function ( $handle ) use ( $host, $ip, $port ) {
			// phpcs:ignore Squiz.Commenting.InlineComment.InvalidEndChar
			// @codeCoverageIgnoreStart
			// phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_setopt -- Necessario per DNS pinning anti-TOCTOU.
			curl_setopt( $handle, CURLOPT_RESOLVE, [ "{$host}:{$port}:{$ip}" ] );
			// @codeCoverageIgnoreEnd
		};
	}

	/**
	 * Risolve un hostname in un indirizzo IP
	 *
	 * Estratto in metodo protetto per testabilità (partial mock pattern).
	 *
	 * @param string $hostname Hostname da risolvere.
	 * @return string Indirizzo IP risolto, o l'hostname se la risoluzione fallisce.
	 */
	protected function resolve_host( string $hostname ): string {
		return gethostbyname( $hostname );
	}

	/**
	 * Verifica se un IP è in un range privato/riservato
	 *
	 * Blocca: loopback (127.0.0.0/8), private (10/8, 172.16/12, 192.168/16),
	 * link-local (169.254/16), e 0.0.0.0.
	 *
	 * Nota: IPv6 e IP non validi sono trattati come non sicuri (safe-fail).
	 * gethostbyname() di PHP restituisce solo IPv4, quindi IPv6 puro viene
	 * rifiutato dal controllo FILTER_FLAG_IPV4 come misura precauzionale.
	 *
	 * @param string $ip Indirizzo IP da verificare.
	 * @return bool True se l'IP è privato/riservato o non IPv4.
	 */
	private function is_private_ip( string $ip ): bool {
		if ( ! filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
			return true; // Non-IPv4 (incluso IPv6): tratta come non sicuro (safe-fail).
		}

		if ( '0.0.0.0' === $ip ) {
			return true;
		}

		$long = ip2long( $ip );

		// Defensive: ip2long never fails after FILTER_VALIDATE_IP.
		// phpcs:ignore Squiz.Commenting.InlineComment.InvalidEndChar
		// @codeCoverageIgnoreStart
		if ( false === $long ) {
			return true;
		}
		// @codeCoverageIgnoreEnd

		// Loopback 127.0.0.0/8.
		if ( ( $long & 0xFF000000 ) === 0x7F000000 ) {
			return true;
		}

		// Private 10.0.0.0/8.
		if ( ( $long & 0xFF000000 ) === 0x0A000000 ) {
			return true;
		}

		// Private 172.16.0.0/12.
		if ( ( $long & 0xFFF00000 ) === 0xAC100000 ) {
			return true;
		}

		// Private 192.168.0.0/16.
		if ( ( $long & 0xFFFF0000 ) === 0xC0A80000 ) {
			return true;
		}

		// Link-local 169.254.0.0/16.
		if ( ( $long & 0xFFFF0000 ) === 0xA9FE0000 ) {
			return true;
		}

		return false;
	}
}
