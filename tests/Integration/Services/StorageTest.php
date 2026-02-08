<?php
/**
 * Integration Test per Storage Service
 *
 * Test di integrazione con WordPress Options API reale.
 *
 * @package OpsHealthDashboard\Tests\Integration\Services
 */

namespace OpsHealthDashboard\Tests\Integration\Services;

use OpsHealthDashboard\Services\Storage;
use WP_UnitTestCase;

/**
 * Class StorageTest
 *
 * Integration test per Storage con WordPress reale.
 */
class StorageTest extends WP_UnitTestCase {

	/**
	 * Testa che Storage può salvare e recuperare valori reali
	 */
	public function test_storage_can_save_and_retrieve_values() {
		$storage = new Storage();

		// Salva un valore.
		$result = $storage->set( 'test_integration', 'integration_value' );
		$this->assertTrue( $result, 'Set should return true' );

		// Recupera il valore.
		$value = $storage->get( 'test_integration' );
		$this->assertEquals( 'integration_value', $value, 'Retrieved value should match' );

		// Cleanup.
		$storage->delete( 'test_integration' );
	}

	/**
	 * Testa che Storage può verificare l'esistenza di chiavi
	 */
	public function test_storage_can_check_existence() {
		$storage = new Storage();

		// Verifica che la chiave non esiste prima.
		$this->assertFalse( $storage->has( 'test_existence' ), 'Key should not exist initially' );

		// Salva un valore.
		$storage->set( 'test_existence', 'some_value' );

		// Verifica che ora esiste.
		$this->assertTrue( $storage->has( 'test_existence' ), 'Key should exist after set' );

		// Cleanup.
		$storage->delete( 'test_existence' );
	}

	/**
	 * Testa che Storage può cancellare valori
	 */
	public function test_storage_can_delete_values() {
		$storage = new Storage();

		// Salva un valore.
		$storage->set( 'test_delete', 'to_be_deleted' );
		$this->assertTrue( $storage->has( 'test_delete' ), 'Key should exist before delete' );

		// Cancella il valore.
		$result = $storage->delete( 'test_delete' );
		$this->assertTrue( $result, 'Delete should return true' );

		// Verifica che non esiste più.
		$this->assertFalse( $storage->has( 'test_delete' ), 'Key should not exist after delete' );
	}

	/**
	 * Testa che Storage ritorna default quando la chiave non esiste
	 */
	public function test_storage_returns_default_for_missing_keys() {
		$storage = new Storage();

		// Verifica che ritorna il default.
		$value = $storage->get( 'nonexistent_key', 'default_value' );
		$this->assertEquals( 'default_value', $value, 'Should return default for missing key' );
	}

	/**
	 * Testa che Storage può salvare array e oggetti serializzati
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

		// Salva dati complessi.
		$storage->set( 'test_complex', $complex_data );

		// Recupera e verifica.
		$retrieved = $storage->get( 'test_complex' );
		$this->assertEquals( $complex_data, $retrieved, 'Complex data should match' );

		// Cleanup.
		$storage->delete( 'test_complex' );
	}

	/**
	 * Testa che Storage usa il prefisso corretto
	 */
	public function test_storage_uses_correct_prefix() {
		$storage = new Storage();

		// Salva con Storage.
		$storage->set( 'prefix_test', 'value' );

		// Verifica che WordPress ha la chiave con prefisso.
		$value = get_option( 'ops_health_prefix_test' );
		$this->assertEquals( 'value', $value, 'Option should exist with prefix' );

		// Cleanup.
		$storage->delete( 'prefix_test' );
	}
}
