# Development Plan - Ops Health Dashboard

**Current Milestone**: M6 - WordPress.org Readiness
**Status**: M5 completata, M6 pianificata

---

## Milestone 0: Setup & Infrastruttura ✅ 8/8

**Obiettivo**: Scaffolding completo con CI verde

### Tasks

- [x] **M0.1** - Struttura directory completa
- [x] **M0.2** - Setup composer.json con dipendenze
- [x] **M0.3** - Configurazione PHPCS (WPCS)
- [x] **M0.4** - Setup PHPUnit (config + bootstrap)
- [x] **M0.5** - GitHub Actions workflows
- [x] **M0.6** - File bootstrap (main plugin + config)
- [x] **M0.7** - Core classes (Container, Plugin, Activator) - TDD
- [x] **M0.8** - Script bin/install-wp-tests.sh

**Pattern Enforcement**:
- ✅ Container usa `share()` per shared instances, NON `singleton()`
- ✅ Plugin riceve Container via constructor, NO `get_instance()`
- ✅ Bootstrap function crea e configura, NO static factories
- ✅ Nessuna classe final, nessun metodo final

**Deliverable**: CI verde con PHPCS + PHPUnit matrix + coverage 8.3

---

## Milestone 1: Core Checks + Storage + Cron ✅ 10/10

**Obiettivo**: Check base con dashboard funzionante

### Tasks

- [x] **M1.1** - StorageInterface + CheckInterface (contratti DI)
- [x] **M1.2** - Storage service (Options API wrapper con prefisso `ops_health_`)
- [x] **M1.3** - CheckRunner orchestrator (esecuzione check + salvataggio risultati)
- [x] **M1.4** - DatabaseCheck con constructor injection `$wpdb` (TDD)
- [x] **M1.5** - Scheduler service (WP-Cron ogni 15 minuti)
- [x] **M1.6** - Admin Menu registration
- [x] **M1.7** - HealthScreen rendering con capability check
- [x] **M1.8** - bootstrap.php con DI wiring completo
- [x] **M1.9** - Unit tests completi (104 test, Brain\Monkey)
- [x] **M1.10** - Integration tests completi (33 test, WP Test Suite)

### Code Review - Issue Risolte (17/18)

- ✅ Activator: hook name corretto `ops_health_run_checks`, rimosso `flush_rewrite_rules()`
- ✅ Activator: gestisce schedule/unschedule cron (non Plugin::init)
- ✅ DatabaseCheck: `$wpdb` via constructor injection (NO global)
- ✅ DatabaseCheck: nessuna esposizione db_host/db_name nei dettagli
- ✅ DatabaseCheck: messaggi i18n con `__()`
- ✅ CheckRunner: try/catch per `\Throwable` su ogni check
- ✅ CheckRunner: `get_latest_results()` type safety (ritorna sempre array)
- ✅ Storage: `has()` con sentinel object pattern
- ✅ HealthScreen: validazione difensiva chiavi risultato (isset)
- ✅ HealthScreen: messaggio "no checks" quando risultati vuoti
- ✅ Plugin: `init()` registra solo hook, non schedula
- ✅ bootstrap.php: inietta `$wpdb` globale in DatabaseCheck
- ✅ composer.json: test script esegue suite sequenzialmente
- ✅ CI: coverage con file separati per suite
- ✅ Test: rimossi tutti i `assertTrue(true)` placeholders
- ✅ Test: aggiunti test per static properties
- ✅ Test: aggiunti test per eccezioni e edge case
- ⏳ uninstall.php → pianificato per M6

**Statistiche Finali**:
- 11 file sorgente in `src/`
- 18 file di test (11 unit + 7 integrazione)
- 137 test totali, 275 assertions
- PHPCS 100% clean (0 errori, 0 warning)

**Deliverable**: la dashboard mostra il Database check con auto-refresh WP-Cron ✅

---

## Milestone 2: Riepilogo Error Log Sicuro ✅ 6/6 + Code Review 15/15

**Obiettivo**: Check error log con redazione automatica dei dati sensibili

### Tasks

- [x] **M2.1** - RedactionInterface (contratto DI per redazione)
- [x] **M2.2** - Redaction service (11 pattern: credenziali, token, PII, path)
- [x] **M2.3** - ErrorLogCheck con TDD (tail log, aggregazione, campioni redatti)
- [x] **M2.4** - DI wiring in bootstrap.php (RedactionInterface + ErrorLogCheck)
- [x] **M2.5** - Unit tests completi (56 nuovi test, Brain\Monkey + Mockery partial mock)
- [x] **M2.6** - Integration tests completi (8 nuovi test, WP Test Suite + file temp)

### Dettagli Implementazione

