<?php
/**
 * Test for HttpClient Service
 *
 * Verifies anti-SSRF validation and secure HTTP requests.
 *
 * @package OpsHealthDashboard\Tests\Unit\Services
 */

namespace OpsHealthDashboard\Tests\Unit\Services;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use OpsHealthDashboard\Interfaces\HttpClientInterface;
use OpsHealthDashboard\Interfaces\RedactionInterface;
use OpsHealthDashboard\Services\HttpClient;
use PHPUnit\Framework\TestCase;

/**
 * Class HttpClientTest
 *
 * Unit tests for the HttpClient service with anti-SSRF protection.
 */
class HttpClientTest extends TestCase {

	use MockeryPHPUnitIntegration;

	/**
	 * Setup Brain\Monkey
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	/**
	 * Teardown Brain\Monkey
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Creates a mock of RedactionInterface
	 *
	 * @return \Mockery\MockInterface|RedactionInterface
	 */
	private function create_redaction_mock() {
		$redaction = Mockery::mock( RedactionInterface::class );
		$redaction->shouldReceive( 'redact' )
			->andReturnUsing(
				function ( $text ) {
					return $text;
				}
			);
		return $redaction;
	}

	/**
	 * Creates a testable HttpClient with mocked resolve_host
	 *
	 * @param RedactionInterface $redaction Redaction service.
	 * @param string             $resolved_ip IP returned by resolve_host.
	 * @return \Mockery\MockInterface|HttpClient
	 */
	private function create_client_with_resolved_ip( $redaction, string $resolved_ip ) {
		$client = Mockery::mock( HttpClient::class, [ $redaction ] )
			->makePartial()
			->shouldAllowMockingProtectedMethods();

		$client->shouldReceive( 'resolve_host' )
			->andReturn( $resolved_ip );

		return $client;
	}

	// ---------------------------------------------------
	// Pattern enforcement
	// ---------------------------------------------------

	/**
	 * Tests that the class is NOT final
	 *
	 * @return void
	 */
	public function test_class_is_not_final() {
		$reflection = new \ReflectionClass( HttpClient::class );
		$this->assertFalse( $reflection->isFinal(), 'HttpClient should NOT be final' );
	}

	/**
	 * Tests that there are NO static methods
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

		$this->assertEmpty( $static_methods, 'HttpClient should have NO static methods' );
	}

	/**
	 * Tests that there are NO static properties
	 *
	 * @return void
	 */
	public function test_no_static_properties() {
		$reflection = new \ReflectionClass( HttpClient::class );
		$properties = $reflection->getProperties( \ReflectionProperty::IS_STATIC );

		$this->assertEmpty( $properties, 'HttpClient should have NO static properties' );
	}

	/**
	 * Tests that the class implements HttpClientInterface
	 *
	 * @return void
	 */
	public function test_implements_interface() {
		$this->assertInstanceOf(
			HttpClientInterface::class,
			new HttpClient( $this->create_redaction_mock() )
		);
	}

	// ---------------------------------------------------
	// is_safe_url() - Schema
	// ---------------------------------------------------

	/**
	 * Tests that HTTPS is allowed
	 *
	 * @return void
	 */
	public function test_is_safe_url_allows_https() {
		$client = $this->create_client_with_resolved_ip(
			$this->create_redaction_mock(),
			'93.184.216.34' // Public IP.
		);

		$this->assertTrue( $client->is_safe_url( 'https://example.com/webhook' ) );
	}

	/**
	 * Tests that HTTP is allowed
	 *
	 * @return void
	 */
	public function test_is_safe_url_allows_http() {
		$client = $this->create_client_with_resolved_ip(
			$this->create_redaction_mock(),
			'93.184.216.34'
		);

		$this->assertTrue( $client->is_safe_url( 'http://example.com/webhook' ) );
	}

	/**
	 * Tests that file:// is rejected
	 *
	 * @return void
	 */
	public function test_is_safe_url_rejects_file_scheme() {
		$client = new HttpClient( $this->create_redaction_mock() );
		$this->assertFalse( $client->is_safe_url( 'file:///etc/passwd' ) );
	}

	/**
	 * Tests that ftp:// is rejected
	 *
	 * @return void
	 */
	public function test_is_safe_url_rejects_ftp_scheme() {
		$client = new HttpClient( $this->create_redaction_mock() );
		$this->assertFalse( $client->is_safe_url( 'ftp://example.com' ) );
	}

