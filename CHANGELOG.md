# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## Planned — Roadmap

### M7 (v0.7.0) — Extensibility API
- 7 WordPress hooks/filters for third-party check and channel registration
- `ops_health_register_checks` / `ops_health_register_channels` actions
- `ops_health_check_results` / `ops_health_alert_payload` filters
- `ops_health_checks_completed` / `ops_health_alert_sent` actions
- `ops_health_cron_interval` filter for configurable check frequency
- WordPress Site Health integration (`site_status_tests` + `debug_information`)

### M8 (v0.8.0) — REST API + JSON Export + Check History
- REST API: 4 endpoints under `ops-health/v1/` (status, run, export, history)
- `ExportService` with redacted JSON diagnostic export
- Check history storage (rolling 24-entry window)
- "Export JSON" button in admin UI

### M9 (v0.9.0) — WP-CLI Integration
- `wp ops-health status|run|export|list-checks` subcommands
- Monitoring-compatible exit codes (0=ok, 1=warning, 2=critical)
- `--format=json|table|csv` output, `--quiet` mode, `--output=<file>`

---

## 0.6.1 - 2026-02-15

### Fixed
- **WebhookChannel HMAC signature** - Body is now serialized only once and the HMAC signature is computed on the pre-serialized string; previously the body was serialized twice (once for the signature, once by HttpClient) producing a signature that was unverifiable on the consumer side
- **HttpClientInterface `post()`** - Accepts `array|string` body (PHPDoc `@param array|string`, no PHP type hint for 7.4 compatibility); allows channels to pass pre-serialized bodies

### Added
- **Uninstaller multisite** - `uninstall()` dispatches to `uninstall_network()` or `uninstall_single()` based on `is_multisite()`
  - `uninstall_network()` iterates all blogs via `$wpdb->get_col()` + `switch_to_blog()`/`restore_current_blog()`
  - Full per-blog cleanup: options, cron, fixed transients, cooldown transients
- **uninstall.php multisite fallback** - Added `elseif(is_multisite())` with prefixed variables for multisite handling without autoloader
- **Multisite integration tests** - 3 new tests in `UninstallerTest`:
  - `test_uninstall_on_multisite_cleans_all_sites` — creates 3 blogs, sets plugin data on all, verifies complete cleanup
  - `test_uninstall_on_multisite_cleans_cooldown_transients` — verifies cooldown transient cleanup on all blogs
  - `test_uninstall_on_multisite_preserves_non_plugin_data` — verifies that non-plugin options are preserved
- **`WP_TESTS_MULTISITE` support** - `tests/bootstrap.php` supports env var to enable multisite mode
- **Composer scripts multisite**:
  - `test:integration:multisite` — runs integration tests in multisite mode
  - `test:coverage:multisite` — multisite coverage with clover XML
  - `test:coverage` updated — runs unit + integration single-site + integration multisite

### Changed
- **build-zip.sh** - Removed `|| true` on `composer install --no-dev`, added explicit check with `exit 1` on failure
- **readme.txt** - Clarified "46 scenarios; 3 viewports locally, desktop-only in CI"

### Tests
- +7 unit tests: WebhookChannel string body, HttpClient string/array body, Uninstaller multisite (mock single-site, network dispatch, single-site dispatch, per-blog cleanup)
- +3 multisite integration tests: Uninstaller multi-blog cleanup, cooldown transient, non-plugin data preservation
- +1 integration test: updated UninstallerTest with new assertions

### Development Notes
- 574 unit tests (Brain\Monkey), 1336 assertions — **100% classes, methods, lines**
- 322 integration tests (WP Test Suite), 655 assertions (single-site) / 684 assertions (multisite) — **100% combined**
- 55 PHP test files (31 unit + 24 integration)
- 31 source files in `src/`, 2 CSS files in `assets/css/`
- PHPCS 100% clean, PHPStan level 6: 0 errors
- Coverage: Uninstaller 81.25% (single-site only) → **100% combined** (single-site + multisite)
- Version bump: 0.6.0 → 0.6.1

## 0.6.0 - 2026-02-12

### Added
- **M6 — WordPress.org Readiness**
- **uninstall.php** - Uninstallation handler with `Uninstaller` class
  - Deletes all plugin options (`ops_health_activated_at`, `ops_health_version`, `ops_health_latest_results`, `ops_health_alert_settings`, `ops_health_alert_log`)
  - Deletes the cron hook `ops_health_run_checks`
  - Deletes fixed transients (`ops_health_cron_check`, `ops_health_admin_notice`, `ops_health_alert_notice`)
  - Deletes dynamic cooldown transients via `$wpdb` LIKE query (future-proof)
  - Constructor injection for `$wpdb` (NO global access in the class)
  - `WP_UNINSTALL_PLUGIN` guard in the wrapper file
