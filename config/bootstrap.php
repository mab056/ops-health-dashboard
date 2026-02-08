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

	// TODO: Configurare i binding quando esistono i servizi
	// Esempio:
	// $container->share( Interfaces\StorageInterface::class, function( $c ) {
	//     return new Services\Storage();
	// } );

	// Crea e restituisce l'istanza del plugin.
	return new Plugin( $container );
}
