<?php
/**
 * Test per AlertManagerInterface
 *
 * Verifica che il contratto dell'interfaccia AlertManager sia corretto.
 *
 * @package OpsHealthDashboard\Tests\Unit\Interfaces
 */

namespace OpsHealthDashboard\Tests\Unit\Interfaces;

use OpsHealthDashboard\Interfaces\AlertManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * Class AlertManagerInterfaceTest
 *
 * Test per l'interfaccia AlertManagerInterface.
 */
class AlertManagerInterfaceTest extends TestCase {

	/**
	 * Verifica che l'interfaccia è un'interfaccia
	 *
	 * @return void
	 */
	public function test_is_interface() {
		$reflection = new \ReflectionClass( AlertManagerInterface::class );
		$this->assertTrue( $reflection->isInterface() );
	}

	/**
	 * Verifica che dichiara add_channel
	 *
	 * @return void
	 */
	public function test_interface_declares_add_channel() {
		$reflection = new \ReflectionClass( AlertManagerInterface::class );
		$this->assertTrue( $reflection->hasMethod( 'add_channel' ) );

		$method = $reflection->getMethod( 'add_channel' );
		$this->assertTrue( $method->isPublic() );
		$this->assertCount( 1, $method->getParameters() );
	}

	/**
	 * Verifica che dichiara process
	 *
	 * @return void
	 */
	public function test_interface_declares_process() {
		$reflection = new \ReflectionClass( AlertManagerInterface::class );
		$this->assertTrue( $reflection->hasMethod( 'process' ) );

		$method = $reflection->getMethod( 'process' );
		$this->assertTrue( $method->isPublic() );
		$this->assertCount( 2, $method->getParameters() );
	}
}
