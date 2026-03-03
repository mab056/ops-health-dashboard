<?php
/**
 * HTTP Client Service
 *
 * Secure HTTP client with anti-SSRF protection for all outgoing requests.
 * Validates scheme, DNS resolution, IP ranges, and ports before each request.
 *
 * @package OpsHealthDashboard\Services
 */

namespace OpsHealthDashboard\Services;

if ( ! defined( 'ABSPATH' ) ) {
	// phpcs:ignore Squiz.Commenting.InlineComment.InvalidEndChar
	// @codeCoverageIgnoreStart
	exit;
	// phpcs:ignore Squiz.Commenting.InlineComment.InvalidEndChar
	// @codeCoverageIgnoreEnd
}

use OpsHealthDashboard\Interfaces\HttpClientInterface;
use OpsHealthDashboard\Interfaces\RedactionInterface;

/**
 * Class HttpClient
 *
 * HTTP client implementation with anti-SSRF protection.
 */
class HttpClient implements HttpClientInterface {

	/**
	 * Timeout for HTTP requests in seconds
	 *
	 * @var int
	 */
	const TIMEOUT = 5;

	/**
	 * Allowed URL schemes
	 *
	 * @var array
	 */
	const ALLOWED_SCHEMES = [ 'http', 'https' ];

	/**
	 * Allowed ports
	 *
	 * @var array
	 */
	const ALLOWED_PORTS = [ 80, 443 ];

	/**
	 * Redaction service for error messages
	 *
	 * @var RedactionInterface
	 */
	private $redaction;

	/**
	 * Constructor
	 *
	 * @param RedactionInterface $redaction Sensitive data redaction service.
	 */
	public function __construct( RedactionInterface $redaction ) {
		$this->redaction = $redaction;
	}

	/**
	 * Validates whether a URL is safe for outgoing requests (anti-SSRF)
	 *
	 * @param string $url URL to validate.
	 * @return bool True if the URL is safe.
	 */
	public function is_safe_url( string $url ): bool {
		return null !== $this->validate_and_resolve( $url );
	}