**Redaction Service** - 11 pattern di redazione in catena ordinata:
1. Path WP_CONTENT_DIR -> `[WP_CONTENT]` (str_replace, più specifico prima)
2. Path ABSPATH -> `[ABSPATH]/` (str_replace)
3. Credenziali DB (DB_PASSWORD, DB_USER, DB_NAME, DB_HOST) -> `[REDACTED]`
4. WordPress salts (AUTH_KEY, SECURE_AUTH_KEY, ecc.) -> `[REDACTED]`
5. API key, secret, token -> `[REDACTED]`
6. Bearer token -> `[REDACTED]`
7. Password in URL e campi generici -> `[REDACTED]`
8. Email -> `[EMAIL_REDACTED]`
9. IPv4 -> `[IP_REDACTED]` (con validazione ottetti 0-255)
10. IPv6 (min 5 gruppi per evitare falsi positivi su timestamp) -> `[IP_REDACTED]`
11. Home directory `/home/user` -> `/home/[USER_REDACTED]`

**ErrorLogCheck** - Riepilogo sicuro del log errori:
- Risoluzione path: `WP_DEBUG_LOG` (stringa) -> `ini_get('error_log')`
- Validazione: esistenza, leggibilità, anti-symlink
- Tail efficiente: max 512KB, max 100 righe, `flock(LOCK_SH)` per accesso concorrente
- Classificazione: fatal, parse, warning, notice, deprecated, strict, other
- Status: critical (fatal/parse > 0), warning (warning/deprecated/strict > 0), ok
- Max 5 campioni critici/warning, redatti prima dell'inclusione
- Protected methods per testabilità con Mockery partial mock

### Code Review - Issue Risolte (15/15)

- ✅ **CheckRunnerInterface**: nuovo contratto per disaccoppiare HealthScreen e Scheduler da CheckRunner concreto
- ✅ **RedactionInterface in CheckRunner**: messaggi eccezione redatti prima dell'inclusione nei risultati
- ✅ **RedactionInterface in DatabaseCheck**: `$wpdb->last_error` redatto prima dell'inclusione nei risultati
- ✅ **Container dipendenze circolari**: array `$resolving` rileva loop infiniti durante la risoluzione
- ✅ **Storage autoload=false**: `update_option()` con terzo parametro `false` per dati grandi
- ✅ **Scheduler self-healing admin-only**: guard `is_admin()` in `register_hooks()` previene self-healing su frontend
- ✅ **Scheduler costanti**: `HOOK_NAME` e `INTERVAL` come costanti di classe
- ✅ **Activator usa costanti Scheduler**: `Scheduler::HOOK_NAME` e `Scheduler::INTERVAL` invece di stringhe hardcoded
- ✅ **HealthScreen CheckRunnerInterface**: type-hint su interfaccia, mostra `result['name']` con `ucfirst()` fallback
- ✅ **ErrorLogCheck flock(LOCK_SH)**: lock condiviso durante lettura log per sicurezza concorrente
- ✅ **IPv4 regex validazione ottetti**: regex verifica che ogni ottetto sia 0-255 (non solo \d{1,3})
- ✅ **URL password regex restrittiva**: no whitespace nel pattern per evitare falsi positivi
- ✅ **bootstrap.php container->instance()**: `$wpdb` registrato con `instance()` invece di closure
- ✅ **bootstrap.php CheckRunnerInterface binding**: CheckRunner registrato sotto `CheckRunnerInterface::class`
- ✅ **CheckRunner include 'name' nei risultati**: ogni risultato include la chiave `name` dal check

**Statistiche Finali**:
- 15 file sorgente in `src/`
- 24 file di test (15 unit + 9 integration)
- 210 test totali (169 unit + 41 integration), 472 assertions
- PHPCS 100% clean (0 errori, 0 warning)

**Deliverable**: Dashboard mostra Database + Error Log check con redazione automatica ✅

---

## Progress Log

### 2026-02-09 (Code Review 2)

**PHPStan Integration** - Analisi statica level 6:
- Installato `phpstan/phpstan` + `szepeviktor/phpstan-wordpress`
- Creato `phpstan.neon` (level 6, `missingType.iterableValue` ignorato)
- Aggiunto `composer analyse` script
- Aggiunto job PHPStan in GitHub Actions CI (`.github/workflows/ci.yml`)
- Integrato in `bin/test-matrix.sh` (eseguito con PHPCS)
- Fix: `ErrorLogCheck::resolve_log_path()` assegnazione WP_DEBUG_LOG a variabile con `@phpstan-ignore` per compatibilità con gli stubs
- Fix: `.phpcs.xml.dist` exclude `.phpstan-cache`
- PHPStan level 6: 0 errori

**Code Review 2** - 3 fix sorgente + 4 test aggiornati:
- Scheduler: self-healing con transient throttle (ogni ora) invece di `is_admin()` guard
- RedisCheck: chiave smoke test unica per run con `uniqid()` (evita race condition cron vs manual)
- RedisCheck `cleanup_and_close()`: accetta `$smoke_key` parametro
- Integration HealthScreenTest: corretta option key `ops_health_results` → `ops_health_latest_results`
- SchedulerTest: mocks `get_transient`/`set_transient` al posto di `is_admin`
- RedisCheckTest: `Mockery::on()` pattern matcher per chiave dinamica
- Integration SchedulerTest: `delete_transient` al posto di `set_current_screen`
- Totale: 265 test (212 unit + 53 integration), 620 assertions, PHPCS clean
- **Lesson**: `is_admin()` limita self-healing al solo admin; usare transient throttle per copertura frontend
- **Lesson**: chiavi Redis condivise tra esecuzioni concorrenti causano race condition; usare `uniqid()` per unicità

