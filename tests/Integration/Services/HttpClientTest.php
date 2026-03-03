<?php
/**
 * Integration Test for HttpClient Service
 *
 * Verifies anti-SSRF and HTTP requests with real WordPress.
 *
 * @package OpsHealthDashboard\Tests\Integration\Services
 */

namespace OpsHealthDashboard\Tests\Integration\Services;

use OpsHealthDashboard\Services\HttpClient;
use OpsHealthDashboard\Services\Redaction;
use WP_UnitTestCase;

/**
 * Testable subclass of HttpClient
 *
 * Override of resolve_host() to avoid dependency on real DNS.
 */
class TestableHttpClient extends HttpClient {

	/**
	 * IP to return on resolution
	 *
	 * @var string
	 */
	private $resolved_ip;

	/**
	 * Constructor
	 *
	 * @param \OpsHealthDashboard\Interfaces\RedactionInterface $redaction   Redaction service.
	 * @param string                                            $resolved_ip IP to return.
	 */
	public function __construct( $redaction, string $resolved_ip = '93.184.216.34' ) {
		parent::__construct( $redaction );
		$this->resolved_ip = $resolved_ip;
	}

	/**
	 * Override resolve_host to return a controlled IP
	 *
	 * @param string $hostname Hostname to resolve.
	 * @return string Configured IP.
	 */
	protected function resolve_host( string $hostname ): string {
		return $this->resolved_ip;
	}
}

/**
 * Class HttpClientTest
 *
 * Integration test for HttpClient with real WordPress.
 */
class HttpClientTest extends WP_UnitTestCase {

	/**
	 * Real redaction service
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
	 * Tests that the class is NOT final
	 *
	 * @return void
	 */
	public function test_class_is_not_final() {
		$reflection = new \ReflectionClass( HttpClient::class );
		$this->assertFalse( $reflection->isFinal(), 'Class should NOT be final' );
	}

	/**
	 * Tests that NO static methods exist
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
	 * Tests that NO static properties exist
	 *
	 * @return void
	 */
	public function test_no_static_properties() {
		$reflection = new \ReflectionClass( HttpClient::class );
		$properties = $reflection->getProperties( \ReflectionProperty::IS_STATIC );

		$this->assertEmpty( $properties, 'Class should have NO static properties' );
	}

	/**
	 * Tests that is_safe_url accepts public HTTPS URLs
	 *
	 * @return void
	 */
	public function test_is_safe_url_allows_public_https_url() {
		$client = new TestableHttpClient( $this->redaction, '93.184.216.34' );

		$this->assertTrue( $client->is_safe_url( 'https://example.com/api' ) );
	}

	/**
	 * Tests that is_safe_url blocks loopback IP
	 *
	 * @return void
	 */
	public function test_is_safe_url_blocks_loopback() {
		$client = new TestableHttpClient( $this->redaction, '127.0.0.1' );

		$this->assertFalse( $client->is_safe_url( 'https://evil.example.com' ) );
	}

	/**
	 * Tests that is_safe_url blocks 10.0.0.0/8 range
	 *
	 * @return void
	 */
	public function test_is_safe_url_blocks_private_10_range() {
		$client = new TestableHttpClient( $this->redaction, '10.0.0.1' );

		$this->assertFalse( $client->is_safe_url( 'https://evil.example.com' ) );
	}

	/**
	 * Tests that is_safe_url blocks 192.168.0.0/16 range
	 *
	 * @return void
	 */
	public function test_is_safe_url_blocks_private_192_168_range() {
		$client = new TestableHttpClient( $this->redaction, '192.168.1.1' );

		$this->assertFalse( $client->is_safe_url( 'https://evil.example.com' ) );
	}

	/**
	 * Tests that is_safe_url blocks link-local range
	 *
	 * @return void
	 */
	public function test_is_safe_url_blocks_link_local() {
		$client = new TestableHttpClient( $this->redaction, '169.254.1.1' );

		$this->assertFalse( $client->is_safe_url( 'https://evil.example.com' ) );
	}

	/**
	 * Tests that is_safe_url rejects non-standard ports
	 *
	 * @return void
	 */
	public function test_is_safe_url_rejects_non_standard_port() {
		$client = new TestableHttpClient( $this->redaction, '93.184.216.34' );

		$this->assertFalse( $client->is_safe_url( 'https://example.com:8080/api' ) );
	}

	/**
	 * Tests that is_safe_url rejects empty string
	 *
	 * @return void
	 */
	public function test_is_safe_url_rejects_empty_string() {
		$client = new TestableHttpClient( $this->redaction );

		$this->assertFalse( $client->is_safe_url( '' ) );
	}

