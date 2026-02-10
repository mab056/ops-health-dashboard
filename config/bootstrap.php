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

	// Registra l'istanza wpdb nel container.
	global $wpdb;
	$container->instance( 'wpdb', $wpdb );

	// Configura i binding per i servizi (shared instances).
	$container->share(
		Interfaces\StorageInterface::class,
		// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		function ( $c ) {
			return new Services\Storage();
		}
	);

	// Servizio di redazione dati sensibili.
	$container->share(
		Interfaces\RedactionInterface::class,
		// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		function ( $c ) {
			return new Services\Redaction(
				defined( 'ABSPATH' ) ? ABSPATH : '',
				defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR : ''
			);
		}
	);

	// Client HTTP con protezione anti-SSRF.
	$container->share(
		Interfaces\HttpClientInterface::class,
		function ( $c ) {
			return new Services\HttpClient(
				$c->make( Interfaces\RedactionInterface::class )
			);
		}
	);

	$container->share(
		Interfaces\CheckRunnerInterface::class,
		function ( $c ) {
			$runner = new Services\CheckRunner(
				$c->make( Interfaces\StorageInterface::class ),
				$c->make( Interfaces\RedactionInterface::class )
			);
			// Registra i check disponibili.
			$runner->add_check(
				new Checks\DatabaseCheck(
					$c->make( 'wpdb' ),
					$c->make( Interfaces\RedactionInterface::class )
				)
			);
			$runner->add_check(
				new Checks\ErrorLogCheck(
					$c->make( Interfaces\RedactionInterface::class )
				)
			);
			$runner->add_check(
				new Checks\RedisCheck(
					$c->make( Interfaces\RedactionInterface::class )
				)
			);
			return $runner;
		}
	);

	// Alert Manager con canali di notifica.
	$container->share(
		Interfaces\AlertManagerInterface::class,
		function ( $c ) {
			$storage   = $c->make( Interfaces\StorageInterface::class );
			$redaction = $c->make( Interfaces\RedactionInterface::class );
			$http      = $c->make( Interfaces\HttpClientInterface::class );

			$manager = new Services\AlertManager( $storage, $redaction );

			$manager->add_channel( new Channels\EmailChannel( $storage ) );
			$manager->add_channel(
				new Channels\WebhookChannel( $storage, $http )
			);
			$manager->add_channel(
				new Channels\SlackChannel( $storage, $http )
			);
			$manager->add_channel(
				new Channels\TelegramChannel( $storage, $http )
			);
			$manager->add_channel(
				new Channels\WhatsAppChannel( $storage, $http )
			);

			return $manager;
		}
	);

	// Scheduler con AlertManager integrato.
	$container->share(
		Services\Scheduler::class,
		function ( $c ) {
			return new Services\Scheduler(
				$c->make( Interfaces\CheckRunnerInterface::class ),
				$c->make( Interfaces\AlertManagerInterface::class )
			);
		}
	);

	// Configura i binding per l'interfaccia admin.
	$container->share(
		Admin\HealthScreen::class,
		function ( $c ) {
			return new Admin\HealthScreen(
				$c->make( Interfaces\CheckRunnerInterface::class )
			);
		}
	);

	// Pagina alert settings.
	$container->share(
		Admin\AlertSettings::class,
		function ( $c ) {
			return new Admin\AlertSettings(
				$c->make( Interfaces\StorageInterface::class )
			);
		}
	);

	// Menu admin con AlertSettings submenu.
	$container->share(
		Admin\Menu::class,
		function ( $c ) {
			return new Admin\Menu(
				$c->make( Admin\HealthScreen::class ),
				$c->make( Admin\AlertSettings::class )
			);
		}
	);

	// Crea e restituisce l'istanza del plugin.
	return new Plugin( $container );
}