- **readme.txt** - Metadata file in WordPress.org standard format
  - Description, Installation, FAQ, Screenshots (placeholder), Changelog, Upgrade Notices
  - `Stable tag: 0.6.0`, `Tested up to: 6.9`, `Requires PHP: 7.4`
- **ABSPATH guards** - Direct access protection on all source files + config
  - `if ( ! defined( 'ABSPATH' ) ) { exit; }` after `namespace`, before code
  - `@codeCoverageIgnore` on the unreachable exit to maintain 100% coverage
  - `tests/bootstrap.php` defines ABSPATH for unit tests (Brain\Monkey)
- **HealthScreen UI** - Admin page with colored cards, summary banner, dedicated CSS
  - `assets/css/health-screen.css` — dedicated admin page styles (card grid, summary banner, status colors)
  - `HealthScreen::register_hooks()` + `enqueue_styles()` with `get_current_screen()` guard
  - `HealthScreen::SCREEN_ID` constant for WordPress screen ID
  - `HealthScreen::determine_overall_status()` — worst-status logic (priority map: critical > warning > ok)
  - Summary banner `.ops-health-summary` with colored left border for global status
  - Card grid `.ops-health-checks` with CSS Grid 2 columns, responsive to 1 column on mobile
  - Check card `.ops-health-check` with white background, colored left border for status
  - Native WordPress admin palette: ok `#00a32a`, warning `#dba617`, critical `#d63638`, unknown `#787c82`

### Changed
- **bin/build-zip.sh** - Includes `uninstall.php` and `readme.txt` in the distribution ZIP
- **Plugin.php** - Calls `HealthScreen::register_hooks()` in `init()` (conditional CSS enqueue)
- **HealthScreen** - Removed 3 inline styles from action buttons, migrated to dedicated CSS

### Fixed
- **DashboardWidget CSS** - Fixed CSS specificity for colored dots (`.ops-health-widget-status-ok .ops-health-widget-dot` etc.); previously all dots were gray

### Tests
- +13 unit tests Uninstaller (pattern enforcement, options cleanup, cron, fixed transients, cooldown $wpdb, prepare, table, idempotency)
- +12 integration tests Uninstaller (real options/cron/transient cleanup, preserves non-plugin data, activate→uninstall cycle, custom cooldown)
- +12 unit tests HealthScreen (register_hooks, enqueue_styles guard, SCREEN_ID, determine_overall_status, render HTML)
- +6 integration tests HealthScreen (enqueue_styles real WP, register_hooks, render summary banner, determine_overall_status Reflection)
- +1 integration test DashboardWidget (test isolation fix: wp_dequeue_style cleanup)
- Updated 2 unit tests PluginTest for HealthScreen::register_hooks() expectations
- +1 integration test PluginTest (admin_enqueue_scripts hook)
- `tests/bootstrap.php` defines ABSPATH for direct access guard compatibility

### Development Notes
- 567 unit tests (Brain\Monkey), 1287 assertions — **100% classes, methods, lines**
- 318 integration tests (WP Test Suite), 654 assertions — **100% classes, methods, lines**
- 55 PHP test files (31 unit + 24 integration)
- 31 source files in `src/`
- 2 CSS files: `dashboard-widget.css`, `health-screen.css`
- PHPCS 100% clean, PHPStan level 6: 0 errors
- WordPress.org readiness: uninstall.php, readme.txt, ABSPATH guards
- HealthScreen UI: card grid, summary banner, dedicated CSS with native WordPress palette

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
  - Output with escaping: `esc_html()`, `esc_attr()`, `esc_url()`
- **E2E Testing** - Playwright + wp-env (Docker-based WordPress environment)
  - 46 test scenarios x 3 viewports (desktop 1280px, tablet 768px, mobile 375px) = 138 test executions
  - 5 spec files: navigation, health-dashboard, alert-settings, dashboard-widget, security
  - Centralized selectors (`tests/e2e/helpers/selectors.ts`) for maintainability
  - Login helpers for admin, subscriber, editor roles (`tests/e2e/helpers/login.ts`)
  - `bin/e2e-setup.sh` creates test users via wp-env WP-CLI
  - Job CI: Node 20, Chromium, wp-env, artifact upload on failure
