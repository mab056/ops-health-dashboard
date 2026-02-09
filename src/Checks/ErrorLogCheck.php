<?php
/**
 * Error Log Check
 *
 * Verifica il log degli errori PHP, aggrega per severità e
 * redige i dati sensibili prima di restituire i risultati.
 *
 * @package OpsHealthDashboard\Checks
 */

namespace OpsHealthDashboard\Checks;

use OpsHealthDashboard\Interfaces\CheckInterface;
use OpsHealthDashboard\Interfaces\RedactionInterface;

/**
 * Class ErrorLogCheck
 *
 * Check per il riepilogo sicuro del log degli errori.
 * NO singleton, NO static methods, NO final.
 */
class ErrorLogCheck implements CheckInterface {

	/**
	 * Servizio di redazione dati sensibili
	 *
	 * @var RedactionInterface
	 */
	private $redaction;

	/**
	 * Numero massimo di righe da leggere dal fondo del file
	 *
	 * @var int
	 */
	private $max_lines;

	/**
	 * Numero massimo di byte da leggere dal fondo del file
	 *
	 * @var int
	 */
	private $max_bytes;

	/**
	 * Numero massimo di campioni da includere nei risultati
	 *
	 * @var int
	 */
	private $max_samples;

	/**
	 * Constructor
	 *
	 * @param RedactionInterface $redaction   Servizio di redazione.
	 * @param int                $max_lines   Massimo righe da leggere (default 100).
	 * @param int                $max_bytes   Massimo byte da leggere (default 512KB).
	 * @param int                $max_samples Massimo campioni da includere (default 5).
	 */
	public function __construct(
		RedactionInterface $redaction,
		int $max_lines = 100,
		int $max_bytes = 524288,
		int $max_samples = 5
	) {
		$this->redaction   = $redaction;
		$this->max_lines   = $max_lines;
		$this->max_bytes   = $max_bytes;
		$this->max_samples = $max_samples;
	}