### 2026-02-09 (Code Review Post-M3)

**Code Review Post-M3** - 5 fix sorgente + 9 nuovi test + 6 test aggiornati:
- Activator.php: `900` → `15 * MINUTE_IN_SECONDS`, aggiunto `__()` i18n
- ErrorLogCheck.php: `classify_line()` da 6 regex a singola regex + map (riduce da 600 a 100 valutazioni per 100 righe)
- HealthScreen.php: estratto `exit` → `do_exit()` protetto per testabilità
- RedisCheck.php: `unset($e)` → `phpcs:ignore` (2 catch block)
- .gitignore: rimosso `composer.lock`
- +7 test HealthScreen (process_actions completo), +2 DatabaseCheck, +1 Menu
- Aggiornati 3 SchedulerTest + 3 ActivatorTest per nuove dipendenze
- Totale: 265 test (212 unit + 53 integration), 617 assertions, PHPCS clean

### 2026-02-09 (M3)

**M3 - Redis Check** completata:
- Implementato `RedisCheck` con graceful degradation (Redis opzionale, tutti i fallimenti sono `warning`)
- Rilevamento estensione PHP Redis, connessione, autenticazione, selezione database, smoke test SET/GET/DEL
- Misurazione tempo di risposta (>100ms = warning)
- Host e errori redatti via `RedactionInterface`
- Protected methods (`is_extension_loaded`, `create_redis_instance`, `get_redis_config`) per testabilità
- `TestableRedisCheck` subclass per integration test (pattern da `TestableErrorLogCheck`)
- Registrato in `config/bootstrap.php` via `$runner->add_check()`
- 25 unit test + 6 integration test
- Totale: 256 test (203 unit + 53 integration), 572 assertions, PHPCS clean

### 2026-02-09 (CI Fix)

**install-wp-tests.sh version resolution fix**:
- `grep -o` returned ALL WordPress versions from API (6.9.1, 6.9.0, 6.8.3, ...) instead of just the latest
- `svn export` received multiple arguments and failed with `E205000: Error parsing arguments`
- Fix: added `head -1` to take only the first (latest) version; removed dead `grep` line
- **Lesson**: WP version-check API returns multiple versions in JSON; always `head -1` when extracting latest

### 2026-02-09 (Post-M2)

**Admin Action Buttons + ErrorLogCheck Fix**:
- Added "Run Now" and "Clear Cache" buttons to admin dashboard page
- `CheckRunnerInterface::clear_results()` new method for cache clearing
- `HealthScreen::process_actions()` handles POST with nonce + capability verification
- `Menu::add_menu()` registers `load-{$page_hook}` hook for PRG pattern (avoids "headers already sent")
- Fixed ErrorLogCheck: distinguishes "not configured" (warning) from "configured but file not yet created" (ok)
- Admin notice with auto-clearing transient for action feedback
- 225 tests (178 unit + 47 integration), 497 assertions, PHPCS clean
- **Lesson**: `wp_safe_redirect()` must be called before any output; use `load-{$page_hook}` hook, not inside `render()` callback
- **Lesson**: `wp_nonce_field()` echoes by default; Brain\Monkey mock must use `echo` not `return`

### 2026-02-09

**M2 Code Review** (15/15 issue risolte):
- CheckRunnerInterface per disaccoppiamento (HealthScreen, Scheduler usano interfaccia)
- RedactionInterface iniettata in CheckRunner (redige eccezioni) e DatabaseCheck (redige $wpdb errors)
- Container: rilevazione dipendenze circolari con array `$resolving`
- Storage: `autoload=false` in `update_option()`
- Scheduler: self-healing solo in contesto admin (`is_admin()` guard), costanti HOOK_NAME/INTERVAL
- ErrorLogCheck: `flock(LOCK_SH)` per accesso concorrente sicuro
- Redaction: IPv4 regex con validazione ottetti (0-255), URL password regex restrittiva
- bootstrap.php: `container->instance($wpdb)`, binding CheckRunnerInterface
- Activator: usa `Scheduler::HOOK_NAME` e `Scheduler::INTERVAL`
- CheckRunner: include chiave `name` nei risultati
- 210 test (169 unit + 41 integration), 472 assertions, PHPCS clean
- Lesson learned: WP test suite `is_admin()` restituisce false; usare `set_current_screen('dashboard')` per abilitare contesto admin nei test di integrazione

### 2026-02-08

