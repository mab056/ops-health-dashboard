# Change delta — webhook-json-encode-guard

> Artefatto storico archiviato (linguaggio di cambiamento). Il delta documenta la transizione
> dallo stato precedente a quello corrente; **non è fonte attiva**.
> La fonte attiva del comportamento è [specs/channels/webhook.md](../../../specs/channels/webhook.md).

## Obiettivo del change

Rendere `WebhookChannel::send()` fail-closed quando il payload non è serializzabile in JSON,
in modo che un payload malformato non venga firmato, inviato e riportato come consegnato.

## Delta di comportamento

| Aspetto | Prima | Dopo |
| --- | --- | --- |
| `json_encode()` ritorna `false` | firma su stringa vuota, POST del corpo spurio `"false"`, esito `success=true` su 2xx | nessuna firma, nessuna POST, esito `success=false` con `error` non vuoto |
| Payload valido (happy path) | invariato | invariato |
| Firma HMAC per payload valido | invariata | invariata |

## Delta di codice (produzione)

`src/Channels/WebhookChannel.php` — guard aggiunto subito dopo `json_encode()` e **prima** di
`hash_hmac()` e `http_client->post()`:

    $raw_body = json_encode( $payload );

    if ( false === $raw_body ) {
        return [
            'success' => false,
            'error'   => __( 'Failed to encode webhook payload.', 'ops-health-dashboard' ),
        ];
    }

## Delta di test

`tests/Unit/Channels/WebhookChannelTest.php`:

- helper `create_http_client_spy( &$called )` — mock `post()` con `andReturnUsing` che registra l'invocazione e ritorna successo (così `send()` ritorna sempre normalmente);
- `test_send_returns_failure_when_payload_contains_invalid_utf8` — payload `[ 'x' => "\xB1\x31" ]`;
- `test_send_returns_failure_when_payload_contains_non_finite_float` — payload `[ 'value' => NAN ]`.

## Decisione di prodotto

Fail-closed (non normalizzazione/coercizione). Registrata come `DR-WEBHOOK-001` (active) nella
spec canonica.

## Confini espliciti (non toccati)

`HttpClient`, altri canali, `AlertManager`, storage/settings/admin, anti-SSRF/redaction,
metadati composer/package. Nessun refactor. Nessun uso di `wp_json_encode()`, `@json_encode()`,
`set_error_handler()`.
