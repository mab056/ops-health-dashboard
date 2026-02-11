# AGENTS.md - Ops Health Dashboard

Istruzioni operative per agenti AI che lavorano su questo repository.

## Obiettivo Progetto

Plugin WordPress production-grade per monitoraggio operativo con health checks, dashboard admin, scheduling WP-Cron e alerting.

## Regole Architetturali Non Negoziabili

1. NO singleton.
2. NO metodi/proprietà statiche.
3. NO classi/metodi `final`.
4. Usare dependency injection via costruttore.
5. Usare il container DI con `share()` per istanze condivise (non singleton globali).

## Pattern Richiesti

1. Design interface-first (`*Interface` + implementazioni concrete).
2. Bootstrap tramite funzione (`config/bootstrap.php`), non tramite factory static.
3. Dipendenze WordPress (es. `$wpdb`) iniettate, evitando accesso globale diretto nella logica di business.

## Testing (Obbligatorio)

1. Seguire sempre TDD: RED -> GREEN -> REFACTOR.
2. Unit test in `tests/Unit/` con Brain\Monkey per logica isolata.
3. Integration test in `tests/Integration/` quando si tocca WordPress reale (Options API, cron, hook, admin, DB).
4. Aggiungere pattern-enforcement test per evitare final/static.
5. Prima di chiudere una modifica, eseguire:
   - `composer test`
   - `composer phpcs`
   - `composer analyse`

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
4. Eseguire `composer test`, `composer phpcs` e `composer analyse`.
5. Usare conventional commits (`feat`, `fix`, `docs`, `refactor`, `test`, `chore`).

## Stato Progetto (Riferimento)

Milestone M1-M5 completate (Core Checks + Storage + Cron + Error Log + Redis + Alerting + DiskCheck + VersionsCheck + DashboardWidget + E2E Testing). 537 unit test, 1203 assertions. 296 integration test, 598 assertions. Coverage: 100% classi, metodi, linee (sia unit che integration). 46 E2E scenari x 3 viewport = 138 esecuzioni di test (Playwright + wp-env). PHPCS 100% clean, PHPStan level 6: 0 errori. 30 file sorgente, 53 file di test PHP (30 unit + 23 integration), 5 file di spec E2E. Milestone corrente: M6 (WordPress.org Readiness, pianificata). Vedi `DEVELOPMENT_PLAN.md` e `CHANGELOG.md` per stato aggiornato.

## Baseline Corrente (v0.5.0)

