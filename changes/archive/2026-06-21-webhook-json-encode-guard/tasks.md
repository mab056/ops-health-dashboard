# Tasks — webhook-json-encode-guard

> Artefatto storico archiviato. Stato finale dei task del change.

| # | Task | Stato |
| --- | --- | --- |
| 1 | RED test UTF-8 invalido (`test_send_returns_failure_when_payload_contains_invalid_utf8`) | ✅ done |
| 2 | RED test NAN/INF (`test_send_returns_failure_when_payload_contains_non_finite_float`) | ✅ done |
| 3 | Verifica RED (fallimento per "post() was called", senza Warning/ErrorException) | ✅ done |
| 4 | Guard fail-closed in `WebhookChannel::send()` | ✅ done |
| 5 | Verifica GREEN (test:unit + phpcs + analyse) | ✅ done |
| 6 | Closeout + bozza spec (no archive) | ✅ done |
| C1 | Spec render-safe (0 fence annidati) | ✅ done |
| C2 | Decision record `DR-WEBHOOK-001` (active) senza fence | ✅ done |
| C3 | Sezione Trasporto in linguaggio di delega | ✅ done |
| C4 | Pilot retrospective con `Key lesson` | ✅ done |
| C5 | Archive definitivo | ✅ done |
| C6 | Igiene working tree | ✅ done |
| C7 | Debito test pre-esistente registrato | ✅ done |

## Done definition — verificata

- ✅ I due nuovi test falliscono prima del fix per il motivo giusto (`post()` chiamato).
- ✅ I due nuovi test passano dopo il fix.
- ✅ I test esistenti `WebhookChannelTest` restano verdi (17/17 totali).
- ✅ `composer phpcs` passa (35/35, 0 errori).
- ✅ `composer analyse` passa (No errors).
- ✅ Nessun file forbidden modificato.
- ✅ Assenti `wp_json_encode()`, `@json_encode()`, `set_error_handler()`.
- ✅ Spec canonica in linguaggio di stato.
