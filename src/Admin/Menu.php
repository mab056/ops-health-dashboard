<?php
/**
 * Admin Menu
 *
 * Gestisce la registrazione del menu admin di WordPress.
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
 * Registra il menu admin per Ops Health Dashboard.
 */
class Menu {

	/**
	 * HealthScreen per renderizzare la pagina
	 *
	 * @var HealthScreen
	 */
	private $health_screen;

	/**
	 * AlertSettings per la pagina di configurazione alert
	 *
	 * @var AlertSettings|null
	 */
	private $alert_settings;

	/**
	 * Constructor
	 *
	 * @param HealthScreen       $health_screen  HealthScreen per la pagina.
	 * @param AlertSettings|null $alert_settings AlertSettings opzionale.
	 */
	public function __construct(
		HealthScreen $health_screen,
		AlertSettings $alert_settings = null
	) {
		$this->health_screen  = $health_screen;
		$this->alert_settings = $alert_settings;
	}

	/**
	 * Registra gli hook WordPress
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'admin_menu', [ $this, 'add_menu' ], 10 );
	}

	/**
	 * Aggiunge la voce di menu in wp-admin
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
	 * Renderizza la pagina admin
	 *
	 * @return void
	 */
	public function render_page(): void {
		$this->health_screen->render();
	}

	/**
	 * Renderizza la pagina alert settings
	 *
	 * @return void
	 */
	public function render_alert_settings(): void {
		if ( null !== $this->alert_settings ) {
			$this->alert_settings->render();
		}
	}

	/**
	 * Aggiunge il sottomenu Alert Settings
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
