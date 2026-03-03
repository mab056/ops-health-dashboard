<?php
/**
 * Unit Test for Storage Service
 *
 * Unit test with Brain\Monkey per Storage (WordPress Options API wrapper).
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
 * Unit test for Storage service.
 */
class StorageTest extends TestCase {
	use MockeryPHPUnitIntegration;

	/**
	 * Setup for each test
	 */
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();
	}

	/**
	 * Teardown after each test
	 */
	protected function tearDown(): void {
		\Brain\Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Tests that Storage can be instantiated
	 */
	public function test_storage_can_be_instantiated() {
		$storage = new Storage();
		$this->assertInstanceOf( Storage::class, $storage );
	}

	/**
	 * Tests that Storage implements StorageInterface
	 */
	public function test_storage_implements_interface() {
		$storage = new Storage();
		$this->assertInstanceOf( StorageInterface::class, $storage );
	}

	/**
	 * Tests that get() retrieves a value with get_option()
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
	 * Tests that get() returns the default if the key does not exist
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
	 * Tests that set() saves a value with update_option() without autoload
	 */
	public function test_set_saves_value() {
		Functions\expect( 'update_option' )
			->once()
			->with( 'ops_health_test_key', 'new_value', false )
			->andReturn( true );

		$storage = new Storage();
		$result  = $storage->set( 'test_key', 'new_value' );

		$this->assertTrue( $result );
	}

	/**
	 * Tests that set() returns false on error
	 */
	public function test_set_returns_false_on_error() {
		Functions\expect( 'update_option' )
			->once()
			->with( 'ops_health_test_key', 'value', false )
			->andReturn( false );

		$storage = new Storage();
		$result  = $storage->set( 'test_key', 'value' );

		$this->assertFalse( $result );
	}

	/**
	 * Tests that delete() deletes a value with delete_option()
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
	 * Tests that delete() returns false if the key does not exist
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
	 * Tests that has() returns true when the key exists (uses sentinel)
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
	 * Tests that has() returns true even when the value is false (sentinel distinguishes)
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
	 * Tests that has() returns false when get_option returns the sentinel
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
	 * Tests that the class is NOT final
	 */
	public function test_class_is_not_final() {
		$reflection = new \ReflectionClass( Storage::class );
		$this->assertFalse( $reflection->isFinal(), 'Storage should NOT be final' );
	}

	/**
	 * Tests that there are NO static methods
	 */
	public function test_no_static_methods() {
		$reflection = new \ReflectionClass( Storage::class );
		$methods    = $reflection->getMethods( \ReflectionMethod::IS_STATIC );

		$static_methods = array_filter(
			$methods,
			function ( $method ) {
				return strpos( $method->getName(), '__' ) !== 0;
			}
		);

		$this->assertEmpty( $static_methods, 'Storage should have NO static methods' );
	}

	/**
	 * Tests that there are NO static properties
	 */
	public function test_no_static_properties() {
		$reflection = new \ReflectionClass( Storage::class );
		$properties = $reflection->getProperties( \ReflectionProperty::IS_STATIC );

		$this->assertEmpty( $properties, 'Storage should have NO static properties' );
	}
}
