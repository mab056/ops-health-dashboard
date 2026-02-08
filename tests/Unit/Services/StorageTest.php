<?php
/**
 * Unit Test per Storage Service
 *
 * Test unitario con Brain\Monkey per Storage (WordPress Options API wrapper).
 *
 * @package OpsHealthDashboard\Tests\Unit\Services
 */

namespace OpsHealthDashboard\Tests\Unit\Services;

use Brain\Monkey\Functions;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use OpsHealthDashboard\Interfaces\StorageInterface;
use OpsHealthDashboard\Services\Storage;
use PHPUnit\Framework\TestCase;

/**
 * Class StorageTest
 *
 * Unit test per Storage service.
 */
class StorageTest extends TestCase {
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
	 * Testa che Storage può essere istanziato
	 */
	public function test_storage_can_be_instantiated() {
		$storage = new Storage();
		$this->assertInstanceOf( Storage::class, $storage );
	}

	/**
	 * Testa che Storage implementa StorageInterface
	 */
	public function test_storage_implements_interface() {
		$storage = new Storage();
		$this->assertInstanceOf( StorageInterface::class, $storage );
	}

	/**
	 * Testa che get() recupera un valore con get_option()
	 */
	public function test_get_retrieves_value() {
		Functions\expect( 'get_option' )
			->once()
			->with( 'ops_health_test_key', null )
			->andReturn( 'test_value' );

		$storage = new Storage();
		$result  = $storage->get( 'test_key' );

		$this->assertEquals( 'test_value', $result );
	}

	/**
	 * Testa che get() ritorna il default se la chiave non esiste
	 */
	public function test_get_returns_default_when_key_not_exists() {
		Functions\expect( 'get_option' )
			->once()
			->with( 'ops_health_missing_key', 'default_value' )
			->andReturn( 'default_value' );

		$storage = new Storage();
		$result  = $storage->get( 'missing_key', 'default_value' );

		$this->assertEquals( 'default_value', $result );
	}

	/**
	 * Testa che set() salva un valore con update_option()
	 */
	public function test_set_saves_value() {
		Functions\expect( 'update_option' )
			->once()
			->with( 'ops_health_test_key', 'new_value' )
			->andReturn( true );

		$storage = new Storage();
		$result  = $storage->set( 'test_key', 'new_value' );

		$this->assertTrue( $result );
	}

	/**
	 * Testa che set() ritorna false in caso di errore
	 */
	public function test_set_returns_false_on_error() {
		Functions\expect( 'update_option' )
			->once()
			->with( 'ops_health_test_key', 'value' )
			->andReturn( false );

		$storage = new Storage();
		$result  = $storage->set( 'test_key', 'value' );

		$this->assertFalse( $result );
	}

	/**
	 * Testa che delete() cancella un valore con delete_option()
	 */
	public function test_delete_removes_value() {
		Functions\expect( 'delete_option' )
			->once()
			->with( 'ops_health_test_key' )
			->andReturn( true );

		$storage = new Storage();
		$result  = $storage->delete( 'test_key' );

		$this->assertTrue( $result );
	}

	/**
	 * Testa che delete() ritorna false se la chiave non esiste
	 */
	public function test_delete_returns_false_when_key_not_exists() {
		Functions\expect( 'delete_option' )
			->once()
			->with( 'ops_health_missing_key' )
			->andReturn( false );

		$storage = new Storage();
		$result  = $storage->delete( 'missing_key' );

		$this->assertFalse( $result );
	}

	/**
	 * Testa che has() ritorna true quando la chiave esiste (usa sentinel)
	 */
	public function test_has_returns_true_when_key_exists() {
		Functions\expect( 'get_option' )
			->once()
			->with( 'ops_health_test_key', \Mockery::type( 'object' ) )
			->andReturn( 'some_value' );

		$storage = new Storage();
		$result  = $storage->has( 'test_key' );

		$this->assertTrue( $result );
	}

	/**
	 * Testa che has() ritorna true anche quando il valore è false (sentinel distingue)
	 */
	public function test_has_returns_true_when_value_is_false() {
		Functions\expect( 'get_option' )
			->once()
			->with( 'ops_health_test_key', \Mockery::type( 'object' ) )
			->andReturn( false );

		$storage = new Storage();
		$result  = $storage->has( 'test_key' );

		$this->assertTrue( $result );
	}

	/**
	 * Testa che has() ritorna false quando get_option ritorna il sentinel
	 */
	public function test_has_returns_false_when_key_not_exists() {
		Functions\expect( 'get_option' )
			->once()
			->with( 'ops_health_missing_key', \Mockery::type( 'object' ) )
			->andReturnUsing( function ( $key, $default ) {
				return $default;
			} );

		$storage = new Storage();
		$result  = $storage->has( 'missing_key' );

		$this->assertFalse( $result );
	}

	/**
	 * Testa che la classe NON è final
	 */
	public function test_class_is_not_final() {
		$reflection = new \ReflectionClass( Storage::class );
		$this->assertFalse( $reflection->isFinal(), 'Storage should NOT be final' );
	}

	/**
	 * Testa che NON esistono metodi static
	 */
	public function test_no_static_methods() {
		$reflection = new \ReflectionClass( Storage::class );
		$methods    = $reflection->getMethods( \ReflectionMethod::IS_STATIC );

		$static_methods = array_filter(
			$methods,
			function ( $method ) {
				return ! str_starts_with( $method->getName(), '__' );
			}
		);

		$this->assertEmpty( $static_methods, 'Storage should have NO static methods' );
	}
}