- **package.json** - `@playwright/test`, `@wordpress/env`, npm scripts for env:start/stop, test:e2e
- **playwright.config.ts** - 3 Chromium projects locally, desktop-only in CI (46 tests vs 138), 60s timeout in CI
- **.wp-env.json** - WP 6.7, PHP 8.3, plugin mapping, WP_DEBUG enabled
- **tsconfig.json** - Minimal TypeScript config for Playwright

### Changed
- **RedisCheck** - `catch (\Exception)` → `catch (\Throwable)` in connection and auth catch blocks (consistency with smoke test and cleanup blocks)
- **Redaction** - URL password regex fix for edge cases
- **bin/test-matrix.sh** - Fixed `--phpcs-only` binary detection check
- **CI** - Health check uses explicit `exit 0`/`exit 1` pattern for reliability
- **config/bootstrap.php** - Registers DiskCheck + VersionsCheck in CheckRunner, adds DashboardWidget share block
- **Plugin.php** - Calls `DashboardWidget::register_hooks()` in `init()`
- **bin/test-matrix.sh** - E2E integration with full lifecycle management (wp-env start/stop, test user creation, prerequisite checks npm/docker)
  - New flags: `--e2e-only`, `--no-e2e`; dot reporter for real-time progress; ANSI stripping for result parsing
  - SKIP (yellow) status for E2E when prerequisites missing
- **composer.json** - Added `"process-timeout": 0` to prevent timeout on long-running matrix+E2E runs
- **playwright.config.ts** - CI: desktop-only (46 tests, ~8 min), all 3 viewports locally (138 tests); `line` + `github` reporter in CI
- **login.ts** - Login timeout increased from 15s to 30s for all three helpers (Docker in CI is slower)
- **.github/workflows/ci.yml** - Added `e2e` job (Node 20, Chromium, wp-env, Playwright) with `timeout-minutes: 15`, health check wait
- **.gitignore** - Added `/playwright-report/`, `/test-results/`, `/tests/e2e/.auth/`
- **.gitattributes** - Added export-ignore for `package.json`, `package-lock.json`, `playwright.config.ts`, `.wp-env.json`, `tsconfig.json`

### Fixed
- **DashboardWidgetTest integration** - `require_once ABSPATH . 'wp-admin/includes/dashboard.php'` before `add_widget()` (function not loaded by default in WP test suite)
- **DashboardWidgetTest integration** - `$storage->save()` → `$storage->set()` (correct Storage method name)
- **DashboardWidgetTest integration** - Storage key `'check_results'` → `'latest_results'` (matches CheckRunner key)
- **DashboardWidgetTest integration** - XSS assertion: `assertStringNotContainsString('<img')` + `assertStringContainsString('&lt;img')` (esc_html preserves attribute text like "onerror" as harmless)
- **DashboardWidgetTest integration** - Replaced 2x `assertTrue(true)` with real `$wp_meta_boxes` global assertions; added `set_current_screen('dashboard')` for proper WP test context
- **VersionsCheck** - PHPCS alignment fix on `$updates_available` variable

### Tests
- +27 unit tests DiskCheck (pattern enforcement, interface, thresholds, edge cases, redaction)
- +32 unit tests VersionsCheck (pattern enforcement, interface, status logic, details, edge cases)
- +18 unit tests DashboardWidget (pattern enforcement, render, capability, overall status)
- +12 integration tests DiskCheck (real filesystem, testable subclasses for error scenarios)
- +11 integration tests VersionsCheck (real WP/PHP versions, testable subclasses for update scenarios)
- +10 integration tests DashboardWidget (admin/subscriber capability, render with/without results)
- Updated PluginTest (unit + integration) for DashboardWidget expectations
- 46 E2E scenarios: navigation (6), health-dashboard (14), alert-settings (14), dashboard-widget (6), security (6)
- +22 unit tests for coverage improvement (post code review):
  - VersionsCheck: 8 Reflection-based tests for protected methods + 5 path coverage tests (update_check_failed+old_php, plugin_updates+old_php, theme_update_message, core_update_without_version, filter_without_response)
  - DashboardWidget: 3 edge case tests (unrecognized status, missing name field, unrecognized status with ok)
- +4 integration tests for VersionsCheck coverage:
  - Theme update, failing update + old PHP, plugin update + old PHP, core update without response property
  - 4 new testable subclasses: ThemeUpdateVersionsCheck, FailingUpdateOldPhpVersionsCheck, PluginUpdateOldPhpVersionsCheck, CoreUpdateNoResponseVersionsCheck
