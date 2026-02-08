<?php
/**
 * Unit Test per CheckInterface
 *
 * Test unitario che verifica l'esistenza e i metodi dell'interfaccia.
 *
 * @package OpsHealthDashboard\Tests\Unit\Interfaces
 */

namespace OpsHealthDashboard\Tests\Unit\Interfaces;

use OpsHealthDashboard\Interfaces\CheckInterface;
use PHPUnit\Framework\TestCase;

/**
 * Class CheckInterfaceTest
 *
 * Unit test per CheckInterface.
 */
class CheckInterfaceTest extends TestCase {

	/**
	 * Testa che l'interfaccia esiste
	 */
	public function test_interface_exists() {
		$this->assertTrue( interface_exists( CheckInterface::class ) );
	}

	/**
	 * Testa che l'interfaccia ha il metodo run()
	 */
	public function test_has_run_method() {
		$this->assertTrue( method_exists( CheckInterface::class, 'run' ) );
	}

	/**
	 * Testa che l'interfaccia ha il metodo get_id()
	 */
	public function test_has_get_id_method() {
		$this->assertTrue( method_exists( CheckInterface::class, 'get_id' ) );
	}

	/**
	 * Testa che l'interfaccia ha il metodo get_name()
	 */
	public function test_has_get_name_method() {
		$this->assertTrue( method_exists( CheckInterface::class, 'get_name' ) );
	}

	/**
	 * Testa che l'interfaccia ha il metodo is_enabled()
	 */
	public function test_has_is_enabled_method() {
		$this->assertTrue( method_exists( CheckInterface::class, 'is_enabled' ) );
	}
}
