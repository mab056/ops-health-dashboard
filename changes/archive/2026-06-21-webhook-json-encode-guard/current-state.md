# Current state (pre-change) — webhook-json-encode-guard

> Artefatto storico archiviato. Cattura lo stato del canale Webhook **prima** del change.
> La fonte attiva del comportamento corrente è [specs/channels/webhook.md](../../../specs/channels/webhook.md).

## Componente

`OpsHealthDashboard\Channels\WebhookChannel` — canale di alert che invia una HTTP POST JSON
con firma HMAC opzionale, tramite `HttpClientInterface::post()`.

## Comportamento osservato prima del change

In `WebhookChannel::send()` il payload veniva serializzato con `json_encode( $payload )`
senza verificare il valore di ritorno. In caso di fallimento della serializzazione:

- `json_encode()` ritorna `false` (silenziosamente, senza emettere PHP Warning);
- `hash_hmac( 'sha256', false, $secret )` calcolava la firma sulla stringa vuota;
- `HttpClient::post( $url, false, ... )` riceveva `false` e lo ri-serializzava nella stringa
  letterale `"false"`, inviata come corpo;
- su risposta HTTP 2xx, `send()` restituiva `success = true`.

Effetto netto: un payload non rappresentabile in JSON veniva trasmesso come corpo spurio,
con una firma che non corrispondeva al corpo, e l'esito veniva riportato come consegnato.
La perdita dell'alert era completamente silenziosa.

## Causa scatenante realistica

Il payload include `message` e `details` provenienti dall'output dei check. `ErrorLogCheck`
legge righe di log via `fread()`; byte UTF-8 non validi (path latin-1, frammenti binari di
stack trace) sopravvivono alla redazione (solo regex, nessuna normalizzazione UTF-8) e
raggiungono il payload. `json_encode()` ritorna `false` (JSON_ERROR_UTF8) su UTF-8 non valido,
e analogamente su valori float non finiti (`NAN`/`INF`).

## Origine

Code review v0.6.2 — finding correctness (medium) su `WebhookChannel.php:104-112`.
Selezione come pilota in [PILOT_CANDIDATE_REPORT.md](../../../PILOT_CANDIDATE_REPORT.md) (Candidate 1).

## Vincoli rilevanti dello stato corrente

- PHP 7.4+ (osservato in CI/dev: 8.3.31).
- `phpunit.xml.dist`: `convertWarningsToExceptions="true"` — ma `json_encode()` non emette
  warning, quindi non viene convertito in eccezione (verificato con probe).
- Tutto l'outbound HTTP deve passare per `HttpClientInterface` (regola anti-SSRF di progetto).
