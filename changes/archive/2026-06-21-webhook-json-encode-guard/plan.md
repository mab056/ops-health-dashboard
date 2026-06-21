# Plan — webhook-json-encode-guard

> Artefatto storico archiviato. Piano approvato dal maintainer prima dell'implementazione.

## Approccio

TDD red→green, scope minimo, fail-closed. Decisione validata dalla Runtime RED Probe
(verità di terra: probe reale + RED reale), non da consenso tra reviewer.

## Sequenza approvata

1. **RED test (UTF-8 invalido)** — aggiungere `test_send_returns_failure_when_payload_contains_invalid_utf8` con spy su `post()` e flag `$called`.
2. **RED test (NAN/INF)** — aggiungere `test_send_returns_failure_when_payload_contains_non_finite_float`.
3. **Verifica RED** — `composer test:unit -- --filter WebhookChannelTest`; i nuovi test devono fallire perché `post()` viene chiamato (non per Warning/ErrorException).
4. **Fix guard** — in `WebhookChannel::send()`, dopo `json_encode()` e prima di `hash_hmac()`/`post()`, ritornare fail-closed se `false === $raw_body`.
5. **Verifica GREEN** — `composer test:unit -- --filter WebhookChannelTest`, `composer phpcs`, `composer analyse`.
6. **Closeout + bozza spec** — preparare evidenze, `specs/channels/webhook.md` in linguaggio di stato; nessun archive finché non approvato.

## Vincoli decisi

- Fix limitato a `WebhookChannel::send()`.
- Niente `wp_json_encode()`, `@json_encode()`, `set_error_handler()`.
- Messaggio d'errore utente generico e localizzato (no `json_last_error_msg()` esposto).
- Nessun commit/tag durante il pilota.

## Rischio mitigato

Un payload malformato non deve essere inviato e riportato come consegnato.