Punti architetturali da preservare nella codebase attuale:
1. Bootstrap plugin in `ops-health-dashboard.php` con autoloader fail-safe (admin notice se `vendor/autoload.php` manca).
2. Orchestrazione principale in `src/Core/Plugin.php` con init idempotente.
3. Container DI custom in `src/Core/Container.php` (`bind`, `share`, `instance`, `make`).
4. Lifecycle activation/deactivation in `src/Core/Activator.php` con setup opzioni e cron hook (`ops_health_run_checks`).
5. Scheduling check periodici tramite `src/Services/Scheduler.php` (WP-Cron ogni 15 minuti) con self-healing throttled via transient `ops_health_cron_check` (valido su admin e frontend).
6. Check registrati in `config/bootstrap.php`: `DatabaseCheck`, `ErrorLogCheck`, `RedisCheck`, `DiskCheck`, `VersionsCheck`.
7. Flusso azioni admin in `src/Admin/HealthScreen.php`: `process_actions()` con nonce + capability check e redirect PRG; uscita isolata in `do_exit()` per testabilità.
8. Redazione dati sensibili centralizzata in `src/Services/Redaction.php`, iniettata in `CheckRunner`, `DatabaseCheck`, `ErrorLogCheck` e `RedisCheck`.
9. `RedisCheck` usa chiave smoke test univoca per run (`ops_health_smoke_test_<uniqid>`) per evitare race condition tra esecuzioni concorrenti.
10. Contratto `CheckRunnerInterface` con `clear_results()` usato dal flusso admin (`Run Now` / `Clear Cache`) in `HealthScreen::process_actions()`.
11. **Alerting**: `AlertManager` rileva cambiamenti stato check, dispatcha a canali abilitati (Email, Webhook, Slack, Telegram, WhatsApp), cooldown per-check via transient, alert log limitato a 50 voci. Integrato in `Scheduler::run_checks()`.
12. **Anti-SSRF**: `HttpClient` blocca IP privati (RFC 1918, loopback, link-local), valida la risoluzione DNS, DNS pinning via `CURLOPT_RESOLVE` (anti-TOCTOU/DNS rebinding), restringe schema (http/https) e porte (80/443), no redirect, timeout 5s, rifiuto IPv6 (safe-fail), validazione HTTP 2xx.
13. **Alert Settings**: pagina admin `Ops → Alert Settings` con PRG pattern, nonce `ops_health_alert_settings`, per-channel enable/disable + credentials (`type="password"` + `value=""` + `placeholder="********"` per token/secret — credenziali mai nel DOM), cooldown globale.
14. **Channel security**: TelegramChannel escape HTML (`htmlspecialchars`), SlackChannel escape mrkdwn, EmailChannel validazione `is_email()`, WhatsAppChannel validazione E.164 phone.
15. **AlertManager resilience**: cooldown transient impostato PRIMA del dispatch (anti-spam), costanti `STATUS_OK/WARNING/CRITICAL/UNKNOWN`, isolamento per-canale `try/catch \Throwable` in `dispatch_to_channels()`, Scheduler `catch (\Throwable)` attorno a `process()` (cron resiliente a qualsiasi errore).
16. **DashboardWidget**: `src/Admin/DashboardWidget.php` registrato in `Plugin::init()`, hook `wp_dashboard_setup`, capability check, worst-status logic.
17. **DiskCheck**: `src/Checks/DiskCheck.php` con soglie `WARNING_THRESHOLD = 20`, `CRITICAL_THRESHOLD = 10` (percent), protected wrappers, RedactionInterface.
18. **VersionsCheck**: `src/Checks/VersionsCheck.php` con `RECOMMENDED_PHP_VERSION = '8.1'`, core update → critical, plugin/theme → warning.
19. Tooling quality gate locale: `composer test`, `composer phpcs`, `composer analyse` (PHPStan livello 6 con `phpstan.neon`).
20. CI separata in `.github/workflows/ci.yml`: job dedicati PHPCS, PHPStan, PHPUnit matrix (PHP 7.4-8.5, coverage su 8.3), E2E Playwright (Chromium, desktop-only in CI, wp-env, `timeout-minutes: 15`). Codecov con flag separati `unit`/`integration` (`codecov.yml`, `CODECOV_TOKEN` secret).
21. **E2E Testing**: Playwright + `@wordpress/env` in Docker. 46 scenari x 3 viewport localmente (desktop/tablet/mobile), desktop-only in CI (46 test, ~8 min). CI: 1 worker, timeout 60s, login timeout 30s, health check wait, `line` + `github` reporter. Selettori centralizzati in `tests/e2e/helpers/selectors.ts`. Login helpers per admin/subscriber/editor. `bin/e2e-setup.sh` crea utenti test.
22. **Test Matrix Locale**: `bin/test-matrix.sh` replica CI localmente: PHPCS + PHPStan + PHPUnit (PHP 7.4-8.5) + E2E Playwright. Flag `--e2e-only`, `--no-e2e`, `--tests-only`, `--phpcs-only`, `--parallel`. E2E integrato con lifecycle management (wp-env start/stop, utenti test, prerequisiti npm/docker). `composer.json` `process-timeout: 0` per esecuzioni lunghe.

Nota operativa:
1. Evitare sezioni datate o checklist "one-shot" in questo file.
2. Aggiornare questa sezione solo quando cambia la baseline tecnica (versione, bootstrap, scheduler, contract principali).

## Checklist Revisione Modifiche

Prima di chiudere PR/commit, verificare sempre:
1. `git diff --name-only` e `git diff` sui file toccati.
2. Copertura test aggiornata per ogni comportamento nuovo/modificato.
3. Assenza regressioni su cron/scheduler (niente duplicati, self-healing attivo).
4. Compatibilità degli script di tooling in esecuzione sequenziale e parallela.
5. Esecuzione:
   - `composer test:unit`
   - `composer test:integration` (in ambiente con DB disponibile)
   - `composer phpcs`
   - `composer analyse`
6. Se l'ambiente locale/sandbox non consente i test di integrazione, esplicitarlo nel report finale con errore concreto (es. DB non raggiungibile), senza marcarli come "passati".

## Documenti di Riferimento

1. `README.md`
2. `CONTRIBUTING.md`
3. `DEVELOPMENT_PLAN.md`
4. `CHANGELOG.md`
5. `CLAUDE.md`
6. `SECURITY.md`
