# Development Plan - Ops Health Dashboard

**Current Milestone**: M2 - Riepilogo Error Log Sicuro
**Started**: 2026-02-08
**Status**: 🚧 In Progress

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
- [x] **M1.9** - Unit tests completi (103 test, Brain\Monkey)
- [x] **M1.10** - Integration tests completi (32 test, WP Test Suite)

### Code Review - Issue Risolte (21/22)

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
- ✅ Plugin: `init()` registra solo hooks, non schedula
- ✅ bootstrap.php: inietta `$wpdb` globale in DatabaseCheck
- ✅ composer.json: test script esegue suite sequenzialmente
- ✅ CI: coverage con file separati per suite
- ✅ Test: rimossi tutti i `assertTrue(true)` placeholders
- ✅ Test: aggiunti test per static properties
- ✅ Test: aggiunti test per eccezioni e edge case
- ⏳ uninstall.php → pianificato per M6

**Statistiche Finali**:
- 11 file sorgente in `src/`
- 18 file di test (11 unit + 7 integration)
- 135 test totali, 267 assertions
- PHPCS 100% clean (0 errori, 0 warning)

**Deliverable**: Dashboard mostra Database check con auto-refresh WP-Cron ✅

---

## Progress Log

### 2026-02-08

**Completed M1**: Core Checks + Storage + Cron ✅
- Implemented StorageInterface and CheckInterface contracts
- Storage service with Options API, sentinel pattern in `has()`
- CheckRunner with try/catch resilience and type safety
- DatabaseCheck with `$wpdb` constructor injection, no info disclosure
- Scheduler with 15-minute WP-Cron interval
- Admin Menu and HealthScreen with capability check and defensive rendering
- Complete code review: fixed 21/22 issues
- All 135 tests green, PHPCS 100% clean
- Added `bin/test-matrix.sh` for local PHP 7.4-8.5 matrix testing (mirrors CI)

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

- **CSS 'unknown' status**: HealthScreen non ha classe CSS per lo stato 'unknown' (solo ok/warning/critical). Aggiungere quando si implementano gli asset CSS (M2+).

## Next Milestones

- **M2**: Error Log Summary Safe (Redaction service + ErrorLogCheck)
- **M3**: Redis Check (opzionale, graceful degradation)
- **M4**: Alerting System (Email, Webhook, Slack, Telegram, WhatsApp + anti-SSRF)
- **M5**: E2E Testing (Playwright multi-viewport)
- **M6**: WordPress.org Readiness (uninstall.php, readme.txt, Plugin Check)
