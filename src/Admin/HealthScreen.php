<?php
/**
 * Health Screen
 *
 * Pagina admin principale che mostra i risultati dei check di salute.
 *
 * @package OpsHealthDashboard\Admin
 */

namespace OpsHealthDashboard\Admin;

use OpsHealthDashboard\Services\CheckRunner;

/**
 * Class HealthScreen
 *
 * Renderizza la pagina principale con i risultati dei check.
 * NO singleton, NO static methods, NO final.
 */
class HealthScreen {

	/**
	 * CheckRunner per ottenere i risultati
	 *
	 * @var CheckRunner
	 */
	private $runner;

	/**
	 * Constructor
	 *
	 * @param CheckRunner $runner CheckRunner per i risultati.
	 */
	public function __construct( CheckRunner $runner ) {
		$this->runner = $runner;
	}

	/**
	 * Renderizza la pagina
	 *
	 * @return void
	 */
	public function render(): void {
		// Verifica capability.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__(
					'You do not have sufficient permissions to access this page.',
					'ops-health-dashboard'
				)
			);
		}

		// Ottiene i risultati.
		$results = $this->runner->get_latest_results();

		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Ops Health Dashboard', 'ops-health-dashboard' ); ?></h1>

			<?php if ( empty( $results ) ) : ?>
				<div class="notice notice-info">
					<p><?php echo esc_html__( 'No health checks have been run yet.', 'ops-health-dashboard' ); ?></p>
				</div>
			<?php else : ?>
				<div class="ops-health-checks">
					<?php foreach ( $results as $check_id => $result ) : ?>
						<?php
						$status  = isset( $result['status'] ) ? $result['status'] : 'unknown';
						$message = isset( $result['message'] ) ? $result['message'] : '';
						?>
						<div class="ops-health-check ops-health-check-<?php echo esc_attr( $status ); ?>">
							<h3><?php echo esc_html( ucfirst( $check_id ) ); ?></h3>
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
}
