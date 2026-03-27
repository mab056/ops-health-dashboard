<?php
/**
 * Test for ConsentIntegration
 *
 * @package OpsHealthDashboard\Tests\Unit\Core
 */

namespace OpsHealthDashboard\Tests\Unit\Core;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use OpsHealthDashboard\Core\ConsentIntegration;
use PHPUnit\Framework\TestCase;

/**
 * Class ConsentIntegrationTest
 *
 * TDD tests for WP Consent API integration.
 */
class ConsentIntegrationTest extends TestCase {
	use MockeryPHPUnitIntegration;

	/**
	 * Set up Brain\Monkey and constants
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		if ( ! defined( 'OPS_HEALTH_DASHBOARD_FILE' ) ) {
			define( 'OPS_HEALTH_DASHBOARD_FILE', '/fake/ops-health-dashboard.php' );
		}
	}

	/**
	 * Tear down Brain\Monkey
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Tests that ConsentIntegration can be instantiated
	 */
	public function test_can_be_instantiated() {
		$consent = new ConsentIntegration();

		$this->assertInstanceOf( ConsentIntegration::class, $consent );
	}

	/**
	 * Tests that the class is NOT final
	 */
	public function test_class_is_not_final() {
		$reflection = new \ReflectionClass( ConsentIntegration::class );
		$this->assertFalse( $reflection->isFinal(), 'ConsentIntegration should NOT be final' );
	}

	/**
	 * Tests that NO static methods exist
	 */
	public function test_no_static_methods() {
		$reflection = new \ReflectionClass( ConsentIntegration::class );
		$methods    = $reflection->getMethods( \ReflectionMethod::IS_STATIC );

		$static_methods = array_filter( $methods, function ( $method ) {
			return strpos( $method->getName(), '__' ) !== 0;
		} );

		$this->assertEmpty( $static_methods, 'ConsentIntegration should have NO static methods' );
	}

	/**
	 * Tests that NO static properties exist
	 */
	public function test_no_static_properties() {
		$reflection = new \ReflectionClass( ConsentIntegration::class );
		$properties = $reflection->getProperties( \ReflectionProperty::IS_STATIC );

		$static_props = array_filter( $properties, function ( $prop ) {
			return strpos( $prop->getName(), '__' ) !== 0;
		} );

		$this->assertEmpty( $static_props, 'ConsentIntegration should have NO static properties' );
	}

	/**
	 * Tests that register_hooks adds the consent API filter
	 */
	public function test_register_hooks_adds_consent_api_filter() {
		$filter_name     = null;
		$filter_callback = null;

		Functions\expect( 'add_filter' )
			->once()
			->andReturnUsing( function ( $name, $callback ) use ( &$filter_name, &$filter_callback ) {
				$filter_name     = $name;
				$filter_callback = $callback;
				return true;
			} );

		Functions\expect( 'plugin_basename' )
			->once()
			->andReturn( 'ops-health-dashboard/ops-health-dashboard.php' );

		Functions\expect( 'add_action' )
			->once()
			->with( 'admin_init', \Mockery::type( 'array' ) );

		$consent = new ConsentIntegration();
		$consent->register_hooks();

		$this->assertSame(
			'wp_consent_api_registered_ops-health-dashboard/ops-health-dashboard.php',
			$filter_name
		);
	}

	/**
	 * Tests that the consent API filter callback returns true
	 */
	public function test_consent_api_filter_returns_true() {
		$filter_callback = null;

		Functions\expect( 'add_filter' )
			->once()
			->andReturnUsing( function ( $name, $callback ) use ( &$filter_callback ) {
				$filter_callback = $callback;
				return true;
			} );

		Functions\expect( 'plugin_basename' )
			->once()
			->andReturn( 'ops-health-dashboard/ops-health-dashboard.php' );

		Functions\expect( 'add_action' )
			->once();

		$consent = new ConsentIntegration();
		$consent->register_hooks();

		$this->assertTrue( $filter_callback() );
	}

	/**
	 * Tests that register_hooks adds privacy policy action
	 */
	public function test_register_hooks_adds_privacy_policy_action() {
		$action_name     = null;
		$action_callback = null;

		Functions\expect( 'add_filter' )
			->once();

		Functions\expect( 'plugin_basename' )
			->once()
			->andReturn( 'ops-health-dashboard/ops-health-dashboard.php' );

		Functions\expect( 'add_action' )
			->once()
			->andReturnUsing( function ( $name, $callback ) use ( &$action_name, &$action_callback ) {
				$action_name     = $name;
				$action_callback = $callback;
				return true;
			} );

		$consent = new ConsentIntegration();
		$consent->register_hooks();

		$this->assertSame( 'admin_init', $action_name );
	}