	/**
	 * Tests that gopher:// is rejected
	 *
	 * @return void
	 */
	public function test_is_safe_url_rejects_gopher_scheme() {
		$client = new HttpClient( $this->create_redaction_mock() );
		$this->assertFalse( $client->is_safe_url( 'gopher://example.com' ) );
	}

	/**
	 * Tests that empty string is rejected
	 *
	 * @return void
	 */
	public function test_is_safe_url_rejects_empty_string() {
		$client = new HttpClient( $this->create_redaction_mock() );
		$this->assertFalse( $client->is_safe_url( '' ) );
	}

	/**
	 * Tests that URL without scheme is rejected
	 *
	 * @return void
	 */
	public function test_is_safe_url_rejects_no_scheme() {
		$client = new HttpClient( $this->create_redaction_mock() );
		$this->assertFalse( $client->is_safe_url( 'example.com/webhook' ) );
	}

	/**
	 * Tests that malformed URL is rejected
	 *
	 * @return void
	 */
	public function test_is_safe_url_rejects_malformed_url() {
		$client = new HttpClient( $this->create_redaction_mock() );
		$this->assertFalse( $client->is_safe_url( 'http://' ) );
	}

	// ---------------------------------------------------
	// is_safe_url() - Private IPs
	// ---------------------------------------------------

	/**
	 * Tests that localhost (127.0.0.1) is blocked
	 *
	 * @return void
	 */
	public function test_is_safe_url_blocks_loopback_127() {
		$client = $this->create_client_with_resolved_ip(
			$this->create_redaction_mock(),
			'127.0.0.1'
		);

		$this->assertFalse( $client->is_safe_url( 'https://localhost/webhook' ) );
	}

	/**
	 * Tests that 127.0.0.53 is blocked
	 *
	 * @return void
	 */
	public function test_is_safe_url_blocks_loopback_53() {
		$client = $this->create_client_with_resolved_ip(
			$this->create_redaction_mock(),
			'127.0.0.53'
		);

		$this->assertFalse( $client->is_safe_url( 'https://internal.local/hook' ) );
	}

	/**
	 * Tests that 10.x.x.x is blocked
	 *
	 * @return void
	 */
	public function test_is_safe_url_blocks_private_10() {
		$client = $this->create_client_with_resolved_ip(
			$this->create_redaction_mock(),
			'10.0.0.1'
		);

		$this->assertFalse( $client->is_safe_url( 'https://internal-service.local/api' ) );
	}

	/**
	 * Tests that 172.16.x.x is blocked
	 *
	 * @return void
	 */
	public function test_is_safe_url_blocks_private_172_16() {
		$client = $this->create_client_with_resolved_ip(
			$this->create_redaction_mock(),
			'172.16.0.1'
		);

		$this->assertFalse( $client->is_safe_url( 'https://docker-host.local/api' ) );
	}

	/**
	 * Tests that 172.31.x.x is blocked (end of /12 range)
	 *
	 * @return void
	 */
	public function test_is_safe_url_blocks_private_172_31() {
		$client = $this->create_client_with_resolved_ip(
			$this->create_redaction_mock(),
			'172.31.255.255'
		);

		$this->assertFalse( $client->is_safe_url( 'https://aws-internal.local/api' ) );
	}

	/**
	 * Tests that 172.32.x.x is NOT blocked (outside /12 range)
	 *
	 * @return void
	 */
	public function test_is_safe_url_allows_172_32() {
		$client = $this->create_client_with_resolved_ip(
			$this->create_redaction_mock(),
			'172.32.0.1'
		);

		$this->assertTrue( $client->is_safe_url( 'https://public-service.com/api' ) );
	}

	/**
	 * Tests that 192.168.x.x is blocked
	 *
	 * @return void
	 */
	public function test_is_safe_url_blocks_private_192_168() {
		$client = $this->create_client_with_resolved_ip(
			$this->create_redaction_mock(),
			'192.168.1.1'
		);

		$this->assertFalse( $client->is_safe_url( 'https://router.local/api' ) );
	}

	/**
	 * Tests that 169.254.x.x (link-local) is blocked
	 *
	 * @return void
	 */
	public function test_is_safe_url_blocks_link_local() {
		$client = $this->create_client_with_resolved_ip(
			$this->create_redaction_mock(),
			'169.254.169.254'
		);

		$this->assertFalse( $client->is_safe_url( 'https://metadata.google.internal/api' ) );
	}