- DashboardWidget: `@codeCoverageIgnore` for unreachable defensive ternary fallback (line 99)

### Development Notes
- 537 unit tests (Brain\Monkey), 1203 assertions — **100% classes, methods, lines**
- 296 integration tests (WP Test Suite), 598 assertions — **100% classes (23/23), methods (163/163), lines (1507/1507)**
- 53 PHP test files (30 unit + 23 integration)
- 30 source files in `src/` (+3 new: DiskCheck, VersionsCheck, DashboardWidget)
- 46 E2E scenarios x 3 viewports = 138 test executions, all green
- Local test matrix (`bin/test-matrix.sh`) now includes E2E with full lifecycle management
- CI E2E: desktop-only (46 tests), 1 worker, 60s timeout, 30s login, 15-min job timeout, health check wait, `line` + `github` reporter
- PHPCS 100% clean, PHPStan level 6: 0 errors
- Strict TDD for every component: RED → GREEN → REFACTOR
- Code review post-M5: coverage push + code review fixes (RedisCheck \Throwable, Redaction regex, test-matrix.sh, CI health check, DashboardWidgetTest real assertions)

## 0.4.1 - 2026-02-10

### Changed
- **HttpClient** - DNS pinning via `CURLOPT_RESOLVE` + `http_api_curl` action (prevents TOCTOU/DNS rebinding between validation and HTTP request)
  - Extracted `validate_and_resolve()` private: validates URL + resolves DNS → returns `[host, ip, port]`
  - Extracted `create_dns_pin()` protected: closure for `http_api_curl` action
  - `post()` uses automatic DNS pinning on every request
- **Scheduler** - `catch (\Exception)` → `catch (\Throwable)` in `run_checks()` (catches TypeError, ValueError, not just Exception)
- **AlertManager** - Try/catch `\Throwable` per-channel in `dispatch_to_channels()` (isolation: a failing channel does not block the others)
- **AlertSettings** - Password fields render `value=""` + `placeholder="********"` (credentials never in the DOM)
  - `build_settings_from_post()` preserves existing secrets when the POST field is empty
- **EmailChannel** - Guard `empty($recipients)` after `parse_recipients()` in `send()` (prevents `wp_mail()` without recipients)

### Security
- **DNS pinning** - `CURLOPT_RESOLVE` via `http_api_curl` action prevents TOCTOU/DNS rebinding attacks between validation and the actual HTTP request
- **Channel isolation** - Try/catch `\Throwable` per-channel in AlertManager (a failing channel does not block the others)
- **Scheduler resilience** - `catch (\Throwable)` around AlertManager (cron survives any type of error)
- **Secret non-prefill** - Password inputs render `value=""` + `placeholder="********"` (credentials never present in the DOM source)

### Tests
- +1 unit test AlertSettings (corrupted existing settings → `!is_array` branch)
- +2 integration tests AlertSettings (preserve existing secrets + corrupted existing settings)
- +1 integration test EmailChannel (send with all invalid recipients → `empty($recipients)` guard)
- +1 integration test AlertingFlowTest (ThrowingChannel → `catch \Throwable` in `dispatch_to_channels()` + per-channel isolation)
- ThrowingChannel: implements `AlertChannelInterface`, throws `\RuntimeException` in `send()` — tests per-channel isolation

### Development Notes
- Code review 2 post-M4: 5 findings resolved (1 High, 2 Medium, 2 Low) — strict TDD RED → GREEN → REFACTOR
- Coverage push: from 99.92%/98.98% to **100%/100%** (unit and integration independently)
- 698 total tests (438 unit + 260 integration), 1548 assertions
- Unit coverage: **100%** lines (1281/1281), **100%** methods (136/136), 20/20 classes
- Integration coverage: **100%** lines (1280/1280), **100%** methods (136/136), 20/20 classes
- PHPCS 100% clean (0 errors, 0 warnings)
- PHPStan level 6: 0 errors

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
- Code review post-M4: 13 findings resolved (4 Critical, 3 High, 3 Medium, 3 Low)
- Integration test coverage push: from 63.87% to 100% (1240/1240 lines, 134/134 methods, 20/20 classes)
  - 7 new integration test files: HttpClientTest, AlertSettingsTest, SlackChannelTest, TelegramChannelTest, WebhookChannelTest, WhatsAppChannelTest, EmailChannelTest
  - 3 enhanced integration test files: MenuTest (+4 tests), SchedulerTest (+1 test), AlertingFlowTest (+6 tests)
  - TestableHttpClient subclass with `resolve_host()` override for anti-SSRF tests without DNS
  - TestableAlertSettings subclass with `do_exit()` override for PRG tests without `exit()`
  - ThrowingAlertManager for Scheduler `try/catch` resilience tests
  - Tests with real HttpClient on direct IPs (127.0.0.1, 172.16.0.1, 192.168.1.1) for complete Xdebug coverage
