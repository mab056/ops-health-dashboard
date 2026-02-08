<?php
/**
 * Check Runner Service
 *
 * Orchestratore che coordina l'esecuzione di tutti i check di salute.
 * Gestisce l'esecuzione, aggregazione e storage dei risultati.
 *
 * @package OpsHealthDashboard\Services
 */

namespace OpsHealthDashboard\Services;

use OpsHealthDashboard\Interfaces\CheckInterface;
use OpsHealthDashboard\Interfaces\StorageInterface;

/**
 * Class CheckRunner
 *
 * Servizio per eseguire e gestire i check di salute.
 */
class CheckRunner {

	/**
	 * Storage per salvare i risultati
	 *
	 * @var StorageInterface
	 */
	private $storage;

	/**
	 * Array di check registrati
	 *
	 * @var CheckInterface[]
	 */
	private $checks = [];

	/**
	 * Constructor
	 *
	 * @param StorageInterface $storage Storage per i risultati.
	 */
	public function __construct( StorageInterface $storage ) {
		$this->storage = $storage;
	}

	/**
	 * Aggiunge un check al runner
	 *
	 * @param CheckInterface $check Check da aggiungere.
	 * @return void
	 */
	public function add_check( CheckInterface $check ): void {
		$this->checks[] = $check;
	}

	/**
	 * Esegue tutti i check abilitati
	 *
	 * @return array Array associativo con i risultati per ogni check (key = check ID).
	 */
	public function run_all(): array {
		$results = [];

		foreach ( $this->checks as $check ) {
			// Salta i check disabilitati.
			if ( ! $check->is_enabled() ) {
				continue;
			}

			$check_id = $check->get_id();

			try {
				$results[ $check_id ] = $check->run();
			} catch ( \Throwable $e ) {
				$results[ $check_id ] = [
					'status'   => 'critical',
					'message'  => 'Check exception: ' . $e->getMessage(),
					'details'  => [],
					'duration' => 0,
				];
			}
		}

		// Salva i risultati nello storage.
		$this->storage->set( 'latest_results', $results );

		return $results;
	}

	/**
	 * Ottiene gli ultimi risultati dallo storage
	 *
	 * @return array Ultimi risultati dei check.
	 */
	public function get_latest_results(): array {
		$results = $this->storage->get( 'latest_results', [] );
		return is_array( $results ) ? $results : [];
	}
}