	/**
	 * Tests that 0.0.0.0 is blocked
	 *
	 * @return void
	 */
	public function test_is_safe_url_blocks_zero_ip() {
		$client = $this->create_client_with_resolved_ip(
			$this->create_redaction_mock(),
			'0.0.0.0'
		);

		$this->assertFalse( $client->is_safe_url( 'https://zero.local/api' ) );
	}

	/**
	 * Tests that public IP is allowed
	 *
	 * @return void
	 */
	public function test_is_safe_url_allows_public_ip() {
		$client = $this->create_client_with_resolved_ip(
			$this->create_redaction_mock(),
			'8.8.8.8'
		);

		$this->assertTrue( $client->is_safe_url( 'https://api.example.com/webhook' ) );
	}

	// ---------------------------------------------------
	// is_safe_url() - Ports
	// ---------------------------------------------------

	/**
	 * Tests that port 443 is allowed
	 *
	 * @return void
	 */
	public function test_is_safe_url_allows_port_443() {
		$client = $this->create_client_with_resolved_ip(
			$this->create_redaction_mock(),
			'93.184.216.34'
		);

		$this->assertTrue( $client->is_safe_url( 'https://example.com:443/webhook' ) );
	}

	/**
	 * Tests that port 80 is allowed
	 *
	 * @return void
	 */
	public function test_is_safe_url_allows_port_80() {
		$client = $this->create_client_with_resolved_ip(
			$this->create_redaction_mock(),
			'93.184.216.34'
		);

		$this->assertTrue( $client->is_safe_url( 'http://example.com:80/webhook' ) );
	}

	/**
	 * Tests that non-standard port is rejected
	 *
	 * @return void
	 */
	public function test_is_safe_url_rejects_non_standard_port() {
		$client = new HttpClient( $this->create_redaction_mock() );
		$this->assertFalse( $client->is_safe_url( 'https://example.com:8080/webhook' ) );
	}

	/**
	 * Tests that port 22 (SSH) is rejected
	 *
	 * @return void
	 */
	public function test_is_safe_url_rejects_ssh_port() {
		$client = new HttpClient( $this->create_redaction_mock() );
		$this->assertFalse( $client->is_safe_url( 'https://example.com:22/webhook' ) );
	}

	// ---------------------------------------------------
	// is_safe_url() - DNS resolution failure
	// ---------------------------------------------------

	/**
	 * Tests that DNS resolution failure is rejected
	 *
	 * gethostbyname() returns the hostname if it cannot resolve.
	 *
	 * @return void
	 */
	public function test_is_safe_url_rejects_unresolvable_host() {
		$client = $this->create_client_with_resolved_ip(
			$this->create_redaction_mock(),
			'nonexistent.example.invalid' // gethostbyname returns the hostname if it fails.
		);

		$this->assertFalse( $client->is_safe_url( 'https://nonexistent.example.invalid/webhook' ) );
	}

	/**
	 * Tests that IPv6 is rejected (safe-fail, only IPv4 supported)
	 *
	 * @return void
	 */
	public function test_is_safe_url_rejects_ipv6_address() {
		$client = $this->create_client_with_resolved_ip(
			$this->create_redaction_mock(),
			'::1' // IPv6 loopback.
		);

		$this->assertFalse( $client->is_safe_url( 'https://ipv6host.example.com/webhook' ) );
	}

	// ---------------------------------------------------
	// post() - Success
	// ---------------------------------------------------

	/**
	 * Tests POST with success
	 *
	 * @return void
	 */
	public function test_post_success() {
		$client = $this->create_client_with_resolved_ip(
			$this->create_redaction_mock(),
			'93.184.216.34'
		);

		Functions\expect( 'wp_remote_post' )
			->once()
			->andReturn(
				[
					'response' => [ 'code' => 200 ],
					'body'     => '{"ok":true}',
				]
			);

		Functions\expect( 'is_wp_error' )
			->once()
			->andReturn( false );

		Functions\expect( 'wp_remote_retrieve_response_code' )
			->once()
			->andReturn( 200 );

		Functions\expect( 'wp_remote_retrieve_body' )
			->once()
			->andReturn( '{"ok":true}' );

		$result = $client->post(
			'https://api.example.com/webhook',
			[ 'key' => 'value' ]
		);

		$this->assertTrue( $result['success'] );
		$this->assertEquals( 200, $result['code'] );
		$this->assertEquals( '{"ok":true}', $result['body'] );
		$this->assertNull( $result['error'] );
	}

