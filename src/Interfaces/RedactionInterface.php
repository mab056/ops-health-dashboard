<?php
/**
 * Redaction Interface
 *
 * Contratto per il servizio di redazione dati sensibili.
 * Sanitizza testi rimuovendo credenziali, path, email, IP e altri dati sensibili.
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
 * Definisce i metodi per la redazione di dati sensibili.
 */
interface RedactionInterface {

	/**
	 * Applica la redazione a un singolo testo
	 *
	 * @param string $text Testo da sanitizzare.
	 * @return string Testo con dati sensibili rimossi.
	 */
	public function redact( string $text ): string;

	/**
	 * Applica la redazione a un array di righe
	 *
	 * @param array $lines Array di stringhe da sanitizzare.
	 * @return array Array di stringhe sanitizzate.
	 */
	public function redact_lines( array $lines ): array;
}
