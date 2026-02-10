<?php
/**
 * Test per AlertChannelInterface
 *
 * Verifica che il contratto dell'interfaccia AlertChannel sia corretto.
 *
 * @package OpsHealthDashboard\Tests\Unit\Interfaces
 */

namespace OpsHealthDashboard\Tests\Unit\Interfaces;

use OpsHealthDashboard\Interfaces\AlertChannelInterface;
use PHPUnit\Framework\TestCase;

/**
 * Class AlertChannelInterfaceTest
 *
 * Test per l'interfaccia AlertChannelInterface.
 */
class AlertChannelInterfaceTest extends TestCase {

	/**
	 * Verifica che l'interfaccia è un'interfaccia
	 *
	 * @return void
	 */
	public function test_is_interface() {
		$reflection = new \ReflectionClass( AlertChannelInterface::class );
		$this->assertTrue( $reflection->isInterface() );
	}

	/**
	 * Verifica che dichiara get_id
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
	 * Verifica che dichiara get_name
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
	 * Verifica che dichiara is_enabled
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
	 * Verifica che dichiara send
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
