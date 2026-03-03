<?php
/**
 * Test for AlertChannelInterface
 *
 * Verifies that the AlertChannel interface contract is correct.
 *
 * @package OpsHealthDashboard\Tests\Unit\Interfaces
 */

namespace OpsHealthDashboard\Tests\Unit\Interfaces;

use OpsHealthDashboard\Interfaces\AlertChannelInterface;
use PHPUnit\Framework\TestCase;

/**
 * Class AlertChannelInterfaceTest
 *
 * Test for the AlertChannelInterface interface.
 */
class AlertChannelInterfaceTest extends TestCase {

	/**
	 * Verifies that it is an interface
	 *
	 * @return void
	 */
	public function test_is_interface() {
		$reflection = new \ReflectionClass( AlertChannelInterface::class );
		$this->assertTrue( $reflection->isInterface() );
	}

	/**
	 * Verifies that it declares get_id
	 *
	 * @return void
	 */
	public function test_interface_declares_get_id() {
		$reflection = new \ReflectionClass( AlertChannelInterface::class );
		$this->assertTrue( $reflection->hasMethod( 'get_id' ) );

		$method = $reflection->getMethod( 'get_id' );
		$this->assertTrue( $method->isPublic() );
		$this->assertCount( 0, $method->getParameters() );
	}

	/**
	 * Verifies that it declares get_name
	 *
	 * @return void
	 */
	public function test_interface_declares_get_name() {
		$reflection = new \ReflectionClass( AlertChannelInterface::class );
		$this->assertTrue( $reflection->hasMethod( 'get_name' ) );

		$method = $reflection->getMethod( 'get_name' );
		$this->assertTrue( $method->isPublic() );
		$this->assertCount( 0, $method->getParameters() );
	}

	/**
	 * Verifies that it declares is_enabled
	 *
	 * @return void
	 */
	public function test_interface_declares_is_enabled() {
		$reflection = new \ReflectionClass( AlertChannelInterface::class );
		$this->assertTrue( $reflection->hasMethod( 'is_enabled' ) );

		$method = $reflection->getMethod( 'is_enabled' );
		$this->assertTrue( $method->isPublic() );
		$this->assertCount( 0, $method->getParameters() );
	}

	/**
	 * Verifies that it declares send
	 *
	 * @return void
	 */
	public function test_interface_declares_send() {
		$reflection = new \ReflectionClass( AlertChannelInterface::class );
		$this->assertTrue( $reflection->hasMethod( 'send' ) );

		$method = $reflection->getMethod( 'send' );
		$this->assertCount( 1, $method->getParameters() );
	}
}
