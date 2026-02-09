<?php
/**
 * Unit Test per HealthScreen
 *
 * Test unitario con Brain\Monkey per HealthScreen.
 *
 * @package OpsHealthDashboard\Tests\Unit\Admin
 */

namespace OpsHealthDashboard\Tests\Unit\Admin;

use Brain\Monkey\Functions;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use OpsHealthDashboard\Admin\HealthScreen;
use OpsHealthDashboard\Interfaces\CheckRunnerInterface;
use PHPUnit\Framework\TestCase;

/**
 * Class HealthScreenTest
 *
 * Unit test per HealthScreen.
 */
class HealthScreenTest extends TestCase {
	use MockeryPHPUnitIntegration;

	/**
	 * Setup per ogni test
	 */
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();
	}

	/**
	 * Teardown dopo ogni test
	 */
	protected function tearDown(): void {
		\Brain\Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Testa che HealthScreen può essere istanziato con dipendenze
	 */
	public function test_health_screen_can_be_instantiated() {
		$runner = Mockery::mock( CheckRunnerInterface::class );
		$screen = new HealthScreen( $runner );

		$this->assertInstanceOf( HealthScreen::class, $screen );
	}

	/**
	 * Testa che render() mostra i risultati dei check
	 */
	public function test_render_shows_check_results() {
		$runner = Mockery::mock( CheckRunnerInterface::class );
		$runner->shouldReceive( 'get_latest_results' )
			->once()
			->andReturn( [
				'database' => [
					'status'  => 'ok',
					'message' => 'Database OK',
					'name'    => 'Database Connection',
					'details' => [],
				],
			] );

		$this->mock_render_functions();

		$screen = new HealthScreen( $runner );

		ob_start();
		$screen->render();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Ops Health Dashboard', $output );
		$this->assertStringContainsString( 'Database Connection', $output );
		$this->assertStringContainsString( 'ops-health-check-ok', $output );
	}

	/**
	 * Testa che render() mostra messaggio "no checks" quando non ci sono risultati
	 */
	public function test_render_shows_no_checks_message_when_empty() {
		$runner = Mockery::mock( CheckRunnerInterface::class );
		$runner->shouldReceive( 'get_latest_results' )
			->once()
			->andReturn( [] );

		$this->mock_render_functions();

		$screen = new HealthScreen( $runner );

		ob_start();
		$screen->render();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'No health checks have been run yet.', $output );
		$this->assertStringContainsString( 'notice-info', $output );
	}

	/**
	 * Testa che render() blocca utenti senza capability
	 */
	public function test_render_blocks_unauthorized_users() {
		$runner = Mockery::mock( CheckRunnerInterface::class );

		Functions\expect( 'current_user_can' )
			->once()
			->with( 'manage_options' )
			->andReturn( false );

		Functions\expect( 'esc_html__' )
			->andReturnUsing( function ( $text ) {
				return $text;
			} );

		// wp_die lancia eccezione per fermare l'esecuzione come in WordPress reale.
		Functions\expect( 'wp_die' )
			->once()
			->with( \Mockery::type( 'string' ) )
			->andReturnUsing( function () {
				throw new \RuntimeException( 'wp_die called' );
			} );

		$screen = new HealthScreen( $runner );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'wp_die called' );
		$screen->render();
	}

	/**
	 * Testa che render() gestisce risultati senza chiavi status/message
	 */
	public function test_render_handles_result_with_missing_keys() {
		$runner = Mockery::mock( CheckRunnerInterface::class );
		$runner->shouldReceive( 'get_latest_results' )
			->once()
			->andReturn( [
				'test_check' => [
					'details' => [],
				],
			] );

		$this->mock_render_functions();

		$screen = new HealthScreen( $runner );

		ob_start();
		$screen->render();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'ops-health-check-unknown', $output );
		$this->assertStringContainsString( 'Test_check', $output );
	}

	/**
	 * Helper: mock comuni per render con bottoni
	 *
	 * @return void
	 */
	private function mock_render_functions() {
		Functions\expect( 'current_user_can' )
			->with( 'manage_options' )
			->andReturn( true );

		Functions\expect( 'esc_html__' )
			->andReturnUsing( function ( $text ) {
				return $text;
			} );

		Functions\expect( 'esc_html' )
			->andReturnUsing( function ( $text ) {
				return $text;
			} );

		Functions\expect( 'esc_attr' )
			->andReturnUsing( function ( $text ) {
				return $text;
			} );

		Functions\expect( 'wp_nonce_field' )
			->andReturnUsing( function ( $action, $name ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo '<input type="hidden" name="' . $name . '" value="nonce_token" />';
			} );

		Functions\expect( 'submit_button' )
			->andReturnUsing( function ( $text, $type, $name ) {
				echo '<input type="submit" name="' . $name . '" value="' . $text . '" class="button ' . $type . '" />';
			} );

		Functions\expect( 'get_transient' )
			->andReturn( false );
	}

	/**
	 * Testa che render() contiene il bottone Run Now
	 */
	public function test_render_contains_run_now_button() {
		$runner = Mockery::mock( CheckRunnerInterface::class );
		$runner->shouldReceive( 'get_latest_results' )->andReturn( [] );

		$this->mock_render_functions();

		$screen = new HealthScreen( $runner );

		ob_start();
		$screen->render();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'run_now', $output );
	}

	/**
	 * Testa che render() contiene il bottone Clear Cache
	 */
	public function test_render_contains_clear_cache_button() {
		$runner = Mockery::mock( CheckRunnerInterface::class );
		$runner->shouldReceive( 'get_latest_results' )->andReturn( [] );

		$this->mock_render_functions();

		$screen = new HealthScreen( $runner );

		ob_start();
		$screen->render();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'clear_cache', $output );
	}

	/**
	 * Testa che render() contiene i campi nonce per sicurezza
	 */
	public function test_render_contains_nonce_fields() {
		$runner = Mockery::mock( CheckRunnerInterface::class );
		$runner->shouldReceive( 'get_latest_results' )->andReturn( [] );

		$this->mock_render_functions();

		$screen = new HealthScreen( $runner );

		ob_start();
		$screen->render();
		$output = ob_get_clean();

		$this->assertStringContainsString( '_ops_health_nonce', $output );
	}

	/**
	 * Testa che la classe NON è final
	 */
	public function test_class_is_not_final() {
		$reflection = new \ReflectionClass( HealthScreen::class );
		$this->assertFalse( $reflection->isFinal(), 'HealthScreen should NOT be final' );
	}

	/**
	 * Testa che NON esistono metodi static
	 */
	public function test_no_static_methods() {
		$reflection = new \ReflectionClass( HealthScreen::class );
		$methods    = $reflection->getMethods( \ReflectionMethod::IS_STATIC );

		$static_methods = array_filter(
			$methods,
			function ( $method ) {
				return strpos( $method->getName(), '__' ) !== 0;
			}
		);

		$this->assertEmpty( $static_methods, 'HealthScreen should have NO static methods' );
	}

	/**
	 * Testa che NON esistono proprietà static
	 */
	public function test_no_static_properties() {
		$reflection = new \ReflectionClass( HealthScreen::class );
		$properties = $reflection->getProperties( \ReflectionProperty::IS_STATIC );

		$this->assertEmpty( $properties, 'HealthScreen should have NO static properties' );
	}
}
