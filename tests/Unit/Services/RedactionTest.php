<?php
/**
 * Unit Test per Redaction Service
 *
 * Test unitario per il servizio di redazione dati sensibili.
 *
 * @package OpsHealthDashboard\Tests\Unit\Services
 */

namespace OpsHealthDashboard\Tests\Unit\Services;

use OpsHealthDashboard\Interfaces\RedactionInterface;
use OpsHealthDashboard\Services\Redaction;
use PHPUnit\Framework\TestCase;

/**
 * Class RedactionTest
 *
 * Unit test per Redaction.
 */
class RedactionTest extends TestCase {

	/**
	 * Testa che Redaction può essere istanziato
	 */
	public function test_redaction_can_be_instantiated() {
		$redaction = new Redaction();
		$this->assertInstanceOf( Redaction::class, $redaction );
	}

	/**
	 * Testa che Redaction implementa RedactionInterface
	 */
	public function test_redaction_implements_interface() {
		$redaction = new Redaction();
		$this->assertInstanceOf( RedactionInterface::class, $redaction );
	}

	/**
	 * Testa che la classe NON è final
	 */
	public function test_class_is_not_final() {
		$reflection = new \ReflectionClass( Redaction::class );
		$this->assertFalse( $reflection->isFinal(), 'Redaction should NOT be final' );
	}

	/**
	 * Testa che NON esistono metodi static
	 */
	public function test_no_static_methods() {
		$reflection = new \ReflectionClass( Redaction::class );
		$methods    = $reflection->getMethods( \ReflectionMethod::IS_STATIC );

		$static_methods = array_filter(
			$methods,
			function ( $method ) {
				return strpos( $method->getName(), '__' ) !== 0;
			}
		);

		$this->assertEmpty( $static_methods, 'Redaction should have NO static methods' );
	}

	/**
	 * Testa che NON esistono proprietà static
	 */
	public function test_no_static_properties() {
		$reflection = new \ReflectionClass( Redaction::class );
		$properties = $reflection->getProperties( \ReflectionProperty::IS_STATIC );

		$this->assertEmpty( $properties, 'Redaction should have NO static properties' );
	}

	/**
	 * Testa che redact() ritorna una stringa
	 */
	public function test_redact_returns_string() {
		$redaction = new Redaction();
		$result    = $redaction->redact( 'some text' );
		$this->assertIsString( $result );
	}

	/**
	 * Testa che una stringa vuota rimane vuota
	 */
	public function test_redact_empty_string_returns_empty() {
		$redaction = new Redaction();
		$this->assertSame( '', $redaction->redact( '' ) );
	}

	/**
	 * Testa che testo senza dati sensibili rimane invariato
	 */
	public function test_redact_clean_text_unchanged() {
		$redaction = new Redaction();
		$text      = '[08-Feb-2026 12:00:00 UTC] PHP Notice: Undefined variable in test.php on line 42';
		$this->assertSame( $text, $redaction->redact( $text ) );
	}

	/**
	 * Testa la redazione degli indirizzi email
	 */
	public function test_redact_email_addresses() {
		$redaction = new Redaction();
		$text      = 'Error for user admin@example.com in module';
		$result    = $redaction->redact( $text );

		$this->assertStringNotContainsString( 'admin@example.com', $result );
		$this->assertStringContainsString( '[EMAIL_REDACTED]', $result );
	}

	/**
	 * Testa la redazione degli indirizzi IPv4
	 */
	public function test_redact_ipv4_addresses() {
		$redaction = new Redaction();
		$text      = 'Connection from 192.168.1.100 failed';
		$result    = $redaction->redact( $text );

		$this->assertStringNotContainsString( '192.168.1.100', $result );
		$this->assertStringContainsString( '[IP_REDACTED]', $result );
	}

	/**
	 * Testa la redazione degli indirizzi IPv6
	 */
	public function test_redact_ipv6_addresses() {
		$redaction = new Redaction();
		$text      = 'Request from 2001:0db8:85a3:0000:0000:8a2e:0370:7334 denied';
		$result    = $redaction->redact( $text );

		$this->assertStringNotContainsString( '2001:0db8:85a3', $result );
		$this->assertStringContainsString( '[IP_REDACTED]', $result );
	}

