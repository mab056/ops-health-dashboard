<?php
/**
 * Integration Test for Storage Service
 *
 * Integration test with real WordPress Options API.
 *
 * @package OpsHealthDashboard\Tests\Integration\Services
 */

namespace OpsHealthDashboard\Tests\Integration\Services;

use OpsHealthDashboard\Services\Storage;
use WP_UnitTestCase;

/**
 * Class StorageTest
 *
 * Integration test for Storage with real WordPress.
 */
class StorageTest extends WP_UnitTestCase {

	/**
	 * Tests that Storage can save and retrieve real values
	 */
	public function test_storage_can_save_and_retrieve_values() {
		$storage = new Storage();

		// Save a value.
		$result = $storage->set( 'test_integration', 'integration_value' );
		$this->assertTrue( $result, 'Set should return true' );

		// Retrieve the value.
		$value = $storage->get( 'test_integration' );
		$this->assertEquals( 'integration_value', $value, 'Retrieved value should match' );

		// Cleanup.
		$storage->delete( 'test_integration' );
	}

	/**
	 * Tests that Storage can check key existence
	 */
	public function test_storage_can_check_existence() {
		$storage = new Storage();

		// Verify that the key does not exist initially.
		$this->assertFalse( $storage->has( 'test_existence' ), 'Key should not exist initially' );

		// Save a value.
		$storage->set( 'test_existence', 'some_value' );

		// Verify that it now exists.
		$this->assertTrue( $storage->has( 'test_existence' ), 'Key should exist after set' );

		// Cleanup.
		$storage->delete( 'test_existence' );
	}

	/**
	 * Tests that Storage can delete values
	 */
	public function test_storage_can_delete_values() {
		$storage = new Storage();

		// Save a value.
		$storage->set( 'test_delete', 'to_be_deleted' );
		$this->assertTrue( $storage->has( 'test_delete' ), 'Key should exist before delete' );

		// Delete the value.
		$result = $storage->delete( 'test_delete' );
		$this->assertTrue( $result, 'Delete should return true' );

		// Verify that it no longer exists.
		$this->assertFalse( $storage->has( 'test_delete' ), 'Key should not exist after delete' );
	}

	/**
	 * Tests that Storage returns default when the key does not exist
	 */
	public function test_storage_returns_default_for_missing_keys() {
		$storage = new Storage();

		// Verify that it returns the default.
		$value = $storage->get( 'nonexistent_key', 'default_value' );
		$this->assertEquals( 'default_value', $value, 'Should return default for missing key' );
	}

	/**
	 * Tests that Storage can save arrays and serialized objects
	 */
	public function test_storage_can_save_complex_data() {
		$storage = new Storage();

		$complex_data = array(
			'string' => 'value',
			'number' => 42,
			'array'  => array( 1, 2, 3 ),
			'nested' => array(
				'key' => 'nested_value',
			),
		);

		// Save complex data.
		$storage->set( 'test_complex', $complex_data );

		// Retrieve and verify.
		$retrieved = $storage->get( 'test_complex' );
		$this->assertEquals( $complex_data, $retrieved, 'Complex data should match' );

		// Cleanup.
		$storage->delete( 'test_complex' );
	}

	/**
	 * Tests that Storage uses the correct prefix
	 */
	public function test_storage_uses_correct_prefix() {
		$storage = new Storage();

		// Save with Storage.
		$storage->set( 'prefix_test', 'value' );

		// Verify that WordPress has the key with prefix.
		$value = get_option( 'ops_health_prefix_test' );
		$this->assertEquals( 'value', $value, 'Option should exist with prefix' );

		// Cleanup.
		$storage->delete( 'prefix_test' );
	}
}
