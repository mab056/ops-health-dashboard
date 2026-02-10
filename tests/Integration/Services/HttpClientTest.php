<?php
/**
 * Integration Test per HttpClient Service
 *
 * Verifica anti-SSRF e richieste HTTP con WordPress reale.
 *
 * @package OpsHealthDashboard\Tests\Integration\Services
 */

namespace OpsHealthDashboard\Tests\Integration\Services;

use OpsHealthDashboard\Services\HttpClient;
use OpsHealthDashboard\Services\Redaction;
use WP_UnitTestCase;

/**
 * Sottoclasse testabile di HttpClient
 *
 * Override di resolve_host() per evitare dipendenza da DNS reale.
 */
class TestableHttpClient extends HttpClient {

	/**
	 * IP da restituire alla risoluzione
	 *
	 * @var string
	 */
	private $resolved_ip;

	/**
	 * Constructor
	 *
	 * @param \OpsHealthDashboard\Interfaces\RedactionInterface $redaction   Redaction service.
	 * @param string                                            $resolved_ip IP da restituire.
	 */
	public function __construct( $redaction, string $resolved_ip = '93.184.216.34' ) {
		parent::__construct( $redaction );
		$this->resolved_ip = $resolved_ip;
	}

	/**
	 * Override resolve_host per restituire un IP controllato
	 *
	 * @param string $hostname Hostname da risolvere.
	 * @return string IP configurato.
	 */
	protected function resolve_host( string $hostname ): string {
		return $this->resolved_ip;
	}
}

/**
 * Class HttpClientTest
 *
 * Integration test per HttpClient con WordPress reale.
 */
class HttpClientTest extends WP_UnitTestCase {

	/**
	 * Redaction service reale
	 *
	 * @var Redaction
	 */
	private $redaction;

	/**
	 * Setup
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		$this->redaction = new Redaction();
	}

	/**
	 * Teardown
	 *
	 * @return void
	 */
	public function tearDown(): void {
		remove_all_filters( 'pre_http_request' );
		parent::tearDown();
	}

	/**
	 * Testa che la classe NON è final
	 *
	 * @return void
	 */
	public function test_class_is_not_final() {
		$reflection = new \ReflectionClass( HttpClient::class );
		$this->assertFalse( $reflection->isFinal(), 'Class should NOT be final' );
	}

	/**
	 * Testa che NON esistono metodi static
	 *
	 * @return void
	 */
	public function test_no_static_methods() {
		$reflection = new \ReflectionClass( HttpClient::class );
		$methods    = $reflection->getMethods( \ReflectionMethod::IS_STATIC );

		$static_methods = array_filter(
			$methods,
			function ( $method ) {
				return strpos( $method->getName(), '__' ) !== 0;
			}
		);

		$this->assertEmpty( $static_methods, 'Class should have NO static methods' );
	}

	/**
	 * Testa che NON esistono proprietà static
	 *
	 * @return void
	 */
	public function test_no_static_properties() {
		$reflection = new \ReflectionClass( HttpClient::class );
		$properties = $reflection->getProperties( \ReflectionProperty::IS_STATIC );

		$this->assertEmpty( $properties, 'Class should have NO static properties' );
	}

	/**
	 * Testa che is_safe_url accetta URL pubblici HTTPS
	 *
	 * @return void
	 */
	public function test_is_safe_url_allows_public_https_url() {
		$client = new TestableHttpClient( $this->redaction, '93.184.216.34' );

		$this->assertTrue( $client->is_safe_url( 'https://example.com/api' ) );
	}

	/**
	 * Testa che is_safe_url blocca IP loopback
	 *
	 * @return void
	 */
	public function test_is_safe_url_blocks_loopback() {
		$client = new TestableHttpClient( $this->redaction, '127.0.0.1' );

		$this->assertFalse( $client->is_safe_url( 'https://evil.example.com' ) );
	}

	/**
	 * Testa che is_safe_url blocca range 10.0.0.0/8
	 *
	 * @return void
	 */
	public function test_is_safe_url_blocks_private_10_range() {
		$client = new TestableHttpClient( $this->redaction, '10.0.0.1' );

		$this->assertFalse( $client->is_safe_url( 'https://evil.example.com' ) );
	}

