<?php
/**
 * Unit Test per StorageInterface
 *
 * Test unitario che verifica l'esistenza e i metodi dell'interfaccia.
 *
 * @package OpsHealthDashboard\Tests\Unit\Interfaces
 */

namespace OpsHealthDashboard\Tests\Unit\Interfaces;

use OpsHealthDashboard\Interfaces\StorageInterface;
use PHPUnit\Framework\TestCase;

/**
 * Class StorageInterfaceTest
 *
 * Unit test per StorageInterface.
 */
class StorageInterfaceTest extends TestCase {

	/**
	 * Testa che l'interfaccia esiste
	 */
	public function test_interface_exists() {
		$this->assertTrue( interface_exists( StorageInterface::class ) );
	}

	/**
	 * Testa che l'interfaccia ha il metodo get()
	 */
	public function test_has_get_method() {
		$this->assertTrue( method_exists( StorageInterface::class, 'get' ) );
	}

	/**
	 * Testa che l'interfaccia ha il metodo set()
	 */
	public function test_has_set_method() {
		$this->assertTrue( method_exists( StorageInterface::class, 'set' ) );
	}

	/**
	 * Testa che l'interfaccia ha il metodo delete()
	 */
	public function test_has_delete_method() {
		$this->assertTrue( method_exists( StorageInterface::class, 'delete' ) );
	}

	/**
	 * Testa che l'interfaccia ha il metodo has()
	 */
	public function test_has_has_method() {
		$this->assertTrue( method_exists( StorageInterface::class, 'has' ) );
	}
}
