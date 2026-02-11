<?php
/**
 * Versions Check
 *
 * Controlla le versioni di WordPress, PHP e aggiornamenti disponibili
 * per core, plugin e temi. Graceful degradation se le funzioni di
 * aggiornamento non sono disponibili.
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

/**
 * Class VersionsCheck
 *
 * Check per versioni software e aggiornamenti disponibili.
 */
class VersionsCheck implements CheckInterface {

	/**
	 * Versione PHP raccomandata
	 *
	 * @var string
	 */
	const RECOMMENDED_PHP_VERSION = '8.1';

	/**
	 * Esegue il check delle versioni
	 *
	 * @return array Risultati del check.
	 */
	public function run(): array {
		$start = microtime( true );

		$wp_version  = $this->get_wp_version();
		$php_version = $this->get_php_version();

		$updates_available   = [];
		$has_core_update     = false;
		$plugin_updates      = [];
		$theme_updates       = [];
		$update_check_failed = false;

		try {
			$this->load_update_functions();

			$core_updates = $this->get_core_updates();
			$core_updates = $this->filter_real_updates( $core_updates );

			if ( ! empty( $core_updates ) ) {
				$has_core_update = true;
				foreach ( $core_updates as $update ) {
					$version             = isset( $update->version ) ? $update->version : '';
					$updates_available[] = sprintf(
						/* translators: %s: version number */
						__( 'WordPress %s available', 'ops-health-dashboard' ),
						$version
					);
				}
			}

			$plugin_updates = $this->get_plugin_updates();
			if ( ! empty( $plugin_updates ) ) {
				$count               = count( $plugin_updates );
				$updates_available[] = sprintf(
					/* translators: %d: number of plugin updates */
					__( '%d plugin update(s) available', 'ops-health-dashboard' ),
					$count
				);
			}

			$theme_updates = $this->get_theme_updates();
			if ( ! empty( $theme_updates ) ) {
				$count               = count( $theme_updates );
				$updates_available[] = sprintf(
					/* translators: %d: number of theme updates */
					__( '%d theme update(s) available', 'ops-health-dashboard' ),
					$count
				);
			}
		} catch ( \Throwable $e ) {
			// Graceful degradation: update functions not available.
			$update_check_failed = true;
		}

		if ( $update_check_failed ) {
			$status  = 'warning';
			$message = __( 'Unable to check for updates', 'ops-health-dashboard' );

			if ( version_compare( $php_version, self::RECOMMENDED_PHP_VERSION, '<' ) ) {
				$message .= '; ' . sprintf(
					/* translators: %s: recommended PHP version */
					__( 'PHP %s+ recommended', 'ops-health-dashboard' ),
					self::RECOMMENDED_PHP_VERSION
				);
			}

			return $this->build_result(
				$status,
				$message,
				[
					'wp_version'        => $wp_version,
					'php_version'       => $php_version,
					'php_recommended'   => self::RECOMMENDED_PHP_VERSION,
					'updates_available' => $updates_available,
				],
				microtime( true ) - $start
			);
		}

		$status = $this->determine_status(
			$has_core_update,
			! empty( $plugin_updates ),
			! empty( $theme_updates ),
			$php_version
		);

		if ( 'ok' === $status ) {
			$message = __( 'All versions up to date', 'ops-health-dashboard' );
		} else {
			$message = implode( '; ', $updates_available );
			if ( version_compare( $php_version, self::RECOMMENDED_PHP_VERSION, '<' ) ) {
				if ( ! empty( $message ) ) {
					$message .= '; ';
				}
				$message .= sprintf(
					/* translators: %s: recommended PHP version */
					__( 'PHP %s+ recommended', 'ops-health-dashboard' ),
					self::RECOMMENDED_PHP_VERSION
				);
			}
		}

		return $this->build_result(
			$status,
			$message,
			[
				'wp_version'        => $wp_version,
				'php_version'       => $php_version,
				'php_recommended'   => self::RECOMMENDED_PHP_VERSION,
				'updates_available' => $updates_available,
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
		return 'versions';
	}

	/**
	 * Ottiene il nome del check
	 *
	 * @return string Nome del check.
	 */
	public function get_name(): string {
		return __( 'Versions', 'ops-health-dashboard' );
	}

	/**
	 * Verifica se il check è abilitato
	 *
	 * Sempre abilitato: le informazioni di versione sono sempre disponibili.
	 *
	 * @return bool Sempre true.
	 */
	public function is_enabled(): bool {
		return true;
	}

	/**
	 * Ottiene la versione di WordPress
	 *
	 * @return string Versione WordPress.
	 */
	protected function get_wp_version(): string {
		global $wp_version;
		return isset( $wp_version ) ? $wp_version : '';
	}

	/**
	 * Ottiene la versione di PHP
	 *
	 * @return string Versione PHP.
	 */
	protected function get_php_version(): string {
		return PHP_VERSION;
	}

	/**
	 * Carica le funzioni di aggiornamento WordPress
	 *
	 * Necessario perché non sono caricate in contesto cron.
	 *
	 * @return void
	 */
	protected function load_update_functions(): void {
		if ( ! function_exists( 'get_core_updates' ) ) {
			// phpcs:ignore Squiz.Commenting.InlineComment.InvalidEndChar
			// @codeCoverageIgnoreStart
			require_once ABSPATH . 'wp-admin/includes/update.php';
			// @codeCoverageIgnoreEnd
		}
	}

	/**
	 * Ottiene gli aggiornamenti core disponibili
	 *
	 * @return array Aggiornamenti core.
	 */
	protected function get_core_updates(): array {
		$updates = get_core_updates();
		return is_array( $updates ) ? $updates : [];
	}

	/**
	 * Ottiene gli aggiornamenti plugin disponibili
	 *
	 * @return array Aggiornamenti plugin.
	 */
	protected function get_plugin_updates(): array {
		$updates = get_plugin_updates();
		// @phpstan-ignore ternary.elseUnreachable
		return is_array( $updates ) ? $updates : [];
	}

	/**
	 * Ottiene gli aggiornamenti tema disponibili
	 *
	 * @return array Aggiornamenti tema.
	 */
	protected function get_theme_updates(): array {
		$updates = get_theme_updates();
		// @phpstan-ignore ternary.elseUnreachable
		return is_array( $updates ) ? $updates : [];
	}

	/**
	 * Filtra gli aggiornamenti core reali (esclude 'latest' e 'development')
	 *
	 * @param array $updates Aggiornamenti core grezzi.
	 * @return array Aggiornamenti reali.
	 */
	private function filter_real_updates( array $updates ): array {
		return array_filter(
			$updates,
			function ( $update ) {
				if ( ! isset( $update->response ) ) {
					return false;
				}
				return 'upgrade' === $update->response;
			}
		);
	}

	/**
	 * Determina lo status del check
	 *
	 * @param bool   $has_core_update   Se c'è un aggiornamento core.
	 * @param bool   $has_plugin_update Se ci sono aggiornamenti plugin.
	 * @param bool   $has_theme_update  Se ci sono aggiornamenti tema.
	 * @param string $php_version       Versione PHP corrente.
	 * @return string Status del check.
	 */
	private function determine_status(
		bool $has_core_update,
		bool $has_plugin_update,
		bool $has_theme_update,
		string $php_version
	): string {
		if ( $has_core_update ) {
			return 'critical';
		}

		if ( $has_plugin_update || $has_theme_update ) {
			return 'warning';
		}

		if ( version_compare( $php_version, self::RECOMMENDED_PHP_VERSION, '<' ) ) {
			return 'warning';
		}

		return 'ok';
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
