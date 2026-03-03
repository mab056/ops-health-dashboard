<?php
/**
 * HTTP Client Interface
 *
 * Contract for a secure HTTP client with anti-SSRF protection.
 * All outgoing HTTP requests MUST go through this interface.
 *
 * @package OpsHealthDashboard\Interfaces
 */

namespace OpsHealthDashboard\Interfaces;

if ( ! defined( 'ABSPATH' ) ) {
	// phpcs:ignore Squiz.Commenting.InlineComment.InvalidEndChar
	// @codeCoverageIgnoreStart
	exit;
	// phpcs:ignore Squiz.Commenting.InlineComment.InvalidEndChar
	// @codeCoverageIgnoreEnd
}

/**
 * Interface HttpClientInterface
 *
 * Defines the contract for secure HTTP requests with anti-SSRF validation.
 */
interface HttpClientInterface {

	/**
	 * Validates whether a URL is safe for outgoing requests (anti-SSRF)
	 *
	 * Verifies scheme, DNS resolution, private IP ranges, and allowed ports.
	 *
	 * @param string $url URL to validate.
	 * @return bool True if the URL is safe.
	 */
	public function is_safe_url( string $url ): bool;

	/**
	 * Sends an HTTP POST request
	 *
	 * The URL MUST pass is_safe_url() validation before sending.
	 *
	 * @param string       $url     Destination URL.
	 * @param array|string $body    Body data: array (auto-serialized to JSON) or pre-serialized string.
	 * @param array        $headers Additional HTTP headers.
	 * @return array {
	 *     @type bool        $success    Whether the request was successful.
	 *     @type int         $code       HTTP response code.
	 *     @type string      $body       Response body.
	 *     @type string|null $error      Error message if failed.
	 * }
	 */
	public function post( string $url, $body, array $headers = [] ): array;
}