	/**
	 * Testa la redazione di DB_PASSWORD
	 */
	public function test_redact_db_password() {
		$redaction = new Redaction();
		$text      = "define('DB_PASSWORD', 'myS3cretP@ss!');";
		$result    = $redaction->redact( $text );

		$this->assertStringNotContainsString( 'myS3cretP@ss!', $result );
		$this->assertStringContainsString( '[REDACTED]', $result );
	}

	/**
	 * Testa la redazione di DB_USER
	 */
	public function test_redact_db_user() {
		$redaction = new Redaction();
		$text      = "define('DB_USER', 'wp_admin');";
		$result    = $redaction->redact( $text );

		$this->assertStringNotContainsString( 'wp_admin', $result );
		$this->assertStringContainsString( '[REDACTED]', $result );
	}

	/**
	 * Testa la redazione di DB_HOST
	 */
	public function test_redact_db_host() {
		$redaction = new Redaction();
		$text      = "define('DB_HOST', 'db.server.com');";
		$result    = $redaction->redact( $text );

		$this->assertStringNotContainsString( 'db.server.com', $result );
		$this->assertStringContainsString( '[REDACTED]', $result );
	}

	/**
	 * Testa la redazione di DB_NAME
	 */
	public function test_redact_db_name() {
		$redaction = new Redaction();
		$text      = "define('DB_NAME', 'wordpress_prod');";
		$result    = $redaction->redact( $text );

		$this->assertStringNotContainsString( 'wordpress_prod', $result );
		$this->assertStringContainsString( '[REDACTED]', $result );
	}

	/**
	 * Testa la redazione di API key
	 */
	public function test_redact_api_keys() {
		$redaction = new Redaction();
		$text      = 'api_key=sk_live_abc123def456ghi789';
		$result    = $redaction->redact( $text );

		$this->assertStringNotContainsString( 'sk_live_abc123def456ghi789', $result );
		$this->assertStringContainsString( '[REDACTED]', $result );
	}

	/**
	 * Testa la redazione di bearer token
	 */
	public function test_redact_bearer_tokens() {
		$redaction = new Redaction();
		$text      = 'Authorization: Bearer eyJhbGciOiJIUzI1NiJ9.payload.signature';
		$result    = $redaction->redact( $text );

		$this->assertStringNotContainsString( 'eyJhbGciOiJIUzI1NiJ9', $result );
		$this->assertStringContainsString( '[REDACTED]', $result );
	}

	/**
	 * Testa la redazione di password in URL
	 */
	public function test_redact_password_in_url() {
		$redaction = new Redaction();
		$text      = 'mysql://dbuser:secretpass123@db.example.com:3306/mydb';
		$result    = $redaction->redact( $text );

		$this->assertStringNotContainsString( 'secretpass123', $result );
		$this->assertStringContainsString( '[REDACTED]', $result );
	}

	/**
	 * Testa la redazione di campi password generici
	 */
	public function test_redact_generic_password_fields() {
		$redaction = new Redaction();
		$text      = "password='admin123secret'";
		$result    = $redaction->redact( $text );

		$this->assertStringNotContainsString( 'admin123secret', $result );
		$this->assertStringContainsString( '[REDACTED]', $result );
	}

	/**
	 * Testa la redazione delle WordPress salts
	 */
	public function test_redact_wordpress_salts() {
		$redaction = new Redaction();
		$text      = "define('AUTH_KEY', 'longRandomSaltValueHere123!@#');";
		$result    = $redaction->redact( $text );

		$this->assertStringNotContainsString( 'longRandomSaltValueHere123', $result );
		$this->assertStringContainsString( '[REDACTED]', $result );
	}

	/**
	 * Testa la redazione di ABSPATH
	 */
	public function test_redact_abspath() {
		$redaction = new Redaction( '/var/www/html/', '' );
		$text      = 'Error in /var/www/html/wp-config.php on line 10';
		$result    = $redaction->redact( $text );

		$this->assertStringNotContainsString( '/var/www/html/', $result );
		$this->assertStringContainsString( '[ABSPATH]/', $result );
	}

