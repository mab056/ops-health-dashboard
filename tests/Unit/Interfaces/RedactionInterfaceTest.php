<?php
/**
 * Unit Test for RedactionInterface
 *
 * Verifies the structure of the RedactionInterface interface.
 *
 * @package OpsHealthDashboard\Tests\Unit\Interfaces
 */

namespace OpsHealthDashboard\Tests\Unit\Interfaces;

use OpsHealthDashboard\Interfaces\RedactionInterface;
use PHPUnit\Framework\TestCase;

/**
 * Class RedactionInterfaceTest
 *
 * Unit test for RedactionInterface.
 */
class RedactionInterfaceTest extends TestCase {

	/**
	 * Tests that the interface exists and is an interface
	 */
	public function test_is_interface() {
		$reflection = new \ReflectionClass( RedactionInterface::class );
		$this->assertTrue( $reflection->isInterface(), 'RedactionInterface should be an interface' );
	}

	/**
	 * Tests that the redact() method is defined, public with 1 parameter
	 */
	public function test_has_redact_method() {
		$reflection = new \ReflectionClass( RedactionInterface::class );
		$this->assertTrue( $reflection->hasMethod( 'redact' ) );

		$method = $reflection->getMethod( 'redact' );
		$this->assertTrue( $method->isPublic() );
		$this->assertCount( 1, $method->getParameters() );
	}

	/**
	 * Tests that the redact_lines() method is defined, public with 1 parameter
	 */
	public function test_has_redact_lines_method() {
		$reflection = new \ReflectionClass( RedactionInterface::class );
		$this->assertTrue( $reflection->hasMethod( 'redact_lines' ) );

		$method = $reflection->getMethod( 'redact_lines' );
		$this->assertTrue( $method->isPublic() );
		$this->assertCount( 1, $method->getParameters() );
	}
}