	/**
	 * Tests POST with custom headers
	 *
	 * @return void
	 */
	public function test_post_sends_correct_headers_and_body() {
		$client = $this->create_client_with_resolved_ip(
			$this->create_redaction_mock(),
			'93.184.216.34'
		);

		Functions\expect( 'wp_remote_post' )
			->once()
			->with(
				'https://api.example.com/webhook',
				Mockery::on(
					function ( $args ) {
						return isset( $args['headers']['Content-Type'] )
							&& 'application/json' === $args['headers']['Content-Type']
							&& isset( $args['headers']['X-Custom'] )
							&& 'custom-value' === $args['headers']['X-Custom']
							&& 0 === $args['redirection']
							&& 5 === $args['timeout']
							&& true === $args['blocking'];
					}
				)
			)
			->andReturn(
				[
					'response' => [ 'code' => 200 ],
					'body'     => 'ok',
				]
			);

		Functions\expect( 'is_wp_error' )->once()->andReturn( false );
		Functions\expect( 'wp_remote_retrieve_response_code' )->once()->andReturn( 200 );
		Functions\expect( 'wp_remote_retrieve_body' )->once()->andReturn( 'ok' );

		$result = $client->post(
			'https://api.example.com/webhook',
			[ 'data' => 'test' ],
			[ 'X-Custom' => 'custom-value' ]
		);

		$this->assertTrue( $result['success'] );
	}

	// ---------------------------------------------------
	// post() - Errors
	// ---------------------------------------------------

	/**
	 * Tests POST to unsafe URL
	 *
	 * @return void
	 */
	public function test_post_rejects_unsafe_url() {
		$client = $this->create_client_with_resolved_ip(
			$this->create_redaction_mock(),
			'127.0.0.1'
		);

		$this->mock_i18n();

		$result = $client->post(
			'https://localhost/internal',
			[ 'key' => 'value' ]
		);

		$this->assertFalse( $result['success'] );
		$this->assertEquals( 0, $result['code'] );
		$this->assertNotNull( $result['error'] );
	}

	/**
	 * Tests POST with WP_Error
	 *
	 * @return void
	 */
	public function test_post_handles_wp_error() {
		$client = $this->create_client_with_resolved_ip(
			$this->create_redaction_mock(),
			'93.184.216.34'
		);

		$wp_error = Mockery::mock( 'WP_Error' );
		$wp_error->shouldReceive( 'get_error_message' )
			->andReturn( 'Connection timed out at /path/to/file' );

		Functions\expect( 'wp_remote_post' )
			->once()
			->andReturn( $wp_error );

		Functions\expect( 'is_wp_error' )
			->once()
			->andReturn( true );

		$this->mock_i18n();

		$result = $client->post(
			'https://api.example.com/webhook',
			[ 'key' => 'value' ]
		);

		$this->assertFalse( $result['success'] );
		$this->assertEquals( 0, $result['code'] );
		$this->assertStringContainsString( 'Connection timed out', $result['error'] );
	}

	/**
	 * Tests POST with non-2xx response returns success:false
	 *
	 * @return void
	 */
	public function test_post_returns_failure_for_non_2xx_response() {
		$client = $this->create_client_with_resolved_ip(
			$this->create_redaction_mock(),
			'93.184.216.34'
		);

		Functions\expect( 'wp_remote_post' )
			->once()
			->andReturn(
				[
					'response' => [ 'code' => 500 ],
					'body'     => 'Internal Server Error',
				]
			);

		Functions\expect( 'is_wp_error' )->once()->andReturn( false );
		Functions\expect( 'wp_remote_retrieve_response_code' )->once()->andReturn( 500 );
		Functions\expect( 'wp_remote_retrieve_body' )->once()->andReturn( 'Internal Server Error' );

		$this->mock_i18n();

		$result = $client->post(
			'https://api.example.com/webhook',
			[ 'key' => 'value' ]
		);

		$this->assertFalse( $result['success'] );
		$this->assertEquals( 500, $result['code'] );
		$this->assertEquals( 'Internal Server Error', $result['body'] );
		$this->assertNotNull( $result['error'] );
	}

