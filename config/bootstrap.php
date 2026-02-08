<?php
/**
 * Configurazione Bootstrap del Plugin
 *
 * Crea e configura il container DI, restituisce l'istanza del Plugin.
 * NO pattern singleton, NO factory static.
 *
 * @package OpsHealthDashboard
 */

namespace OpsHealthDashboard;

use OpsHealthDashboard\Core\Container;
use OpsHealthDashboard\Core\Plugin;

/**
 * Funzione di bootstrap
 *
 * Crea il container, configura i binding, restituisce l'istanza del plugin.
 * Chiamata dal file principale del plugin sull'hook 'plugins_loaded'.
 *
 * @return Plugin Istanza del plugin con container configurato.
 */
function bootstrap(): Plugin {
	// Crea una nuova istanza del container.
	$container = new Container();

	// Configura i binding per i servizi (shared instances).
	$container->share(
		Interfaces\StorageInterface::class,
		// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		function ( $c ) {
			return new Services\Storage();
		}
	);

	$container->share(
		Services\CheckRunner::class,
		function ( $c ) {
			$runner = new Services\CheckRunner(
				$c->make( Interfaces\StorageInterface::class )
			);
			// Registra i check disponibili.
			global $wpdb;
			$runner->add_check( new Checks\DatabaseCheck( $wpdb ) );
			return $runner;
		}
	);

	$container->share(
		Services\Scheduler::class,
		function ( $c ) {
			return new Services\Scheduler(
				$c->make( Services\CheckRunner::class )
			);
		}
	);

	// Configura i binding per l'interfaccia admin.
	$container->share(
		Admin\HealthScreen::class,
		function ( $c ) {
			return new Admin\HealthScreen(
				$c->make( Services\CheckRunner::class )
			);
		}
	);

	$container->share(
		Admin\Menu::class,
		function ( $c ) {
			return new Admin\Menu(
				$c->make( Admin\HealthScreen::class )
			);
		}
	);

	// Crea e restituisce l'istanza del plugin.
	return new Plugin( $container );
}
