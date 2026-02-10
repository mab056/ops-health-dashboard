<?php
/**
 * Test per HttpClient Service
 *
 * Verifica la validazione anti-SSRF e le richieste HTTP sicure.
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
 * Test unitari per il servizio HttpClient con protezione anti-SSRF.
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
	 * Crea un mock di RedactionInterface
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
	 * Crea un HttpClient testabile con resolve_host mockato
	 *
	 * @param RedactionInterface $redaction Servizio di redazione.
	 * @param string             $resolved_ip IP restituito da resolve_host.
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
	 * Testa che la classe NON è final
	 *
	 * @return void
	 */
	public function test_class_is_not_final() {
		$reflection = new \ReflectionClass( HttpClient::class );
		$this->assertFalse( $reflection->isFinal(), 'HttpClient should NOT be final' );
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

		$this->assertEmpty( $static_methods, 'HttpClient should have NO static methods' );
	}

	/**
	 * Testa che NON esistono proprietà static
	 *
	 * @return void
	 */
	public function test_no_static_properties() {
		$reflection = new \ReflectionClass( HttpClient::class );
		$properties = $reflection->getProperties( \ReflectionProperty::IS_STATIC );

		$this->assertEmpty( $properties, 'HttpClient should have NO static properties' );
	}

	/**
	 * Testa che la classe implementa HttpClientInterface
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
	 * Testa che HTTPS è consentito
	 *
	 * @return void
	 */
	public function test_is_safe_url_allows_https() {
		$client = $this->create_client_with_resolved_ip(
			$this->create_redaction_mock(),
			'93.184.216.34' // IP pubblico.
		);

		$this->assertTrue( $client->is_safe_url( 'https://example.com/webhook' ) );
	}

	/**
	 * Testa che HTTP è consentito
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
	 * Testa che file:// è rifiutato
	 *
	 * @return void
	 */
	public function test_is_safe_url_rejects_file_scheme() {
		$client = new HttpClient( $this->create_redaction_mock() );
		$this->assertFalse( $client->is_safe_url( 'file:///etc/passwd' ) );
	}

	/**
	 * Testa che ftp:// è rifiutato
	 *
	 * @return void
	 */
	public function test_is_safe_url_rejects_ftp_scheme() {
		$client = new HttpClient( $this->create_redaction_mock() );
		$this->assertFalse( $client->is_safe_url( 'ftp://example.com' ) );
	}

	/**
	 * Testa che gopher:// è rifiutato
	 *
	 * @return void
	 */
	public function test_is_safe_url_rejects_gopher_scheme() {
		$client = new HttpClient( $this->create_redaction_mock() );
		$this->assertFalse( $client->is_safe_url( 'gopher://example.com' ) );
	}

	/**
	 * Testa che stringa vuota è rifiutata
	 *
	 * @return void
	 */
	public function test_is_safe_url_rejects_empty_string() {
		$client = new HttpClient( $this->create_redaction_mock() );
		$this->assertFalse( $client->is_safe_url( '' ) );
	}

	/**
	 * Testa che URL senza schema è rifiutato
	 *
	 * @return void
	 */
	public function test_is_safe_url_rejects_no_scheme() {
		$client = new HttpClient( $this->create_redaction_mock() );
		$this->assertFalse( $client->is_safe_url( 'example.com/webhook' ) );
	}

	/**
	 * Testa che URL malformato è rifiutato
	 *
	 * @return void
	 */
	public function test_is_safe_url_rejects_malformed_url() {
		$client = new HttpClient( $this->create_redaction_mock() );
		$this->assertFalse( $client->is_safe_url( 'http://' ) );
	}

	// ---------------------------------------------------
	// is_safe_url() - IP privati
	// ---------------------------------------------------

	/**
	 * Testa che localhost (127.0.0.1) è bloccato
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
	 * Testa che 127.0.0.53 è bloccato
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
	 * Testa che 10.x.x.x è bloccato
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
	 * Testa che 172.16.x.x è bloccato
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
	 * Testa che 172.31.x.x è bloccato (fine del range /12)
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
	 * Testa che 172.32.x.x NON è bloccato (fuori dal range /12)
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
	 * Testa che 192.168.x.x è bloccato
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
	 * Testa che 169.254.x.x (link-local) è bloccato
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
	 * Testa che 0.0.0.0 è bloccato
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
	 * Testa che IP pubblico è consentito
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
	// is_safe_url() - Porte
	// ---------------------------------------------------

	/**
	 * Testa che porta 443 è consentita
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
	 * Testa che porta 80 è consentita
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
	 * Testa che porta non standard è rifiutata
	 *
	 * @return void
	 */
	public function test_is_safe_url_rejects_non_standard_port() {
		$client = new HttpClient( $this->create_redaction_mock() );
		$this->assertFalse( $client->is_safe_url( 'https://example.com:8080/webhook' ) );
	}

	/**
	 * Testa che porta 22 (SSH) è rifiutata
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
	 * Testa che DNS resolution failure è rifiutato
	 *
	 * gethostbyname() ritorna l'hostname se non riesce a risolvere.
	 *
	 * @return void
	 */
	public function test_is_safe_url_rejects_unresolvable_host() {
		$client = $this->create_client_with_resolved_ip(
			$this->create_redaction_mock(),
			'nonexistent.example.invalid' // gethostbyname ritorna l'hostname se fallisce.
		);

		$this->assertFalse( $client->is_safe_url( 'https://nonexistent.example.invalid/webhook' ) );
	}

	// ---------------------------------------------------
	// post() - Successo
	// ---------------------------------------------------

	/**
	 * Testa POST con successo
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
	 * Testa POST con headers personalizzati
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
	// post() - Errori
	// ---------------------------------------------------

	/**
	 * Testa POST a URL non sicuro
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
	 * Testa POST con WP_Error
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
	 * Testa POST con risposta non-2xx
	 *
	 * @return void
	 */
	public function test_post_handles_non_200_response() {
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

		$result = $client->post(
			'https://api.example.com/webhook',
			[ 'key' => 'value' ]
		);

		$this->assertTrue( $result['success'] );
		$this->assertEquals( 500, $result['code'] );
		$this->assertEquals( 'Internal Server Error', $result['body'] );
	}

	/**
	 * Testa che errori sono redatti via RedactionInterface
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
	// Helper
	// ---------------------------------------------------

	/**
	 * Configura i mock per le funzioni i18n di WordPress
	 *
	 * @return void
	 */
	private function mock_i18n() {
		Functions\when( '__' )->returnArg( 1 );
		Functions\when( 'esc_html__' )->returnArg( 1 );
	}
}