	/**
	 * Testa che is_safe_url blocca range 192.168.0.0/16
	 *
	 * @return void
	 */
	public function test_is_safe_url_blocks_private_192_168_range() {
		$client = new TestableHttpClient( $this->redaction, '192.168.1.1' );

		$this->assertFalse( $client->is_safe_url( 'https://evil.example.com' ) );
	}

	/**
	 * Testa che is_safe_url blocca range link-local
	 *
	 * @return void
	 */
	public function test_is_safe_url_blocks_link_local() {
		$client = new TestableHttpClient( $this->redaction, '169.254.1.1' );

		$this->assertFalse( $client->is_safe_url( 'https://evil.example.com' ) );
	}

	/**
	 * Testa che is_safe_url rifiuta porte non standard
	 *
	 * @return void
	 */
	public function test_is_safe_url_rejects_non_standard_port() {
		$client = new TestableHttpClient( $this->redaction, '93.184.216.34' );

		$this->assertFalse( $client->is_safe_url( 'https://example.com:8080/api' ) );
	}

	/**
	 * Testa che is_safe_url rifiuta stringa vuota
	 *
	 * @return void
	 */
	public function test_is_safe_url_rejects_empty_string() {
		$client = new TestableHttpClient( $this->redaction );

		$this->assertFalse( $client->is_safe_url( '' ) );
	}

	/**
	 * Testa post() con HTTP interceptato via pre_http_request
	 *
	 * @return void
	 */
	public function test_post_success_with_intercepted_http() {
		add_filter(
			'pre_http_request',
			function () {
				return [
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
					'body'     => '{"ok":true}',
				];
			}
		);

		$client = new TestableHttpClient( $this->redaction, '93.184.216.34' );
		$result = $client->post( 'https://example.com/api', [ 'key' => 'value' ] );

		$this->assertTrue( $result['success'] );
		$this->assertEquals( 200, $result['code'] );
		$this->assertEquals( '{"ok":true}', $result['body'] );
		$this->assertNull( $result['error'] );
	}

	/**
	 * Testa che post() non effettua HTTP call per URL non sicuri
	 *
	 * @return void
	 */
	public function test_post_unsafe_url_returns_failure() {
		$http_called = false;

		add_filter(
			'pre_http_request',
			function () use ( &$http_called ) {
				$http_called = true;
				return [
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
					'body'     => 'ok',
				];
			}
		);

		$client = new TestableHttpClient( $this->redaction, '127.0.0.1' );
		$result = $client->post( 'https://evil.example.com/api', [ 'data' => 'test' ] );

		$this->assertFalse( $result['success'] );
		$this->assertFalse( $http_called, 'HTTP call should NOT have been made for unsafe URL' );
	}

	/**
	 * Testa che post() gestisce WP_Error da wp_remote_post
	 *
	 * @return void
	 */
	public function test_post_handles_wp_error() {
		add_filter(
			'pre_http_request',
			function () {
				return new \WP_Error( 'http_request_failed', 'Connection timed out' );
			}
		);

		$client = new TestableHttpClient( $this->redaction, '93.184.216.34' );
		$result = $client->post( 'https://example.com/api', [ 'key' => 'value' ] );

		$this->assertFalse( $result['success'] );
		$this->assertEquals( 0, $result['code'] );
		$this->assertStringContainsString( 'HTTP request failed', $result['error'] );
	}

	/**
	 * Testa che is_safe_url rifiuta URL malformati (parse_url failure)
	 * Usa HttpClient reale (non subclass) per validare copertura diretta.
	 *
	 * @return void
	 */
	public function test_is_safe_url_rejects_malformed_url() {
		$client = new HttpClient( $this->redaction );

		$this->assertFalse( $client->is_safe_url( 'http:///invalid' ) );
	}

	/**
	 * Testa che is_safe_url rifiuta schema non consentiti
	 * Usa HttpClient reale per copertura diretta.
	 *
	 * @return void
	 */
	public function test_is_safe_url_rejects_ftp_scheme() {
		$client = new HttpClient( $this->redaction );

		$this->assertFalse( $client->is_safe_url( 'ftp://example.com/file.txt' ) );
	}