- 685 total tests (429 unit + 256 integration), 1497 assertions
- Unit coverage: 100% (1241/1241 lines, 134/134 methods, 20/20 classes)
- Integration coverage: 100% (1240/1240 lines, 134/134 methods, 20/20 classes)
- PHPCS 100% clean (0 errors, 0 warnings)
- PHPStan level 6: 0 errors
- 27 source files in `src/`, 47 test files (27 unit + 20 integration)
- Pattern enforcement (NO singleton/static/final) on all 11 new classes
- Strict TDD for every component: RED → GREEN → REFACTOR

## 0.3.1 - 2026-02-09

### Added
- **SECURITY.md**
- **.gitattributes** - `export-ignore` to exclude development files from `git archive` and GitHub Download ZIP
- **codecov.yml** - Codecov configuration with project threshold 95%, patch 90%, separate `unit`/`integration` flags with `carryforward`

### Changed
- **DatabaseCheckTest** - Timing test uses busy-wait loop (resistant to EINTR from SIGALRM) instead of single `usleep()`
- **test-matrix.sh** - Grep fallback for PHPUnit output with skipped tests (`Tests: N, ...` in addition to `OK (N tests, ...)`)
- **CheckRunner** - Exception message internationalized with `__()` (`sprintf(__('Check exception: %s'), ...)`)
- **DatabaseCheck** - Slow query threshold extracted to constant `SLOW_QUERY_THRESHOLD = 0.5`
- **ErrorLogCheck** - Added defensive null coalesce `?? 'other'` in `classify_line()`
- **Activator** - Explanatory comment on the duplicate `cron_schedules` filter (necessary during activation)
- **CI workflow** - Removed deprecated `--no-suggest` flag from Composer; added `permissions: contents: read`; separate Codecov upload for unit and integration with distinct flags (`codecov-action@v5`, `CODECOV_TOKEN`)
- **HealthScreenTest** - `$_POST` cleanup centralized in `tearDown()` for robust test isolation
- **HealthScreenTest/MenuTest/ActivatorTest** - 7x `assertTrue(true)` replaced with `assertInstanceOf()` (real assertions)
- **CheckRunnerTest** - Verifies `__()` called in exception test for i18n
- **build-zip.sh** - `mkdir -p` for custom output directory
- **install-wp-tests.sh** - Variables quoted on the most critical lines (paths with spaces)

### Fixed
- **test-matrix.sh** - Integration test count showed "?" when PHPUnit had skipped tests (output format different from `OK (N tests, ...)`)
- **DatabaseCheckTest flaky** - `usleep()` interrupted by SIGALRM (PHPUnit php-invoker) caused intermittently failing timing tests; replaced with busy-wait loop resistant to EINTR

### Development Notes
- Code review post-M3: 4 source fixes, 4 improved tests, 1 new file, 2 CI/script fixes
- Coverage push: +36 tests (+12 unit, +24 integration), 2 new integration test files (ContainerTest, PluginTest)
- 350 total tests (227 unit + 123 integration), 810 assertions
- Coverage: unit 99.50% lines, integration 100% lines (12/12 classes, 73/73 methods, 603/603 lines)
- PHPCS 100% clean, PHPStan level 6: 0 errors

## 0.3.0 - 2026-02-09

### Added
- **PHPStan** - Static analysis level 6 with `szepeviktor/phpstan-wordpress`
  - Configuration `phpstan.neon` (level 6, `missingType.iterableValue` ignored)
  - Script `composer analyse` for local execution
  - Dedicated job in GitHub Actions CI
  - Integration in `bin/test-matrix.sh` (run alongside PHPCS)
- **RedisCheck** - Redis health check with graceful degradation (M3)
  - PHP Redis extension detection (`extension_loaded`)
  - Connection test with WordPress constants (`WP_REDIS_HOST`, `WP_REDIS_PORT`)
  - Optional authentication (`WP_REDIS_PASSWORD`)
  - Database selection (`WP_REDIS_DATABASE`)
  - Smoke test SET/GET/DEL with response time measurement
  - Slow response threshold (>100ms = warning)
  - All failures are `warning` (Redis is optional, never `critical`)
  - Host and errors redacted via `RedactionInterface`