**Completed M1**: Core Checks + Storage + Cron ✅
- Implemented StorageInterface and CheckInterface contracts
- Storage service with Options API, sentinel pattern in `has()`
- CheckRunner with try/catch resilience and type safety
- DatabaseCheck with `$wpdb` constructor injection, no info disclosure
- Scheduler with 15-minute WP-Cron interval
- Admin Menu and HealthScreen with capability check and defensive rendering
- Complete code review: fixed 17/18 issues
- All 137 tests green, PHPCS 100% clean
- Added `bin/test-matrix.sh` for local PHP 7.4-8.5 matrix testing (mirrors CI)

**Post-M1 Hardening** (code review Codex):
- Fixed `--parallel` in test-matrix.sh: i risultati delle subshell ora sono propagati via file temporanei
- Added Scheduler self-healing: `register_hooks()` calls `schedule()` to re-create missing cron event
- 137 tests (104 unit + 33 integration), 275 assertions

**Post-M1 Improvements**:
- Added autoloader guard in main plugin file (admin notice instead of fatal error if vendor/ missing)
- Created `bin/build-zip.sh` for production ZIP generation (`zip` CLI with PHP ZipArchive fallback)
- Fixed PHPCS doc comment SpacingAfter in 4 source files (Storage, Scheduler, DatabaseCheck, Menu)
- Added Version and PHPCS badges to README.md, updated license badge to GPL v3
- Added `dist/` to .gitignore

**Completed M0**: Setup & Infrastruttura ✅
- Created complete directory structure
- Setup composer.json with all dependencies (PHPUnit, WPCS, Brain\Monkey, etc.)
- Configured PHPCS for WordPress Coding Standards
- Setup PHPUnit configuration with coverage support
- Created GitHub Actions CI workflow (PHPCS + PHPUnit matrix PHP 7.4-8.5)
- Implemented core classes with TDD:
  - `Container` class - DI container with NO singleton pattern
  - `Plugin` class - Main orchestrator with constructor injection
  - `Activator` class - Activation/deactivation handler
- Created main plugin file and bootstrap function

**Git Commits**:
- `1b0944c` - feat(M0): Complete setup & infrastructure with TDD
- `4e2d182` - docs: add comprehensive README and CONTRIBUTING guides
- `d7b3dd2` - docs: traduci documentazione in italiano
- `70a708e` - docs: traduci commenti codice in italiano

---

## Known Issues / Tech Debt

- **CSS 'unknown' status**: HealthScreen non ha classe CSS per lo stato 'unknown' (solo ok/warning/critical). Aggiungere quando si implementano gli asset CSS (M4+).
- **Action buttons inline style**: i bottoni usano `style="display:inline"` inline; migrarli a foglio CSS dedicato in M4+.
- **uninstall.php**: pianificato per M6.

## Milestone 3: Check Redis ✅ 1/1

**Obiettivo**: Check Redis opzionale con graceful degradation

### Tasks

- [x] **M3.1** - RedisCheck con TDD (rilevamento estensione, connessione, auth, smoke test, response time)

### Code Review Post-M3

- ✅ **Activator MINUTE_IN_SECONDS**: usa `15 * MINUTE_IN_SECONDS` e `__()` i18n per intervallo cron (allineato con Scheduler)
- ✅ **ErrorLogCheck classify_line()**: ottimizzato da 6 regex sequenziali a singola regex con alternazione + mappa di lookup
- ✅ **HealthScreen do_exit()**: estratto `exit` in metodo protetto per testabilità con Mockery partial mock
- ✅ **RedisCheck unset($e)**: rimosso anti-pattern, sostituito con `phpcs:ignore`
- ✅ **.gitignore composer.lock**: rimossa voce (deve essere committato per build riproducibili)
- ✅ **HealthScreenTest +7 test**: copertura completa di `process_actions()` (early returns, run_now, clear_cache, notice)
- ✅ **DatabaseCheckTest +2 test**: warning su query lenta, fallback `Unknown error`
- ✅ **MenuTest +1 test**: skip `load-hook` quando `add_menu_page` ritorna false
- ✅ **SchedulerTest aggiornati**: expectation `add_filter` in 3 test `register_hooks`
- ✅ **ActivatorTest aggiornati**: definizione `MINUTE_IN_SECONDS` e mock `__()`

**Statistiche Finali**:
- 16 file sorgente in `src/`
- 26 file di test (16 unit + 10 integration)
- 314 test totali (215 unit + 99 integration), 744 assertions
- PHPCS 100% clean (0 errori, 0 warning)
- PHPStan level 6: 0 errori

**Deliverable**: Dashboard mostra Database + Error Log + Redis check con graceful degradation ✅

---

## Progress Log

### 2026-02-09 (Code Review 3)

