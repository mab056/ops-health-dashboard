<?php
/**
 * Alert Manager Service
 *
 * Orchestratore centrale degli alert. Rileva cambiamenti di stato,
 * gestisce cooldown e dispatch ai canali di notifica.
 *
 * @package OpsHealthDashboard\Services
 */

namespace OpsHealthDashboard\Services;

use OpsHealthDashboard\Interfaces\AlertChannelInterface;
use OpsHealthDashboard\Interfaces\AlertManagerInterface;
use OpsHealthDashboard\Interfaces\RedactionInterface;
use OpsHealthDashboard\Interfaces\StorageInterface;

/**
 * Class AlertManager
 *
 * Gestisce la rilevazione dei cambiamenti di stato e l'invio degli alert.
 */
class AlertManager implements AlertManagerInterface {

	/**
	 * Cooldown di default in secondi (60 minuti)
	 *
	 * @var int
	 */
	const DEFAULT_COOLDOWN = 3600;

	/**
	 * Numero massimo di entry nel log degli alert
	 *
	 * @var int
	 */
	const MAX_LOG_ENTRIES = 50;

	/**
	 * Prefisso per i transient di cooldown
	 *
	 * @var string
	 */
	const COOLDOWN_TRANSIENT_PREFIX = 'ops_health_alert_cooldown_';

	/**
	 * Storage per leggere/salvare stato e log
	 *
	 * @var StorageInterface
	 */
	private $storage;

	/**
	 * Servizio di redazione
	 *
	 * @var RedactionInterface
	 */
	private $redaction;

	/**
	 * Canali di notifica registrati
	 *
	 * @var AlertChannelInterface[]
	 */
	private $channels = [];

	/**
	 * Constructor
	 *
	 * @param StorageInterface   $storage   Storage per stato e log.
	 * @param RedactionInterface $redaction Servizio di redazione.
	 */
	public function __construct( StorageInterface $storage, RedactionInterface $redaction ) {
		$this->storage   = $storage;
		$this->redaction = $redaction;
	}

	/**
	 * Aggiunge un canale di notifica
	 *
	 * @param AlertChannelInterface $channel Canale da aggiungere.
	 * @return void
	 */
	public function add_channel( AlertChannelInterface $channel ): void {
		$this->channels[] = $channel;
	}

	/**
	 * Processa i risultati e invia alert sui cambiamenti di stato
	 *
	 * @param array $current_results  Risultati correnti da run_all().
	 * @param array $previous_results Risultati precedenti.
	 * @return array Array dei risultati di invio alert.
	 */
	public function process( array $current_results, array $previous_results ): array {
		$all_results   = [];
		$changes       = $this->detect_state_changes( $current_results, $previous_results );
		$cooldown_secs = $this->get_cooldown_seconds();

		foreach ( $changes as $check_id => $change ) {
			$is_recovery = 'ok' === $change['current_status'];

			// Recovery bypassa il cooldown.
			if ( ! $is_recovery && $this->is_in_cooldown( $check_id ) ) {
				continue;
			}

			$payload = $this->build_payload( $check_id, $change );

			$send_results = $this->dispatch_to_channels( $payload );

			if ( ! empty( $send_results ) ) {
				$all_results[ $check_id ] = $send_results;

				// Imposta cooldown solo per alert non-recovery.
				if ( ! $is_recovery ) {
					$this->set_cooldown( $check_id, $cooldown_secs );
				}

				$this->log_alert( $payload, $send_results );
			}
		}

		return $all_results;
	}

	/**
	 * Rileva i cambiamenti di stato tra risultati correnti e precedenti
	 *
	 * @param array $current  Risultati correnti.
	 * @param array $previous Risultati precedenti.
	 * @return array Cambiamenti rilevati (check_id => dati).
	 */
	private function detect_state_changes( array $current, array $previous ): array {
		$changes = [];

		foreach ( $current as $check_id => $result ) {
			$current_status = isset( $result['status'] ) ? $result['status'] : 'unknown';

			if ( isset( $previous[ $check_id ] ) ) {
				$prev_status = isset( $previous[ $check_id ]['status'] )
					? $previous[ $check_id ]['status']
					: 'unknown';
			} else {
				// Primo avvio: alerta solo se non ok.
				$prev_status = null;
			}

			// Nessun cambiamento: salta.
			if ( $current_status === $prev_status ) {
				continue;
			}

			// Primo avvio con ok: nessun alert.
			if ( null === $prev_status && 'ok' === $current_status ) {
				continue;
			}

			$changes[ $check_id ] = [
				'current_status'  => $current_status,
				'previous_status' => $prev_status,
				'result'          => $result,
			];
		}

		return $changes;
	}

