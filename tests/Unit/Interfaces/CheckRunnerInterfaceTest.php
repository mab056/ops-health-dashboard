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
	 * Testa che l'interfaccia esiste ed è un'interfaccia
	 */
	public function test_is_interface() {
		$reflection = new \ReflectionClass( CheckRunnerInterface::class );
		$this->assertTrue( $reflection->isInterface(), 'CheckRunnerInterface should be an interface' );
	}

	/**
	 * Testa che l'interfaccia ha il metodo add_check pubblico con 1 parametro
	 */
	public function test_interface_has_add_check_method() {
		$reflection = new \ReflectionClass( CheckRunnerInterface::class );
		$this->assertTrue( $reflection->hasMethod( 'add_check' ) );

		$method = $reflection->getMethod( 'add_check' );
		$this->assertTrue( $method->isPublic() );
		$this->assertCount( 1, $method->getParameters() );
	}

	/**
	 * Testa che l'interfaccia ha il metodo run_all pubblico con 0 parametri
	 */
	public function test_interface_has_run_all_method() {
		$reflection = new \ReflectionClass( CheckRunnerInterface::class );
		$this->assertTrue( $reflection->hasMethod( 'run_all' ) );

		$method = $reflection->getMethod( 'run_all' );
		$this->assertTrue( $method->isPublic() );
		$this->assertCount( 0, $method->getParameters() );
	}

	/**
	 * Testa che l'interfaccia ha il metodo get_latest_results pubblico con 0 parametri
	 */
	public function test_interface_has_get_latest_results_method() {
		$reflection = new \ReflectionClass( CheckRunnerInterface::class );
		$this->assertTrue( $reflection->hasMethod( 'get_latest_results' ) );

		$method = $reflection->getMethod( 'get_latest_results' );
		$this->assertTrue( $method->isPublic() );
		$this->assertCount( 0, $method->getParameters() );
	}

	/**
	 * Testa che l'interfaccia ha il metodo clear_results pubblico con 0 parametri
	 */
	public function test_interface_has_clear_results_method() {
		$reflection = new \ReflectionClass( CheckRunnerInterface::class );
		$this->assertTrue( $reflection->hasMethod( 'clear_results' ) );

		$method = $reflection->getMethod( 'clear_results' );
		$this->assertTrue( $method->isPublic() );
		$this->assertCount( 0, $method->getParameters() );
	}
}