**Code Review 3** - 4 fix sorgente + 4 test migliorati + CI/config:
- CheckRunner: exception message internazionalizzato con `__()` per i18n
- DatabaseCheck: soglia 0.5s estratta in costante `SLOW_QUERY_THRESHOLD`
- ErrorLogCheck: null coalesce difensivo `?? 'other'` in `classify_line()`
- Activator: commento esplicativo sul filtro `cron_schedules` duplicato
- HealthScreenTest: `$_POST` cleanup in `tearDown()`, 4× `assertTrue(true)` → `assertInstanceOf()`
- MenuTest: 2× `assertTrue(true)` → `assertInstanceOf()`
- ActivatorTest: 1× `assertTrue(true)` → `assertInstanceOf()`
- CheckRunnerTest: verifica `__()` nel test eccezione
- CI: rimosso `--no-suggest` deprecato, aggiunto `permissions: contents: read`
- Nuovo `.gitattributes` con `export-ignore` per file di sviluppo
- build-zip.sh: `mkdir -p` per directory output custom
- install-wp-tests.sh: variabili quotate nei path critici
- Totale: 314 test (215 unit + 99 integration), 744 assertions, PHPCS + PHPStan clean

### 2026-02-09 (Test Matrix Stabilization)

**Test Matrix Fix** - 3 problemi risolti per stabilità test su PHP 7.4-8.5:

1. **RedisCheckTest fatale senza ext-redis**: `extends \Redis` nelle classi FakeRedis* helper causava `Class 'Redis' not found` su PHP senza ext-redis. Fix: guard `extension_loaded('redis')` su `require_once` + `@requires extension redis` sui metodi test che usano FakeRedis subclasses. Il `return;` a livello file non funzionava perché PHPUnit bypassa il guard durante la discovery dei test.

2. **DatabaseCheckTest flaky (EINTR)**: `usleep()` singolo veniva interrotto da SIGALRM (PHPUnit php-invoker) causando duration < 0.5s nonostante usleep di 1.5-3s. Fix: busy-wait loop con `usleep(50000)` in incrementi che riprende dopo ogni interruzione.

3. **test-matrix.sh count "?"**: il grep `'OK \(\K[0-9]+'` non matchava l'output PHPUnit quando ci sono test skipped (`Tests: N, Assertions: M, Skipped: S.` invece di `OK (N tests, M assertions)`). Fix: grep fallback con `Tests: \K[0-9]+`.

**Copertura test migliorata**: 265 → 314 test (+49), 620 → 743 assertions (+123)
- Documentazione post-mortem del fix test matrix Redis integrata nel changelog e nel progress log
- **Lesson**: `usleep()` può essere interrotto da SIGALRM (EINTR); usare busy-wait loop per timing tests
- **Lesson**: PHPUnit cambia formato output con test skipped; grep patterns devono gestire entrambi i formati
- **Lesson**: `@requires extension redis` su metodi individuali è il modo corretto per skip senza ext-redis
- **Lesson**: `extends \Redis` è eager (parent deve esistere a definizione classe), ma `: \Redis` return type è lazy

---

## Milestone 4: Alerting System ✅ 10/10

**Obiettivo**: Multi-channel alerting on check status changes with anti-SSRF protection

### Tasks

- [x] **M4.1** - HttpClientInterface + HttpClient (anti-SSRF: scheme/port/IP validation, risoluzione DNS, no redirects)
- [x] **M4.2** - AlertChannelInterface + EmailChannel (`wp_mail()`, configurable recipients)
- [x] **M4.3** - AlertManagerInterface + AlertManager (state change detection, cooldown, dispatch, alert log)
- [x] **M4.4** - WebhookChannel (generic JSON POST, optional HMAC `X-OpsHealth-Signature`)
- [x] **M4.5** - SlackChannel (Block Kit payload, color-coded attachments)
- [x] **M4.6** - TelegramChannel (Bot API `sendMessage`, HTML parse mode)
- [x] **M4.7** - WhatsAppChannel (generic webhook, phone number, Bearer auth)
- [x] **M4.8** - Scheduler modification (optional `AlertManagerInterface`, backward compatible)
- [x] **M4.9** - AlertSettings admin page + Menu submenu (PRG, nonce, capability check)
- [x] **M4.10** - Bootstrap wiring + AlertingFlowTest (end-to-end integration)

### Dettagli Implementazione

**HttpClient (Anti-SSRF):**
- `is_safe_url()`: scheme http/https only, ports 80/443 only, block private IPs (10/172.16/192.168/127/169.254/0.0.0.0)
- `post()`: `wp_remote_post()` with `redirection => 0`, timeout 5s, DNS pinning via `CURLOPT_RESOLVE`
- Private `validate_and_resolve()`: validates URL + resolves DNS → returns `[host, ip, port]`
- Protected `create_dns_pin()`: returns closure for `http_api_curl` action (anti-TOCTOU)
- Protected `resolve_host()` wraps `gethostbyname()` for testability (partial mock pattern)
- Deps: `RedactionInterface`

