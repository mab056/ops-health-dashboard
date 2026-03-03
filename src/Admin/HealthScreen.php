<?php
/**
 * Health Screen
 *
 * Pagina admin principale che mostra i risultati dei check di salute.
 * Interfaccia "Site Health vibe": semaforo + spiegazione + azione consigliata.
 *
 * @package OpsHealthDashboard\Admin
 */

namespace OpsHealthDashboard\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	// phpcs:ignore Squiz.Commenting.InlineComment.InvalidEndChar
	// @codeCoverageIgnoreStart
	exit;
	// phpcs:ignore Squiz.Commenting.InlineComment.InvalidEndChar
	// @codeCoverageIgnoreEnd
}

use OpsHealthDashboard\Interfaces\CheckRunnerInterface;
use OpsHealthDashboard\Interfaces\StorageInterface;
use OpsHealthDashboard\Services\Scheduler;

/**
 * Class HealthScreen
 *
 * Renderizza la pagina principale con i risultati dei check.
 */
class HealthScreen {

	/**
	 * Screen ID per la pagina admin health dashboard
	 *
	 * @var string
	 */
	const SCREEN_ID = 'toplevel_page_ops-health-dashboard';

	/**
	 * CheckRunner per ottenere i risultati
	 *
	 * @var CheckRunnerInterface
	 */
	private $runner;

	/**
	 * Storage per leggere last_run_at
	 *
	 * @var StorageInterface
	 */
	private $storage;

	/**
	 * Constructor
	 *
	 * @param CheckRunnerInterface $runner  CheckRunner per i risultati.
	 * @param StorageInterface     $storage Storage per dati di timing.
	 */
	public function __construct( CheckRunnerInterface $runner, StorageInterface $storage ) {
		$this->runner  = $runner;
		$this->storage = $storage;
	}

	/**
	 * Registra gli hook WordPress
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_styles' ] );
	}

	/**
	 * Carica gli stili sulla pagina health dashboard
	 *
	 * Enqueue il CSS solo sulla schermata health dashboard.
	 *
	 * @return void
	 */
	public function enqueue_styles(): void {
		$screen = get_current_screen();

		if ( null === $screen || self::SCREEN_ID !== $screen->id ) {
			return;
		}

		wp_enqueue_style(
			'ops-health-dashboard-screen',
			plugin_dir_url( OPS_HEALTH_DASHBOARD_FILE ) . 'assets/css/health-screen.css',
			[],
			OPS_HEALTH_DASHBOARD_VERSION
		);
	}

	/**
	 * Processa le azioni admin (Run Now, Clear Cache)
	 *
	 * Verifica nonce e capability prima di eseguire.
	 *
	 * @return void
	 */
	public function process_actions(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified below.
		if ( ! isset( $_POST['ops_health_action'] ) ) {
			return;
		}

		if (
			! isset( $_POST['_ops_health_nonce'] )
			|| ! wp_verify_nonce(
				sanitize_text_field( wp_unslash( $_POST['_ops_health_nonce'] ) ),
				'ops_health_admin_action'
			)
		) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$action = sanitize_text_field( wp_unslash( $_POST['ops_health_action'] ) );

		switch ( $action ) {
			case 'run_now':
				$this->runner->run_all();
				set_transient(
					'ops_health_admin_notice',
					__( 'Health checks executed successfully.', 'ops-health-dashboard' ),
					30
				);
				break;

			case 'clear_cache':
				$this->runner->clear_results();
				set_transient(
					'ops_health_admin_notice',
					__( 'Cached results cleared.', 'ops-health-dashboard' ),
					30
				);
				break;
		}

		wp_safe_redirect( admin_url( 'admin.php?page=ops-health-dashboard' ) );
		$this->do_exit();
	}

	/**
	 * Termina l'esecuzione dello script
	 *
	 * Estratto in metodo protetto per testabilità.
	 *
	 * @return void
	 * @codeCoverageIgnore
	 */
	protected function do_exit(): void {
		exit;
	}

