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
	 * Testa che l'interfaccia esiste
	 */
	public function test_interface_exists() {
		$this->assertTrue( interface_exists( RedactionInterface::class ) );
	}

	/**
	 * Testa che il metodo redact() è definito
	 */
	public function test_has_redact_method() {
		$reflection = new \ReflectionClass( RedactionInterface::class );
		$this->assertTrue( $reflection->hasMethod( 'redact' ) );
	}

	/**
	 * Testa che il metodo redact_lines() è definito
	 */
	public function test_has_redact_lines_method() {
		$reflection = new \ReflectionClass( RedactionInterface::class );
		$this->assertTrue( $reflection->hasMethod( 'redact_lines' ) );
	}
}