**AlertManager (State Change Detection):**
- Alert triggers: ok→warning, ok→critical, warning→critical, critical→warning, *→ok (recovery)
- No alert: same status (ok→ok, critical→critical, etc.)
- First run (empty previous): alert only if status ≠ ok
- Per-check cooldown via transient `ops_health_alert_cooldown_{check_id}` (default 60 min / `DEFAULT_COOLDOWN = 3600`)
- Recovery alerts (*→ok) bypass cooldown
- Alert log capped at `MAX_LOG_ENTRIES = 50` entries
- Storage keys: `alert_settings`, `alert_log`
- Deps: `StorageInterface`, `RedactionInterface`

**Notification Channels:**
| Channel | Transport | Auth | Key Features |
|---------|-----------|------|--------------|
| Email | `wp_mail()` | N/A | Comma-separated recipients |
| Webhook | HTTP POST JSON | HMAC SHA-256 | `X-OpsHealth-Signature` header |
| Slack | HTTP POST JSON | Webhook URL | Block Kit, color attachments |
| Telegram | Bot API | Bot token | HTML parse mode |
| WhatsApp | HTTP POST JSON | Bearer token | Phone number field |

**AlertSettings Admin Page:**
- PRG pattern with nonce `ops_health_alert_settings`, capability `manage_options`
- Per-channel enable/disable + credentials
- Global cooldown minutes setting (absint)
- `protected do_exit()` for testability

**Scheduler Integration:**
- Optional `AlertManagerInterface $alert_manager = null` (backward compatible)
- Flow: read previous results → `run_all()` → `alert_manager->process($current, $previous)`
- "Run Now" button does NOT trigger alerts (alerts only on cron)

**Settings Structure** (stored as `alert_settings` via Storage):
```php
[
  'email'    => ['enabled' => bool, 'recipients' => string],
  'webhook'  => ['enabled' => bool, 'url' => string, 'secret' => string],
  'slack'    => ['enabled' => bool, 'webhook_url' => string],
  'telegram' => ['enabled' => bool, 'bot_token' => string, 'chat_id' => string],
  'whatsapp' => ['enabled' => bool, 'webhook_url' => string, 'phone_number' => string, 'api_token' => string],
  'cooldown_minutes' => int,
]
```

**Statistiche Finali**:
- 27 file sorgente in `src/` (+11 da M3)
- 47 file di test (27 unit + 20 integration) (+21 da M3)
- 698 test totali (438 unit + 260 integration), 1548 assertions
- Unit coverage: **100%** (1281/1281 lines, 136/136 methods, 20/20 classes)
- Integration coverage: **100%** (1280/1280 lines, 136/136 methods, 20/20 classes)
- PHPCS 100% clean (0 errori, 0 warning)
- PHPStan level 6: 0 errori
- +196 nuovi test per M4 + 10 test aggiunti in code review 1 + 120 test integrazione aggiunti in coverage push + 8 test aggiunti in code review 2 + 5 test aggiunti in coverage 100% push

**Deliverable**: Alerting multi-canale con anti-SSRF, DNS pinning, cooldown intelligente, UI admin configurazione ✅

---

## Progress Log (M4)

### 2026-02-10 (Code Review 2 Post-M4)

**Code Review 2 Post-M4** - 5 rilievi risolti con TDD rigoroso (RED → GREEN → REFACTOR):

**High:**
- HttpClient: DNS pinning via `CURLOPT_RESOLVE` + `http_api_curl` action (previene TOCTOU/DNS rebinding tra validazione e richiesta HTTP). Estratti `validate_and_resolve()` private + `create_dns_pin()` protected.

**Medium:**
- Scheduler: `catch (\Exception)` → `catch (\Throwable)` in `run_checks()` (cattura TypeError, ValueError, non solo Exception)
- AlertManager: try/catch `\Throwable` per-canale in `dispatch_to_channels()` (isolamento canali: un canale che fallisce non blocca gli altri)

**Low:**
- AlertSettings: password fields rendono `value=""` + `placeholder="********"` (credenziali mai presenti nel sorgente HTML/DOM). `build_settings_from_post()` preserva secret esistenti quando il campo POST è vuoto.
- EmailChannel: guard `empty($recipients)` dopo `parse_recipients()` in `send()` (previene chiamata `wp_mail()` senza destinatari)

Totale: 693 test (437 unit + 256 integration), 1529 assertions, PHPCS + PHPStan clean
- **Lesson**: `CURLOPT_RESOLVE` via `http_api_curl` WordPress hook è la soluzione standard per DNS pinning senza rompere HTTPS/SNI
- **Lesson**: password fields devono usare `value=""` (mai il valore reale) + preserve-on-empty per non perdere il secret al salvataggio

### 2026-02-10 (Coverage 100% Push)

**Coverage push: 99.92%/98.98% → 100%/100%** — 5 nuovi test per chiudere le ultime linee non coperte:

**Gap identificati tramite Clover XML parsing:**
- AlertSettings `build_settings_from_post()` linea 327: `$existing = []` dentro `if (!is_array($existing))` — storage corrotto
- AlertSettings linee 336/340/344: preserve existing secrets quando campo POST vuoto — non esercitato in integrazione
- EmailChannel `send()` linee 86-89: guard `empty($recipients)` — tutti i recipients invalidi dopo `parse_recipients()`
- AlertManager `dispatch_to_channels()` linee 281-285: `catch (\Throwable)` — nessun canale lanciava eccezione in integrazione

