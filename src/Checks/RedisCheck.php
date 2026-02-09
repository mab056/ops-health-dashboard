<?php
/**
 * Redis Check
 *
 * Verifica la disponibilità e le performance di Redis.
 * Graceful degradation: Redis è opzionale, tutti i fallimenti sono 'warning'.
 *
 * @package OpsHealthDashboard\Checks
 */

namespace OpsHealthDashboard\Checks;

use OpsHealthDashboard\Interfaces\CheckInterface;
use OpsHealthDashboard\Interfaces\RedactionInterface;

/**
 * Class RedisCheck
 *
 * Check per la salute di Redis con rilevamento estensione,
 * test di connessione e smoke test SET/GET/DEL.
 * NO singleton, NO static methods, NO final.
 */
class RedisCheck implements CheckInterface {

	/**
	 * Chiave usata per lo smoke test
	 *
	 * @var string
	 */
	const SMOKE_TEST_KEY = 'ops_health_smoke_test';

	/**
	 * Valore usato per lo smoke test
	 *
	 * @var string
	 */
	const SMOKE_TEST_VALUE = 'ops_health_test_value';

	/**
	 * Soglia tempo di risposta in secondi (100ms)
	 *
	 * @var float
	 */
	const SLOW_THRESHOLD = 0.1;

	/**
	 * Timeout di connessione in secondi
	 *
	 * @var float
	 */
	const CONNECT_TIMEOUT = 2.0;

	/**
	 * Servizio di redazione dati sensibili
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
	 * Esegue il check di Redis
	 *
	 * @return array Risultati del check.
	 */
	public function run(): array {
		$start = microtime( true );

		// 1. Verifica estensione Redis.
		if ( ! $this->is_extension_loaded() ) {
			return $this->build_result(
				'warning',
				__( 'Redis PHP extension not installed (optional)', 'ops-health-dashboard' ),
				[],
				microtime( true ) - $start
			);
		}

		// 2. Configurazione.
		$config = $this->get_redis_config();

		// 3. Connessione.
		try {
			$redis = $this->create_redis_instance();
			$redis->connect( $config['host'], $config['port'], self::CONNECT_TIMEOUT );
		} catch ( \Exception $e ) {
			return $this->build_result(
				'warning',
				__( 'Redis connection failed', 'ops-health-dashboard' ),
				[ 'error' => $this->redaction->redact( $e->getMessage() ) ],
				microtime( true ) - $start
			);
		}

		// 4. Autenticazione (se configurata).
		if ( '' !== $config['password'] ) {
			try {
				$redis->auth( $config['password'] );
			} catch ( \Exception $e ) {
				$this->close_connection( $redis );
				return $this->build_result(
					'warning',
					__( 'Redis authentication failed', 'ops-health-dashboard' ),
					[ 'error' => $this->redaction->redact( $e->getMessage() ) ],
					microtime( true ) - $start
				);
			}
		}

		// 5. Selezione database (se diverso da 0).
		if ( 0 !== $config['database'] ) {
			try {
				$redis->select( $config['database'] );
			} catch ( \Exception $e ) {
				$this->close_connection( $redis );
				return $this->build_result(
					'warning',
					__( 'Redis database selection failed', 'ops-health-dashboard' ),
					[ 'error' => $this->redaction->redact( $e->getMessage() ) ],
					microtime( true ) - $start
				);
			}
		}

		// 6. Smoke test SET/GET/DEL (chiave unica per evitare race condition).
		$smoke_key   = self::SMOKE_TEST_KEY . '_' . uniqid( '', true );
		$smoke_start = microtime( true );

		try {
			$set_result = $redis->set( $smoke_key, self::SMOKE_TEST_VALUE );

			if ( false === $set_result ) {
				$this->cleanup_and_close( $redis, $smoke_key );
				return $this->build_result(
					'warning',
					__( 'Redis smoke test failed (SET returned false)', 'ops-health-dashboard' ),
					[],
					microtime( true ) - $start
				);
			}

			$get_result = $redis->get( $smoke_key );

			if ( self::SMOKE_TEST_VALUE !== $get_result ) {
				$this->cleanup_and_close( $redis, $smoke_key );
				return $this->build_result(
					'warning',
					__( 'Redis smoke test failed (GET value mismatch)', 'ops-health-dashboard' ),
					[],
					microtime( true ) - $start
				);
			}

			$redis->del( $smoke_key );
		} catch ( \Exception $e ) {
			$this->cleanup_and_close( $redis, $smoke_key );
			return $this->build_result(
				'warning',
				__( 'Redis smoke test failed', 'ops-health-dashboard' ),
				[ 'error' => $this->redaction->redact( $e->getMessage() ) ],
				microtime( true ) - $start
			);
		}

		$smoke_duration = microtime( true ) - $smoke_start;

		$this->close_connection( $redis );

		// 7. Valuta tempo di risposta.
		$response_time_ms = round( $smoke_duration * 1000, 2 ) . 'ms';
		$redacted_host    = $this->redaction->redact( $config['host'] );

		if ( $smoke_duration > self::SLOW_THRESHOLD ) {
			return $this->build_result(
				'warning',
				__( 'Redis response slow', 'ops-health-dashboard' ),
				[
					'response_time' => $response_time_ms,
					'host'          => $redacted_host,
				],
				microtime( true ) - $start
			);
		}

		// 8. Tutto ok.
		return $this->build_result(
			'ok',
			__( 'Redis connection healthy', 'ops-health-dashboard' ),
			[
				'response_time' => $response_time_ms,
				'host'          => $redacted_host,
			],
			microtime( true ) - $start
		);
	}

