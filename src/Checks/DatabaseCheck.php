<?php
/**
 * Database Check
 *
 * Verifica la connessione e le performance del database WordPress.
 * Esegue una query semplice (SELECT 1) e misura il tempo di risposta.
 *
 * @package OpsHealthDashboard\Checks
 */

namespace OpsHealthDashboard\Checks;

use OpsHealthDashboard\Interfaces\CheckInterface;
use OpsHealthDashboard\Interfaces\RedactionInterface;

/**
 * Class DatabaseCheck
 *
 * Check per la salute del database WordPress.
 */
class DatabaseCheck implements CheckInterface {

	/**
	 * Soglia per query lenta in secondi
	 *
	 * @var float
	 */
	const SLOW_QUERY_THRESHOLD = 0.5;

	/**
	 * Istanza wpdb iniettata
	 *
	 * @var \wpdb
	 */
	private $wpdb;

	/**
	 * Servizio di redazione dati sensibili
	 *
	 * @var RedactionInterface
	 */
	private $redaction;

	/**
	 * Constructor
	 *
	 * @param \wpdb              $wpdb      Istanza wpdb per le query.
	 * @param RedactionInterface $redaction Servizio di redazione dati sensibili.
	 */
	public function __construct( \wpdb $wpdb, RedactionInterface $redaction ) {
		$this->wpdb      = $wpdb;
		$this->redaction = $redaction;
	}

	/**
	 * Esegue il check del database
	 *
	 * @return array Risultati del check.
	 */
	public function run(): array {
		$start = microtime( true );

		// Esegue una query semplice per testare la connessione.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $this->wpdb->query( 'SELECT 1' );

		$duration = microtime( true ) - $start;

		// Verifica il risultato.
		if ( false === $result || ! empty( $this->wpdb->last_error ) ) {
			$error_msg = ! empty( $this->wpdb->last_error )
				? $this->redaction->redact( $this->wpdb->last_error )
				: __( 'Unknown error', 'ops-health-dashboard' );
			return [
				'status'   => 'critical',
				'message'  => __( 'Database connection failed', 'ops-health-dashboard' ),
				'details'  => [
					'error' => $error_msg,
				],
				'duration' => $duration,
			];
		}

		// Query performance check.
		$status = 'ok';
		if ( $duration > self::SLOW_QUERY_THRESHOLD ) {
			$status = 'warning';
		}

		return [
			'status'   => $status,
			'message'  => __( 'Database connection healthy', 'ops-health-dashboard' ),
			'details'  => [
				'query_time' => round( $duration * 1000, 2 ) . 'ms',
			],
			'duration' => $duration,
		];
	}

	/**
	 * Ottiene l'ID del check
	 *
	 * @return string ID del check.
	 */
	public function get_id(): string {
		return 'database';
	}

	/**
	 * Ottiene il nome del check
	 *
	 * @return string Nome del check.
	 */
	public function get_name(): string {
		return __( 'Database Connection', 'ops-health-dashboard' );
	}

	/**
	 * Verifica se il check è abilitato
	 *
	 * @return bool True se abilitato.
	 */
	public function is_enabled(): bool {
		return true;
	}
}
