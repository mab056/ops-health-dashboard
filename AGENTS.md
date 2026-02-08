# AGENTS.md - Ops Health Dashboard

Istruzioni operative per agenti AI che lavorano su questo repository.

## Obiettivo Progetto

Plugin WordPress production-grade per monitoraggio operativo con health checks, dashboard admin, scheduling WP-Cron e alerting.

## Regole Architetturali Non Negoziabili

1. NO singleton.
2. NO metodi/proprieta' static.
3. NO classi/metodi final.
4. Usare dependency injection via costruttore.
5. Usare il container DI con `share()` per istanze condivise (non singleton globali).

## Pattern Richiesti

1. Design interface-first (`*Interface` + implementazioni concrete).
2. Bootstrap tramite funzione (`config/bootstrap.php`), non factory static.
3. Dipendenze WordPress (es. `$wpdb`) iniettate, evitando accesso globale diretto nella business logic.

## Testing (Obbligatorio)

1. Seguire sempre TDD: RED -> GREEN -> REFACTOR.
2. Unit test in `tests/Unit/` con Brain\Monkey per logica isolata.
3. Integration test in `tests/Integration/` quando si tocca WordPress reale (Options API, cron, hook, admin, DB).
4. Aggiungere pattern-enforcement test per evitare final/static.
5. Prima di chiudere una modifica, eseguire:
   - `composer test`
   - `composer phpcs`

## Sicurezza (Obbligatoria)

1. Sanitizzare input (`sanitize_text_field`, `sanitize_email`, `esc_url_raw`, `absint`, ecc.).
2. Effettuare l'escaping dell'output (`esc_html`, `esc_attr`, `esc_url`, `esc_js`).
3. Verificare capability su pagine admin (`current_user_can('manage_options')`).
4. Usare e verificare nonce su form/AJAX.
5. Per webhook/HTTP esterni applicare anti-SSRF con validazione URL.

## Coding Standards

1. Rispettare WordPress Coding Standards (WPCS).
2. Indentazione a tab.
3. Allman style per parentesi.
4. PHPDoc completo su classi/metodi pubblici.
5. Naming:
   - Classi: PascalCase
   - Metodi: snake_case
   - Costanti: UPPER_SNAKE_CASE
   - Chiavi globali/opzioni: prefisso `ops_health_`

## Workflow Consigliato

1. Scrivere/aggiornare test.
2. Implementare il minimo codice necessario.
3. Rifinire (refactor) mantenendo i test verdi.
4. Eseguire `composer test` e `composer phpcs`.
5. Usare conventional commits (`feat`, `fix`, `docs`, `refactor`, `test`, `chore`).

## Stato Progetto (Riferimento)

Milestone M1 completata (Core Checks + Storage + Cron). M2 in corso (Error Log Summary Safe). Vedi `DEVELOPMENT_PLAN.md` e `CHANGELOG.md` per stato aggiornato.

## Verifica Modifiche Recenti (2026-02-08)

Modifiche controllate in working tree:
1. `src/Services/Scheduler.php`: aggiunto self-healing in `register_hooks()` con chiamata a `schedule()`.
2. `tests/Unit/Services/SchedulerTest.php`: aggiornati test unit per coprire self-healing (cron presente/mancante).
3. `tests/Integration/Services/SchedulerTest.php`: aggiunto test integrazione per ri-schedulazione automatica.
4. `bin/test-matrix.sh`: corretto supporto `--parallel` con raccolta risultati da subshell via file temporanei.
5. `README.md`: aggiornata sezione autori/riferimenti assistenti AI.

Esito verifica locale:
1. `composer test:unit` OK (104 test, 212 assertion).
2. `composer phpcs` OK.
3. `composer test:integration` non eseguibile in sandbox senza accesso DB MySQL (`mysqli_real_connect ... Operation not permitted`).

## Checklist Revisione Modifiche

Prima di chiudere PR/commit, verificare sempre:
1. `git diff --name-only` e `git diff` sui file toccati.
2. Copertura test aggiornata per ogni comportamento nuovo/modificato.
3. Assenza regressioni su cron/scheduler (niente duplicati, self-healing attivo).
4. Compatibilita' script di tooling in esecuzione sequenziale e parallela.
5. Esecuzione:
   - `composer test:unit`
   - `composer test:integration` (in ambiente con DB disponibile)
   - `composer phpcs`

## Documenti di Riferimento

1. `README.md`
2. `CONTRIBUTING.md`
3. `DEVELOPMENT_PLAN.md`
4. `CHANGELOG.md`
5. `CLAUDE.md`