- 25 unit tests + 6 integration tests for RedisCheck
- RedisCheck registration in `config/bootstrap.php`
- **HealthScreenTest** - +7 tests: `process_actions()` early returns (3), `run_now` action, `clear_cache` action, notice transient display; helper `create_testable_screen()` and `mock_process_functions()`
- **DatabaseCheckTest** - +2 tests: warning on slow query (>0.5s), `Unknown error` fallback with empty `last_error`
- **MenuTest** - +1 test: skip `load-hook` when `add_menu_page` returns false; updated existing test with `add_action` assertion for `load-{$page_hook}`
- **SchedulerTest** - Updated 3 `register_hooks` tests with `get_transient`/`set_transient` mocks (replaced `is_admin`)
- **ActivatorTest** - Updated 3 tests with `MINUTE_IN_SECONDS` definition and `__()` mock
- **RedisCheckTest** - Updated `test_returns_ok_when_smoke_test_passes` with `Mockery::on()` pattern matcher for dynamic key
- **Improved test coverage** - 49 new tests (215 unit + 99 integration), 743 assertions

### Changed
- **Activator** - Uses `MINUTE_IN_SECONDS` and `__()` i18n for cron interval (aligned with Scheduler)
- **ErrorLogCheck::classify_line()** - Optimized from 6 sequential regex to single regex with alternation + lookup map
- **HealthScreen** - Extracted `exit` to protected method `do_exit()` for testability with Mockery
- **RedisCheck** - Removed `unset($e)` anti-pattern in catch blocks, replaced with `phpcs:ignore`
- **.gitignore** - Removed `composer.lock` (must be committed for reproducible builds)
- **Scheduler** - Self-healing uses transient throttle (every hour) instead of `is_admin()` guard; works on frontend as well
- **RedisCheck** - Smoke test uses unique key per run (`uniqid()`) to avoid race conditions between cron and manual run
- **cleanup_and_close()** - Accepts `$smoke_key` as parameter for precise cleanup
- **ErrorLogCheck::resolve_log_path()** - `WP_DEBUG_LOG` assigned to local variable with `@phpstan-ignore` for PHPStan compatibility (WordPress stubs type it as `bool`, but WordPress also accepts strings)
- **.phpcs.xml.dist** - Added exclude for `.phpstan-cache`
- **RedisCheckTest integration** - `@requires extension redis` on individual tests (not file-level `return;`); `extension_loaded('redis')` guard on `require_once FakeRedisHelpers.php`

### Fixed
- **Integration HealthScreenTest** - Fixed option key from `ops_health_results` to `ops_health_latest_results` (aligned with Storage prefix `ops_health_` + CheckRunner key `latest_results`)
- **RedisCheckTest fatal without ext-redis** - `extends \Redis` in helper classes caused fatal error on PHP without ext-redis; resolved with `extension_loaded('redis')` guard and `@requires extension redis`

### Development Notes
- 314 total tests (215 unit + 99 integration), 743 assertions
- PHPCS 100% clean (0 errors, 0 warnings)
- PHPStan level 6: 0 errors
- 16 source files, 29 test files (16 unit + 12 integration)
- Code review post-M3: 5 source fixes + 9 new tests + 6 updated tests
- Code review 2: 3 source fixes + 4 updated tests

## 0.2.1 - 2026-02-09

### Added
- **Admin Action Buttons** - "Run Now" and "Clear Cache" buttons on the Ops Health Dashboard page
  - "Run Now": runs all checks immediately via `CheckRunnerInterface::run_all()`
  - "Clear Cache": clears cached results via `CheckRunnerInterface::clear_results()`
  - Nonce protection (`ops_health_admin_action`) and capability check (`manage_options`)
  - PRG pattern (Post-Redirect-Get) via `load-{$page_hook}` hook to avoid "headers already sent"
  - Confirmation notice with self-deleting transient
- **CheckRunnerInterface::clear_results()** - New method to clear results from storage
- Integration tests for HealthScreen (render output, no-checks message, capability check, action buttons)
- Integration tests for Menu::render_page() (delegates to HealthScreen)
- Unit test for Scheduler::add_custom_cron_interval() (interval addition + no-overwrite)
- Unit test for critical vs warning sample priority in ErrorLogCheck
- Unit tests for Run Now/Clear Cache buttons and nonce fields in HealthScreen

