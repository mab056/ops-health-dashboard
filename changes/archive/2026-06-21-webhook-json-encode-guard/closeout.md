# Closeout — webhook-json-encode-guard

> Artefatto storico archiviato. Evidenze red/green e stato di chiusura del change.

## RED evidence

Comando (PRIMA del fix): `vendor/bin/phpunit --testsuite=unit --filter WebhookChannelTest`

    Tests: 17, Failures: 2.   (gli altri 15 verdi)

    1) test_send_returns_failure_when_payload_contains_invalid_utf8
       "post() must NOT be called when the payload cannot be encoded"
       Failed asserting that true is false.   (WebhookChannelTest.php:556)
    2) test_send_returns_failure_when_payload_contains_non_finite_float
       "post() must NOT be called when the payload cannot be encoded"
       Failed asserting that true is false.   (WebhookChannelTest.php:591)

Motivo del fallimento: **corretto**. `json_encode()` ritorna `false` silenziosamente (nessun
Warning/ErrorException), `send()` prosegue e chiama `post()` → lo spy imposta `$called=true`.
RED per "post() was called", coerente con la Runtime RED Probe.

## GREEN evidence

- `composer test:unit -- --filter WebhookChannelTest` → OK (17 tests, 28 assertions)
- `composer phpcs` → 35/35, 0 errori
- `composer analyse` (PHPStan level 6) → [OK] No errors
- Suite unit completa → OK (639 tests, 1508 assertions); 1 risky **pre-esistente**
  (`CheckRunnerTest::test_clear_results_deletes_from_storage`, file non toccato — vedi
  `pilot-retrospective.md › Follow-up debt`).

## Changed files

- `src/Channels/WebhookChannel.php` (+9) — guard fail-closed in `send()`.
- `tests/Unit/Channels/WebhookChannelTest.php` (+98) — 2 test + helper `create_http_client_spy()`.
- `specs/channels/webhook.md` (new) — spec canonica (fonte attiva).
- `changes/archive/2026-06-21-webhook-json-encode-guard/` (new) — artefatti storici.

## Scope deviations

none. Non modificati: `HttpClient`, altri canali, `AlertManager`, storage/settings/admin,
anti-SSRF/redaction, metadati composer/package. Assenti nel diff: `wp_json_encode()`,
`@json_encode()`, `set_error_handler()`.

## Excluded / unrelated

- `package-lock.json` — bump `0.5.0 → 0.6.2` da `npm install`, estraneo al change. **Escluso**
  dal commit (lasciato `M` nel working tree per decisione del maintainer).

## Versioning decision (maintainer)

Da versionare nel commit del change (branch dedicato):

- `src/Channels/WebhookChannel.php`, `tests/Unit/Channels/WebhookChannelTest.php` (codice + test);
- `specs/channels/webhook.md` (spec canonica);
- `changes/archive/2026-06-21-webhook-json-encode-guard/**` (artefatti d'archivio);
- `CODE_REVIEW_v0.6.2.md`, `PILOT_CANDIDATE_REPORT.md` (report del programma pilota).

Escluso dal commit: `package-lock.json` (lasciato com'è).

## Closeout readiness

READY → ARCHIVED. Commit in attesa di via libera esplicito del maintainer (no commit/tag finora).
