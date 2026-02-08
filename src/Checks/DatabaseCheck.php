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

/**
 * Class DatabaseCheck
 *
 * Check per la salute del database WordPress.
 * NO singleton, NO static methods, NO final.
 */
class DatabaseCheck implements CheckInterface {

	/**
	 * Istanza wpdb iniettata
	 *
	 * @var \wpdb
	 */
	private $wpdb;

	/**
	 * Constructor
	 *
	 * @param \wpdb $wpdb Istanza wpdb per le query.
	 */
	public function __construct( \wpdb $wpdb ) {
		$this->wpdb = $wpdb;
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
			return [
				'status'   => 'critical',
				'message'  => __( 'Database connection failed', 'ops-health-dashboard' ),
				'details'  => [
					'error' => ! empty( $this->wpdb->last_error )
						? $this->wpdb->last_error
						: __( 'Unknown error', 'ops-health-dashboard' ),
				],
				'duration' => $duration,
			];
		}

		// Query performance check (warning se > 0.5s).
		$status = 'ok';
		if ( $duration > 0.5 ) {
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
		return 'Database Connection';
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