**Test aggiunti:**
- Unit AlertSettingsTest: `test_process_actions_handles_corrupted_existing_settings` (storage returns non-array)
- Integration AlertSettingsTest: `test_process_actions_preserves_existing_secrets_when_empty` + `test_process_actions_handles_corrupted_existing_settings`
- Integration EmailChannelTest: `test_send_returns_error_when_all_recipients_invalid`
- Integration AlertingFlowTest: `test_dispatch_catches_throwable_from_channel` (ThrowingChannel + per-channel isolation)

Totale: 698 test (438 unit + 260 integration), 1548 assertions
Coverage: **100%** sia per unit che per integration, indipendentemente (1281/1281 + 1280/1280 lines)
PHPCS + PHPStan clean

### 2026-02-10 (Code Review Post-M4)

**Code Review Post-M4** - 13 rilievi risolti (4 Critical, 3 High, 3 Medium, 3 Low):

**Critical:**
- HttpClient.post(): restituisce `success: false` per risposte HTTP non-2xx
- AlertManager: cooldown impostato PRIMA del dispatch (previene alert spam in caso di errori dei canali)
- TelegramChannel: `htmlspecialchars()` su tutte le variabili interpolate in messaggi HTML
- Scheduler: `try/catch` attorno a `alert_manager->process()` (cron resilience)