	/**
	 * Testa la redazione di WP_CONTENT_DIR
	 */
	public function test_redact_wp_content_dir() {
		$redaction = new Redaction( '', '/var/www/html/wp-content' );
		$text      = 'Error in /var/www/html/wp-content/plugins/myplugin/file.php';
		$result    = $redaction->redact( $text );

		$this->assertStringNotContainsString( '/var/www/html/wp-content', $result );
		$this->assertStringContainsString( '[WP_CONTENT]', $result );
	}

	/**
	 * Testa che WP_CONTENT_DIR è sostituito prima di ABSPATH
	 */
	public function test_redact_wp_content_dir_before_abspath() {
		$redaction = new Redaction( '/var/www/html/', '/var/www/html/wp-content' );
		$text      = 'Error in /var/www/html/wp-content/plugins/file.php';
		$result    = $redaction->redact( $text );

		$this->assertStringContainsString( '[WP_CONTENT]', $result );
		$this->assertStringNotContainsString( '/var/www/html/wp-content', $result );
	}

	/**
	 * Testa la redazione di directory home
	 */
	public function test_redact_home_directory() {
		$redaction = new Redaction();
		$text      = 'File /home/deploy/.ssh/authorized_keys not found';
		$result    = $redaction->redact( $text );

		$this->assertStringNotContainsString( '/home/deploy', $result );
		$this->assertStringContainsString( '/home/[USER_REDACTED]', $result );
	}

	/**
	 * Testa che path vuoti non causano errori
	 */
	public function test_redact_empty_paths_skipped() {
		$redaction = new Redaction( '', '' );
		$text      = 'Normal log line with no sensitive data here';
		$this->assertSame( $text, $redaction->redact( $text ) );
	}

	/**
	 * Testa che redact_lines() ritorna un array
	 */
	public function test_redact_lines_returns_array() {
		$redaction = new Redaction();
		$result    = $redaction->redact_lines( [ 'line 1', 'line 2' ] );
		$this->assertIsArray( $result );
	}

	/**
	 * Testa che redact_lines() applica la redazione a ogni riga
	 */
	public function test_redact_lines_applies_to_each_line() {
		$redaction = new Redaction();
		$lines     = [
			'Error for admin@example.com',
			'Connection from 192.168.1.1',
		];
		$result    = $redaction->redact_lines( $lines );

		$this->assertStringContainsString( '[EMAIL_REDACTED]', $result[0] );
		$this->assertStringContainsString( '[IP_REDACTED]', $result[1] );
	}

	/**
	 * Testa che redact_lines() con array vuoto ritorna array vuoto
	 */
	public function test_redact_lines_empty_array() {
		$redaction = new Redaction();
		$this->assertSame( [], $redaction->redact_lines( [] ) );
	}

	/**
	 * Testa la redazione multipla su una singola riga
	 */
	public function test_redact_multiple_patterns_in_one_line() {
		$redaction = new Redaction( '/var/www/html/', '' );
		$text      = 'Error for user@test.com from 10.0.0.1 in /var/www/html/wp-config.php';
		$result    = $redaction->redact( $text );

		$this->assertStringContainsString( '[EMAIL_REDACTED]', $result );
		$this->assertStringContainsString( '[IP_REDACTED]', $result );
		$this->assertStringContainsString( '[ABSPATH]/', $result );
	}

	/**
	 * Testa che la struttura del log non sensibile viene preservata
	 */
	public function test_redact_preserves_non_sensitive_log_structure() {
		$redaction = new Redaction();
		$text      = '[08-Feb-2026 12:00:00 UTC] PHP Warning: Division by zero in test.php on line 42';
		$result    = $redaction->redact( $text );

		$this->assertStringContainsString( '[08-Feb-2026 12:00:00 UTC]', $result );
		$this->assertStringContainsString( 'PHP Warning', $result );
		$this->assertStringContainsString( 'Division by zero', $result );
		$this->assertStringContainsString( 'line 42', $result );
	}
}
