<?php
/**
 * Classe principale del Plugin
 *
 * @package OpsHealthDashboard\Core
 */

namespace OpsHealthDashboard\Core;

/**
 * Class Plugin
 *
 * Orchestratore principale del plugin con dependency injection.
 * NO pattern singleton, NO metodi static, NO modificatore final.
 */
class Plugin {

	/**
	 * Container per la dependency injection
	 *
	 * @var Container
	 */
	private $container;

	/**
	 * Flag di inizializzazione
	 *
	 * @var bool
	 */
	private $initialized = false;

	/**
	 * Costruttore
	 *
	 * Riceve il container tramite dependency injection (NO singleton).
	 *
	 * @param Container $container Container DI.
	 */
	public function __construct( Container $container ) {
		$this->container = $container;
	}

	/**
	 * Inizializza il plugin
	 *
	 * Idempotente - sicuro da chiamare più volte.
	 *
	 * @return void
	 */
	public function init(): void {
		if ( $this->initialized ) {
			return;
		}

		// Registra il menu admin.
		$menu = $this->container->make( \OpsHealthDashboard\Admin\Menu::class );
		$menu->register_hooks();

		// Registra lo scheduler WP-Cron.
		$scheduler = $this->container->make( \OpsHealthDashboard\Services\Scheduler::class );
		$scheduler->register_hooks();

		$this->initialized = true;
	}

	/**
	 * Ottiene l'istanza del container
	 *
	 * @return Container
	 */
	public function get_container(): Container {
		return $this->container;
	}
}
