<?php
/**
 * Versions Check
 *
 * Checks WordPress and PHP versions and available updates
 * for core, plugins, and themes. Graceful degradation if
 * update functions are not available.
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
 * Software version and available updates check.
 */
class VersionsCheck implements CheckInterface {

	/**
	 * Recommended PHP version
	 *
	 * @var string
	 */
	const RECOMMENDED_PHP_VERSION = '8.3';

	/**
	 * Runs the versions check
	 *
	 * @return array Check results.
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
	 * Gets the check ID
	 *
	 * @return string Check ID.
	 */
	public function get_id(): string {
		return 'versions';
	}

	/**
	 * Gets the check name
	 *
	 * @return string Check name.
	 */
	public function get_name(): string {
		return __( 'Versions', 'ops-health-dashboard' );
	}

	/**
	 * Checks if the check is enabled
	 *
	 * Always enabled: version information is always available.
	 *
	 * @return bool Always true.
	 */
	public function is_enabled(): bool {
		return true;
	}

	/**
	 * Gets the WordPress version
	 *
	 * @return string WordPress version.
	 */
	protected function get_wp_version(): string {
		global $wp_version;
		return isset( $wp_version ) ? $wp_version : '';
	}

	/**
	 * Gets the PHP version
	 *
	 * @return string PHP version.
	 */
	protected function get_php_version(): string {
		return PHP_VERSION;
	}

	/**
	 * Loads WordPress update functions
	 *
	 * Required because they are not loaded in cron context.
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
	 * Gets available core updates
	 *
	 * @return array Core updates.
	 */
	protected function get_core_updates(): array {
		$updates = get_core_updates();
		return is_array( $updates ) ? $updates : [];
	}

	/**
	 * Gets available plugin updates
	 *
	 * @return array Plugin updates.
	 */
	protected function get_plugin_updates(): array {
		$updates = get_plugin_updates();
		// @phpstan-ignore ternary.elseUnreachable
		return is_array( $updates ) ? $updates : [];
	}

	/**
	 * Gets available theme updates
	 *
	 * @return array Theme updates.
	 */
	protected function get_theme_updates(): array {
		$updates = get_theme_updates();
		// @phpstan-ignore ternary.elseUnreachable
		return is_array( $updates ) ? $updates : [];
	}

	/**
	 * Filters real core updates (excludes 'latest' and 'development')
	 *
	 * @param array $updates Raw core updates.
	 * @return array Real updates.
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
	 * Determines the check status
	 *
	 * @param bool   $has_core_update   Whether there is a core update.
	 * @param bool   $has_plugin_update Whether there are plugin updates.
	 * @param bool   $has_theme_update  Whether there are theme updates.
	 * @param string $php_version       Current PHP version.
	 * @return string Check status.
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
	 * Builds the standard result array
	 *
	 * @param string $status   Check status.
	 * @param string $message  Descriptive message.
	 * @param array  $details  Additional details.
	 * @param float  $duration Execution duration.
	 * @return array Formatted result.
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