### Fixed
- **ErrorLogCheck::validate_log_file()** - Distinguishes "not configured" (warning) from "configured but file does not exist yet" (ok); previously incorrectly showed warning when `WP_DEBUG_LOG=true` but `debug.log` had not yet been created
- **ErrorLogCheck::collect_samples()** - Critical samples now have priority over warning; previously `array_slice(..., -max)` discarded critical samples when there were many warnings
- **ErrorLogCheck::resolve_log_path()** - Handles `WP_DEBUG_LOG === true` (WordPress writes to `wp-content/debug.log`)
- **ErrorLogCheck::read_tail()** - Checks return value of `flock()`; explicit `flock(LOCK_UN)` before `fclose()`
- **DatabaseCheck::get_name()** - Wrapped with `__()` for i18n (was the only check without translation)
- **install-wp-tests.sh** - `grep -o` returned all WordPress versions from the API instead of just the latest; `svn export` received multiple arguments and failed in CI. Added `head -1` to take only the first (latest). Removed dead `grep` line with no effect.

### Changed
- **HealthScreen::process_actions()** - Method is now public, invoked from `load-{$page_hook}` hook (no longer inside `render()`)
- **Menu::add_menu()** - Registers `load-{$page_hook}` hook to process actions before output
- **build-zip.sh** - Conditional copy of `languages/` and `assets/` (does not fail if missing)
- **install-wp-tests.sh** - WordPress API endpoint uses HTTPS instead of HTTP (anti-MITM hardening)

### Removed
- `tests/bootstrap-integration.php` - Orphan file not referenced in phpunit.xml.dist

### Development Notes
- 225 total tests (178 unit + 47 integration), 497 assertions
- PHPCS 100% clean (0 errors, 0 warnings)
- ActivatorTest integration uses `OPS_HEALTH_DASHBOARD_VERSION` constant instead of hardcoded string

## 0.2.0 - 2026-02-09

### Added
- **RedactionInterface** - Contract for the sensitive data redaction service
  - `redact( string $text ): string` - Redacts a single text
  - `redact_lines( array $lines ): array` - Redacts an array of lines
- **CheckRunnerInterface** - Contract to decouple HealthScreen and Scheduler from concrete CheckRunner
  - `add_check()`, `run_all()`, `get_latest_results()`
- **Redaction** - Sensitive data sanitization service with 11 patterns
  - DB credentials (DB_PASSWORD, DB_USER, DB_NAME, DB_HOST) -> `[REDACTED]`
  - WordPress salts (AUTH_KEY, SECURE_AUTH_KEY, etc.) -> `[REDACTED]`
  - API key, secret, token, bearer -> `[REDACTED]`
  - Password in URL and generic fields -> `[REDACTED]`
  - Email addresses -> `[EMAIL_REDACTED]`
  - IPv4 and IPv6 addresses -> `[IP_REDACTED]`
  - ABSPATH and WP_CONTENT_DIR paths -> placeholder
  - User home directories -> `/home/[USER_REDACTED]`
  - Constructor injection of paths (ABSPATH, WP_CONTENT_DIR) for testability
  - Chain order: WP_CONTENT_DIR before ABSPATH (more specific)
- **ErrorLogCheck** - Secure PHP error log summary check
  - Log path resolution: WP_DEBUG_LOG (string) -> ini_get('error_log')
  - File validation: existence, readability, anti-symlink
  - Efficient tail reading: max 512KB, max 100 lines with `flock(LOCK_SH)`
  - Aggregation by severity: fatal, parse, warning, notice, deprecated, strict, other
  - Redacted samples: max 5 critical/warning lines, sanitized via Redaction
  - Status: critical (fatal/parse), warning (warning/deprecated/strict), ok (notice/other only)
  - No raw log file path exposure
  - Internationalized messages with `__()`
- **73 new tests** (65 unit + 8 integration) for the new classes
- Pattern enforcement tests on RedactionInterface, CheckRunnerInterface, Redaction, ErrorLogCheck

### Changed
- **CheckRunner** - Receives `RedactionInterface` in constructor, redacts exception messages; includes `name` in results
- **DatabaseCheck** - Receives `RedactionInterface` in constructor, redacts `$wpdb->last_error`
- **Scheduler** - Uses `CheckRunnerInterface` (not concrete class); constants `HOOK_NAME`/`INTERVAL`; self-healing only in admin context (`is_admin()` guard)
- **HealthScreen** - Uses `CheckRunnerInterface`; shows `result['name']` with `ucfirst()` fallback
- **Activator** - Uses constants `Scheduler::HOOK_NAME` and `Scheduler::INTERVAL`
- **Container** - Added circular dependency detection with `$resolving` array
- **Storage** - `update_option()` with `autoload=false` for large data
- **Redaction** - IPv4 regex with octet validation (0-255); restrictive URL password regex (no whitespace)
- **bootstrap.php** - Uses `container->instance()` for `$wpdb`; CheckRunnerInterface binding; RedactionInterface injected in CheckRunner and DatabaseCheck

