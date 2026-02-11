# Changelog

Tutte le modifiche rilevanti a questo progetto saranno documentate in questo file.

Il formato è basato su [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
e questo progetto aderisce al [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## 0.5.0 - 2026-02-11

### Added
- **M5 — New Checks + Dashboard Widget + E2E Testing**
- **DiskCheck** - Disk space monitoring with configurable thresholds
  - Constants: `WARNING_THRESHOLD = 20`, `CRITICAL_THRESHOLD = 10` (percent free)
  - Protected wrappers (`get_disk_path()`, `get_free_space()`, `get_total_space()`) for testability
  - `is_enabled()` returns false when `disk_free_space`/`disk_total_space` functions are disabled
  - Path redacted via `RedactionInterface`, bytes formatted via `size_format()`
  - Edge cases: functions return false → warning, total=0 → division-by-zero guard
- **VersionsCheck** - WordPress/PHP version monitoring with update notifications
  - Constant: `RECOMMENDED_PHP_VERSION = '8.1'`
  - Status logic: core update → critical, plugin/theme updates → warning, old PHP → warning
  - `filter_real_updates()` keeps only `response === 'upgrade'` (filters 'latest', 'development')
  - `load_update_functions()` with try/catch \Throwable for graceful fallback
  - Protected wrappers for all version/update functions for testability
- **DashboardWidget** - wp-admin Dashboard widget showing global health status
  - `determine_overall_status()`: worst-status wins (critical > warning > ok), empty = unknown
  - Per-check status list with CSS classes `ops-health-widget-status-{status}`
  - Link to full dashboard page
  - Capability check `manage_options` on both `add_widget()` and `render()`
  - Output con escaping: `esc_html()`, `esc_attr()`, `esc_url()`
- **E2E Testing** - Playwright + wp-env (Docker-based WordPress environment)
  - 46 scenari di test x 3 viewport (desktop 1280px, tablet 768px, mobile 375px) = 138 esecuzioni di test
  - 5 file di spec: navigation, health-dashboard, alert-settings, dashboard-widget, security
  - Centralized selectors (`tests/e2e/helpers/selectors.ts`) for maintainability
  - Login helpers for admin, subscriber, editor roles (`tests/e2e/helpers/login.ts`)
  - `bin/e2e-setup.sh` creates test users via wp-env WP-CLI
  - Job CI: Node 20, Chromium, wp-env, artifact upload on failure
- **package.json** - `@playwright/test`, `@wordpress/env`, npm scripts for env:start/stop, test:e2e
- **playwright.config.ts** - 3 Chromium projects, retries 2 in CI, 2 workers in CI, timeout 60s in CI
- **.wp-env.json** - WP 6.7, PHP 8.3, plugin mapping, WP_DEBUG enabled
- **tsconfig.json** - Minimal TypeScript config for Playwright

### Changed
- **config/bootstrap.php** - Registers DiskCheck + VersionsCheck in CheckRunner, adds DashboardWidget share block
- **Plugin.php** - Calls `DashboardWidget::register_hooks()` in `init()`
- **bin/test-matrix.sh** - E2E integration with full lifecycle management (wp-env start/stop, test user creation, prerequisite checks npm/docker)
  - New flags: `--e2e-only`, `--no-e2e`; dot reporter for real-time progress; ANSI stripping for result parsing
  - SKIP (yellow) status for E2E when prerequisites missing
- **composer.json** - Added `"process-timeout": 0` to prevent timeout on long-running matrix+E2E runs
- **playwright.config.ts** - CI optimizations: 1 worker (shared wp-env), `timeout: 60_000` in CI (vs 30s locally)
- **login.ts** - Login timeout increased from 15s to 30s for all three helpers (Docker in CI is slower)
- **.github/workflows/ci.yml** - Added `e2e` job (Node 20, Chromium, wp-env, Playwright) with `timeout-minutes: 25`, health check wait
- **.gitignore** - Added `/playwright-report/`, `/test-results/`, `/tests/e2e/.auth/`
- **.gitattributes** - Added export-ignore for `package.json`, `package-lock.json`, `playwright.config.ts`, `.wp-env.json`, `tsconfig.json`

### Fixed
- **DashboardWidgetTest integration** - `require_once ABSPATH . 'wp-admin/includes/dashboard.php'` before `add_widget()` (function not loaded by default in WP test suite)
- **DashboardWidgetTest integration** - `$storage->save()` → `$storage->set()` (correct Storage method name)
- **DashboardWidgetTest integration** - Storage key `'check_results'` → `'latest_results'` (matches CheckRunner key)
- **DashboardWidgetTest integration** - XSS assertion: `assertStringNotContainsString('<img')` + `assertStringContainsString('&lt;img')` (esc_html preserves attribute text like "onerror" as harmless)

### Tests
- +27 unit tests DiskCheck (pattern enforcement, interface, thresholds, edge cases, redaction)
- +32 unit tests VersionsCheck (pattern enforcement, interface, status logic, details, edge cases)
- +18 unit tests DashboardWidget (pattern enforcement, render, capability, overall status)
- +12 integration tests DiskCheck (real filesystem, testable subclasses for error scenarios)
- +11 integration tests VersionsCheck (real WP/PHP versions, testable subclasses for update scenarios)
- +10 integration tests DashboardWidget (admin/subscriber capability, render with/without results)
- Updated PluginTest (unit + integration) for DashboardWidget expectations
- 46 E2E scenarios: navigation (6), health-dashboard (14), alert-settings (14), dashboard-widget (6), security (6)

### Development Notes
- 515 unit tests (Brain\Monkey), 1162 assertions
- 292 integration tests (WP Test Suite)
- 54 PHP test files (30 unit + 24 integration)
- 30 source files in `src/` (+3 new: DiskCheck, VersionsCheck, DashboardWidget)
- 46 scenari E2E x 3 viewport = 138 esecuzioni di test, tutti verdi
- Local test matrix (`bin/test-matrix.sh`) now includes E2E with full lifecycle management
- CI E2E: 1 worker (shared wp-env), 60s timeout, 30s login timeout, 25-minute job timeout, health check wait
- PHPCS 100% clean, PHPStan level 6: 0 errori
- TDD rigoroso per ogni componente: RED → GREEN → REFACTOR

## 0.4.1 - 2026-02-10

### Changed
- **HttpClient** - DNS pinning via `CURLOPT_RESOLVE` + `http_api_curl` action (previene TOCTOU/DNS rebinding tra validazione e richiesta HTTP)
  - Estratto `validate_and_resolve()` private: valida URL + risolve DNS → ritorna `[host, ip, port]`
  - Estratto `create_dns_pin()` protected: closure per `http_api_curl` action
  - `post()` usa DNS pinning automatico su ogni richiesta
- **Scheduler** - `catch (\Exception)` → `catch (\Throwable)` in `run_checks()` (cattura TypeError, ValueError, non solo Exception)
- **AlertManager** - Try/catch `\Throwable` per-canale in `dispatch_to_channels()` (isolamento: un canale che fallisce non blocca gli altri)
- **AlertSettings** - Password fields rendono `value=""` + `placeholder="********"` (credenziali mai nel DOM)
  - `build_settings_from_post()` preserva secret esistenti quando il campo POST è vuoto
- **EmailChannel** - Guard `empty($recipients)` dopo `parse_recipients()` in `send()` (previene `wp_mail()` senza destinatari)

### Security
- **DNS pinning** - `CURLOPT_RESOLVE` via `http_api_curl` action previene attacchi TOCTOU/DNS rebinding tra validazione e richiesta HTTP effettiva
- **Channel isolation** - Try/catch `\Throwable` per-canale in AlertManager (un canale che fallisce non blocca gli altri)
- **Scheduler resilience** - `catch (\Throwable)` attorno ad AlertManager (il cron sopravvive a qualsiasi tipo di errore)
- **Secret non-prefill** - Password inputs rendono `value=""` + `placeholder="********"` (credenziali mai presenti nel sorgente DOM)

### Tests
- +1 unit test AlertSettings (corrupted existing settings → `!is_array` branch)
- +2 integration test AlertSettings (preserve existing secrets + corrupted existing settings)
- +1 integration test EmailChannel (send con tutti i recipients invalidi → guard `empty($recipients)`)
- +1 integration test AlertingFlowTest (ThrowingChannel → `catch \Throwable` in `dispatch_to_channels()` + isolamento per-canale)
- ThrowingChannel: implementa `AlertChannelInterface`, lancia `\RuntimeException` in `send()` — testa isolamento per-canale

### Development Notes
- Code review 2 post-M4: 5 rilievi risolti (1 High, 2 Medium, 2 Low) — TDD rigoroso RED → GREEN → REFACTOR
- Coverage push: da 99.92%/98.98% a **100%/100%** (unit e integration indipendentemente)
- 698 test totali (438 unit + 260 integration), 1548 assertions
- Unit coverage: **100%** lines (1281/1281), **100%** methods (136/136), 20/20 classes
- Integration coverage: **100%** lines (1280/1280), **100%** methods (136/136), 20/20 classes
- PHPCS 100% clean (0 errori, 0 warning)
- PHPStan level 6: 0 errori

## 0.4.0 - 2026-02-10

### Added
- **M4 — Alerting System** - Multi-channel alert notifications on check status changes
- **HttpClientInterface + HttpClient** - Anti-SSRF HTTP client for outbound requests
  - Scheme validation (http/https only)
  - Private IP blocking (RFC 1918, loopback, link-local, 0.0.0.0)
  - IPv6 rejection (safe-fail, `gethostbyname()` returns only IPv4)
  - Port restriction (80/443 only)
  - DNS resolution validation via `gethostbyname()` (DNS rebinding prevention)
  - HTTP status validation (only 2xx = success)
  - No redirect following, 5s timeout
  - `is_safe_url()` + `post()` API with RedactionInterface integration
- **AlertChannelInterface** - Contract for notification channels: `get_id()`, `get_name()`, `is_enabled()`, `send()`
- **AlertManagerInterface + AlertManager** - State change detection and channel dispatch
  - Alert on status transitions: ok→warning, ok→critical, warning→critical, critical→warning, *→ok (recovery)
  - Per-check cooldown via WordPress transients (default 60 min)
  - Cooldown set BEFORE dispatch (prevents alert spam on channel failures)
  - Recovery alerts bypass cooldown
  - First run: alert only if status ≠ ok
  - Alert log capped at 50 entries via Storage
  - Constants: `STATUS_OK`, `STATUS_WARNING`, `STATUS_CRITICAL`, `STATUS_UNKNOWN`, `DEFAULT_COOLDOWN = 3600`, `MAX_LOG_ENTRIES = 50`
- **EmailChannel** - Email alerts via `wp_mail()` with configurable recipients, `is_email()` validation
- **WebhookChannel** - Generic JSON POST with optional HMAC signature (`X-OpsHealth-Signature` via `hash_hmac('sha256', ...)`)
- **SlackChannel** - Slack Block Kit payload with color-coded attachments (red/orange/green by status), mrkdwn escape
- **TelegramChannel** - Telegram Bot API `sendMessage` with HTML parse mode, `htmlspecialchars()` on interpolated variables
- **WhatsAppChannel** - Generic webhook with phone number field + optional Bearer auth header, E.164 phone validation
- **AlertSettings admin page** - Alert channel configuration UI under `Ops → Alert Settings`
  - PRG pattern with nonce `ops_health_alert_settings` and capability check `manage_options`
  - Per-channel enable/disable + credentials (email recipients, webhook URL/secret, Slack webhook, Telegram bot token/chat ID, WhatsApp webhook/phone/token)
  - `type="password"` + `autocomplete="off"` for token/secret inputs
  - Global cooldown minutes setting
  - `protected do_exit()` for testability
- **Menu submenu** - Alert Settings as submenu under Ops Health Dashboard
- **Scheduler AlertManager integration** - Optional `AlertManagerInterface` injection (backward compatible)
  - Reads previous results before `run_all()`, then calls `alert_manager->process()`
  - `try/catch` around `alert_manager->process()` (cron resilience)
- **AlertingFlowTest** - End-to-end integration tests: state change → dispatch → cooldown → recovery

### Changed
- **Scheduler** - Accepts optional `AlertManagerInterface $alert_manager` parameter; triggers alert processing after check runs
- **Menu** - Accepts optional `AlertSettings $alert_settings` parameter; registers submenu when present
- **config/bootstrap.php** - Wires all M4 bindings: HttpClient, AlertManager with 5 channels, AlertSettings, updated Scheduler + Menu

### Security
- **Anti-SSRF implemented** - HttpClient blocks private IPs, validates DNS, restricts schemes/ports, rejects IPv6, validates HTTP 2xx
- **Channel security** - TelegramChannel HTML escape, SlackChannel mrkdwn escape, EmailChannel `is_email()` validation, WhatsAppChannel E.164 phone validation
- **Cooldown pre-dispatch** - Transient set BEFORE channel dispatch (prevents alert spam on failure)
- **Capability checks** on AlertSettings admin page (`manage_options`)
- **Nonce protection** on AlertSettings form (`ops_health_alert_settings`)
- **Input sanitization** - `sanitize_text_field()`, `sanitize_email()`, `esc_url_raw()`, `absint()` on all alert settings
- **Output escaping** - `esc_html()`, `esc_attr()`, `esc_url()` on all rendered settings
- **Input masking** - `type="password"` + `autocomplete="off"` for token/secret fields

### Development Notes
- Code review post-M4: 13 rilievi risolti (4 Critical, 3 High, 3 Medium, 3 Low)
- Integration test coverage push: da 63.87% a 100% (1240/1240 lines, 134/134 methods, 20/20 classes)
  - 7 nuovi file integration test: HttpClientTest, AlertSettingsTest, SlackChannelTest, TelegramChannelTest, WebhookChannelTest, WhatsAppChannelTest, EmailChannelTest
  - 3 file integration test potenziati: MenuTest (+4 test), SchedulerTest (+1 test), AlertingFlowTest (+6 test)
  - TestableHttpClient subclass con `resolve_host()` override per test anti-SSRF senza DNS
  - TestableAlertSettings subclass con `do_exit()` override per test PRG senza `exit()`
  - ThrowingAlertManager per test resilienza Scheduler `try/catch`
  - Test con HttpClient reale su IP diretti (127.0.0.1, 172.16.0.1, 192.168.1.1) per copertura Xdebug completa
- 685 test totali (429 unit + 256 integration), 1497 assertions
- Unit coverage: 100% (1241/1241 lines, 134/134 methods, 20/20 classes)
- Integration coverage: 100% (1240/1240 lines, 134/134 methods, 20/20 classes)
- PHPCS 100% clean (0 errori, 0 warning)
- PHPStan level 6: 0 errori
- 27 file sorgente in `src/`, 47 file di test (27 unit + 20 integration)
- Pattern enforcement (NO singleton/static/final) su tutte le 11 nuove classi
- TDD rigoroso per ogni componente: RED → GREEN → REFACTOR

## 0.3.1 - 2026-02-09

### Added
- **SECURITY.md**
- **.gitattributes** - `export-ignore` per escludere file di sviluppo da `git archive` e GitHub Download ZIP
- **codecov.yml** - Configurazione Codecov con soglie progetto 95%, patch 90%, flag separati `unit`/`integration` con `carryforward`

### Changed
- **DatabaseCheckTest** - Timing test usa busy-wait loop (resiste a EINTR da SIGALRM) invece di `usleep()` singolo
- **test-matrix.sh** - Grep fallback per output PHPUnit con test skipped (`Tests: N, ...` oltre a `OK (N tests, ...)`)
- **CheckRunner** - Messaggio eccezione internazionalizzato con `__()` (`sprintf(__('Check exception: %s'), ...)`)
- **DatabaseCheck** - Soglia slow query estratta in costante `SLOW_QUERY_THRESHOLD = 0.5`
- **ErrorLogCheck** - Aggiunto null coalesce difensivo `?? 'other'` in `classify_line()`
- **Activator** - Commento esplicativo sul filtro `cron_schedules` duplicato (necessario durante attivazione)
- **CI workflow** - Rimosso flag deprecato `--no-suggest` da Composer; aggiunto `permissions: contents: read`; Codecov upload separato per unit e integration con flag distinti (`codecov-action@v5`, `CODECOV_TOKEN`)
- **HealthScreenTest** - `$_POST` cleanup centralizzato in `tearDown()` per isolamento test robusto
- **HealthScreenTest/MenuTest/ActivatorTest** - 7× `assertTrue(true)` sostituiti con `assertInstanceOf()` (asserzioni reali)
- **CheckRunnerTest** - Verifica `__()` chiamata in test eccezione per i18n
- **build-zip.sh** - `mkdir -p` per directory output custom
- **install-wp-tests.sh** - Variabili quotate nelle righe più critiche (path con spazi)

### Fixed
- **test-matrix.sh** - Integration test count mostrava "?" quando PHPUnit aveva test skipped (formato output diverso da `OK (N tests, ...)`)
- **DatabaseCheckTest flaky** - `usleep()` interrotto da SIGALRM (PHPUnit php-invoker) causava test timing intermittentemente fallimentari; sostituito con busy-wait loop resistente a EINTR

### Development Notes
- Code review post-M3: 4 fix sorgente, 4 test migliorati, 1 file nuovo, 2 fix CI/script
- Coverage push: +36 test (+12 unit, +24 integration), 2 nuovi file test integration (ContainerTest, PluginTest)
- 350 test totali (227 unit + 123 integration), 810 assertions
- Coverage: unit 99.50% lines, integration 100% lines (12/12 classi, 73/73 metodi, 603/603 linee)
- PHPCS 100% clean, PHPStan level 6: 0 errori

## 0.3.0 - 2026-02-09

### Added
- **PHPStan** - Analisi statica livello 6 con `szepeviktor/phpstan-wordpress`
  - Configurazione `phpstan.neon` (level 6, `missingType.iterableValue` ignorato)
  - Script `composer analyse` per esecuzione locale
  - Job dedicato in GitHub Actions CI
  - Integrazione in `bin/test-matrix.sh` (eseguito insieme a PHPCS)
- **RedisCheck** - Health check per Redis con graceful degradation (M3)
  - Rilevamento estensione PHP Redis (`extension_loaded`)
  - Test di connessione con costanti WordPress (`WP_REDIS_HOST`, `WP_REDIS_PORT`)
  - Autenticazione opzionale (`WP_REDIS_PASSWORD`)
  - Selezione database (`WP_REDIS_DATABASE`)
  - Smoke test SET/GET/DEL con misurazione tempo di risposta
  - Soglia slow response (>100ms = warning)
  - Tutti i fallimenti sono `warning` (Redis è opzionale, mai `critical`)
  - Host e errori redatti via `RedactionInterface`
- 25 unit test + 6 integration test per RedisCheck
- Registrazione RedisCheck in `config/bootstrap.php`
- **HealthScreenTest** - +7 test: `process_actions()` early returns (3), `run_now` action, `clear_cache` action, notice transient display; helper `create_testable_screen()` e `mock_process_functions()`
- **DatabaseCheckTest** - +2 test: warning su query lenta (>0.5s), fallback `Unknown error` con `last_error` vuoto
- **MenuTest** - +1 test: skip `load-hook` quando `add_menu_page` ritorna false; aggiornato test esistente con asserzione `add_action` per `load-{$page_hook}`
- **SchedulerTest** - Aggiornati 3 test `register_hooks` con `get_transient`/`set_transient` mocks (sostituito `is_admin`)
- **ActivatorTest** - Aggiornati 3 test con definizione `MINUTE_IN_SECONDS` e mock `__()`
- **RedisCheckTest** - Aggiornato `test_returns_ok_when_smoke_test_passes` con `Mockery::on()` pattern matcher per chiave dinamica
- **Copertura test migliorata** - 49 nuovi test (215 unit + 99 integration), 743 assertions

### Changed
- **Activator** - Usa `MINUTE_IN_SECONDS` e `__()` i18n per intervallo cron (allineato con Scheduler)
- **ErrorLogCheck::classify_line()** - Ottimizzato da 6 regex sequenziali a singola regex con alternazione + mappa di lookup
- **HealthScreen** - Estratto `exit` in metodo protetto `do_exit()` per testabilità con Mockery
- **RedisCheck** - Rimosso anti-pattern `unset($e)` nei catch block, sostituito con `phpcs:ignore`
- **.gitignore** - Rimosso `composer.lock` (deve essere committato per build riproducibili)
- **Scheduler** - Self-healing usa transient throttle (ogni ora) invece di `is_admin()` guard; funziona anche su frontend
- **RedisCheck** - Smoke test usa chiave unica per run (`uniqid()`) per evitare race condition tra cron e run manuale
- **cleanup_and_close()** - Accetta `$smoke_key` come parametro per cleanup preciso
- **ErrorLogCheck::resolve_log_path()** - `WP_DEBUG_LOG` assegnato a variabile locale con `@phpstan-ignore` per compatibilità PHPStan (stubs WordPress tipano `bool`, ma WordPress accetta anche stringhe)
- **.phpcs.xml.dist** - Aggiunto exclude per `.phpstan-cache`
- **RedisCheckTest integration** - `@requires extension redis` su test individuali (non `return;` a livello file); `extension_loaded('redis')` guard su `require_once FakeRedisHelpers.php`

### Fixed
- **Integration HealthScreenTest** - Corretta option key da `ops_health_results` a `ops_health_latest_results` (allineata con Storage prefix `ops_health_` + CheckRunner key `latest_results`)
- **RedisCheckTest fatale senza ext-redis** - `extends \Redis` nelle classi helper causava fatal error su PHP senza ext-redis; risolto con guard `extension_loaded('redis')` e `@requires extension redis`

### Development Notes
- 314 test totali (215 unit + 99 integration), 743 assertions
- PHPCS 100% clean (0 errori, 0 warning)
- PHPStan level 6: 0 errori
- 16 file sorgente, 29 file di test (16 unit + 12 integration)
- Code review post-M3: 5 fix sorgente + 9 nuovi test + 6 test aggiornati
- Code review 2: 3 fix sorgente + 4 test aggiornati

## 0.2.1 - 2026-02-09

### Added
- **Admin Action Buttons** - Bottoni "Run Now" e "Clear Cache" nella pagina Ops Health Dashboard
  - "Run Now": esegue tutti i check immediatamente via `CheckRunnerInterface::run_all()`
  - "Clear Cache": cancella i risultati in cache via `CheckRunnerInterface::clear_results()`
  - Protezione nonce (`ops_health_admin_action`) e capability check (`manage_options`)
  - Pattern PRG (Post-Redirect-Get) via hook `load-{$page_hook}` per evitare "headers already sent"
  - Notice di conferma con transient auto-cancellante
- **CheckRunnerInterface::clear_results()** - Nuovo metodo per cancellare risultati dallo storage
- Integration test per HealthScreen (render output, no-checks message, capability check, action buttons)
- Integration test per Menu::render_page() (delega a HealthScreen)
- Unit test per Scheduler::add_custom_cron_interval() (aggiunta intervallo + no-overwrite)
- Unit test per priorità campioni critical vs warning in ErrorLogCheck
- Unit test per bottoni Run Now/Clear Cache e nonce fields in HealthScreen

### Fixed
- **ErrorLogCheck::validate_log_file()** - Distingue "non configurato" (warning) da "configurato ma file non esiste ancora" (ok); prima mostrava erroneamente warning quando `WP_DEBUG_LOG=true` ma `debug.log` non era ancora stato creato
- **ErrorLogCheck::collect_samples()** - I campioni critical ora hanno priorità sui warning; prima `array_slice(..., -max)` scartava i critical quando c'erano molti warning
- **ErrorLogCheck::resolve_log_path()** - Gestisce `WP_DEBUG_LOG === true` (WordPress scrive in `wp-content/debug.log`)
- **ErrorLogCheck::read_tail()** - Verifica return value di `flock()`; `flock(LOCK_UN)` esplicito prima di `fclose()`
- **DatabaseCheck::get_name()** - Wrappato con `__()` per i18n (era l'unico check senza traduzione)
- **install-wp-tests.sh** - `grep -o` restituiva tutte le versioni WordPress dall'API invece della sola ultima; `svn export` riceveva argomenti multipli e falliva in CI. Aggiunto `head -1` per prendere solo la prima (latest). Rimossa riga `grep` morta senza effetto.

### Changed
- **HealthScreen::process_actions()** - Metodo ora public, invocato da `load-{$page_hook}` hook (non più dentro `render()`)
- **Menu::add_menu()** - Registra hook `load-{$page_hook}` per processare azioni prima dell'output
- **build-zip.sh** - Copia condizionale di `languages/` e `assets/` (non fallisce se mancanti)
- **install-wp-tests.sh** - Endpoint API WordPress usa HTTPS invece di HTTP (hardening anti-MITM)

### Removed
- `tests/bootstrap-integration.php` - File orfano non referenziato in phpunit.xml.dist

### Development Notes
- 225 test totali (178 unit + 47 integration), 497 assertions
- PHPCS 100% clean (0 errori, 0 warning)
- ActivatorTest integration usa costante `OPS_HEALTH_DASHBOARD_VERSION` invece di stringa hardcoded

## 0.2.0 - 2026-02-09

### Added
- **RedactionInterface** - Contratto per il servizio di redazione dati sensibili
  - `redact( string $text ): string` - Redige un singolo testo
  - `redact_lines( array $lines ): array` - Redige un array di righe
- **CheckRunnerInterface** - Contratto per disaccoppiare HealthScreen e Scheduler da CheckRunner concreto
  - `add_check()`, `run_all()`, `get_latest_results()`
- **Redaction** - Servizio di sanitizzazione dati sensibili con 11 pattern
  - Credenziali DB (DB_PASSWORD, DB_USER, DB_NAME, DB_HOST) -> `[REDACTED]`
  - WordPress salts (AUTH_KEY, SECURE_AUTH_KEY, ecc.) -> `[REDACTED]`
  - API key, secret, token, bearer -> `[REDACTED]`
  - Password in URL e campi generici -> `[REDACTED]`
  - Indirizzi email -> `[EMAIL_REDACTED]`
  - Indirizzi IPv4 e IPv6 -> `[IP_REDACTED]`
  - Path ABSPATH e WP_CONTENT_DIR -> placeholder
  - Directory home utenti -> `/home/[USER_REDACTED]`
  - Constructor injection di path (ABSPATH, WP_CONTENT_DIR) per testabilità
  - Ordine chain: WP_CONTENT_DIR prima di ABSPATH (più specifico)
- **ErrorLogCheck** - Check riepilogo sicuro del log errori PHP
  - Risoluzione path log: WP_DEBUG_LOG (stringa) -> ini_get('error_log')
  - Validazione file: esistenza, leggibilità, anti-symlink
  - Lettura tail efficiente: max 512KB, max 100 righe con `flock(LOCK_SH)`
  - Aggregazione per severità: fatal, parse, warning, notice, deprecated, strict, other
  - Campioni redatti: max 5 righe critiche/warning, sanitizzate via Redaction
  - Status: critical (fatal/parse), warning (warning/deprecated/strict), ok (solo notice/other)
  - Nessuna esposizione del path raw del file di log
  - Messaggi internazionalizzati con `__()`
- **73 nuovi test** (65 unit + 8 integration) per le nuove classi
- Pattern enforcement tests su RedactionInterface, CheckRunnerInterface, Redaction, ErrorLogCheck

### Changed
- **CheckRunner** - Riceve `RedactionInterface` in constructor, redige messaggi eccezione; include `name` nei risultati
- **DatabaseCheck** - Riceve `RedactionInterface` in constructor, redige `$wpdb->last_error`
- **Scheduler** - Usa `CheckRunnerInterface` (non classe concreta); costanti `HOOK_NAME`/`INTERVAL`; self-healing solo in contesto admin (`is_admin()` guard)
- **HealthScreen** - Usa `CheckRunnerInterface`; mostra `result['name']` con `ucfirst()` fallback
- **Activator** - Usa costanti `Scheduler::HOOK_NAME` e `Scheduler::INTERVAL`
- **Container** - Aggiunta rilevazione dipendenze circolari con array `$resolving`
- **Storage** - `update_option()` con `autoload=false` per dati grandi
- **Redaction** - IPv4 regex con validazione ottetti (0-255); URL password regex restrittiva (no whitespace)
- **bootstrap.php** - Usa `container->instance()` per `$wpdb`; binding CheckRunnerInterface; RedactionInterface iniettata in CheckRunner e DatabaseCheck

### Security
- Servizio Redaction impedisce esposizione di credenziali, token, PII nei log
- ErrorLogCheck non espone path raw del filesystem nei risultati
- Protezione anti-symlink: file di log symlink vengono rifiutati
- Limite di lettura a 512KB per prevenire consumo eccessivo di memoria
- `flock(LOCK_SH)` su lettura log per sicurezza in accesso concorrente
- RedactionInterface iniettata in CheckRunner per redazione messaggi eccezione
- RedactionInterface iniettata in DatabaseCheck per redazione errori `$wpdb`
- Storage `autoload=false` previene caricamento dati grandi ad ogni richiesta
- Container rileva dipendenze circolari (previene loop infiniti)

### Development Notes
- **M2 Completed**: Riepilogo Error Log Sicuro + Code Review (15/15 issue risolte)
- 210 test totali (169 unit + 41 integration), 472 assertions
- PHPCS 100% clean (0 errori, 0 warning)
- TDD rigoroso: RED -> GREEN -> REFACTOR per ogni componente

## 0.1.0 - 2026-02-08

### Added
- **StorageInterface** e **CheckInterface** - Contratti per DI e testabilità
- **Storage** - Wrapper WordPress Options API con prefisso `ops_health_`
  - Metodo `has()` con sentinel object pattern (distingue `false` da chiave mancante)
- **CheckRunner** - Orchestratore dei check con salvataggio risultati
  - Try/catch per `\Throwable` su ogni check (resilienza a errori)
  - Type safety su `get_latest_results()` (ritorna sempre array)
- **DatabaseCheck** - Check connettività database con `SELECT 1`
  - Constructor injection di `$wpdb` (NO global)
  - Misurazione durata query in millisecondi
  - Nessuna esposizione di informazioni sensibili (no db_host, db_name)
  - Messaggi internazionalizzati con `__()`
- **Scheduler** - Scheduling WP-Cron ogni 15 minuti
  - Intervallo custom `every_15_minutes`
  - Prevenzione duplicati di scheduling
- **Menu** - Registrazione pagina admin `Ops Health Dashboard`
- **HealthScreen** - Rendering pagina admin con risultati check
  - Capability check `manage_options` con `wp_die()`
  - Validazione difensiva di chiavi risultato (status, message)
  - Messaggio informativo quando nessun check è stato eseguito
- **104 unit test** (Brain\Monkey) + **33 integration test** (WP Test Suite)
- Pattern enforcement tests su ogni classe (NO final, NO static methods/properties)

### Changed
- **Activator** - Gestisce scheduling/unscheduling cron in activate/deactivate
  - Hook name corretto: `ops_health_run_checks`
  - Rimosso `flush_rewrite_rules()` (non necessario)
- **Plugin** - `init()` registra solo hooks, non schedula direttamente
- **bootstrap.php** - Inietta `$wpdb` globale in DatabaseCheck
- **composer.json** - Script `test` esegue unit e integration sequenzialmente
- **Scheduler** - `register_hooks()` include self-healing del cron

### Fixed
- **Scheduler self-healing** - `register_hooks()` ora chiama `schedule()` per ri-creare l'evento cron se scomparso (migrazione DB, cleanup cron, corruzione option). Idempotente grazie al guard `is_scheduled()`.
- **test-matrix.sh `--parallel`** - I risultati delle subshell ora sono propagati al processo padre tramite file temporanei. Prima la tabella riepilogativa era vuota e l'exit code era sempre 0 in modalità parallela.

### Security
- Constructor injection di `$wpdb` per evitare accesso globale nei test
- Nessuna esposizione di host/nome database nei risultati dei check
- Capability check `manage_options` su pagina admin
- Output escaped con `esc_html()`, `esc_attr()`

### Development Notes
- **M1 Completed**: Core Checks + Storage + Cron
- 137 test totali, 275 assertions, PHPCS 100% clean
- Code review: 21/22 issue risolte (uninstall.php pianificato per M6)

## 0.0.0 - 2026-02-08

### Added
- Scaffolding iniziale del plugin
- Core dependency injection container (NO singleton pattern)
- Orchestratore principale del plugin con constructor injection
- Handler di attivazione/disattivazione
- Suite di test completa con approccio TDD
- GitHub Actions CI workflow (PHPCS + PHPUnit matrix)
- Configurazione WordPress Coding Standards
- Configurazione PHPUnit con supporto coverage

### Development Notes
- **M0 Completed**: Setup & Infrastruttura ✅
- Tutte le classi core seguono il pattern NO singleton, NO static, NO final
- TDD workflow applicato: RED → GREEN → REFACTOR
- Target di coverage: PHP 8.3
