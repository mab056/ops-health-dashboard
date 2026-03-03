<?php
/**
 * Dashboard Widget
 *
 * Widget for the wp-admin dashboard showing the overall status
 * of operational health checks with timing and direct links.
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

/**
 * Class DashboardWidget
 *
 * Widget in the wp-admin dashboard with overall health status.
 */
class DashboardWidget {

	/**
	 * CheckRunner for retrieving results
	 *
	 * @var CheckRunnerInterface
	 */
	private $runner;

	/**
	 * Storage for reading last_run_at
	 *
	 * @var StorageInterface
	 */
	private $storage;

	/**
	 * Constructor
	 *
	 * @param CheckRunnerInterface $runner  CheckRunner for retrieving results.
	 * @param StorageInterface     $storage Storage for timing data.
	 */
	public function __construct( CheckRunnerInterface $runner, StorageInterface $storage ) {
		$this->runner  = $runner;
		$this->storage = $storage;
	}

	/**
	 * Registers WordPress hooks
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'wp_dashboard_setup', [ $this, 'add_widget' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_styles' ] );
	}

	/**
	 * Enqueues widget styles on the dashboard page
	 *
	 * Enqueues CSS only on the main wp-admin dashboard screen.
	 *
	 * @return void
	 */
	public function enqueue_styles(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$screen = get_current_screen();

		if ( null === $screen || 'dashboard' !== $screen->id ) {
			return;
		}

		wp_enqueue_style(
			'ops-health-dashboard-widget',
			plugin_dir_url( OPS_HEALTH_DASHBOARD_FILE ) . 'assets/css/dashboard-widget.css',
			[],
			OPS_HEALTH_DASHBOARD_VERSION
		);
	}

	/**
	 * Adds the widget to the dashboard
	 *
	 * @return void
	 */
	public function add_widget(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		wp_add_dashboard_widget(
			'ops_health_dashboard_widget',
			__( 'Ops Health Status', 'ops-health-dashboard' ),
			[ $this, 'render' ]
		);
	}

	/**
	 * Renders the widget content
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$results        = $this->runner->get_latest_results();
		$overall_status = $this->determine_overall_status( $results );
		$dashboard_url  = admin_url( 'admin.php?page=ops-health-dashboard' );

		if ( empty( $results ) ) {
			?>
			<p>
				<?php
				echo esc_html__(
					'No health checks have been run yet.',
					'ops-health-dashboard'
				);
				?>
			</p>
			<p>
				<a href="<?php echo esc_url( $dashboard_url ); ?>">
					<?php
					echo esc_html__(
						'Go to Ops Health Dashboard',
						'ops-health-dashboard'
					);
					?>
				</a>
			</p>
			<?php
			return;
		}

		$status_labels = [
			'ok'       => __( 'Healthy', 'ops-health-dashboard' ),
			'warning'  => __( 'Warning', 'ops-health-dashboard' ),
			'critical' => __( 'Critical', 'ops-health-dashboard' ),
			'unknown'  => __( 'Unknown', 'ops-health-dashboard' ),
		];

		$overall_label = isset( $status_labels[ $overall_status ] )
			? $status_labels[ $overall_status ]
			// phpcs:ignore Squiz.Commenting.InlineComment.InvalidEndChar
			// @codeCoverageIgnoreStart
			: $status_labels['unknown'];
		// @codeCoverageIgnoreEnd

		?>
		<div class="ops-health-widget-status ops-health-widget-status-<?php echo esc_attr( $overall_status ); ?>">
			<strong><?php echo esc_html( $overall_label ); ?></strong>
		</div>

		<ul class="ops-health-widget-checks">
			<?php foreach ( $results as $check_id => $result ) : ?>
				<?php
				$status = isset( $result['status'] ) ? $result['status'] : 'unknown';
				$name   = isset( $result['name'] ) ? $result['name'] : ucfirst( $check_id );
				$anchor = $dashboard_url . '#check-' . $check_id;
				?>
				<li class="ops-health-widget-check-<?php echo esc_attr( $status ); ?>">
					<a href="<?php echo esc_url( $anchor ); ?>">
						<?php echo esc_html( $name ); ?>
					</a>:
					<strong><?php echo esc_html( ucfirst( $status ) ); ?></strong>
				</li>
			<?php endforeach; ?>
		</ul>

		<?php
		$last_run_at = (int) $this->storage->get( 'last_run_at', 0 );
		if ( $last_run_at > 0 ) :
			?>
			<p class="ops-health-widget-timing">
				<?php
				printf(
					/* translators: %s: relative time */
					esc_html__( 'Last run: %s', 'ops-health-dashboard' ),
					esc_html(
						sprintf(
							/* translators: %s: time difference */
							__( '%s ago', 'ops-health-dashboard' ),
							human_time_diff( $last_run_at, time() )
						)
					)
				);
				?>
			</p>
		<?php endif; ?>

		<p class="ops-health-widget-footer">
			<a href="<?php echo esc_url( $dashboard_url ); ?>">
				<?php echo esc_html__( 'View full dashboard', 'ops-health-dashboard' ); ?>
			</a>
		</p>
		<?php
	}

	/**
	 * Determines overall status (worst wins)
	 *
	 * Critical > warning > ok; empty results = unknown.
	 *
	 * @param array $results Check results.
	 * @return string Overall status.
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
