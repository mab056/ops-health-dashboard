<?php
/**
 * Email Alert Channel
 *
 * Canale di alert che invia notifiche via email usando wp_mail().
 *
 * @package OpsHealthDashboard\Channels
 */

namespace OpsHealthDashboard\Channels;

if ( ! defined( 'ABSPATH' ) ) {
	// phpcs:ignore Squiz.Commenting.InlineComment.InvalidEndChar
	// @codeCoverageIgnoreStart
	exit;
	// phpcs:ignore Squiz.Commenting.InlineComment.InvalidEndChar
	// @codeCoverageIgnoreEnd
}

use OpsHealthDashboard\Interfaces\AlertChannelInterface;
use OpsHealthDashboard\Interfaces\StorageInterface;

/**
 * Class EmailChannel
 *
 * Implementazione del canale email per gli alert.
 */
class EmailChannel implements AlertChannelInterface {

	/**
	 * Storage per leggere le impostazioni del canale
	 *
	 * @var StorageInterface
	 */
	private $storage;

	/**
	 * Constructor
	 *
	 * @param StorageInterface $storage Storage per le impostazioni.
	 */
	public function __construct( StorageInterface $storage ) {
		$this->storage = $storage;
	}

	/**
	 * Ottiene l'identificatore del canale
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'email';
	}

	/**
	 * Ottiene il nome visualizzabile del canale
	 *
	 * @return string
	 */
	public function get_name(): string {
		return __( 'Email', 'ops-health-dashboard' );
	}

	/**
	 * Verifica se il canale è abilitato e configurato
	 *
	 * @return bool True se abilitato con destinatari configurati.
	 */
	public function is_enabled(): bool {
		$settings = $this->get_channel_settings();

		if ( empty( $settings['enabled'] ) ) {
			return false;
		}

		if ( empty( $settings['recipients'] ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Invia una notifica di alert via email
	 *
	 * @param array $payload Dati dell'alert.
	 * @return array Risultato con chiavi success e error.
	 */
	public function send( array $payload ): array {
		$settings   = $this->get_channel_settings();
		$recipients = $this->parse_recipients( $settings['recipients'] ?? '' );

		if ( empty( $recipients ) ) {
			return [
				'success' => false,
				'error'   => __( 'No valid email recipients configured', 'ops-health-dashboard' ),
			];
		}

		$subject = $this->format_subject( $payload );
		$body    = $this->format_body( $payload );

		$sent = wp_mail( $recipients, $subject, $body );

		if ( ! $sent ) {
			return [
				'success' => false,
				'error'   => __( 'wp_mail() failed to send email', 'ops-health-dashboard' ),
			];
		}

		return [
			'success' => true,
			'error'   => null,
		];
	}

	/**
	 * Ottiene le impostazioni del canale email
	 *
	 * @return array Impostazioni del canale.
	 */
	private function get_channel_settings(): array {
		$settings = $this->storage->get( 'alert_settings', [] );

		if ( ! is_array( $settings ) || ! isset( $settings['email'] ) ) {
			return [];
		}

		return is_array( $settings['email'] ) ? $settings['email'] : [];
	}

	/**
	 * Converte stringa di destinatari in array
	 *
	 * @param string $recipients Destinatari separati da virgola.
	 * @return array Array di indirizzi email.
	 */
	private function parse_recipients( string $recipients ): array {
		$list    = explode( ',', $recipients );
		$trimmed = array_map( 'trim', $list );
		return array_values(
			array_filter(
				$trimmed,
				function ( $email ) {
					return '' !== $email && is_email( $email );
				}
			)
		);
	}

	/**
	 * Formatta il soggetto dell'email
	 *
	 * @param array $payload Dati dell'alert.
	 * @return string Soggetto formattato.
	 */
	private function format_subject( array $payload ): string {
		$check_name = $payload['check_name'] ?? $payload['check_id'] ?? 'Unknown';
		$status     = $payload['current_status'] ?? 'unknown';

		return sprintf(
			/* translators: 1: check name, 2: status */
			__( '[Ops Health] %1$s: %2$s', 'ops-health-dashboard' ),
			$check_name,
			strtoupper( $status )
		);
	}

	/**
	 * Formatta il corpo dell'email
	 *
	 * @param array $payload Dati dell'alert.
	 * @return string Corpo formattato.
	 */
	private function format_body( array $payload ): string {
		$check_name      = $payload['check_name'] ?? $payload['check_id'] ?? 'Unknown';
		$current_status  = $payload['current_status'] ?? 'unknown';
		$previous_status = $payload['previous_status'] ?? 'none';
		$message         = $payload['message'] ?? '';
		$site_url        = $payload['site_url'] ?? '';
		$site_name       = $payload['site_name'] ?? '';
		$is_recovery     = ! empty( $payload['is_recovery'] );
		$timestamp       = $payload['timestamp'] ?? time();

		$lines   = [];
		$lines[] = sprintf(
			/* translators: %s: check name */
			__( 'Check: %s', 'ops-health-dashboard' ),
			$check_name
		);
		$lines[] = sprintf(
			/* translators: %s: previous status */
			__( 'Previous Status: %s', 'ops-health-dashboard' ),
			strtoupper( $previous_status )
		);
		$lines[] = sprintf(
			/* translators: %s: current status */
			__( 'Current Status: %s', 'ops-health-dashboard' ),
			strtoupper( $current_status )
		);
		$lines[] = sprintf(
			/* translators: %s: message */
			__( 'Message: %s', 'ops-health-dashboard' ),
			$message
		);
		$lines[] = '';

		if ( $is_recovery ) {
			$lines[] = __( 'This is a RECOVERY alert.', 'ops-health-dashboard' );
			$lines[] = '';
		}

		$lines[] = sprintf(
			/* translators: %s: timestamp */
			__( 'Timestamp: %s', 'ops-health-dashboard' ),
			gmdate( 'Y-m-d H:i:s', $timestamp ) . ' UTC'
		);

		if ( '' !== $site_name ) {
			$lines[] = sprintf(
				/* translators: %s: site name */
				__( 'Site: %s', 'ops-health-dashboard' ),
				$site_name
			);
		}

		if ( '' !== $site_url ) {
			$lines[] = sprintf(
				/* translators: %s: site URL */
				__( 'URL: %s', 'ops-health-dashboard' ),
				$site_url
			);
		}

		return implode( "\n", $lines );
	}
}
