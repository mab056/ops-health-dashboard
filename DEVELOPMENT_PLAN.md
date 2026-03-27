# Development Plan - Ops Health Dashboard

**Current Baseline**: v0.6.2 (M6 completed)
**Next Milestone**: M7 - Extensibility API

---

## Milestone 0: Setup & Infrastructure ✅ 8/8

**Goal**: Complete scaffolding with green CI

- [x] **M0.1** - Complete directory structure
- [x] **M0.2** - Set up `composer.json` with dependencies
- [x] **M0.3** - PHPCS configuration (WPCS)
- [x] **M0.4** - Set up PHPUnit (config + bootstrap)
- [x] **M0.5** - GitHub Actions workflows
- [x] **M0.6** - Bootstrap files (main plugin + config)
- [x] **M0.7** - Core classes (Container, Plugin, Activator) - TDD
- [x] **M0.8** - `bin/install-wp-tests.sh` script

**Deliverable**: Green CI with PHPCS, a PHPUnit matrix, and coverage on PHP 8.3

---

## Milestone 1: Core Checks + Storage + Cron ✅ 10/10

**Goal**: Base checks with working dashboard

- [x] **M1.1** - StorageInterface + CheckInterface (DI contracts)
- [x] **M1.2** - Storage service (Options API wrapper with `ops_health_` prefix)
- [x] **M1.3** - CheckRunner orchestrator (check execution + result saving)
- [x] **M1.4** - DatabaseCheck with constructor injection `$wpdb` (TDD)
- [x] **M1.5** - Scheduler service (WP-Cron every 15 minutes)
- [x] **M1.6** - Admin Menu registration
- [x] **M1.7** - HealthScreen rendering with capability check
- [x] **M1.8** - `bootstrap.php` with complete DI wiring
- [x] **M1.9** - Complete unit tests (Brain\Monkey)
- [x] **M1.10** - Complete integration tests (WP Test Suite)

**Deliverable**: Dashboard displays the Database check with WP-Cron auto-refresh

---

## Milestone 2: Secure Error Log Summary ✅ 6/6

**Goal**: Error log check with automatic sensitive data redaction

- [x] **M2.1** - RedactionInterface (DI contract for redaction)
- [x] **M2.2** - Redaction service (11 patterns: credentials, tokens, PII, paths)
- [x] **M2.3** - ErrorLogCheck with TDD (tail log, aggregation, redacted samples)
- [x] **M2.4** - DI wiring in bootstrap.php (RedactionInterface + ErrorLogCheck)
- [x] **M2.5** - Complete unit tests (Brain\Monkey + Mockery partial mock)
- [x] **M2.6** - Complete integration tests (WP Test Suite + temp files)

**Deliverable**: Dashboard displays the Database and Error Log checks with automatic redaction

---

## Milestone 3: Redis Check ✅ 1/1

**Goal**: Optional Redis check with graceful degradation

- [x] **M3.1** - RedisCheck with TDD (extension detection, connection, auth, smoke test, response time)

**Deliverable**: Dashboard displays the Database, Error Log, and Redis checks with graceful degradation

---

## Milestone 4: Alerting System ✅ 10/10

**Goal**: Multi-channel alerting on check status changes with anti-SSRF protection

- [x] **M4.1** - HttpClientInterface + HttpClient (anti-SSRF: scheme/port/IP validation, DNS pinning, no redirects)
- [x] **M4.2** - AlertChannelInterface + EmailChannel (`wp_mail()`, configurable recipients)
- [x] **M4.3** - AlertManagerInterface + AlertManager (state change detection, cooldown, dispatch, alert log)
- [x] **M4.4** - WebhookChannel (generic JSON POST, optional HMAC `X-OpsHealth-Signature`)
- [x] **M4.5** - SlackChannel (Block Kit payload, color-coded attachments)
- [x] **M4.6** - TelegramChannel (Bot API `sendMessage`, HTML parse mode)
- [x] **M4.7** - WhatsAppChannel (generic webhook, phone number, Bearer auth)
- [x] **M4.8** - Scheduler update (optional `AlertManagerInterface`, backward-compatible)
- [x] **M4.9** - AlertSettings admin page + Menu submenu (PRG, nonce, capability check)
- [x] **M4.10** - Bootstrap wiring + AlertingFlowTest (end-to-end integration)

