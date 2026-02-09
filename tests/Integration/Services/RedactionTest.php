<?php
/**
 * Integration Test per Redaction Service
 *
 * Test di integrazione con costanti WordPress reali.
 *
 * @package OpsHealthDashboard\Tests\Integration\Services
 */

namespace OpsHealthDashboard\Tests\Integration\Services;

use OpsHealthDashboard\Interfaces\RedactionInterface;
use OpsHealthDashboard\Services\Redaction;
use WP_UnitTestCase;

/**
 * Class RedactionTest
 *
 * Integration test per Redaction con WordPress reale.
 */
class RedactionTest extends WP_UnitTestCase {

	/**
	 * Testa la redazione con ABSPATH reale di WordPress
	 */
	public function test_redaction_with_real_abspath() {
		$redaction = new Redaction( ABSPATH, '' );
		$text      = 'Error in ' . ABSPATH . 'wp-config.php on line 10';
		$result    = $redaction->redact( $text );

		$this->assertStringNotContainsString( ABSPATH, $result );
		$this->assertStringContainsString( '[ABSPATH]/', $result );
	}

	/**
	 * Testa la redazione con WP_CONTENT_DIR reale di WordPress
	 */
	public function test_redaction_with_real_wp_content_dir() {
		$redaction = new Redaction( '', WP_CONTENT_DIR );
		$text      = 'Error in ' . WP_CONTENT_DIR . '/plugins/test/file.php';
		$result    = $redaction->redact( $text );

		$this->assertStringNotContainsString( WP_CONTENT_DIR, $result );
		$this->assertStringContainsString( '[WP_CONTENT]', $result );
	}

	/**
	 * Testa la pipeline completa con una riga di log realistica
	 */
	public function test_redaction_end_to_end() {
		$redaction = new Redaction( ABSPATH, WP_CONTENT_DIR );

		$log_line = sprintf(
			'[08-Feb-2026 12:00:00 UTC] PHP Warning: include(%splugins/test/file.php): '
			. 'failed for user admin@example.com from /home/deploy/app',
			WP_CONTENT_DIR . '/'
		);

		$result = $redaction->redact( $log_line );

		// Il path WP_CONTENT_DIR deve essere redatto.
		$this->assertStringNotContainsString( WP_CONTENT_DIR, $result );
		$this->assertStringContainsString( '[WP_CONTENT]', $result );

		// L'email deve essere redatta.
		$this->assertStringNotContainsString( 'admin@example.com', $result );
		$this->assertStringContainsString( '[EMAIL_REDACTED]', $result );

		// La home directory deve essere redatta.
		$this->assertStringNotContainsString( '/home/deploy', $result );
		$this->assertStringContainsString( '/home/[USER_REDACTED]', $result );

		// La struttura del log deve essere preservata.
		$this->assertStringContainsString( 'PHP Warning', $result );

		// Implementa RedactionInterface.
		$this->assertInstanceOf( RedactionInterface::class, $redaction );
	}
}