	/**
	 * Ottiene l'ID del check
	 *
	 * @return string ID del check.
	 */
	public function get_id(): string {
		return 'redis';
	}

	/**
	 * Ottiene il nome del check
	 *
	 * @return string Nome del check.
	 */
	public function get_name(): string {
		return __( 'Redis Cache', 'ops-health-dashboard' );
	}

	/**
	 * Verifica se il check è abilitato
	 *
	 * @return bool True se abilitato.
	 */
	public function is_enabled(): bool {
		return true;
	}

	/**
	 * Verifica se l'estensione Redis PHP è caricata
	 *
	 * @return bool True se l'estensione è disponibile.
	 */
	protected function is_extension_loaded(): bool {
		return extension_loaded( 'redis' );
	}

	/**
	 * Crea una nuova istanza Redis
	 *
	 * @return \Redis Istanza Redis.
	 */
	protected function create_redis_instance(): \Redis {
		return new \Redis();
	}

	/**
	 * Ottiene la configurazione Redis dalle costanti WordPress
	 *
	 * Legge WP_REDIS_HOST, WP_REDIS_PORT, WP_REDIS_PASSWORD, WP_REDIS_DATABASE.
	 *
	 * @return array {
	 *     Configurazione Redis.
	 *
	 *     @type string $host     Host Redis (default 127.0.0.1).
	 *     @type int    $port     Porta Redis (default 6379).
	 *     @type string $password Password Redis (default '').
	 *     @type int    $database Database Redis (default 0).
	 * }
	 */
	protected function get_redis_config(): array {
		return [
			'host'     => defined( 'WP_REDIS_HOST' ) ? WP_REDIS_HOST : '127.0.0.1',
			'port'     => defined( 'WP_REDIS_PORT' ) ? (int) WP_REDIS_PORT : 6379,
			'password' => defined( 'WP_REDIS_PASSWORD' ) ? WP_REDIS_PASSWORD : '',
			'database' => defined( 'WP_REDIS_DATABASE' ) ? (int) WP_REDIS_DATABASE : 0,
		];
	}

	/**
	 * Chiude la connessione Redis in modo sicuro
	 *
	 * @param \Redis $redis Istanza Redis.
	 */
	private function close_connection( $redis ): void {
		try {
			$redis->close();
			// phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
		} catch ( \Exception $e ) {
			// Ignora errori di chiusura.
		}
	}

	/**
	 * Cleanup dello smoke test key e chiusura connessione
	 *
	 * @param \Redis $redis     Istanza Redis.
	 * @param string $smoke_key Chiave smoke test da cancellare.
	 */
	private function cleanup_and_close( $redis, string $smoke_key ): void {
		try {
			$redis->del( $smoke_key );
			// phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
		} catch ( \Exception $e ) {
			// Ignora errori di cleanup.
		}
		$this->close_connection( $redis );
	}

	/**
	 * Costruisce l'array di risultato standard
	 *
	 * @param string $status   Stato del check.
	 * @param string $message  Messaggio descrittivo.
	 * @param array  $details  Dettagli aggiuntivi.
	 * @param float  $duration Durata dell'esecuzione.
	 * @return array Risultato formattato.
	 */
	private function build_result( string $status, string $message, array $details, float $duration ): array {
		return [
			'status'   => $status,
			'message'  => $message,
			'details'  => $details,
			'duration' => $duration,
		];
	}
}
