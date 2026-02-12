<?php
/**
 * Health Screen
 *
 * Pagina admin principale che mostra i risultati dei check di salute.
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
	 * Constructor
	 *
	 * @param CheckRunnerInterface $runner CheckRunner per i risultati.
	 */
	public function __construct( CheckRunnerInterface $runner ) {
		$this->runner = $runner;
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
					<p><?php echo esc_html__( 'No health checks have been run yet.', 'ops-health-dashboard' ); ?></p>
				</div>
			<?php else : ?>
				<?php
				$overall_status = $this->determine_overall_status( $results );
				$status_labels  = [
					'ok'       => __( 'Healthy', 'ops-health-dashboard' ),
					'warning'  => __( 'Warning', 'ops-health-dashboard' ),
					'critical' => __( 'Critical', 'ops-health-dashboard' ),
					'unknown'  => __( 'Unknown', 'ops-health-dashboard' ),
				];
				$overall_label  = isset( $status_labels[ $overall_status ] )
					? $status_labels[ $overall_status ]
					// phpcs:ignore Squiz.Commenting.InlineComment.InvalidEndChar
					// @codeCoverageIgnoreStart
					: $status_labels['unknown'];
				// @codeCoverageIgnoreEnd
				?>
				<div class="ops-health-summary ops-health-summary-<?php echo esc_attr( $overall_status ); ?>">
					<strong><?php echo esc_html( $overall_label ); ?></strong>
				</div>

				<div class="ops-health-checks">
					<?php foreach ( $results as $check_id => $result ) : ?>
						<?php
						$status  = isset( $result['status'] ) ? $result['status'] : 'unknown';
						$message = isset( $result['message'] ) ? $result['message'] : '';
						$name    = isset( $result['name'] ) ? $result['name'] : ucfirst( $check_id );
						?>
						<div class="ops-health-check ops-health-check-<?php echo esc_attr( $status ); ?>">
							<h3><?php echo esc_html( $name ); ?></h3>
							<p class="status">
								<strong><?php echo esc_html__( 'Status:', 'ops-health-dashboard' ); ?></strong>
								<?php echo esc_html( ucfirst( $status ) ); ?>
							</p>
							<p class="message">
								<?php echo esc_html( $message ); ?>
							</p>
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
}