**High:**
- SlackChannel: `escape_mrkdwn()` per `*`, `_`, `~`, `` ` ``, `&`, `<`, `>`
- EmailChannel: `is_email()` in `parse_recipients()` filtra email non valide
- AlertSettings: `type="password"` + `autocomplete="off"` per token/secret

**Medium:**
- AlertingFlowTest: rafforzato con EmailChannel + asserzioni meaningful
- HttpClient: IPv6 rejection documentato in `is_private_ip()` PHPDoc
- AlertManager: costanti `STATUS_OK/WARNING/CRITICAL/UNKNOWN` sostituiscono stringhe hardcoded

**Low:**
- Interface tests: `assertTrue(interface_exists/method_exists)` → Reflection-based assertions
- WebhookChannel: `X-OpsHealth-Signature` header documentato con istruzioni verifica
- WhatsAppChannel: validazione E.164 phone (`is_valid_phone`) in `is_enabled()`

Totale: 556 test (420 unit + 136 integration), 1285 assertions, PHPCS + PHPStan clean

### 2026-02-10 (Integration Test Coverage Push)

**Integration test coverage: 63.87% → 100%** — 120 nuovi test di integrazione:

**7 nuovi file integration test:**
- `HttpClientTest` (~25 test): TestableHttpClient subclass, anti-SSRF validation, `pre_http_request` interception, test con HttpClient reale su IP diretti
- `AlertSettingsTest` (~16 test): TestableAlertSettings subclass, PRG pattern, nonce/capability security, render con settings reali
- `SlackChannelTest` (~12 test): Block Kit payload, color-coded attachments, recovery title, corrupted settings
- `TelegramChannelTest` (~10 test): Bot API URL, HTML parse mode, chat_id, corrupted settings
- `WebhookChannelTest` (~12 test): HMAC signature verification, no-secret header check
- `WhatsAppChannelTest` (~13 test): E.164 phone validation, Bearer auth header
- `EmailChannelTest` (~12 test): `pre_wp_mail` interception, `is_email()` validation, wp_mail failure

**3 file integration test potenziati:**
- `MenuTest` (+4 test): submenu registration, render delegation, null AlertSettings, load hook
- `SchedulerTest` (+1 test): ThrowingAlertManager resilience (try/catch around process())
- `AlertingFlowTest` (+6 test): real home_url/bloginfo, DEFAULT_COOLDOWN, missing status key, corrupted log, disabled channels, error redaction

**Tecniche chiave:**
- TestableHttpClient: override `resolve_host()` per IP controllato senza DNS
- TestableAlertSettings: override `do_exit()` per prevenire exit() nei test
- ThrowingAlertManager: implementa AlertManagerInterface, lancia in process() per test resilienza
- HttpClient reale con IP diretti (127.0.0.1, 172.16.0.1, 192.168.1.1): `gethostbyname()` su IP restituisce l'IP stesso, risolve limitazione di attribuzione della coverage Xdebug su subclass
- `pre_http_request` filter (2nd arg=$args, 3rd=$url) per intercettare `wp_remote_post()`
- `pre_wp_mail` filter: return true=intercetta, return false=simula fallimento
- Admin user context: `self::factory()->user->create(['role'=>'administrator'])` per `$submenu` global

Totale: 685 test (429 unit + 256 integration), 1497 assertions
Coverage: **100%** sia per unit test sia per integration test, considerati separatamente
PHPCS + PHPStan clean

### 2026-02-10 (M4 Implementation)

**M4 - Alerting System** completata in 10 sub-task:
- M4.1-M4.7: HttpClient + 5 channels + AlertManager (TDD, ~170 unit tests)
- M4.8: Scheduler modification with optional AlertManager injection
- M4.9: AlertSettings admin page + Menu submenu (+22 unit, +6 menu tests)
- M4.10: Bootstrap wiring + AlertingFlowTest (10 E2E integration tests)

---

## Milestone 5: New Checks + Dashboard Widget + E2E Testing ✅

**Obiettivo**: DiskCheck, VersionsCheck, DashboardWidget + E2E testing con Playwright

### Tasks

- [x] **M5.1** - DiskCheck con TDD (soglie configurabili, protected wrappers, RedactionInterface)
- [x] **M5.2** - VersionsCheck con TDD (WP/PHP versions, update notifications, graceful fallback)
- [x] **M5.3** - DashboardWidget con TDD (worst-status, capability check, render con escaping)
- [x] **M5.4** - Registrazione DiskCheck + VersionsCheck + DashboardWidget in bootstrap.php + Plugin.php
- [x] **M5.5** - E2E infrastruttura (package.json, .wp-env.json, playwright.config.ts, tsconfig.json)
- [x] **M5.6** - E2E helpers (login.ts, selectors.ts) + bin/e2e-setup.sh
- [x] **M5.7** - File di spec E2E (navigation, health-dashboard, alert-settings, dashboard-widget, security)
- [x] **M5.8** - CI integration (e2e job in ci.yml, .gitignore, .gitattributes)
- [x] **M5.9** - Unit + integration tests per DiskCheck, VersionsCheck, DashboardWidget
- [x] **M5.10** - Tutti i quality gate passano + 138 esecuzioni E2E verdi

### Dettagli Implementazione

**DiskCheck:**
- `WARNING_THRESHOLD = 20`, `CRITICAL_THRESHOLD = 10` (percent free space)
- Protected wrappers: `get_disk_path()`, `get_free_space()`, `get_total_space()`
- Edge cases: functions disabled → `is_enabled()` false; functions return false → warning; total=0 → guard
- `size_format((int) $free)` cast for PHPStan compatibility
- Deps: `RedactionInterface`

**VersionsCheck:**
- `RECOMMENDED_PHP_VERSION = '8.1'`
- Status: core update → critical, plugin/theme update → warning, old PHP → warning
- `filter_real_updates()`: keeps only `response === 'upgrade'`
- `load_update_functions()`: try/catch \Throwable, `@codeCoverageIgnoreStart/End` around require_once
- No constructor dependencies (removed unused RedactionInterface after PHPStan flagged it)

**DashboardWidget:**
- Injects `CheckRunnerInterface`
- `determine_overall_status()`: priority map (critical=3 > warning=2 > ok=1), empty=unknown
- Capability check on both `add_widget()` and `render()`
- Registered via `Plugin::init()` → `register_hooks()` → `wp_dashboard_setup`

**E2E Testing:**
- `@wordpress/env` ^10.0.0 for Docker-based WordPress (WP 6.7, PHP 8.3)
- `@playwright/test` ^1.49.0 with Chromium
- 3 viewports: desktop (1280x720), tablet (768x1024), mobile (375x812)
- CI: 1 worker (wp-env condiviso), 60s timeout, login timeout 30s, job timeout 25 min, health check wait
- Locally: 1 worker, 30s timeout, 1 retry
- `bin/e2e-setup.sh`: creates subscriber_e2e + editor_e2e test users
- 5 file di spec: navigation (6), health-dashboard (14), alert-settings (14), dashboard-widget (6), security (6)

**Test Matrix Locale (`bin/test-matrix.sh`):**
- E2E integrato con lifecycle management (wp-env start/stop, utenti test, prerequisiti npm/docker)
- Flag: `--e2e-only`, `--no-e2e`, `--tests-only`, `--phpcs-only`, `--parallel`
- Dot reporter per progresso real-time, ANSI stripping per parsing risultati
- SKIP (giallo) quando npm/Docker non disponibili
- `composer.json` `process-timeout: 0` per esecuzioni lunghe

**Statistiche Finali**:
- 30 file sorgente in `src/` (+3 da M4)
- 54 file di test PHP (30 unit + 24 integration) (+7 da M4)
- 515 unit test, 1162 assertions
- 292 integration test
- 46 E2E scenari x 3 viewport = 138 esecuzioni di test
- Test matrix locale integrata con E2E (PHPCS + PHPStan + PHP 7.4-8.5 + E2E)
- PHPCS 100% clean, PHPStan level 6: 0 errori

**Deliverable**: Dashboard con 5 check (Database, Error Log, Redis, Disk, Versions) + widget dashboard + E2E testing completo + test matrix locale con E2E ✅

---

## Next Milestones

- **M6**: WordPress.org Readiness (uninstall.php, readme.txt, Plugin Check)