	/**
	 * Tests post() with HTTP intercepted via pre_http_request
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
	 * Tests that post() does not make HTTP call for unsafe URLs
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
	 * Tests that post() handles WP_Error from wp_remote_post
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
	 * Tests that is_safe_url rejects malformed URLs (parse_url failure)
	 * Uses real HttpClient (not subclass) to validate direct coverage.
	 *
	 * @return void
	 */
	public function test_is_safe_url_rejects_malformed_url() {
		$client = new HttpClient( $this->redaction );

		$this->assertFalse( $client->is_safe_url( 'http:///invalid' ) );
	}

	/**
	 * Tests that is_safe_url rejects disallowed schemes
	 * Uses real HttpClient for direct coverage.
	 *
	 * @return void
	 */
	public function test_is_safe_url_rejects_ftp_scheme() {
		$client = new HttpClient( $this->redaction );

		$this->assertFalse( $client->is_safe_url( 'ftp://example.com/file.txt' ) );
	}

	/**
	 * Tests that is_safe_url rejects URLs without host (parse_url without host key)
	 * Uses real HttpClient for direct coverage.
	 *
	 * @return void
	 */
	public function test_is_safe_url_rejects_url_without_host() {
		$client = new HttpClient( $this->redaction );

		// parse_url('http:path') → ['scheme'=>'http','path'=>'path'] without host key.
		$this->assertFalse( $client->is_safe_url( 'http:path' ) );
	}

	/**
	 * Tests that is_safe_url blocks loopback IP with real HttpClient
	 * Covers resolve_host() and is_private_ip() on the real class.
	 *
	 * @return void
	 */
	public function test_is_safe_url_blocks_loopback_ip_on_real_client() {
		$client = new HttpClient( $this->redaction );

		// gethostbyname('127.0.0.1') → '127.0.0.1' (direct IP, no DNS).
		$this->assertFalse( $client->is_safe_url( 'https://127.0.0.1/api' ) );
	}

	/**
	 * Tests that is_safe_url blocks 192.168 range with real HttpClient
	 * Covers the 192.168.0.0/16 line in is_private_ip().
	 *
	 * @return void
	 */
	public function test_is_safe_url_blocks_192_168_range_on_real_client() {
		$client = new HttpClient( $this->redaction );

		// gethostbyname('192.168.1.1') → '192.168.1.1' (direct IP, no DNS).
		$this->assertFalse( $client->is_safe_url( 'https://192.168.1.1/api' ) );
	}

	/**
	 * Tests that is_safe_url blocks 172.16.0.0/12 range with real HttpClient
	 * Covers the 172.16.0.0/12 line in is_private_ip().
	 *
	 * @return void
	 */
	public function test_is_safe_url_blocks_172_16_range_on_real_client() {
		$client = new HttpClient( $this->redaction );

		// gethostbyname('172.16.0.1') → '172.16.0.1' (direct IP, no DNS).
		$this->assertFalse( $client->is_safe_url( 'https://172.16.0.1/api' ) );
	}

	/**
	 * Tests post() on real HttpClient with private IP
	 * Covers the post() method on the real class (not subclass).
	 *
	 * @return void
	 */
	public function test_post_on_real_client_rejects_private_ip() {
		$client = new HttpClient( $this->redaction );
		$result = $client->post( 'https://127.0.0.1/api', [ 'key' => 'value' ] );

		$this->assertFalse( $result['success'] );
	}

	/**
	 * Tests that is_safe_url blocks unresolvable hostnames
	 *
	 * @return void
	 */
	public function test_is_safe_url_rejects_unresolvable_hostname() {
		// Simulate unresolvable hostname: resolve_host returns the hostname itself.
		$client = new TestableHttpClient( $this->redaction, 'unresolvable.invalid' );

		$this->assertFalse( $client->is_safe_url( 'https://unresolvable.invalid/api' ) );
	}

	/**
	 * Tests that is_safe_url blocks IPv6 addresses
	 *
	 * @return void
	 */
	public function test_is_safe_url_blocks_ipv6() {
		$client = new TestableHttpClient( $this->redaction, '::1' );

		$this->assertFalse( $client->is_safe_url( 'https://example.com/api' ) );
	}

	/**
	 * Tests that is_safe_url blocks 0.0.0.0 address
	 *
	 * @return void
	 */
	public function test_is_safe_url_blocks_zero_address() {
		$client = new TestableHttpClient( $this->redaction, '0.0.0.0' );

		$this->assertFalse( $client->is_safe_url( 'https://example.com/api' ) );
	}

	/**
	 * Tests that post() handles non-2xx status codes
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
