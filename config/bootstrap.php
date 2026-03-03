<?php
/**
 * Plugin Bootstrap Configuration
 *
 * Creates and configures the DI container, returns the Plugin instance.
 * NO singleton pattern, NO static factory.
 *
 * @package OpsHealthDashboard
 */

namespace OpsHealthDashboard;

if ( ! defined( 'ABSPATH' ) ) {
	// phpcs:ignore Squiz.Commenting.InlineComment.InvalidEndChar
	// @codeCoverageIgnoreStart
	exit;
	// phpcs:ignore Squiz.Commenting.InlineComment.InvalidEndChar
	// @codeCoverageIgnoreEnd
}

use OpsHealthDashboard\Core\Container;
use OpsHealthDashboard\Core\Plugin;

/**
 * Bootstrap function
 *
 * Creates the container, configures bindings, returns the plugin instance.
 * Called from the main plugin file on the 'plugins_loaded' hook.
 *
 * @return Plugin Plugin instance with configured container.
 */
function bootstrap(): Plugin {
	// Create a new container instance.
	$container = new Container();

	// Register the wpdb instance in the container.
	global $wpdb;
	$container->instance( 'wpdb', $wpdb );

	// Configure service bindings (shared instances).
	$container->share(
		Interfaces\StorageInterface::class,
		// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		function ( $c ) {
			return new Services\Storage();
		}
	);

	// Sensitive data redaction service.
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

	// HTTP client with anti-SSRF protection.
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
			// Register available checks.
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
			$runner->add_check(
				new Checks\DiskCheck(
					$c->make( Interfaces\RedactionInterface::class )
				)
			);
			$runner->add_check( new Checks\VersionsCheck() );
			return $runner;
		}
	);

	// Alert Manager with notification channels.
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

	// Scheduler with integrated AlertManager.
	$container->share(
		Services\Scheduler::class,
		function ( $c ) {
			return new Services\Scheduler(
				$c->make( Interfaces\CheckRunnerInterface::class ),
				$c->make( Interfaces\AlertManagerInterface::class )
			);
		}
	);

	// Configure bindings for the admin interface.
	$container->share(
		Admin\HealthScreen::class,
		function ( $c ) {
			return new Admin\HealthScreen(
				$c->make( Interfaces\CheckRunnerInterface::class ),
				$c->make( Interfaces\StorageInterface::class )
			);
		}
	);

	// Alert settings page.
	$container->share(
		Admin\AlertSettings::class,
		function ( $c ) {
			return new Admin\AlertSettings(
				$c->make( Interfaces\StorageInterface::class )
			);
		}
	);

	// Dashboard widget for overall status.
	$container->share(
		Admin\DashboardWidget::class,
		function ( $c ) {
			return new Admin\DashboardWidget(
				$c->make( Interfaces\CheckRunnerInterface::class ),
				$c->make( Interfaces\StorageInterface::class )
			);
		}
	);

	// Admin menu with AlertSettings submenu.
	$container->share(
		Admin\Menu::class,
		function ( $c ) {
			return new Admin\Menu(
				$c->make( Admin\HealthScreen::class ),
				$c->make( Admin\AlertSettings::class )
			);
		}
	);

	// Create and return the plugin instance.
	return new Plugin( $container );
}