**Deliverable**: Multi-channel alerting with anti-SSRF, DNS pinning, smart cooldown, admin configuration UI

---

## Milestone 5: New Checks + Dashboard Widget + E2E Testing ✅ 10/10

**Goal**: DiskCheck, VersionsCheck, DashboardWidget + E2E testing with Playwright

- [x] **M5.1** - DiskCheck with TDD (configurable thresholds, protected wrappers, RedactionInterface)
- [x] **M5.2** - VersionsCheck with TDD (WP/PHP versions, update notifications, graceful fallback)
- [x] **M5.3** - DashboardWidget with TDD (worst-status, capability check, render with escaping)
- [x] **M5.4** - DiskCheck + VersionsCheck + DashboardWidget registration in bootstrap.php + Plugin.php
- [x] **M5.5** - E2E infrastructure (package.json, .wp-env.json, playwright.config.ts)
- [x] **M5.6** - E2E helpers (login.ts, selectors.ts) + bin/e2e-setup.sh
- [x] **M5.7** - E2E spec files (navigation, health-dashboard, alert-settings, dashboard-widget, security)
- [x] **M5.8** - CI integration (e2e job in ci.yml)
- [x] **M5.9** - Unit + integration tests for DiskCheck, VersionsCheck, DashboardWidget
- [x] **M5.10** - All quality gates pass + E2E green

**Deliverable**: Dashboard with 5 checks + dashboard widget + E2E testing + local test matrix

---

## Milestone 6: WordPress.org Readiness ✅ 5/5

**Goal**: Prepare the plugin for WordPress.org submission

- [x] `uninstall.php` with `Uninstaller` class (options, cron, transients cleanup + multisite)
- [x] `readme.txt` in WordPress.org standard format
- [x] ABSPATH guards on all source files
- [x] HealthScreen UI: card grid, summary banner, dedicated CSS
- [x] WP Consent API: ConsentIntegration + Privacy Policy content

**Deliverable**: WordPress.org-ready plugin with full multisite support and WP Consent API compatibility

---

## Milestone 7: Extensibility API ⏳ 0/9

**Version**: 0.7.0
**Goal**: Make the plugin extensible via standard WordPress hooks and filters, and integrate it with WordPress Site Health.

- [ ] **M7.1** - Hook: `ops_health_register_checks` in `config/bootstrap.php`
- [ ] **M7.2** - Hook: `ops_health_register_channels` in `config/bootstrap.php`
- [ ] **M7.3** - Filter: `ops_health_check_results` in `CheckRunner::run_all()`
- [ ] **M7.4** - Action: `ops_health_checks_completed` in `CheckRunner::run_all()`
- [ ] **M7.5** - Filter: `ops_health_alert_payload` in `AlertManager::build_payload()`
- [ ] **M7.6** - Action: `ops_health_alert_sent` in `AlertManager::dispatch_to_channels()`
- [ ] **M7.7** - Filter: `ops_health_cron_interval` in `Scheduler`
- [ ] **M7.8** - WordPress Site Health integration (`site_status_tests` + `debug_information`)
- [ ] **M7.9** - Tests + documentation

### Hooks Reference

| Hook | Type | Location | Purpose |
|------|------|----------|---------|
| `ops_health_register_checks` | action | `config/bootstrap.php` | Register custom checks |
| `ops_health_register_channels` | action | `config/bootstrap.php` | Register custom alert channels |
| `ops_health_check_results` | filter | `CheckRunner::run_all()` | Modify results before storage |
| `ops_health_checks_completed` | action | `CheckRunner::run_all()` | React after checks complete |
| `ops_health_alert_payload` | filter | `AlertManager::build_payload()` | Customize alert content |
| `ops_health_alert_sent` | action | `AlertManager::dispatch_to_channels()` | Per-channel audit logging |
| `ops_health_cron_interval` | filter | `Scheduler` | Configure check frequency |

**Deliverable**: Fully extensible plugin with seven hooks/filters and WordPress Site Health integration

---

## Milestone 8: REST API + JSON Export + Check History ⏳ 0/9