	/**
	 * Tests POST with 4xx response returns success:false
	 *
	 * @return void
	 */
	public function test_post_returns_failure_for_4xx_response() {
		$client = $this->create_client_with_resolved_ip(
			$this->create_redaction_mock(),
			'93.184.216.34'
		);

		Functions\expect( 'wp_remote_post' )
			->once()
			->andReturn(
				[
					'response' => [ 'code' => 403 ],
					'body'     => 'Forbidden',
				]
			);

		Functions\expect( 'is_wp_error' )->once()->andReturn( false );
		Functions\expect( 'wp_remote_retrieve_response_code' )->once()->andReturn( 403 );
		Functions\expect( 'wp_remote_retrieve_body' )->once()->andReturn( 'Forbidden' );

		$this->mock_i18n();

		$result = $client->post(
			'https://api.example.com/webhook',
			[ 'key' => 'value' ]
		);

		$this->assertFalse( $result['success'] );
		$this->assertEquals( 403, $result['code'] );
	}

	/**
	 * Tests POST with 201 response returns success:true
	 *
	 * @return void
	 */
	public function test_post_returns_success_for_201_response() {
		$client = $this->create_client_with_resolved_ip(
			$this->create_redaction_mock(),
			'93.184.216.34'
		);

		Functions\expect( 'wp_remote_post' )
			->once()
			->andReturn(
				[
					'response' => [ 'code' => 201 ],
					'body'     => '{"created":true}',
				]
			);

		Functions\expect( 'is_wp_error' )->once()->andReturn( false );
		Functions\expect( 'wp_remote_retrieve_response_code' )->once()->andReturn( 201 );
		Functions\expect( 'wp_remote_retrieve_body' )->once()->andReturn( '{"created":true}' );

		$result = $client->post(
			'https://api.example.com/webhook',
			[ 'key' => 'value' ]
		);

		$this->assertTrue( $result['success'] );
		$this->assertEquals( 201, $result['code'] );
	}

	/**
	 * Tests that errors are redacted via RedactionInterface
	 *
	 * @return void
	 */
	public function test_post_error_messages_are_redacted() {
		$redaction = Mockery::mock( RedactionInterface::class );
		$redaction->shouldReceive( 'redact' )
			->once()
			->with( 'Connection failed to 10.0.0.1:3306' )
			->andReturn( '[REDACTED]' );

		$client = $this->create_client_with_resolved_ip( $redaction, '93.184.216.34' );

		$wp_error = Mockery::mock( 'WP_Error' );
		$wp_error->shouldReceive( 'get_error_message' )
			->andReturn( 'Connection failed to 10.0.0.1:3306' );

		Functions\expect( 'wp_remote_post' )->once()->andReturn( $wp_error );
		Functions\expect( 'is_wp_error' )->once()->andReturn( true );

		$this->mock_i18n();

		$result = $client->post(
			'https://api.example.com/webhook',
			[ 'key' => 'value' ]
		);

		$this->assertFalse( $result['success'] );
		$this->assertStringContainsString( '[REDACTED]', $result['error'] );
	}

	// ---------------------------------------------------
	// post() - Pre-serialized body (string)
	// ---------------------------------------------------

	/**
	 * Tests POST with pre-serialized string body (no re-encode)
	 *
	 * @return void
	 */
	public function test_post_sends_pre_serialized_string_body_as_is() {
		$client = $this->create_client_with_resolved_ip(
			$this->create_redaction_mock(),
			'93.184.216.34'
		);

		$raw_body = '{"key":"value"}';

		Functions\expect( 'wp_remote_post' )
			->once()
			->with(
				'https://api.example.com/webhook',
				Mockery::on(
					function ( $args ) use ( $raw_body ) {
						return $args['body'] === $raw_body;
					}
				)
			)
			->andReturn(
				[
					'response' => [ 'code' => 200 ],
					'body'     => 'ok',
				]
			);

		Functions\expect( 'is_wp_error' )->once()->andReturn( false );
		Functions\expect( 'wp_remote_retrieve_response_code' )->once()->andReturn( 200 );
		Functions\expect( 'wp_remote_retrieve_body' )->once()->andReturn( 'ok' );

		$result = $client->post(
			'https://api.example.com/webhook',
			$raw_body
		);

		$this->assertTrue( $result['success'] );
	}

