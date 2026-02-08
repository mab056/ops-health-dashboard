<?php
/**
 * Plugin Bootstrap Configuration
 *
 * Creates and configures the DI container, returns Plugin instance.
 * NO singleton pattern, NO static factories.
 *
 * @package OpsHealthDashboard
 */

namespace OpsHealthDashboard;

use OpsHealthDashboard\Core\Container;
use OpsHealthDashboard\Core\Plugin;

/**
 * Bootstrap function
 *
 * Creates container, configures bindings, returns plugin instance.
 * Called from main plugin file on 'plugins_loaded' hook.
 *
 * @return Plugin Plugin instance with configured container.
 */
function bootstrap(): Plugin {
	// Create fresh container instance.
	$container = new Container();

	// TODO: Configure bindings when services exist
	// Example:
	// $container->share( Interfaces\StorageInterface::class, function( $c ) {
	//     return new Services\Storage();
	// } );

	// Create and return plugin instance.
	return new Plugin( $container );
}