	/**
	 * Verifica se un check è in cooldown
	 *
	 * @param string $check_id ID del check.
	 * @return bool True se in cooldown.
	 */
	private function is_in_cooldown( string $check_id ): bool {
		$transient_key = self::COOLDOWN_TRANSIENT_PREFIX . $check_id;
		return false !== get_transient( $transient_key );
	}

	/**
	 * Imposta il cooldown per un check
	 *
	 * @param string $check_id ID del check.
	 * @param int    $seconds  Durata in secondi.
	 * @return void
	 */
	private function set_cooldown( string $check_id, int $seconds ): void {
		$transient_key = self::COOLDOWN_TRANSIENT_PREFIX . $check_id;
		set_transient( $transient_key, time(), $seconds );
	}

	/**
	 * Ottiene il cooldown configurato in secondi
	 *
	 * @return int Secondi di cooldown.
	 */
	private function get_cooldown_seconds(): int {
		$settings = $this->storage->get( 'alert_settings', [] );

		if ( is_array( $settings ) && isset( $settings['cooldown_minutes'] ) ) {
			$minutes = (int) $settings['cooldown_minutes'];
			if ( $minutes > 0 ) {
				return $minutes * 60;
			}
		}

		return self::DEFAULT_COOLDOWN;
	}

	/**
	 * Costruisce il payload dell'alert
	 *
	 * @param string $check_id ID del check.
	 * @param array  $change   Dati del cambiamento.
	 * @return array Payload strutturato.
	 */
	private function build_payload( string $check_id, array $change ): array {
		$result          = $change['result'];
		$current_status  = $change['current_status'];
		$previous_status = $change['previous_status'];

		return [
			'check_id'        => $check_id,
			'check_name'      => isset( $result['name'] ) ? $result['name'] : $check_id,
			'previous_status' => $previous_status,
			'current_status'  => $current_status,
			'message'         => isset( $result['message'] ) ? $result['message'] : '',
			'details'         => isset( $result['details'] ) ? $result['details'] : [],
			'timestamp'       => time(),
			'site_url'        => home_url(),
			'site_name'       => get_bloginfo( 'name' ),
			'is_recovery'     => 'ok' === $current_status,
		];
	}

	/**
	 * Invia il payload a tutti i canali abilitati
	 *
	 * @param array $payload Payload dell'alert.
	 * @return array Risultati di invio per canale.
	 */
	private function dispatch_to_channels( array $payload ): array {
		$results = [];

		foreach ( $this->channels as $channel ) {
			if ( ! $channel->is_enabled() ) {
				continue;
			}

			$results[ $channel->get_id() ] = $channel->send( $payload );
		}

		return $results;
	}

	/**
	 * Registra l'alert nel log (limitato a MAX_LOG_ENTRIES)
	 *
	 * @param array $payload Payload dell'alert.
	 * @param array $results Risultati di invio.
	 * @return void
	 */
	private function log_alert( array $payload, array $results ): void {
		$log = $this->storage->get( 'alert_log', [] );

		if ( ! is_array( $log ) ) {
			$log = [];
		}

		// Redige eventuali messaggi di errore dai canali.
		$redacted_errors = [];
		foreach ( $results as $channel_id => $r ) {
			if ( ! empty( $r['error'] ) ) {
				$redacted_errors[ $channel_id ] = $this->redaction->redact( $r['error'] );
			}
		}

		$entry = [
			'check_id'  => $payload['check_id'],
			'status'    => $payload['current_status'],
			'channels'  => array_keys( $results ),
			'errors'    => $redacted_errors,
			'success'   => ! empty(
				array_filter(
					$results,
					function ( $r ) {
						return ! empty( $r['success'] );
					}
				)
			),
			'timestamp' => $payload['timestamp'],
		];

		array_unshift( $log, $entry );

		// Limita a MAX_LOG_ENTRIES.
		$log = array_slice( $log, 0, self::MAX_LOG_ENTRIES );

		$this->storage->set( 'alert_log', $log );
	}
}
