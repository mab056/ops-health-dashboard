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
	 * Testa che l'interfaccia esiste ed è un'interfaccia
	 */
	public function test_is_interface() {
		$reflection = new \ReflectionClass( CheckInterface::class );
		$this->assertTrue( $reflection->isInterface(), 'CheckInterface should be an interface' );
	}

	/**
	 * Testa che l'interfaccia ha il metodo run() pubblico con 0 parametri
	 */
	public function test_has_run_method() {
		$reflection = new \ReflectionClass( CheckInterface::class );
		$this->assertTrue( $reflection->hasMethod( 'run' ) );

		$method = $reflection->getMethod( 'run' );
		$this->assertTrue( $method->isPublic() );
		$this->assertCount( 0, $method->getParameters() );
	}

	/**
	 * Testa che l'interfaccia ha il metodo get_id() pubblico con 0 parametri
	 */
	public function test_has_get_id_method() {
		$reflection = new \ReflectionClass( CheckInterface::class );
		$this->assertTrue( $reflection->hasMethod( 'get_id' ) );

		$method = $reflection->getMethod( 'get_id' );
		$this->assertTrue( $method->isPublic() );
		$this->assertCount( 0, $method->getParameters() );
	}

	/**
	 * Testa che l'interfaccia ha il metodo get_name() pubblico con 0 parametri
	 */
	public function test_has_get_name_method() {
		$reflection = new \ReflectionClass( CheckInterface::class );
		$this->assertTrue( $reflection->hasMethod( 'get_name' ) );

		$method = $reflection->getMethod( 'get_name' );
		$this->assertTrue( $method->isPublic() );
		$this->assertCount( 0, $method->getParameters() );
	}

	/**
	 * Testa che l'interfaccia ha il metodo is_enabled() pubblico con 0 parametri
	 */
	public function test_has_is_enabled_method() {
		$reflection = new \ReflectionClass( CheckInterface::class );
		$this->assertTrue( $reflection->hasMethod( 'is_enabled' ) );

		$method = $reflection->getMethod( 'is_enabled' );
		$this->assertTrue( $method->isPublic() );
		$this->assertCount( 0, $method->getParameters() );
	}
}
