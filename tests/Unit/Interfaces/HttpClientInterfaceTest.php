<?php
/**
 * Test for HttpClientInterface
 *
 * Verifies that the HttpClient interface contract is correct.
 *
 * @package OpsHealthDashboard\Tests\Unit\Interfaces
 */

namespace OpsHealthDashboard\Tests\Unit\Interfaces;

use OpsHealthDashboard\Interfaces\HttpClientInterface;
use PHPUnit\Framework\TestCase;

/**
 * Class HttpClientInterfaceTest
 *
 * Test for the HttpClientInterface interface.
 */
class HttpClientInterfaceTest extends TestCase {

	/**
	 * Verifies that the interface declares is_safe_url
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
	 * Verifies that the interface declares post
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
	 * Verifies that it is an interface
	 *
	 * @return void
	 */
	public function test_is_interface() {
		$reflection = new \ReflectionClass( HttpClientInterface::class );
		$this->assertTrue( $reflection->isInterface() );
	}
}
