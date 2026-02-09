# Development Plan - Ops Health Dashboard

**Current Milestone**: M4 - Alerting System
**Status**: M3 completed, M4 planned

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
1. Path WP_CONTENT_DIR -> `[WP_CONTENT]` (str_replace, piu' specifico prima)
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
- Validazione: esistenza, leggibilita', anti-symlink
- Tail efficiente: max 512KB, max 100 righe, `flock(LOCK_SH)` per accesso concorrente
- Classificazione: fatal, parse, warning, notice, deprecated, strict, other
- Status: critical (fatal/parse > 0), warning (warning/deprecated/strict > 0), ok
- Max 5 campioni critici/warning, redatti prima dell'inclusione
- Protected methods per testabilita' con Mockery partial mock

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

**Statistiche Finali**:
- 16 file sorgente in `src/`
- 27 file di test (16 unit + 11 integration)
- 256 test totali (203 unit + 53 integration), 572 assertions
- PHPCS 100% clean (0 errori, 0 warning)

**Deliverable**: Dashboard mostra Database + Error Log + Redis check con graceful degradation ✅

---

## Next Milestones

- **M4**: Alerting System (Email, Webhook, Slack, Telegram, WhatsApp + anti-SSRF)
- **M5**: E2E Testing (Playwright multi-viewport)
- **M6**: WordPress.org Readiness (uninstall.php, readme.txt, Plugin Check)