	/**
	 * Sends an HTTP POST request
	 *
	 * Uses DNS pinning via CURLOPT_RESOLVE to prevent TOCTOU/DNS rebinding
	 * attacks between validation and the actual request.
	 *
	 * @param string       $url     Destination URL.
	 * @param array|string $body    Body data: array (auto-serialized to JSON) or pre-serialized string.
	 * @param array        $headers Additional HTTP headers.
	 * @return array Result with keys success, code, body, error.
	 */
	public function post( string $url, $body, array $headers = [] ): array {
		$resolved = $this->validate_and_resolve( $url );

		if ( null === $resolved ) {
			return [
				'success' => false,
				'code'    => 0,
				'body'    => '',
				'error'   => __( 'Unsafe URL detected', 'ops-health-dashboard' ),
			];
		}

		// Pin DNS to prevent TOCTOU/DNS rebinding.
		$pin = $this->create_dns_pin(
			$resolved['host'],
			$resolved['ip'],
			$resolved['port']
		);
		add_action( 'http_api_curl', $pin );

		$args = [
			// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- wp_json_encode not available in unit test.
			'body'        => is_string( $body ) ? $body : json_encode( $body ),
			'headers'     => array_merge(
				[ 'Content-Type' => 'application/json' ],
				$headers
			),
			'timeout'     => self::TIMEOUT,
			'redirection' => 0,
			'blocking'    => true,
		];

		$response = wp_remote_post( $url, $args );

		remove_action( 'http_api_curl', $pin );

		if ( is_wp_error( $response ) ) {
			return [
				'success' => false,
				'code'    => 0,
				'body'    => '',
				'error'   => sprintf(
					/* translators: %s: redacted error message */
					__( 'HTTP request failed: %s', 'ops-health-dashboard' ),
					$this->redaction->redact( $response->get_error_message() )
				),
			];
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		if ( $code < 200 || $code >= 300 ) {
			return [
				'success' => false,
				'code'    => $code,
				'body'    => $body,
				'error'   => sprintf(
					/* translators: %d: HTTP status code */
					__( 'HTTP request returned status %d', 'ops-health-dashboard' ),
					$code
				),
			];
		}

		return [
			'success' => true,
			'code'    => $code,
			'body'    => $body,
			'error'   => null,
		];
	}

	/**
	 * Validates URL and resolves DNS, returns connection data
	 *
	 * Performs all anti-SSRF checks (scheme, host, port, DNS, private IP)
	 * and returns the data needed for DNS pinning.
	 *
	 * @param string $url URL to validate.
	 * @return array|null Array with host, ip, port if valid, null otherwise.
	 */
	private function validate_and_resolve( string $url ) {
		if ( '' === $url ) {
			return null;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- Uses native parse_url to avoid WP dependency.
		$parts = parse_url( $url );

		if ( false === $parts || ! is_array( $parts ) ) {
			return null;
		}

		// Verify scheme.
		$scheme = isset( $parts['scheme'] ) ? strtolower( $parts['scheme'] ) : '';
		if ( ! in_array( $scheme, self::ALLOWED_SCHEMES, true ) ) {
			return null;
		}

		// Verify host is present.
		if ( ! isset( $parts['host'] ) || '' === $parts['host'] ) {
			return null;
		}

		// Determine port (default based on scheme).
		$port = isset( $parts['port'] )
			? (int) $parts['port']
			: ( 'https' === $scheme ? 443 : 80 );

		if ( ! in_array( $port, self::ALLOWED_PORTS, true ) ) {
			return null;
		}

		// Resolve hostname and verify IP.
		$ip = $this->resolve_host( $parts['host'] );

		// gethostbyname returns the hostname if it cannot resolve.
		if ( $ip === $parts['host'] && ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			return null;
		}

		// Verify the IP is not private.
		if ( $this->is_private_ip( $ip ) ) {
			return null;
		}

		return [
			'host' => $parts['host'],
			'ip'   => $ip,
			'port' => $port,
		];
	}

	/**
	 * Creates a closure for DNS pinning via CURLOPT_RESOLVE
	 *
	 * Forces cURL to use the already-validated IP, preventing DNS rebinding.
	 * Extracted to protected method for testability (Reflection pattern).
	 *
	 * @param string $host Hostname to pin.
	 * @param string $ip   Resolved and validated IP.
	 * @param int    $port Destination port.
	 * @return \Closure Closure to pass to add_action('http_api_curl').
	 */
	protected function create_dns_pin( string $host, string $ip, int $port ): \Closure {
		return function ( $handle ) use ( $host, $ip, $port ) {
			// phpcs:ignore Squiz.Commenting.InlineComment.InvalidEndChar
			// @codeCoverageIgnoreStart
			// phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_setopt -- Required for anti-TOCTOU DNS pinning.
			curl_setopt( $handle, CURLOPT_RESOLVE, [ "{$host}:{$port}:{$ip}" ] );
			// @codeCoverageIgnoreEnd
		};
	}

	/**
	 * Resolves a hostname to an IP address
	 *
	 * Extracted to protected method for testability (partial mock pattern).
	 *
	 * @param string $hostname Hostname to resolve.
	 * @return string Resolved IP address, or the hostname if resolution fails.
	 */
	protected function resolve_host( string $hostname ): string {
		return gethostbyname( $hostname );
	}

	/**
	 * Checks if an IP is in a private/reserved range
	 *
	 * Blocks: loopback (127.0.0.0/8), private (10/8, 172.16/12, 192.168/16),
	 * link-local (169.254/16), and 0.0.0.0.
	 *
	 * Note: IPv6 and invalid IPs are treated as unsafe (safe-fail).
	 * PHP's gethostbyname() returns only IPv4, so pure IPv6 is rejected
	 * by the FILTER_FLAG_IPV4 check as a precautionary measure.
	 *
	 * @param string $ip IP address to check.
	 * @return bool True if the IP is private/reserved or non-IPv4.
	 */
	private function is_private_ip( string $ip ): bool {
		if ( ! filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
			return true; // Non-IPv4 (including IPv6): treated as unsafe (safe-fail).
		}

		if ( '0.0.0.0' === $ip ) {
			return true;
		}

		$long = ip2long( $ip );

		// Defensive: ip2long never fails after FILTER_VALIDATE_IP.
		// phpcs:ignore Squiz.Commenting.InlineComment.InvalidEndChar
		// @codeCoverageIgnoreStart
		if ( false === $long ) {
			return true;
		}
		// @codeCoverageIgnoreEnd

		// Loopback 127.0.0.0/8.
		if ( ( $long & 0xFF000000 ) === 0x7F000000 ) {
			return true;
		}

		// Private 10.0.0.0/8.
		if ( ( $long & 0xFF000000 ) === 0x0A000000 ) {
			return true;
		}

		// Private 172.16.0.0/12.
		if ( ( $long & 0xFFF00000 ) === 0xAC100000 ) {
			return true;
		}

		// Private 192.168.0.0/16.
		if ( ( $long & 0xFFFF0000 ) === 0xC0A80000 ) {
			return true;
		}

		// Link-local 169.254.0.0/16.
		if ( ( $long & 0xFFFF0000 ) === 0xA9FE0000 ) {
			return true;
		}

		return false;
	}
}