	/**
	 * Testa che is_safe_url rifiuta URL senza host (parse_url senza chiave host)
	 * Usa HttpClient reale per copertura diretta.
	 *
	 * @return void
	 */
	public function test_is_safe_url_rejects_url_without_host() {
		$client = new HttpClient( $this->redaction );

		// parse_url('http:path') → ['scheme'=>'http','path'=>'path'] senza chiave host.
		$this->assertFalse( $client->is_safe_url( 'http:path' ) );
	}

	/**
	 * Testa che is_safe_url blocca IP loopback con HttpClient reale
	 * Copre resolve_host() e is_private_ip() sulla classe reale.
	 *
	 * @return void
	 */
	public function test_is_safe_url_blocks_loopback_ip_on_real_client() {
		$client = new HttpClient( $this->redaction );

		// gethostbyname('127.0.0.1') → '127.0.0.1' (IP diretto, no DNS).
		$this->assertFalse( $client->is_safe_url( 'https://127.0.0.1/api' ) );
	}

	/**
	 * Testa che is_safe_url blocca 192.168 range con HttpClient reale
	 * Copre la linea 192.168.0.0/16 in is_private_ip().
	 *
	 * @return void
	 */
	public function test_is_safe_url_blocks_192_168_range_on_real_client() {
		$client = new HttpClient( $this->redaction );

		// gethostbyname('192.168.1.1') → '192.168.1.1' (IP diretto, no DNS).
		$this->assertFalse( $client->is_safe_url( 'https://192.168.1.1/api' ) );
	}

	/**
	 * Testa che is_safe_url blocca 172.16.0.0/12 range con HttpClient reale
	 * Copre la linea 172.16.0.0/12 in is_private_ip().
	 *
	 * @return void
	 */
	public function test_is_safe_url_blocks_172_16_range_on_real_client() {
		$client = new HttpClient( $this->redaction );

		// gethostbyname('172.16.0.1') → '172.16.0.1' (IP diretto, no DNS).
		$this->assertFalse( $client->is_safe_url( 'https://172.16.0.1/api' ) );
	}

	/**
	 * Testa post() su HttpClient reale con IP privato
	 * Copre il metodo post() sulla classe reale (non subclass).
	 *
	 * @return void
	 */
	public function test_post_on_real_client_rejects_private_ip() {
		$client = new HttpClient( $this->redaction );
		$result = $client->post( 'https://127.0.0.1/api', [ 'key' => 'value' ] );

		$this->assertFalse( $result['success'] );
	}

	/**
	 * Testa che is_safe_url blocca hostname non risolvibili
	 *
	 * @return void
	 */
	public function test_is_safe_url_rejects_unresolvable_hostname() {
		// Simula hostname non risolvibile: resolve_host ritorna l'hostname stesso.
		$client = new TestableHttpClient( $this->redaction, 'unresolvable.invalid' );

		$this->assertFalse( $client->is_safe_url( 'https://unresolvable.invalid/api' ) );
	}

	/**
	 * Testa che is_safe_url blocca indirizzi IPv6
	 *
	 * @return void
	 */
	public function test_is_safe_url_blocks_ipv6() {
		$client = new TestableHttpClient( $this->redaction, '::1' );

		$this->assertFalse( $client->is_safe_url( 'https://example.com/api' ) );
	}

	/**
	 * Testa che is_safe_url blocca indirizzo 0.0.0.0
	 *
	 * @return void
	 */
	public function test_is_safe_url_blocks_zero_address() {
		$client = new TestableHttpClient( $this->redaction, '0.0.0.0' );

		$this->assertFalse( $client->is_safe_url( 'https://example.com/api' ) );
	}

	/**
	 * Testa che post() gestisce status code non-2xx
	 *
	 * @return void
	 */
	public function test_post_handles_non_2xx_status() {
		add_filter(
			'pre_http_request',
			function () {
				return [
					'response' => [
						'code'    => 500,
						'message' => 'Internal Server Error',
					],
					'body'     => 'Server Error',
				];
			}
		);

		$client = new TestableHttpClient( $this->redaction, '93.184.216.34' );
		$result = $client->post( 'https://example.com/api', [ 'key' => 'value' ] );

		$this->assertFalse( $result['success'] );
		$this->assertEquals( 500, $result['code'] );
		$this->assertStringContainsString( '500', $result['error'] );
	}
}
