<?php
/**
 * Redaction Service
 *
 * Sanitizes text by removing sensitive data such as credentials,
 * system paths, emails, IP addresses, and tokens.
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

use OpsHealthDashboard\Interfaces\RedactionInterface;

/**
 * Class Redaction
 *
 * Sensitive data redaction service.
 */
class Redaction implements RedactionInterface {

	/**
	 * WordPress ABSPATH for path replacement
	 *
	 * @var string
	 */
	private $abspath;

	/**
	 * WordPress WP_CONTENT_DIR for path replacement
	 *
	 * @var string
	 */
	private $content_dir;

	/**
	 * Constructor
	 *
	 * @param string $abspath     WordPress ABSPATH.
	 * @param string $content_dir WordPress WP_CONTENT_DIR.
	 */
	public function __construct( string $abspath = '', string $content_dir = '' ) {
		$this->abspath     = $abspath;
		$this->content_dir = $content_dir;
	}

	/**
	 * Applies redaction to a single text
	 *
	 * Applies all redaction patterns sequentially.
	 * Order matters: specific paths before generic ones.
	 *
	 * @param string $text Text to sanitize.
	 * @return string Text with sensitive data removed.
	 */
	public function redact( string $text ): string {
		if ( '' === $text ) {
			return $text;
		}

		// 1. Path replacement (most specific first).
		$text = $this->redact_paths( $text );

		// 2. DB credentials and WordPress salts.
		$text = $this->redact_credentials( $text );

		// 3. API key, token, bearer.
		$text = $this->redact_tokens( $text );

		// 4. Password in URL and generic fields.
		$text = $this->redact_passwords( $text );

		// 5. PII: email and IP.
		$text = $this->redact_pii( $text );

		// 6. Home directory.
		$text = $this->redact_home_dirs( $text );

		return $text;
	}

	/**
	 * Applies redaction to an array of lines
	 *
	 * @param array $lines Array of strings to sanitize.
	 * @return array Array of sanitized strings.
	 */
	public function redact_lines( array $lines ): array {
		return array_map( [ $this, 'redact' ], $lines );
	}

	/**
	 * Redacts system paths
	 *
	 * WP_CONTENT_DIR is replaced before ABSPATH because it is more specific
	 * and is often a sub-path of ABSPATH.
	 *
	 * @param string $text Text to sanitize.
	 * @return string Text with redacted paths.
	 */
	private function redact_paths( string $text ): string {
		// WP_CONTENT_DIR first (more specific, longer).
		if ( '' !== $this->content_dir ) {
			$text = str_replace( $this->content_dir, '[WP_CONTENT]', $text );
		}

		// ABSPATH after.
		if ( '' !== $this->abspath ) {
			$text = str_replace( $this->abspath, '[ABSPATH]/', $text );
		}

		return $text;
	}

	/**
	 * Redacts DB credentials and WordPress salts
	 *
	 * @param string $text Text to sanitize.
	 * @return string Text with redacted credentials.
	 */
	private function redact_credentials( string $text ): string {
		// DB credentials: DB_PASSWORD, DB_USER, DB_NAME, DB_HOST.
		// Handles both define('DB_PASSWORD', 'value') and DB_PASSWORD = 'value'.
		$text = preg_replace(
			"/(DB_PASSWORD|DB_USER|DB_NAME|DB_HOST)(['\"]?\s*[,=]\s*['\"])[^'\"]*(['\"])/i",
			'$1$2[REDACTED]$3',
			$text
		);

		// WordPress salts/keys.
		$salt_names  = 'AUTH_KEY|SECURE_AUTH_KEY|LOGGED_IN_KEY|NONCE_KEY';
		$salt_names .= '|AUTH_SALT|SECURE_AUTH_SALT|LOGGED_IN_SALT|NONCE_SALT';
		$text        = preg_replace(
			"/({$salt_names})(['\"]?\s*[,=]\s*['\"])[^'\"]*(['\"])/i",
			'$1$2[REDACTED]$3',
			$text
		);

		return $text;
	}

	/**
	 * Redacts API keys, tokens, and bearer
	 *
	 * @param string $text Text to sanitize.
	 * @return string Text with redacted tokens.
	 */
	private function redact_tokens( string $text ): string {
		// API key, secret, token patterns.
		$token_names  = 'api[_-]?key|api[_-]?secret|auth[_-]?token';
		$token_names .= '|access[_-]?token|secret[_-]?key|private[_-]?key';
		$text         = preg_replace(
			"/({$token_names})\s*[=:]\s*['\"]?[\w\-\.]{8,}['\"]?/i",
			'$1=[REDACTED]',
			$text
		);

		// Bearer tokens.
		$text = preg_replace(
			'/(bearer)\s+[\w\-\.]{8,}/i',
			'$1 [REDACTED]',
			$text
		);

		return $text;
	}

	/**
	 * Redacts passwords in URLs and generic fields
	 *
	 * @param string $text Text to sanitize.
	 * @return string Text with redacted passwords.
	 */
	private function redact_passwords( string $text ): string {
		// Password in URL (e.g.: mysql://user:pass@host).
		// Uses [^\s]+ (greedy) to handle passwords with @ — backtracking finds the last @.
		$text = preg_replace(
			'/(:\/\/[^:\/\s]+:)[^\s]+(@)/',
			'$1[REDACTED]$2',
			$text
		);

		// Generic password fields.
		$text = preg_replace(
			'/(password|passwd|pwd)\s*[=:]\s*[\'"]?[^\s\'\"]+[\'"]?/i',
			'$1=[REDACTED]',
			$text
		);

		return $text;
	}

	/**
	 * Redacts personal data: emails and IP addresses
	 *
	 * @param string $text Text to sanitize.
	 * @return string Text with redacted PII.
	 */
	private function redact_pii( string $text ): string {
		// Email.
		$text = preg_replace(
			'/[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}/',
			'[EMAIL_REDACTED]',
			$text
		);

		// IPv4 (with octet validation 0-255).
		$text = preg_replace(
			'/\b(?:(?:25[0-5]|2[0-4]\d|[01]?\d\d?)\.){3}(?:25[0-5]|2[0-4]\d|[01]?\d\d?)\b/',
			'[IP_REDACTED]',
			$text
		);

		// IPv6 (minimum 5 groups to avoid false positives on timestamps).
		$text = preg_replace(
			'/\b([0-9a-fA-F]{1,4}:){4,7}[0-9a-fA-F]{1,4}\b/',
			'[IP_REDACTED]',
			$text
		);

		return $text;
	}

	/**
	 * Redacts home directory paths
	 *
	 * @param string $text Text to sanitize.
	 * @return string Text with redacted home directories.
	 */
	private function redact_home_dirs( string $text ): string {
		return preg_replace(
			'/\/home\/[a-zA-Z0-9._\-]+/',
			'/home/[USER_REDACTED]',
			$text
		);
	}
}
