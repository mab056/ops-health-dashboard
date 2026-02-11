<?php
/**
 * Disk Space Check
 *
 * Verifica lo spazio disco disponibile con soglie configurabili.
 * Graceful degradation: se disk_free_space/disk_total_space sono disabilitati.
 *
 * @package OpsHealthDashboard\Checks
 */

namespace OpsHealthDashboard\Checks;

if ( ! defined( 'ABSPATH' ) ) {
	// phpcs:ignore Squiz.Commenting.InlineComment.InvalidEndChar
	// @codeCoverageIgnoreStart
	exit;
	// phpcs:ignore Squiz.Commenting.InlineComment.InvalidEndChar
	// @codeCoverageIgnoreEnd
}

use OpsHealthDashboard\Interfaces\CheckInterface;
use OpsHealthDashboard\Interfaces\RedactionInterface;

/**
 * Class DiskCheck
 *
 * Check per lo spazio disco con soglie percentuali.
 */
class DiskCheck implements CheckInterface {

	/**
	 * Soglia percentuale per warning (sotto questo valore → warning)
	 *
	 * @var int
	 */
	const WARNING_THRESHOLD = 20;

	/**
	 * Soglia percentuale per critical (sotto questo valore → critical)
	 *
	 * @var int
	 */
	const CRITICAL_THRESHOLD = 10;

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
	 * Esegue il check dello spazio disco
	 *
	 * @return array Risultati del check.
	 */
	public function run(): array {
		$start = microtime( true );

		$path  = $this->get_disk_path();
		$free  = $this->get_free_space( $path );
		$total = $this->get_total_space( $path );

		if ( false === $free || false === $total || $total <= 0 ) {
			return $this->build_result(
				'warning',
				__( 'Unable to determine disk space', 'ops-health-dashboard' ),
				[ 'path' => $this->redaction->redact( $path ) ],
				microtime( true ) - $start
			);
		}

		$free_percent = ( $free / $total ) * 100;

		if ( $free_percent < self::CRITICAL_THRESHOLD ) {
			$status = 'critical';
		} elseif ( $free_percent < self::WARNING_THRESHOLD ) {
			$status = 'warning';
		} else {
			$status = 'ok';
		}

		$message = sprintf(
			/* translators: 1: free space, 2: total space, 3: free percent */
			__( '%1$s free of %2$s (%3$.1f%%)', 'ops-health-dashboard' ),
			size_format( (int) $free ),
			size_format( (int) $total ),
			$free_percent
		);

		return $this->build_result(
			$status,
			$message,
			[
				'free_bytes'   => $free,
				'total_bytes'  => $total,
				'free_percent' => round( $free_percent, 1 ),
				'path'         => $this->redaction->redact( $path ),
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
		return 'disk';
	}

	/**
	 * Ottiene il nome del check
	 *
	 * @return string Nome del check.
	 */
	public function get_name(): string {
		return __( 'Disk Space', 'ops-health-dashboard' );
	}

	/**
	 * Verifica se il check è abilitato
	 *
	 * Ritorna false se disk_free_space o disk_total_space sono
	 * disabilitati via php.ini disable_functions.
	 *
	 * @return bool True se abilitato.
	 */
	public function is_enabled(): bool {
		return function_exists( 'disk_free_space' ) && function_exists( 'disk_total_space' );
	}

	/**
	 * Ottiene il path del disco da controllare
	 *
	 * @return string Path WordPress root.
	 */
	protected function get_disk_path(): string {
		return defined( 'ABSPATH' ) ? ABSPATH : '/';
	}

	/**
	 * Ottiene lo spazio libero su disco
	 *
	 * @param string $path Path da controllare.
	 * @return float|false Spazio libero in bytes o false in caso di errore.
	 */
	protected function get_free_space( string $path ) {
		return disk_free_space( $path );
	}

	/**
	 * Ottiene lo spazio totale su disco
	 *
	 * @param string $path Path da controllare.
	 * @return float|false Spazio totale in bytes o false in caso di errore.
	 */
	protected function get_total_space( string $path ) {
		return disk_total_space( $path );
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
