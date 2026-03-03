<?php
/**
 * Admin Menu
 *
 * Manages the registration of the WordPress admin menu.
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

/**
 * Class Menu
 *
 * Registers the admin menu for Ops Health Dashboard.
 */
class Menu {

	/**
	 * HealthScreen for rendering the page
	 *
	 * @var HealthScreen
	 */
	private $health_screen;

	/**
	 * AlertSettings for the alert configuration page
	 *
	 * @var AlertSettings|null
	 */
	private $alert_settings;

	/**
	 * Constructor
	 *
	 * @param HealthScreen       $health_screen  HealthScreen for the page.
	 * @param AlertSettings|null $alert_settings Optional AlertSettings.
	 */
	public function __construct(
		HealthScreen $health_screen,
		AlertSettings $alert_settings = null
	) {
		$this->health_screen  = $health_screen;
		$this->alert_settings = $alert_settings;
	}

	/**
	 * Registers WordPress hooks
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'admin_menu', [ $this, 'add_menu' ], 10 );
	}

	/**
	 * Adds the menu item in wp-admin
	 *
	 * @return void
	 */
	public function add_menu(): void {
		$hook = add_menu_page(
			__( 'Ops Health Dashboard', 'ops-health-dashboard' ),
			__( 'Ops Health', 'ops-health-dashboard' ),
			'manage_options',
			'ops-health-dashboard',
			[ $this, 'render_page' ],
			'dashicons-heart',
			80
		);

		if ( false !== $hook ) {
			add_action(
				'load-' . $hook,
				[ $this->health_screen, 'process_actions' ]
			);
			add_action(
				'load-' . $hook,
				[ $this->health_screen, 'add_help_tabs' ]
			);
		}

		if ( null !== $this->alert_settings ) {
			$this->add_alert_settings_submenu();
		}
	}

	/**
	 * Renders the admin page
	 *
	 * @return void
	 */
	public function render_page(): void {
		$this->health_screen->render();
	}

	/**
	 * Renders the alert settings page
	 *
	 * @return void
	 */
	public function render_alert_settings(): void {
		if ( null !== $this->alert_settings ) {
			$this->alert_settings->render();
		}
	}

	/**
	 * Adds the Alert Settings submenu
	 *
	 * @return void
	 */
	private function add_alert_settings_submenu(): void {
		$hook = add_submenu_page(
			'ops-health-dashboard',
			__( 'Alert Settings', 'ops-health-dashboard' ),
			__( 'Alert Settings', 'ops-health-dashboard' ),
			'manage_options',
			'ops-health-alert-settings',
			[ $this, 'render_alert_settings' ]
		);

		if ( false !== $hook ) {
			add_action(
				'load-' . $hook,
				[ $this->alert_settings, 'process_actions' ]
			);
			add_action(
				'load-' . $hook,
				[ $this->alert_settings, 'add_help_tabs' ]
			);
		}
	}
}
