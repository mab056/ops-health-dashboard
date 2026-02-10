<?php
/**
 * Test per HttpClientInterface
 *
 * Verifica che il contratto dell'interfaccia HttpClient sia corretto.
 *
 * @package OpsHealthDashboard\Tests\Unit\Interfaces
 */

namespace OpsHealthDashboard\Tests\Unit\Interfaces;

use OpsHealthDashboard\Interfaces\HttpClientInterface;
use PHPUnit\Framework\TestCase;

/**
 * Class HttpClientInterfaceTest
 *
 * Test per l'interfaccia HttpClientInterface.
 */
class HttpClientInterfaceTest extends TestCase {

	/**
	 * Verifica che l'interfaccia esiste
	 *
	 * @return void
	 */
	public function test_interface_exists() {
		$this->assertTrue( interface_exists( HttpClientInterface::class ) );
	}

	/**
	 * Verifica che l'interfaccia dichiara is_safe_url
	 *
	 * @return void
	 */
	public function test_interface_declares_is_safe_url() {
		$reflection = new \ReflectionClass( HttpClientInterface::class );
		$this->assertTrue( $reflection->hasMethod( 'is_safe_url' ) );

		$method = $reflection->getMethod( 'is_safe_url' );
		$this->assertTrue( $method->isPublic() );
		$this->assertCount( 1, $method->getParameters() );
	}

	/**
	 * Verifica che l'interfaccia dichiara post
	 *
	 * @return void
	 */
	public function test_interface_declares_post() {
		$reflection = new \ReflectionClass( HttpClientInterface::class );
		$this->assertTrue( $reflection->hasMethod( 'post' ) );

		$method = $reflection->getMethod( 'post' );
		$this->assertTrue( $method->isPublic() );
		$this->assertCount( 3, $method->getParameters() );
	}

	/**
	 * Verifica che l'interfaccia è un'interfaccia
	 *
	 * @return void
	 */
	public function test_is_interface() {
		$reflection = new \ReflectionClass( HttpClientInterface::class );
		$this->assertTrue( $reflection->isInterface() );
	}
}