**Version**: 0.8.0
**Goal**: Expose plugin data via REST API, add downloadable JSON export, and store check history.

- [ ] **M8.1** - `ExportServiceInterface` contract
- [ ] **M8.2** - `ExportService` implementation with redaction
- [ ] **M8.3** - `RestController` with 4 endpoints
- [ ] **M8.4** - Check history in `CheckRunner` (rolling 24-entry window)
- [ ] **M8.5** - Admin UI: "Export JSON" button in `HealthScreen`
- [ ] **M8.6** - Container wiring for ExportService + RestController
- [ ] **M8.7** - `CheckRunnerInterface`: add `get_history(): array`
- [ ] **M8.8** - `Uninstaller`: add `ops_health_results_history` to cleanup
- [ ] **M8.9** - Tests + documentation

### REST API Endpoints

| Method | Endpoint | Description | Auth |
|--------|----------|-------------|------|
| GET | `/wp-json/ops-health/v1/status` | Latest check results (cached) | `manage_options` |
| POST | `/wp-json/ops-health/v1/run` | Trigger check run (rate-limited) | `manage_options` |
| GET | `/wp-json/ops-health/v1/export` | Full diagnostic JSON (redacted) | `manage_options` |
| GET | `/wp-json/ops-health/v1/history` | Check history (last 24 runs) | `manage_options` |

**Deliverable**: REST API with 4 endpoints, JSON diagnostic export, check history

---

## Milestone 9: WP-CLI Integration ⏳ 0/8

**Version**: 0.9.0
**Goal**: Full WP-CLI interface for headless and DevOps use, with monitoring-compatible exit codes.

- [ ] **M9.1** - `HealthCommand` class with DI, `WP_CLI` guard
- [ ] **M9.2** - Subcommand: `wp ops-health status` (table/json/csv, exit codes 0/1/2)
- [ ] **M9.3** - Subcommand: `wp ops-health run` (fresh check, `--quiet` mode)
- [ ] **M9.4** - Subcommand: `wp ops-health export` (JSON to stdout or `--output=<file>`)
- [ ] **M9.5** - Subcommand: `wp ops-health list-checks` (registered checks with status)
- [ ] **M9.6** - `CheckRunnerInterface`: add `get_checks(): array`
- [ ] **M9.7** - Container wiring for HealthCommand with `WP_CLI` guard
- [ ] **M9.8** - Tests + documentation

### WP-CLI Commands

| Command | Description | Exit Codes |
|---------|-------------|------------|
| `wp ops-health status` | Show latest cached results | 0=ok, 1=warning, 2=critical |
| `wp ops-health run` | Trigger fresh check run | 0=ok, 1=warning, 2=critical |
| `wp ops-health export` | JSON diagnostic export | 0=success, 1=error |
| `wp ops-health list-checks` | List registered checks | 0=success |

All commands support `--format=json|table|csv`. Exit codes are compatible with Nagios, Icinga, and Zabbix.

**Deliverable**: Full WP-CLI interface with 4 subcommands, monitoring-compatible exit codes

---

## Milestone Summary

| Milestone | Version | Status | Source Files | Test Files | Tests |
|-----------|---------|--------|--------------|------------|-------|
| M0 | 0.1.0 | ✅ | 7 | 7 | ~40 |
| M1 | 0.1.0 | ✅ | 11 | 18 | 137 |
| M2 | 0.2.0 | ✅ | 15 | 24 | 210 |
| M3 | 0.3.0 | ✅ | 16 | 26 | 314 |
| M4 | 0.4.0 | ✅ | 27 | 47 | 698 |
| M5 | 0.5.0 | ✅ | 30 | 53 | 833 + 46 E2E |
| M6 | 0.6.2 | ✅ | 32 | 56 | 963 + 57 E2E |
| M7 | 0.7.0 | ⏳ | +1 | +2 | +65-80 |
| M8 | 0.8.0 | ⏳ | +3 | +4 | +80-98 |
| M9 | 0.9.0 | ⏳ | +1 | +2 | +58-70 |

Post-M9 projection: ~37 source files, ~64 test files, ~1200+ PHP tests + ~61 E2E scenarios
