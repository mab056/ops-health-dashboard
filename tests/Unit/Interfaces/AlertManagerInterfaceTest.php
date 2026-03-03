<?php
/**
 * Test for AlertManagerInterface
 *
 * Verifies that the AlertManager interface contract is correct.
 *
 * @package OpsHealthDashboard\Tests\Unit\Interfaces
 */

namespace OpsHealthDashboard\Tests\Unit\Interfaces;

use OpsHealthDashboard\Interfaces\AlertManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * Class AlertManagerInterfaceTest
 *
 * Test for the AlertManagerInterface interface.
 */
class AlertManagerInterfaceTest extends TestCase {

	/**
	 * Verifies that it is an interface
	 *
	 * @return void
	 */
	public function test_is_interface() {
		$reflection = new \ReflectionClass( AlertManagerInterface::class );
		$this->assertTrue( $reflection->isInterface() );
	}

	/**
	 * Verifies that it declares add_channel
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
	 * Verifies that it declares process
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