### Security
- Redaction service prevents exposure of credentials, tokens, PII in logs
- ErrorLogCheck does not expose raw filesystem paths in results
- Anti-symlink protection: symlinked log files are rejected
- Read limit of 512KB to prevent excessive memory consumption
- `flock(LOCK_SH)` on log reading for concurrent access safety
- RedactionInterface injected in CheckRunner for exception message redaction
- RedactionInterface injected in DatabaseCheck for `$wpdb` error redaction
- Storage `autoload=false` prevents loading large data on every request
- Container detects circular dependencies (prevents infinite loops)

### Development Notes
- **M2 Completed**: Secure Error Log Summary + Code Review (15/15 issues resolved)
- 210 total tests (169 unit + 41 integration), 472 assertions
- PHPCS 100% clean (0 errors, 0 warnings)
- Strict TDD: RED -> GREEN -> REFACTOR for every component

## 0.1.0 - 2026-02-08

### Added
- **StorageInterface** and **CheckInterface** - Contracts for DI and testability
- **Storage** - WordPress Options API wrapper with `ops_health_` prefix
  - `has()` method with sentinel object pattern (distinguishes `false` from missing key)
- **CheckRunner** - Check orchestrator with result saving
  - Try/catch for `\Throwable` on every check (error resilience)
  - Type safety on `get_latest_results()` (always returns array)
- **DatabaseCheck** - Database connectivity check with `SELECT 1`
  - Constructor injection of `$wpdb` (NO global)
  - Query duration measurement in milliseconds
  - No sensitive information exposure (no db_host, db_name)
  - Internationalized messages with `__()`
- **Scheduler** - WP-Cron scheduling every 15 minutes
  - Custom interval `every_15_minutes`
  - Duplicate scheduling prevention
- **Menu** - Admin page registration `Ops Health Dashboard`
- **HealthScreen** - Admin page rendering with check results
  - Capability check `manage_options` with `wp_die()`
  - Defensive validation of result keys (status, message)
  - Informational message when no checks have been run
- **104 unit tests** (Brain\Monkey) + **33 integration tests** (WP Test Suite)
- Pattern enforcement tests on every class (NO final, NO static methods/properties)

### Changed
- **Activator** - Handles cron scheduling/unscheduling in activate/deactivate
  - Correct hook name: `ops_health_run_checks`
  - Removed `flush_rewrite_rules()` (not needed)
- **Plugin** - `init()` only registers hooks, does not schedule directly
- **bootstrap.php** - Injects global `$wpdb` into DatabaseCheck
- **composer.json** - `test` script runs unit and integration sequentially
- **Scheduler** - `register_hooks()` includes cron self-healing

### Fixed
- **Scheduler self-healing** - `register_hooks()` now calls `schedule()` to re-create the cron event if missing (DB migration, cron cleanup, option corruption). Idempotent thanks to the `is_scheduled()` guard.
- **test-matrix.sh `--parallel`** - Subshell results are now propagated to the parent process via temporary files. Previously the summary table was empty and the exit code was always 0 in parallel mode.

### Security
- Constructor injection of `$wpdb` to avoid global access in tests
- No database host/name exposure in check results
- Capability check `manage_options` on admin page
- Output escaped with `esc_html()`, `esc_attr()`

### Development Notes
- **M1 Completed**: Core Checks + Storage + Cron
- 137 total tests, 275 assertions, PHPCS 100% clean
- Code review: 21/22 issues resolved (uninstall.php planned for M6)

## 0.0.0 - 2026-02-08

### Added
- Initial plugin scaffolding
- Core dependency injection container (NO singleton pattern)
- Main plugin orchestrator with constructor injection
- Activation/deactivation handler
- Complete test suite with TDD approach
- GitHub Actions CI workflow (PHPCS + PHPUnit matrix)
- WordPress Coding Standards configuration
- PHPUnit configuration with coverage support

### Development Notes
- **M0 Completed**: Setup & Infrastructure
- All core classes follow the NO singleton, NO static, NO final pattern
- TDD workflow applied: RED → GREEN → REFACTOR
- Coverage target: PHP 8.3
