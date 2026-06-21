# Analyze — webhook-json-encode-guard

> Artefatto storico archiviato. L'`analyze` verifica la **coerenza documentale** del change:
> che codice, test, spec e regole di progetto non si contraddicano. Non è validazione del
> comportamento reale — quella proviene dal RED eseguito (vedi `runtime-red-probe.md`).

## Coerenza con le regole di progetto (CLAUDE.md)

- **No singleton / static / final:** il change non introduce metodi/proprietà static né classi/metodi final. `WebhookChannel` resta non-final, `send()` resta metodo d'istanza. ✅
- **Sicurezza — escape/sanitize:** il guard non introduce input/output utente nuovo; il messaggio d'errore è una stringa fissa localizzata via `__()`. ✅
- **Anti-SSRF:** l'outbound HTTP resta interamente su `HttpClientInterface::post()`; il guard anzi *previene* una chiamata su corpo malformato. Nessuna chiamata HTTP diretta introdotta. ✅
- **WPCS:** indentazione tab, Allman, PHPDoc invariata; `composer phpcs` 0 errori. ✅
- **TDD:** test scritti prima (RED osservato), poi fix (GREEN). ✅

## Coerenza tra artefatti

- `current-state.md` (bug) ↔ `change-delta.md` (delta) ↔ `specs/channels/webhook.md` (stato): la sezione "Codifica del payload → Fail-closed" della spec descrive esattamente l'esito del guard implementato.
- `DR-WEBHOOK-001` (active) registra la scelta fail-closed e indica cosa aggiornare se in futuro si scegliesse la normalizzazione: spec, sezione Codifica, Contratto di ritorno, test. Coerente con il codice.
- Il "Contratto di ritorno" della spec (`success`/`error`) corrisponde alla shape ritornata da `send()` nel ramo guard e nei rami esistenti.
- La sezione "Verifica richiesta" della spec elenca rischi coperti dai test presenti (happy path, firma, fail-closed non inviato, errore trasporto propagato).

## Punti di attenzione documentale

- `package-lock.json` riporta un bump `version 0.5.0 → 0.6.2` causato da `npm install` (sincronizzazione da `package.json`), **estraneo** a questo change. Va escluso. È un sintomo del finding di review sul disallineamento di versione, da trattare separatamente.
- La spec dichiara la **delega** al client HTTP per anti-SSRF/timeout/limiti, senza certificarne il livello: coerente con il principio "la spec del canale documenta la delega, non certifica un altro layer".

## Limite dell'analyze

Questa analisi è documentale. La prova che il comportamento reale sia quello descritto viene
**solo** dal RED eseguito e dal GREEN eseguito sul codice reale, non dalla coerenza tra documenti
né dal consenso tra reviewer.
