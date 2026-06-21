# Runtime RED Probe — webhook-json-encode-guard

> Validazione TDD del pilota `webhook-json-encode-guard` prima dell'implementazione.
> Modalità recon: nessuna modifica al codice produttivo, nessun fix definitivo, nessun commit.
> Il test RED è stato aggiunto temporaneamente al working tree, eseguito, e rimosso.
> Riferimenti: [PILOT_CANDIDATE_REPORT.md](../../../PILOT_CANDIDATE_REPORT.md) (Candidate 1), [CODE_REVIEW_v0.6.2.md](../../../CODE_REVIEW_v0.6.2.md) (finding correctness su WebhookChannel).
> Artefatto storico archiviato. Fonte attiva del comportamento: [specs/channels/webhook.md](../../../specs/channels/webhook.md).

## Environment

* **PHP version:** 8.3.31 (cli, NTS)
* **PHPUnit:** 9.6.34
* **PHPUnit config relevant flags:** `convertErrorsToExceptions="true"`, `convertNoticesToExceptions="true"`, `convertWarningsToExceptions="true"` (da `phpunit.xml.dist`)
* **Command run:**
  * Probe: `php /tmp/json_probe.php` (script in `/tmp`, fuori dal codice produttivo — rimosso a fine validazione)
  * RED test: `vendor/bin/phpunit --testsuite=unit --filter WebhookChannelTest`

## Probe results

`error_reporting(E_ALL)` + `set_error_handler` per intercettare qualunque warning. Comportamento reale osservato:

| Payload | Return value | json_last_error_msg | Warning/Exception? |
| ------- | ------------ | ------------------- | ------------------ |
| `['x' => "\xB1\x31"]` (UTF-8 invalido) | `false` | Malformed UTF-8 characters, possibly incorrectly encoded | **NONE** |
| `['value' => NAN]` | `false` | Inf and NaN cannot be JSON encoded | **NONE** |
| `['value' => INF]` | `false` | Inf and NaN cannot be JSON encoded | **NONE** |
| `['a' => 1]` (controllo) | `{"a":1}` | No error | NONE |

`json_encode()` **ritorna `false` silenziosamente**: nessun warning, nessuna eccezione, in tutti i casi di fallimento.

## RED test result

```text
EXPECTED RED
```

Test temporaneo `test_send_returns_failure_when_payload_contains_invalid_utf8` (spy su `post()`, payload `['x' => "\xB1\x31"]`): `send()` è ritornato **normalmente** e il primo assert ha colto il bug.

Failure summary:

```text
Tests: 16, Assertions: 23, Failures: 1.   (gli altri 15 test verdi)

1) ...WebhookChannelTest::test_send_returns_failure_when_payload_contains_invalid_utf8
   post() must NOT be called when the payload cannot be encoded
   Failed asserting that true is false.
   tests/Unit/Channels/WebhookChannelTest.php:542

→ json_encode() ha ritornato false senza warning; send() ha proseguito e chiamato post()
  (lo spy ha settato $called = true). NESSUN Warning/ErrorException prima del guard.
  RED per il motivo giusto: "post() was called".
```

### Test RED usato (temporaneo, non committato)

```php
public function test_send_returns_failure_when_payload_contains_invalid_utf8()
{
	$this->mock_i18n();

	$settings = [
		'webhook' => [
			'enabled' => true,
			'url'     => 'https://hooks.example.com/alert',
		],
	];

	$called      = false;
	$http_client = $this->create_http_client_mock();
	// Spy: returns normally if called, so send() returns normally either way.
	$http_client->shouldReceive( 'post' )
		->andReturnUsing(
			function () use ( &$called ) {
				$called = true;
				return [
					'success' => true,
					'code'    => 200,
					'body'    => 'ok',
					'error'   => null,
				];
			}
		);

	$channel = new WebhookChannel(
		$this->create_storage_mock( $settings ),
		$http_client
	);

	// Invalid UTF-8 -> json_encode() returns false.
	$payload = [ 'x' => "\xB1\x31" ];
	$result  = $channel->send( $payload );

	$this->assertFalse( $called, 'post() must NOT be called when the payload cannot be encoded' );
	$this->assertFalse( $result['success'], 'send() must return success=false on encode failure' );
	$this->assertNotEmpty( $result['error'], 'send() must return an error message on encode failure' );
}
```

## Recommendation

```text
PROCEED WITH json_encode + guard
```

Fix minimo proposto (da applicare in `WebhookChannel::send()` PRIMA di `hash_hmac()` e `post()`):

```php
$raw_body = json_encode( $payload );

if ( false === $raw_body ) {
	return [
		'success' => false,
		'error'   => __( 'Failed to encode webhook payload.', 'ops-health-dashboard' ),
	];
}
```

## Rationale

Il probe dimostra — sia in CLI con `E_ALL`, sia **end-to-end dentro PHPUnit con `convertWarningsToExceptions` attivo** — che `json_encode()` non emette alcun warning quando fallisce: ritorna `false` e popola `json_last_error()`. Il fatto che il test sia arrivato a chiamare `post()` e sia ritornato normalmente (failure su un assert, non una `ErrorException`) prova che non c'è alcun warning da convertire. Di conseguenza l'Opzione A (`@json_encode`) e l'Opzione B (`set_error_handler` locale) sopprimerebbero un warning che non esiste: complessità inutile, e `@` attirerebbe obiezioni WPCS/PHPStan senza alcun beneficio. Il guard fail-closed con `json_encode()` puro + `if ( false === $raw_body )` è minimale, pulito per WPCS/PHPStan, e dà un red→green netto. NAN/INF si comportano in modo identico, quindi un solo guard copre tutte le cause realistiche. **Vincolo di posizionamento:** il guard deve stare *prima* di `hash_hmac()` e `post()`, così da chiudere davvero il bug (firmare/inviare `false`). Il piano dello Step 4 è quindi corretto così com'è.

## Constraints

* `HttpClient` non toccato.
* Niente `wp_json_encode()`.
* Nessun altro canale toccato.
* Nessun refactor.
* Nessun fix definitivo applicato: test temporaneo rimosso, working tree ripristinato, nessun commit.
* **In attesa di approvazione del maintainer** sulla raccomandazione (`json_encode` + guard) prima di implementare.

Nota minore: PHPUnit segnala `XDEBUG_MODE=coverage non impostato` — irrilevante qui (riguarda solo la coverage), non blocca la validazione.