	/**
	 * Tests that add_privacy_policy_content calls wp_add_privacy_policy_content
	 */
	public function test_add_privacy_policy_content_calls_wp_function() {
		$policy_plugin_name = null;
		$policy_content     = null;

		Functions\expect( 'wp_add_privacy_policy_content' )
			->once()
			->andReturnUsing( function ( $name, $content ) use ( &$policy_plugin_name, &$policy_content ) {
				$policy_plugin_name = $name;
				$policy_content     = $content;
			} );

		Functions\expect( '__' )
			->andReturnUsing( function ( $text ) {
				return $text;
			} );

		$consent = new ConsentIntegration();
		$consent->add_privacy_policy_content();

		$this->assertSame( 'Ops Health Dashboard', $policy_plugin_name );
		$this->assertStringContainsString( '<h2>', $policy_content );
	}

	/**
	 * Tests that privacy policy content mentions external services
	 */
	public function test_privacy_policy_mentions_external_services() {
		$policy_content = null;

		Functions\expect( 'wp_add_privacy_policy_content' )
			->once()
			->andReturnUsing( function ( $name, $content ) use ( &$policy_content ) {
				$policy_content = $content;
			} );

		Functions\expect( '__' )
			->andReturnUsing( function ( $text ) {
				return $text;
			} );

		$consent = new ConsentIntegration();
		$consent->add_privacy_policy_content();

		$this->assertStringContainsString( 'Email', $policy_content );
		$this->assertStringContainsString( 'Slack', $policy_content );
		$this->assertStringContainsString( 'Telegram', $policy_content );
		$this->assertStringContainsString( 'WhatsApp', $policy_content );
		$this->assertStringContainsString( 'Webhook', $policy_content );
	}

	/**
	 * Tests that privacy policy content mentions no cookies
	 */
	public function test_privacy_policy_mentions_no_cookies() {
		$policy_content = null;

		Functions\expect( 'wp_add_privacy_policy_content' )
			->once()
			->andReturnUsing( function ( $name, $content ) use ( &$policy_content ) {
				$policy_content = $content;
			} );

		Functions\expect( '__' )
			->andReturnUsing( function ( $text ) {
				return $text;
			} );

		$consent = new ConsentIntegration();
		$consent->add_privacy_policy_content();

		$this->assertStringContainsString( 'cookie', strtolower( $policy_content ) );
	}

	/**
	 * Tests that privacy policy content mentions admin-only
	 */
	public function test_privacy_policy_mentions_admin_only() {
		$policy_content = null;

		Functions\expect( 'wp_add_privacy_policy_content' )
			->once()
			->andReturnUsing( function ( $name, $content ) use ( &$policy_content ) {
				$policy_content = $content;
			} );

		Functions\expect( '__' )
			->andReturnUsing( function ( $text ) {
				return $text;
			} );

		$consent = new ConsentIntegration();
		$consent->add_privacy_policy_content();

		$this->assertStringContainsString( 'admin', strtolower( $policy_content ) );
	}

	/**
	 * Tests that privacy policy content mentions redaction
	 */
	public function test_privacy_policy_mentions_redaction() {
		$policy_content = null;

		Functions\expect( 'wp_add_privacy_policy_content' )
			->once()
			->andReturnUsing( function ( $name, $content ) use ( &$policy_content ) {
				$policy_content = $content;
			} );

		Functions\expect( '__' )
			->andReturnUsing( function ( $text ) {
				return $text;
			} );

		$consent = new ConsentIntegration();
		$consent->add_privacy_policy_content();

		$this->assertStringContainsString( 'redact', strtolower( $policy_content ) );
	}

	/**
	 * Tests that get_plugin_basename returns correct value
	 */
	public function test_get_plugin_basename_returns_correct_value() {
		Functions\expect( 'plugin_basename' )
			->once()
			->andReturn( 'ops-health-dashboard/ops-health-dashboard.php' );

		$consent    = new ConsentIntegration();
		$reflection = new \ReflectionMethod( $consent, 'get_plugin_basename' );
		$reflection->setAccessible( true );

		$result = $reflection->invoke( $consent );

		$this->assertSame( 'ops-health-dashboard/ops-health-dashboard.php', $result );
	}
}
