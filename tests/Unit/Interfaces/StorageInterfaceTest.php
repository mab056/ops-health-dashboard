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
	 * Testa che l'interfaccia esiste ed è un'interfaccia
	 */
	public function test_is_interface() {
		$reflection = new \ReflectionClass( StorageInterface::class );
		$this->assertTrue( $reflection->isInterface(), 'StorageInterface should be an interface' );
	}

	/**
	 * Testa che l'interfaccia ha il metodo get() pubblico con 2 parametri
	 */
	public function test_has_get_method() {
		$reflection = new \ReflectionClass( StorageInterface::class );
		$this->assertTrue( $reflection->hasMethod( 'get' ) );

		$method = $reflection->getMethod( 'get' );
		$this->assertTrue( $method->isPublic() );
		$this->assertCount( 2, $method->getParameters() );
	}

	/**
	 * Testa che l'interfaccia ha il metodo set() pubblico con 2 parametri
	 */
	public function test_has_set_method() {
		$reflection = new \ReflectionClass( StorageInterface::class );
		$this->assertTrue( $reflection->hasMethod( 'set' ) );

		$method = $reflection->getMethod( 'set' );
		$this->assertTrue( $method->isPublic() );
		$this->assertCount( 2, $method->getParameters() );
	}

	/**
	 * Testa che l'interfaccia ha il metodo delete() pubblico con 1 parametro
	 */
	public function test_has_delete_method() {
		$reflection = new \ReflectionClass( StorageInterface::class );
		$this->assertTrue( $reflection->hasMethod( 'delete' ) );

		$method = $reflection->getMethod( 'delete' );
		$this->assertTrue( $method->isPublic() );
		$this->assertCount( 1, $method->getParameters() );
	}

	/**
	 * Testa che l'interfaccia ha il metodo has() pubblico con 1 parametro
	 */
	public function test_has_has_method() {
		$reflection = new \ReflectionClass( StorageInterface::class );
		$this->assertTrue( $reflection->hasMethod( 'has' ) );

		$method = $reflection->getMethod( 'has' );
		$this->assertTrue( $method->isPublic() );
		$this->assertCount( 1, $method->getParameters() );
	}
}
