<?php
/**
 * Redaction Service
 *
 * Sanitizza testi rimuovendo dati sensibili come credenziali,
 * path di sistema, email, indirizzi IP e token.
 *
 * @package OpsHealthDashboard\Services
 */

namespace OpsHealthDashboard\Services;

use OpsHealthDashboard\Interfaces\RedactionInterface;

/**
 * Class Redaction
 *
 * Servizio di redazione dati sensibili.
 * NO singleton, NO static methods, NO final.
 */
class Redaction implements RedactionInterface {

	/**
	 * WordPress ABSPATH per sostituzione path
	 *
	 * @var string
	 */
	private $abspath;

	/**
	 * WordPress WP_CONTENT_DIR per sostituzione path
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
	 * Applica la redazione a un singolo testo
	 *
	 * Applica sequenzialmente tutti i pattern di redazione.
	 * L'ordine è importante: path specifici prima di quelli generici.
	 *
	 * @param string $text Testo da sanitizzare.
	 * @return string Testo con dati sensibili rimossi.
	 */
	public function redact( string $text ): string {
		if ( '' === $text ) {
			return $text;
		}

		// 1. Path replacement (più specifici prima).
		$text = $this->redact_paths( $text );

		// 2. Credenziali DB e WordPress salts.
		$text = $this->redact_credentials( $text );

		// 3. API key, token, bearer.
		$text = $this->redact_tokens( $text );

		// 4. Password in URL e campi generici.
		$text = $this->redact_passwords( $text );

		// 5. PII: email e IP.
		$text = $this->redact_pii( $text );

		// 6. Home directory.
		$text = $this->redact_home_dirs( $text );

		return $text;
	}

	/**
	 * Applica la redazione a un array di righe
	 *
	 * @param array $lines Array di stringhe da sanitizzare.
	 * @return array Array di stringhe sanitizzate.
	 */
	public function redact_lines( array $lines ): array {
		return array_map( [ $this, 'redact' ], $lines );
	}

	/**
	 * Redige i path di sistema
	 *
	 * WP_CONTENT_DIR viene sostituito prima di ABSPATH perché è più specifico
	 * e spesso è un sotto-path di ABSPATH.
	 *
	 * @param string $text Testo da sanitizzare.
	 * @return string Testo con path redatti.
	 */
	private function redact_paths( string $text ): string {
		// WP_CONTENT_DIR prima (più specifico, più lungo).
		if ( '' !== $this->content_dir ) {
			$text = str_replace( $this->content_dir, '[WP_CONTENT]', $text );
		}

		// ABSPATH dopo.
		if ( '' !== $this->abspath ) {
			$text = str_replace( $this->abspath, '[ABSPATH]/', $text );
		}

		return $text;
	}

	/**
	 * Redige credenziali DB e WordPress salts
	 *
	 * @param string $text Testo da sanitizzare.
	 * @return string Testo con credenziali redatte.
	 */
	private function redact_credentials( string $text ): string {
		// DB credentials: DB_PASSWORD, DB_USER, DB_NAME, DB_HOST.
		// Gestisce sia define('DB_PASSWORD', 'value') sia DB_PASSWORD = 'value'.
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
	 * Redige API key, token e bearer
	 *
	 * @param string $text Testo da sanitizzare.
	 * @return string Testo con token redatti.
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
	 * Redige password in URL e campi generici
	 *
	 * @param string $text Testo da sanitizzare.
	 * @return string Testo con password redatte.
	 */
	private function redact_passwords( string $text ): string {
		// Password in URL (es: mysql://user:pass@host).
		$text = preg_replace(
			'/(:\/\/[^:\/]+:)[^@]+(@)/',
			'$1[REDACTED]$2',
			$text
		);

		// Campi password generici.
		$text = preg_replace(
			'/(password|passwd|pwd)\s*[=:]\s*[\'"]?[^\s\'\"]+[\'"]?/i',
			'$1=[REDACTED]',
			$text
		);

		return $text;
	}

	/**
	 * Redige dati personali: email e indirizzi IP
	 *
	 * @param string $text Testo da sanitizzare.
	 * @return string Testo con PII redatti.
	 */
	private function redact_pii( string $text ): string {
		// Email.
		$text = preg_replace(
			'/[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}/',
			'[EMAIL_REDACTED]',
			$text
		);

		// IPv4.
		$text = preg_replace(
			'/\b\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}\b/',
			'[IP_REDACTED]',
			$text
		);

		// IPv6 (minimo 5 gruppi per evitare falsi positivi su timestamp).
		$text = preg_replace(
			'/\b([0-9a-fA-F]{1,4}:){4,7}[0-9a-fA-F]{1,4}\b/',
			'[IP_REDACTED]',
			$text
		);

		return $text;
	}

	/**
	 * Redige i path delle directory home
	 *
	 * @param string $text Testo da sanitizzare.
	 * @return string Testo con home directory redatte.
	 */
	private function redact_home_dirs( string $text ): string {
		return preg_replace(
			'/\/home\/[a-zA-Z0-9._\-]+/',
			'/home/[USER_REDACTED]',
			$text
		);
	}
}