	/**
	 * Tests POST with array body is JSON-encoded
	 *
	 * @return void
	 */
	public function test_post_json_encodes_array_body() {
		$client = $this->create_client_with_resolved_ip(
			$this->create_redaction_mock(),
			'93.184.216.34'
		);

		$body     = [ 'key' => 'value' ];
		$expected = json_encode( $body );

		Functions\expect( 'wp_remote_post' )
			->once()
			->with(
				'https://api.example.com/webhook',
				Mockery::on(
					function ( $args ) use ( $expected ) {
						return $args['body'] === $expected;
					}
				)
			)
			->andReturn(
				[
					'response' => [ 'code' => 200 ],
					'body'     => 'ok',
				]
			);

		Functions\expect( 'is_wp_error' )->once()->andReturn( false );
		Functions\expect( 'wp_remote_retrieve_response_code' )->once()->andReturn( 200 );
		Functions\expect( 'wp_remote_retrieve_body' )->once()->andReturn( 'ok' );

		$result = $client->post(
			'https://api.example.com/webhook',
			$body
		);

		$this->assertTrue( $result['success'] );
	}

	// ---------------------------------------------------
	// Helper
	// ---------------------------------------------------

	/**
	 * Configures the mocks for WordPress i18n functions
	 *
	 * @return void
	 */
	private function mock_i18n() {
		Functions\when( '__' )->returnArg( 1 );
		Functions\when( 'esc_html__' )->returnArg( 1 );
	}

	/**
	 * Tests that URL with valid scheme but without host is rejected
	 *
	 * Covers line 86: branch `!isset($parts['host']) || '' === $parts['host']`.
	 *
	 * @return void
	 */
	public function test_is_safe_url_rejects_url_without_host() {
		$client = new HttpClient( $this->create_redaction_mock() );

		// 'http:path' has scheme 'http' but no host.
		$this->assertFalse( $client->is_safe_url( 'http:path' ) );
	}

	/**
	 * Tests that resolve_host calls gethostbyname
	 *
	 * Covers line 188: `return gethostbyname($hostname)`.
	 *
	 * @return void
	 */
	public function test_resolve_host_returns_resolved_ip() {
		$client = new HttpClient( $this->create_redaction_mock() );

		$method = new \ReflectionMethod( HttpClient::class, 'resolve_host' );
		$method->setAccessible( true );

		$result = $method->invoke( $client, 'localhost' );
		$this->assertIsString( $result );
		$this->assertEquals( '127.0.0.1', $result );
	}

	// ---------------------------------------------------
	// DNS pinning (anti-TOCTOU)
	// ---------------------------------------------------

	/**
	 * Tests that post() registers http_api_curl action for DNS pinning
	 *
	 * Prevents DNS rebinding (TOCTOU) between validation and request.
	 *
	 * @return void
	 */
	public function test_post_registers_dns_pin_action() {
		$client = $this->create_client_with_resolved_ip(
			$this->create_redaction_mock(),
			'93.184.216.34'
		);

		Monkey\Actions\expectAdded( 'http_api_curl' )
			->once();

		Functions\expect( 'wp_remote_post' )
			->once()
			->andReturn(
				[
					'response' => [ 'code' => 200 ],
					'body'     => 'ok',
				]
			);

		Functions\expect( 'is_wp_error' )->once()->andReturn( false );
		Functions\expect( 'wp_remote_retrieve_response_code' )->once()->andReturn( 200 );
		Functions\expect( 'wp_remote_retrieve_body' )->once()->andReturn( 'ok' );

		$result = $client->post(
			'https://api.example.com/webhook',
			[ 'key' => 'value' ]
		);

		$this->assertTrue( $result['success'] );
	}

	/**
	 * Tests that create_dns_pin() returns a valid Closure
	 *
	 * @return void
	 */
	public function test_create_dns_pin_returns_closure() {
		$client = new HttpClient( $this->create_redaction_mock() );

		$method = new \ReflectionMethod( HttpClient::class, 'create_dns_pin' );
		$method->setAccessible( true );

		$pin = $method->invoke( $client, 'example.com', '93.184.216.34', 443 );

		$this->assertInstanceOf( \Closure::class, $pin );
	}

	/**
	 * Tests that post() does not register DNS pin for unsafe URLs
	 *
	 * @return void
	 */
	public function test_post_does_not_pin_dns_for_unsafe_url() {
		$client = $this->create_client_with_resolved_ip(
			$this->create_redaction_mock(),
			'127.0.0.1'
		);

		$this->mock_i18n();

		// http_api_curl action should NOT be added.
		Monkey\Actions\expectAdded( 'http_api_curl' )->never();

		// wp_remote_post should NOT be called.
		Functions\expect( 'wp_remote_post' )->never();

		$result = $client->post(
			'https://localhost/internal',
			[ 'key' => 'value' ]
		);

		$this->assertFalse( $result['success'] );
	}
}
