<?php
/**
 * Admin Menu
 *
 * Gestisce la registrazione del menu admin di WordPress.
 *
 * @package OpsHealthDashboard\Admin
 */

namespace OpsHealthDashboard\Admin;

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
	 * Constructor
	 *
	 * @param HealthScreen $health_screen HealthScreen per la pagina.
	 */
	public function __construct( HealthScreen $health_screen ) {
		$this->health_screen = $health_screen;
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
		add_menu_page(
			__( 'Ops Health Dashboard', 'ops-health-dashboard' ),
			__( 'Ops Health', 'ops-health-dashboard' ),
			'manage_options',
			'ops-health-dashboard',
			[ $this, 'render_page' ],
			'dashicons-heart',
			80
		);
	}

	/**
	 * Renderizza la pagina admin
	 *
	 * @return void
	 */
	public function render_page(): void {
		$this->health_screen->render();
	}
}