	/**
	 * Esegue il check del log degli errori
	 *
	 * @return array Risultati del check.
	 */
	public function run(): array {
		$start = microtime( true );

		// 1. Risolvi il path del log.
		$log_path = $this->resolve_log_path();

		if ( '' === $log_path ) {
			return $this->build_result(
				'warning',
				__( 'Error log file not found or not configured', 'ops-health-dashboard' ),
				[ 'log_path_exists' => false ],
				microtime( true ) - $start
			);
		}

		// 2. Valida il file.
		$validation = $this->validate_log_file( $log_path );

		if ( ! $validation['valid'] ) {
			return $this->build_result(
				$validation['status'],
				$validation['message'],
				[ 'log_path_exists' => true ],
				microtime( true ) - $start
			);
		}

		// 3. Leggi le ultime righe.
		$lines = $this->read_tail( $log_path );

		if ( empty( $lines ) ) {
			return $this->build_result(
				'ok',
				__( 'Error log is empty', 'ops-health-dashboard' ),
				[ 'log_path_exists' => true ],
				microtime( true ) - $start
			);
		}

		// 4. Analizza e aggrega.
		$parsed = $this->parse_lines( $lines );

		// 5. Determina lo stato.
		$status  = $this->determine_status( $parsed['counts'] );
		$message = $this->build_message( $status, $parsed['counts'], count( $lines ) );

		// 6. Colleziona campioni redatti.
		$samples = $this->collect_samples( $lines, $parsed['severity_lines'] );

		// 7. File size formattato.
		$file_size = $this->get_file_size( $log_path );

		return $this->build_result(
			$status,
			$message,
			[
				'log_path_exists' => true,
				'file_size'       => $file_size,
				'lines_analyzed'  => count( $lines ),
				'counts'          => $parsed['counts'],
				'recent_samples'  => $samples,
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
		return 'error_log';
	}

	/**
	 * Ottiene il nome del check
	 *
	 * @return string Nome del check.
	 */
	public function get_name(): string {
		return __( 'Error Log Summary', 'ops-health-dashboard' );
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
	 * Risolve il path del file di log degli errori
	 *
	 * Controlla WP_DEBUG_LOG (stringa = path custom, true = fallback a ini_get).
	 * Se non configurato, usa ini_get('error_log').
	 *
	 * @return string Path del file di log, stringa vuota se non trovato.
	 */
	protected function resolve_log_path(): string {
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound
		if ( defined( 'WP_DEBUG_LOG' ) && is_string( WP_DEBUG_LOG ) && '' !== WP_DEBUG_LOG ) {
			return WP_DEBUG_LOG;
		}

		$ini_path = ini_get( 'error_log' );
		if ( is_string( $ini_path ) && '' !== $ini_path ) {
			return $ini_path;
		}

		return '';
	}

	/**
	 * Valida il file di log
	 *
	 * Verifica esistenza, leggibilità e assenza di symlink.
	 *
	 * @param string $path Path del file da validare.
	 * @return array {
	 *     Risultato della validazione.
	 *
	 *     @type bool   $valid   True se il file è valido.
	 *     @type string $status  Stato in caso di errore.
	 *     @type string $message Messaggio in caso di errore.
	 * }
	 */
	protected function validate_log_file( string $path ): array {
		if ( is_link( $path ) ) {
			return [
				'valid'   => false,
				'status'  => 'warning',
				'message' => __( 'Error log path is a symbolic link (skipped for security)', 'ops-health-dashboard' ),
			];
		}

		if ( ! is_file( $path ) ) {
			return [
				'valid'   => false,
				'status'  => 'warning',
				'message' => __( 'Error log file not found or not configured', 'ops-health-dashboard' ),
			];
		}

		if ( ! is_readable( $path ) ) {
			return [
				'valid'   => false,
				'status'  => 'warning',
				'message' => __( 'Error log file not readable (check permissions)', 'ops-health-dashboard' ),
			];
		}

		return [ 'valid' => true ];
	}

	/**
	 * Legge le ultime righe del file
	 *
	 * Usa seek al fondo del file per leggere solo gli ultimi max_bytes.
	 *
	 * @param string $path Path del file.
	 * @return array Array di righe lette.
	 */
	protected function read_tail( string $path ): array {
		$file_size = filesize( $path );

		if ( false === $file_size || 0 === $file_size ) {
			return [];
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$handle = fopen( $path, 'r' );

		if ( false === $handle ) {
			return [];
		}

		// Seek alla posizione di partenza.
		$offset = max( 0, $file_size - $this->max_bytes );
		if ( $offset > 0 ) {
			fseek( $handle, $offset );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread
		$content = fread( $handle, $this->max_bytes );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		fclose( $handle );

		if ( false === $content || '' === $content ) {
			return [];
		}

		$lines = explode( "\n", $content );

		// Scarta la prima riga se abbiamo seekato (potrebbe essere parziale).
		if ( $offset > 0 && count( $lines ) > 1 ) {
			array_shift( $lines );
		}

		// Rimuovi righe vuote dal fondo.
		while ( ! empty( $lines ) && '' === trim( end( $lines ) ) ) {
			array_pop( $lines );
		}

		// Prendi solo le ultime max_lines righe.
		if ( count( $lines ) > $this->max_lines ) {
			$lines = array_slice( $lines, -$this->max_lines );
		}

		return array_values( $lines );
	}

	/**
	 * Analizza le righe e classifica per severità
	 *
	 * @param array $lines Array di righe del log.
	 * @return array {
	 *     Risultati dell'analisi.
	 *
	 *     @type array $counts         Conteggi per severità.
	 *     @type array $severity_lines Righe indicizzate per severità.
	 * }
	 */
	private function parse_lines( array $lines ): array {
		$counts = [
			'fatal'      => 0,
			'parse'      => 0,
			'warning'    => 0,
			'notice'     => 0,
			'deprecated' => 0,
			'strict'     => 0,
			'other'      => 0,
		];

		$severity_lines = [
			'critical' => [],
			'warning'  => [],
		];

		foreach ( $lines as $line ) {
			$severity = $this->classify_line( $line );
			if ( isset( $counts[ $severity ] ) ) {
				++$counts[ $severity ];
			}

			// Raccogli righe per campioni.
			if ( 'fatal' === $severity || 'parse' === $severity ) {
				$severity_lines['critical'][] = $line;
			} elseif ( 'warning' === $severity || 'deprecated' === $severity || 'strict' === $severity ) {
				$severity_lines['warning'][] = $line;
			}
		}

		return [
			'counts'         => $counts,
			'severity_lines' => $severity_lines,
		];
	}

	/**
	 * Classifica una singola riga per severità
	 *
	 * @param string $line Riga del log.
	 * @return string Severità: fatal, parse, warning, notice, deprecated, strict, other.
	 */
	private function classify_line( string $line ): string {
		if ( preg_match( '/PHP Fatal error/i', $line ) || preg_match( '/PHP Core error/i', $line ) ) {
			return 'fatal';
		}

		if ( preg_match( '/PHP Parse error/i', $line ) ) {
			return 'parse';
		}

		if ( preg_match( '/PHP Warning/i', $line ) ) {
			return 'warning';
		}

		if ( preg_match( '/PHP Notice/i', $line ) ) {
			return 'notice';
		}

		if ( preg_match( '/PHP Deprecated/i', $line ) ) {
			return 'deprecated';
		}

		if ( preg_match( '/PHP Strict Standards/i', $line ) || preg_match( '/PHP Strict standards/i', $line ) ) {
			return 'strict';
		}

		return 'other';
	}

	/**
	 * Determina lo stato in base ai conteggi
	 *
	 * @param array $counts Conteggi per severità.
	 * @return string Stato: ok, warning, critical.
	 */
	private function determine_status( array $counts ): string {
		if ( $counts['fatal'] > 0 || $counts['parse'] > 0 ) {
			return 'critical';
		}

		if ( $counts['warning'] > 0 || $counts['deprecated'] > 0 || $counts['strict'] > 0 ) {
			return 'warning';
		}

		return 'ok';
	}

	/**
	 * Costruisce il messaggio di stato
	 *
	 * @param string $status      Stato determinato.
	 * @param array  $counts      Conteggi per severità.
	 * @param int    $total_lines Totale righe analizzate.
	 * @return string Messaggio localizzato.
	 */
	private function build_message( string $status, array $counts, int $total_lines ): string {
		if ( 'critical' === $status ) {
			return sprintf(
				/* translators: 1: fatal error count, 2: warning count, 3: total lines */
				__( 'Error log: %1$d fatal errors, %2$d warnings in last %3$d lines', 'ops-health-dashboard' ),
				$counts['fatal'] + $counts['parse'],
				$counts['warning'],
				$total_lines
			);
		}

		if ( 'warning' === $status ) {
			return sprintf(
				/* translators: 1: warning count, 2: notice count, 3: total lines */
				__( 'Error log: %1$d warnings, %2$d notices in last %3$d lines', 'ops-health-dashboard' ),
				$counts['warning'] + $counts['deprecated'] + $counts['strict'],
				$counts['notice'],
				$total_lines
			);
		}

		return sprintf(
			/* translators: %d: total lines analyzed */
			__( 'Error log: no significant errors in last %d lines', 'ops-health-dashboard' ),
			$total_lines
		);
	}

	/**
	 * Colleziona campioni redatti per i risultati
	 *
	 * Prende le righe critiche/warning più recenti e le redige.
	 *
	 * @param array $lines          Tutte le righe.
	 * @param array $severity_lines Righe raggruppate per severità.
	 * @return array Campioni redatti (massimo max_samples).
	 */
	private function collect_samples( array $lines, array $severity_lines ): array {
		// Priorità: critical prima, poi warning.
		$samples = array_merge(
			array_slice( $severity_lines['critical'], -$this->max_samples ),
			array_slice( $severity_lines['warning'], -$this->max_samples )
		);

		// Limita a max_samples.
		$samples = array_slice( $samples, -$this->max_samples );

		// Redigi tutti i campioni.
		return $this->redaction->redact_lines( $samples );
	}

	/**
	 * Ottiene la dimensione formattata del file
	 *
	 * @param string $path Path del file.
	 * @return string Dimensione formattata.
	 */
	protected function get_file_size( string $path ): string {
		$size = filesize( $path );

		if ( false === $size ) {
			return __( 'Unknown', 'ops-health-dashboard' );
		}

		return size_format( $size );
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
