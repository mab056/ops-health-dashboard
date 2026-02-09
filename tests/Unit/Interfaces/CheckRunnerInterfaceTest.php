<?php
/**
 * Unit Test per CheckRunnerInterface
 *
 * Pattern enforcement test per CheckRunnerInterface.
 *
 * @package OpsHealthDashboard\Tests\Unit\Interfaces
 */

namespace OpsHealthDashboard\Tests\Unit\Interfaces;

use OpsHealthDashboard\Interfaces\CheckRunnerInterface;
use PHPUnit\Framework\TestCase;

/**
 * Class CheckRunnerInterfaceTest
 *
 * Test per verificare che CheckRunnerInterface esiste e ha i metodi richiesti.
 */
class CheckRunnerInterfaceTest extends TestCase {

	/**
	 * Testa che l'interfaccia esiste
	 */
	public function test_interface_exists() {
		$this->assertTrue( interface_exists( CheckRunnerInterface::class ) );
	}

	/**
	 * Testa che l'interfaccia ha il metodo add_check
	 */
	public function test_interface_has_add_check_method() {
		$reflection = new \ReflectionClass( CheckRunnerInterface::class );
		$this->assertTrue( $reflection->hasMethod( 'add_check' ) );
	}

	/**
	 * Testa che l'interfaccia ha il metodo run_all
	 */
	public function test_interface_has_run_all_method() {
		$reflection = new \ReflectionClass( CheckRunnerInterface::class );
		$this->assertTrue( $reflection->hasMethod( 'run_all' ) );
	}

	/**
	 * Testa che l'interfaccia ha il metodo get_latest_results
	 */
	public function test_interface_has_get_latest_results_method() {
		$reflection = new \ReflectionClass( CheckRunnerInterface::class );
		$this->assertTrue( $reflection->hasMethod( 'get_latest_results' ) );
	}
}
