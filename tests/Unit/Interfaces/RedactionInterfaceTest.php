<?php
/**
 * Unit Test per RedactionInterface
 *
 * Verifica la struttura dell'interfaccia RedactionInterface.
 *
 * @package OpsHealthDashboard\Tests\Unit\Interfaces
 */

namespace OpsHealthDashboard\Tests\Unit\Interfaces;

use OpsHealthDashboard\Interfaces\RedactionInterface;
use PHPUnit\Framework\TestCase;

/**
 * Class RedactionInterfaceTest
 *
 * Unit test per RedactionInterface.
 */
class RedactionInterfaceTest extends TestCase {

	/**
	 * Testa che l'interfaccia esiste ed è un'interfaccia
	 */
	public function test_is_interface() {
		$reflection = new \ReflectionClass( RedactionInterface::class );
		$this->assertTrue( $reflection->isInterface(), 'RedactionInterface should be an interface' );
	}

	/**
	 * Testa che il metodo redact() è definito, pubblico con 1 parametro
	 */
	public function test_has_redact_method() {
		$reflection = new \ReflectionClass( RedactionInterface::class );
		$this->assertTrue( $reflection->hasMethod( 'redact' ) );

		$method = $reflection->getMethod( 'redact' );
		$this->assertTrue( $method->isPublic() );
		$this->assertCount( 1, $method->getParameters() );
	}

	/**
	 * Testa che il metodo redact_lines() è definito, pubblico con 1 parametro
	 */
	public function test_has_redact_lines_method() {
		$reflection = new \ReflectionClass( RedactionInterface::class );
		$this->assertTrue( $reflection->hasMethod( 'redact_lines' ) );

		$method = $reflection->getMethod( 'redact_lines' );
		$this->assertTrue( $method->isPublic() );
		$this->assertCount( 1, $method->getParameters() );
	}
}
