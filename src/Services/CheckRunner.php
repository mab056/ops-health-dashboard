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
use OpsHealthDashboard\Interfaces\CheckRunnerInterface;
use OpsHealthDashboard\Interfaces\RedactionInterface;
use OpsHealthDashboard\Interfaces\StorageInterface;

/**
 * Class CheckRunner
 *
 * Servizio per eseguire e gestire i check di salute.
 */
class CheckRunner implements CheckRunnerInterface {

	/**
	 * Storage per salvare i risultati
	 *
	 * @var StorageInterface
	 */
	private $storage;

	/**
	 * Servizio di redazione per sanitizzare messaggi di eccezione
	 *
	 * @var RedactionInterface
	 */
	private $redaction;

	/**
	 * Array di check registrati
	 *
	 * @var CheckInterface[]
	 */
	private $checks = [];

	/**
	 * Constructor
	 *
	 * @param StorageInterface   $storage   Storage per i risultati.
	 * @param RedactionInterface $redaction Servizio di redazione dati sensibili.
	 */
	public function __construct( StorageInterface $storage, RedactionInterface $redaction ) {
		$this->storage   = $storage;
		$this->redaction = $redaction;
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
	 * @return array Array associativo con i risultati per ogni check (chiave = ID check).
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
				$result               = $check->run();
				$result['name']       = $check->get_name();
				$results[ $check_id ] = $result;
			} catch ( \Throwable $e ) {
				$results[ $check_id ] = [
					'status'   => 'critical',
					'message'  => 'Check exception: ' . $this->redaction->redact( $e->getMessage() ),
					'name'     => $check->get_name(),
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
