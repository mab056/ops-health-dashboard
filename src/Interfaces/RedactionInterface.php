<?php
/**
 * Redaction Interface
 *
 * Contract for the sensitive data redaction service.
 * Sanitizes text by removing credentials, paths, emails, IPs, and other sensitive data.
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
 * Interface RedactionInterface
 *
 * Defines the methods for sensitive data redaction.
 */
interface RedactionInterface {

	/**
	 * Applies redaction to a single text
	 *
	 * @param string $text Text to sanitize.
	 * @return string Text with sensitive data removed.
	 */
	public function redact( string $text ): string;

	/**
	 * Applies redaction to an array of lines
	 *
	 * @param array $lines Array of strings to sanitize.
	 * @return array Array of sanitized strings.
	 */
	public function redact_lines( array $lines ): array;
}