	/**
	 * Renderizza la pagina
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__(
					'You do not have sufficient permissions to access this page.',
					'ops-health-dashboard'
				)
			);
		}

		$results = $this->runner->get_latest_results();
		$notice  = get_transient( 'ops_health_admin_notice' );

		if ( false !== $notice ) {
			delete_transient( 'ops_health_admin_notice' );
		}

		$last_run_at = (int) $this->storage->get( 'last_run_at', 0 );
		$next_run    = (int) wp_next_scheduled( Scheduler::HOOK_NAME );

		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Ops Health Dashboard', 'ops-health-dashboard' ); ?></h1>

			<?php if ( false !== $notice ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php echo esc_html( $notice ); ?></p>
				</div>
			<?php endif; ?>

			<div class="ops-health-actions">
				<form method="post">
					<?php wp_nonce_field( 'ops_health_admin_action', '_ops_health_nonce' ); ?>
					<input type="hidden" name="ops_health_action" value="run_now" />
					<?php
					submit_button(
						esc_html__( 'Run Now', 'ops-health-dashboard' ),
						'primary',
						'ops_health_submit',
						false
					);
					?>
				</form>
				<form method="post">
					<?php wp_nonce_field( 'ops_health_admin_action', '_ops_health_nonce' ); ?>
					<input type="hidden" name="ops_health_action" value="clear_cache" />
					<?php
					submit_button(
						esc_html__( 'Clear Cache', 'ops-health-dashboard' ),
						'secondary',
						'ops_health_submit',
						false
					);
					?>
				</form>
			</div>

			<?php if ( empty( $results ) ) : ?>
				<div class="notice notice-info">
					<p>
						<?php
						echo esc_html__(
							'No health checks have been run yet.',
							'ops-health-dashboard'
						);
						?>
					</p>
				</div>
			<?php else : ?>
				<?php
				$overall_status  = $this->determine_overall_status( $results );
				$affected_checks = $this->get_affected_checks( $results );
				$affected_count  = count( $affected_checks );
				$status_labels   = [
					'ok'       => __( 'Healthy', 'ops-health-dashboard' ),
					'warning'  => __( 'Warning', 'ops-health-dashboard' ),
					'critical' => __( 'Critical', 'ops-health-dashboard' ),
					'unknown'  => __( 'Unknown', 'ops-health-dashboard' ),
				];
				$overall_label   = isset( $status_labels[ $overall_status ] )
					? $status_labels[ $overall_status ]
					// phpcs:ignore Squiz.Commenting.InlineComment.InvalidEndChar
					// @codeCoverageIgnoreStart
					: $status_labels['unknown'];
				// @codeCoverageIgnoreEnd
				$notice_class = 'ok' === $overall_status ? 'notice-success' : 'notice-warning';
				if ( 'critical' === $overall_status ) {
					$notice_class = 'notice-error';
				}
				?>
				<?php // phpcs:ignore Generic.WhiteSpace.ScopeIndent.IncorrectExact ?>
				<div class="ops-health-summary notice <?php echo esc_attr( $notice_class ); ?>
					ops-health-summary-<?php echo esc_attr( $overall_status ); ?>">
					<div class="ops-health-summary-header">
						<span class="ops-health-status-icon" aria-hidden="true">
							<?php echo esc_html( $this->get_status_icon( $overall_status ) ); ?>
						</span>
						<strong><?php echo esc_html( $overall_label ); ?></strong>
						<?php if ( $affected_count > 0 ) : ?>
							<span class="ops-health-summary-affected">
								<?php
								$affected_text = _n(
									'%d check requires attention',
									'%d checks require attention',
									$affected_count,
									'ops-health-dashboard'
								);
								printf(
									esc_html( $affected_text ),
									(int) $affected_count
								);
								?>
							</span>
						<?php endif; ?>
					</div>
					<?php if ( ! empty( $affected_checks ) ) : ?>
						<ul class="ops-health-summary-issues">
							<?php foreach ( $affected_checks as $ac ) : ?>
								<li>
									<strong>
										<?php echo esc_html( $ac['name'] ); ?>:
									</strong>
									<?php echo esc_html( $ac['message'] ); ?>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
					<div class="ops-health-summary-meta">
						<?php if ( $last_run_at > 0 ) : ?>
							<span class="ops-health-meta-item">
								<?php
								printf(
									/* translators: %s: relative time */
									esc_html__( 'Last run: %s', 'ops-health-dashboard' ),
									esc_html( $this->format_relative_time( $last_run_at ) )
								);
								?>
							</span>
						<?php endif; ?>
						<?php if ( $next_run > 0 ) : ?>
							<span class="ops-health-meta-item">
								<?php
								printf(
									/* translators: %s: relative time */
									esc_html__( 'Next run: %s', 'ops-health-dashboard' ),
									esc_html( $this->format_relative_time( $next_run ) )
								);
								?>
							</span>
						<?php endif; ?>
						<span class="ops-health-meta-item">
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=ops-health-alert-settings' ) ); ?>">
								<?php
								echo esc_html__(
									'Alert Settings',
									'ops-health-dashboard'
								);
								?>
							</a>
						</span>
					</div>
				</div>

				<div class="ops-health-checks">
					<?php foreach ( $results as $check_id => $result ) : ?>
						<?php
						$status  = isset( $result['status'] ) ? $result['status'] : 'unknown';
						$message = isset( $result['message'] ) ? $result['message'] : '';
						$name    = isset( $result['name'] ) ? $result['name'] : ucfirst( $check_id );
						?>
						<div class="ops-health-check ops-health-check-<?php echo esc_attr( $status ); ?>"
							id="check-<?php echo esc_attr( $check_id ); ?>">
							<div class="ops-health-check-header">
								<h3>
									<span class="ops-health-status-icon" aria-hidden="true">
										<?php
										echo esc_html(
											$this->get_status_icon( $status )
										);
										?>
									</span>
									<?php echo esc_html( $name ); ?>
									<span class="ops-health-badge ops-health-badge-<?php echo esc_attr( $status ); ?>">
										<?php echo esc_html( ucfirst( $status ) ); ?>
									</span>
								</h3>
							</div>
							<p class="ops-health-check-metric">
								<?php echo esc_html( $message ); ?>
							</p>
							<?php if ( $last_run_at > 0 ) : ?>
								<p class="ops-health-check-timestamp">
									<?php
									printf(
										/* translators: %s: relative time */
										esc_html__( 'Checked %s', 'ops-health-dashboard' ),
										esc_html(
											$this->format_relative_time( $last_run_at )
										)
									);
									?>
								</p>
							<?php endif; ?>
							<?php $this->render_check_details( $check_id, $result ); ?>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Determina lo status globale (il peggiore vince)
	 *
	 * Critical > warning > ok; risultati vuoti = unknown.
	 *
	 * @param array $results Risultati dei check.
	 * @return string Status globale.
	 */
	private function determine_overall_status( array $results ): string {
		if ( empty( $results ) ) {
			return 'unknown';
		}

		$priority = [
			'critical' => 3,
			'warning'  => 2,
			'ok'       => 1,
		];

		$worst        = 0;
		$worst_status = 'unknown';

		foreach ( $results as $result ) {
			$status = isset( $result['status'] ) ? $result['status'] : 'unknown';
			$value  = isset( $priority[ $status ] ) ? $priority[ $status ] : 0;

			if ( $value > $worst ) {
				$worst        = $value;
				$worst_status = $status;
			}
		}

		return $worst_status;
	}

	/**
	 * Returns a text icon for the given status
	 *
	 * @param string $status Status string (ok, warning, critical, unknown).
	 * @return string Unicode character for the status.
	 */
	private function get_status_icon( string $status ): string {
		$icons = [
			'ok'       => "\xe2\x9c\x94",
			'warning'  => "\xe2\x9a\xa0",
			'critical' => "\xe2\x9c\x96",
			'unknown'  => '?',
		];
		return isset( $icons[ $status ] ) ? $icons[ $status ] : '?';
	}

	/**
	 * Formats a Unix timestamp as human-readable relative time
	 *
	 * @param int $timestamp Unix timestamp.
	 * @return string Human-readable time difference.
	 */
	private function format_relative_time( int $timestamp ): string {
		$diff = human_time_diff( $timestamp, time() );

		if ( $timestamp <= time() ) {
			return sprintf(
				/* translators: %s: time difference */
				__( '%s ago', 'ops-health-dashboard' ),
				$diff
			);
		}

		return sprintf(
			/* translators: %s: time difference */
			__( '%s from now', 'ops-health-dashboard' ),
			$diff
		);
	}

	/**
	 * Collects checks that are not OK
	 *
	 * @param array $results Check results array.
	 * @return array Array of arrays with 'name' and 'message' keys.
	 */
	private function get_affected_checks( array $results ): array {
		$affected = [];
		foreach ( $results as $result ) {
			$status = isset( $result['status'] ) ? $result['status'] : 'unknown';
			if ( 'ok' !== $status ) {
				$affected[] = [
					'name'    => isset( $result['name'] ) ? $result['name'] : '',
					'message' => isset( $result['message'] ) ? $result['message'] : '',
				];
			}
		}
		return $affected;
	}

	/**
	 * Renders expandable details for a specific check
	 *
	 * Uses HTML5 details/summary for zero-JS progressive enhancement.
	 *
	 * @param string $check_id Check identifier.
	 * @param array  $result   Check result data including details.
	 * @return void
	 */
	private function render_check_details( string $check_id, array $result ): void {
		$details  = isset( $result['details'] ) && is_array( $result['details'] )
			? $result['details'] : [];
		$duration = isset( $result['duration'] ) ? (float) $result['duration'] : 0;

		if ( empty( $details ) && $duration <= 0 ) {
			return;
		}

		?>
		<details class="ops-health-check-details">
			<summary>
				<?php echo esc_html__( 'Details', 'ops-health-dashboard' ); ?>
			</summary>
			<div class="ops-health-check-details-content">
				<?php if ( $duration > 0 ) : ?>
					<p>
						<strong>
							<?php echo esc_html__( 'Duration:', 'ops-health-dashboard' ); ?>
						</strong>
						<?php
						echo esc_html(
							number_format( $duration * 1000, 1 ) . ' ms'
						);
						?>
					</p>
				<?php endif; ?>
				<?php $this->render_specific_details( $check_id, $details ); ?>
			</div>
		</details>
		<?php
	}

	/**
	 * Renders check-specific detail data
	 *
	 * @param string $check_id Check identifier.
	 * @param array  $details  Check-specific details array.
	 * @return void
	 */
	private function render_specific_details( string $check_id, array $details ): void {
		switch ( $check_id ) {
			case 'database':
				$this->render_database_details( $details );
				break;
			case 'error_log':
				$this->render_error_log_details( $details );
				break;
			case 'redis':
				$this->render_redis_details( $details );
				break;
			case 'disk':
				$this->render_disk_details( $details );
				break;
			case 'versions':
				$this->render_versions_details( $details );
				break;
		}
	}

	/**
	 * Renders database check details
	 *
	 * @param array $details Database check details.
	 * @return void
	 */
	private function render_database_details( array $details ): void {
		if ( isset( $details['query_time'] ) ) {
			echo '<dl class="ops-health-detail-list">';
			echo '<dt>' . esc_html__( 'Query Time', 'ops-health-dashboard' ) . '</dt>';
			echo '<dd>' . esc_html( $details['query_time'] ) . '</dd>';
			echo '</dl>';
		}
	}

	/**
	 * Renders error log check details with severity breakdown
	 *
	 * @param array $details Error log check details.
	 * @return void
	 */
	private function render_error_log_details( array $details ): void {
		if ( isset( $details['counts'] ) && is_array( $details['counts'] ) ) {
			echo '<dl class="ops-health-detail-list">';
			foreach ( $details['counts'] as $severity => $count ) {
				echo '<dt>' . esc_html( ucfirst( $severity ) ) . '</dt>';
				echo '<dd>' . esc_html( (string) $count ) . '</dd>';
			}
			echo '</dl>';
		}
		if ( isset( $details['file_size'] ) ) {
			echo '<p><strong>';
			echo esc_html__( 'Log size:', 'ops-health-dashboard' );
			echo '</strong> ';
			echo esc_html( $details['file_size'] );
			echo '</p>';
		}
	}

	/**
	 * Renders Redis check details
	 *
	 * @param array $details Redis check details.
	 * @return void
	 */
	private function render_redis_details( array $details ): void {
		if ( isset( $details['response_time'] ) ) {
			echo '<dl class="ops-health-detail-list">';
			echo '<dt>' . esc_html__( 'Response Time', 'ops-health-dashboard' ) . '</dt>';
			echo '<dd>' . esc_html( $details['response_time'] ) . '</dd>';
			echo '</dl>';
		}
	}

	/**
	 * Renders disk check details
	 *
	 * @param array $details Disk check details.
	 * @return void
	 */
	private function render_disk_details( array $details ): void {
		echo '<dl class="ops-health-detail-list">';
		if ( isset( $details['free_percent'] ) ) {
			echo '<dt>' . esc_html__( 'Free', 'ops-health-dashboard' ) . '</dt>';
			echo '<dd>' . esc_html( number_format( (float) $details['free_percent'], 1 ) . '%' ) . '</dd>';
		}
		if ( isset( $details['free_bytes'] ) && isset( $details['total_bytes'] ) ) {
			echo '<dt>' . esc_html__( 'Capacity', 'ops-health-dashboard' ) . '</dt>';
			echo '<dd>';
			echo esc_html(
				size_format( $details['free_bytes'] ) . ' / ' . size_format( $details['total_bytes'] )
			);
			echo '</dd>';
		}
		echo '</dl>';
	}

	/**
	 * Renders versions check details
	 *
	 * @param array $details Versions check details.
	 * @return void
	 */
	private function render_versions_details( array $details ): void {
		echo '<dl class="ops-health-detail-list">';
		if ( isset( $details['wp_version'] ) ) {
			echo '<dt>' . esc_html__( 'WordPress', 'ops-health-dashboard' ) . '</dt>';
			echo '<dd>' . esc_html( $details['wp_version'] ) . '</dd>';
		}
		if ( isset( $details['php_version'] ) ) {
			echo '<dt>' . esc_html__( 'PHP', 'ops-health-dashboard' ) . '</dt>';
			echo '<dd>' . esc_html( $details['php_version'] ) . '</dd>';
		}
		echo '</dl>';
		$has_updates = isset( $details['updates_available'] )
			&& is_array( $details['updates_available'] )
			&& ! empty( $details['updates_available'] );
		if ( $has_updates ) {
			echo '<ul class="ops-health-updates-list">';
			foreach ( $details['updates_available'] as $update ) {
				echo '<li>' . esc_html( $update ) . '</li>';
			}
			echo '</ul>';
		}
	}
}
