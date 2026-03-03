# Development Plan - Ops Health Dashboard

**Current Milestone**: M7 - Extensibility API (planned)
**Status**: M0-M6 completed, M7-M9 planned

---

## Milestone 0: Setup & Infrastructure ✅ 8/8

**Goal**: Complete scaffolding with green CI

### Tasks

- [x] **M0.1** - Complete directory structure
- [x] **M0.2** - Setup composer.json with dependencies
- [x] **M0.3** - PHPCS configuration (WPCS)
- [x] **M0.4** - Setup PHPUnit (config + bootstrap)
- [x] **M0.5** - GitHub Actions workflows
- [x] **M0.6** - Bootstrap files (main plugin + config)
- [x] **M0.7** - Core classes (Container, Plugin, Activator) - TDD
- [x] **M0.8** - Script bin/install-wp-tests.sh

**Pattern Enforcement**:
- ✅ Container uses `share()` for shared instances, NOT `singleton()`
- ✅ Plugin receives Container via constructor, NO `get_instance()`
- ✅ Bootstrap function creates and configures, NO static factories
- ✅ No final classes, no final methods

**Deliverable**: Green CI with PHPCS + PHPUnit matrix + coverage 8.3

---

## Milestone 1: Core Checks + Storage + Cron ✅ 10/10

**Goal**: Base checks with working dashboard

### Tasks

- [x] **M1.1** - StorageInterface + CheckInterface (DI contracts)
- [x] **M1.2** - Storage service (Options API wrapper with `ops_health_` prefix)
- [x] **M1.3** - CheckRunner orchestrator (check execution + result saving)
- [x] **M1.4** - DatabaseCheck with constructor injection `$wpdb` (TDD)
- [x] **M1.5** - Scheduler service (WP-Cron every 15 minutes)
- [x] **M1.6** - Admin Menu registration
- [x] **M1.7** - HealthScreen rendering with capability check
- [x] **M1.8** - bootstrap.php with complete DI wiring
- [x] **M1.9** - Complete unit tests (104 tests, Brain\Monkey)
- [x] **M1.10** - Complete integration tests (33 tests, WP Test Suite)

### Code Review - Issues Resolved (17/18)

- ✅ Activator: correct hook name `ops_health_run_checks`, removed `flush_rewrite_rules()`
- ✅ Activator: handles cron schedule/unschedule (not Plugin::init)
- ✅ DatabaseCheck: `$wpdb` via constructor injection (NO global)
- ✅ DatabaseCheck: no db_host/db_name exposure in details
- ✅ DatabaseCheck: i18n messages with `__()`
- ✅ CheckRunner: try/catch for `\Throwable` on each check
- ✅ CheckRunner: `get_latest_results()` type safety (always returns array)
- ✅ Storage: `has()` with sentinel object pattern
- ✅ HealthScreen: defensive result key validation (isset)
- ✅ HealthScreen: "no checks" message when results are empty
- ✅ Plugin: `init()` only registers hooks, does not schedule
- ✅ bootstrap.php: injects global `$wpdb` into DatabaseCheck
- ✅ composer.json: test script runs suites sequentially
- ✅ CI: coverage with separate files per suite
- ✅ Test: removed all `assertTrue(true)` placeholders
- ✅ Test: added tests for static properties
- ✅ Test: added tests for exceptions and edge cases
- ⏳ uninstall.php → planned for M6

**Final Statistics**:
- 11 source files in `src/`
- 18 test files (11 unit + 7 integration)
- 137 total tests, 275 assertions
- PHPCS 100% clean (0 errors, 0 warnings)

**Deliverable**: the dashboard shows the Database check with WP-Cron auto-refresh ✅

---

## Milestone 2: Secure Error Log Summary ✅ 6/6 + Code Review 15/15

**Goal**: Error log check with automatic sensitive data redaction

### Tasks

- [x] **M2.1** - RedactionInterface (DI contract for redaction)
- [x] **M2.2** - Redaction service (11 patterns: credentials, tokens, PII, paths)
- [x] **M2.3** - ErrorLogCheck with TDD (tail log, aggregation, redacted samples)
- [x] **M2.4** - DI wiring in bootstrap.php (RedactionInterface + ErrorLogCheck)
- [x] **M2.5** - Complete unit tests (56 new tests, Brain\Monkey + Mockery partial mock)
- [x] **M2.6** - Complete integration tests (8 new tests, WP Test Suite + temp files)

### Implementation Details

**Redaction Service** - 11 redaction patterns in ordered chain:
1. Path WP_CONTENT_DIR -> `[WP_CONTENT]` (str_replace, most specific first)
2. Path ABSPATH -> `[ABSPATH]/` (str_replace)
3. DB credentials (DB_PASSWORD, DB_USER, DB_NAME, DB_HOST) -> `[REDACTED]`
4. WordPress salts (AUTH_KEY, SECURE_AUTH_KEY, etc.) -> `[REDACTED]`
5. API key, secret, token -> `[REDACTED]`
6. Bearer token -> `[REDACTED]`
7. Password in URL and generic fields -> `[REDACTED]`
8. Email -> `[EMAIL_REDACTED]`
9. IPv4 -> `[IP_REDACTED]` (with octet validation 0-255)
10. IPv6 (min 5 groups to avoid false positives on timestamps) -> `[IP_REDACTED]`
11. Home directory `/home/user` -> `/home/[USER_REDACTED]`

**ErrorLogCheck** - Secure error log summary:
- Path resolution: `WP_DEBUG_LOG` (string) -> `ini_get('error_log')`
- Validation: existence, readability, anti-symlink
- Efficient tail: max 512KB, max 100 lines, `flock(LOCK_SH)` for concurrent access
- Classification: fatal, parse, warning, notice, deprecated, strict, other
- Status: critical (fatal/parse > 0), warning (warning/deprecated/strict > 0), ok
- Max 5 critical/warning samples, redacted before inclusion
- Protected methods for testability with Mockery partial mock

### Code Review - Issues Resolved (15/15)

- ✅ **CheckRunnerInterface**: new contract to decouple HealthScreen and Scheduler from concrete CheckRunner
- ✅ **RedactionInterface in CheckRunner**: exception messages redacted before inclusion in results
- ✅ **RedactionInterface in DatabaseCheck**: `$wpdb->last_error` redacted before inclusion in results
- ✅ **Container circular dependencies**: `$resolving` array detects infinite loops during resolution
- ✅ **Storage autoload=false**: `update_option()` with third parameter `false` for large data
- ✅ **Scheduler self-healing admin-only**: `is_admin()` guard in `register_hooks()` prevents self-healing on frontend
- ✅ **Scheduler constants**: `HOOK_NAME` and `INTERVAL` as class constants
- ✅ **Activator uses Scheduler constants**: `Scheduler::HOOK_NAME` and `Scheduler::INTERVAL` instead of hardcoded strings
- ✅ **HealthScreen CheckRunnerInterface**: type-hint on interface, shows `result['name']` with `ucfirst()` fallback
- ✅ **ErrorLogCheck flock(LOCK_SH)**: shared lock during log reading for concurrent safety
- ✅ **IPv4 regex octet validation**: regex verifies each octet is 0-255 (not just \d{1,3})
- ✅ **URL password restrictive regex**: no whitespace in pattern to avoid false positives
- ✅ **bootstrap.php container->instance()**: `$wpdb` registered with `instance()` instead of closure
- ✅ **bootstrap.php CheckRunnerInterface binding**: CheckRunner registered under `CheckRunnerInterface::class`
- ✅ **CheckRunner includes 'name' in results**: each result includes the `name` key from the check

**Final Statistics**:
- 15 source files in `src/`
- 24 test files (15 unit + 9 integration)
- 210 total tests (169 unit + 41 integration), 472 assertions
- PHPCS 100% clean (0 errors, 0 warnings)

**Deliverable**: Dashboard shows Database + Error Log check with automatic redaction ✅

---

## Progress Log

### 2026-02-09 (Code Review 2)

**PHPStan Integration** - Static analysis level 6:
- Installed `phpstan/phpstan` + `szepeviktor/phpstan-wordpress`
- Created `phpstan.neon` (level 6, `missingType.iterableValue` ignored)
- Added `composer analyse` script
- Added PHPStan job in GitHub Actions CI (`.github/workflows/ci.yml`)
- Integrated in `bin/test-matrix.sh` (executed with PHPCS)
- Fix: `ErrorLogCheck::resolve_log_path()` WP_DEBUG_LOG assignment to variable with `@phpstan-ignore` for stub compatibility
- Fix: `.phpcs.xml.dist` exclude `.phpstan-cache`
- PHPStan level 6: 0 errors

**Code Review 2** - 3 source fixes + 4 tests updated:
- Scheduler: self-healing with transient throttle (every hour) instead of `is_admin()` guard
- RedisCheck: unique smoke test key per run with `uniqid()` (avoids race condition cron vs manual)
- RedisCheck `cleanup_and_close()`: accepts `$smoke_key` parameter
- Integration HealthScreenTest: corrected option key `ops_health_results` → `ops_health_latest_results`
- SchedulerTest: mocks `get_transient`/`set_transient` instead of `is_admin`
- RedisCheckTest: `Mockery::on()` pattern matcher for dynamic key
- Integration SchedulerTest: `delete_transient` instead of `set_current_screen`
- Total: 265 tests (212 unit + 53 integration), 620 assertions, PHPCS clean
- **Lesson**: `is_admin()` limits self-healing to admin only; use transient throttle for frontend coverage
- **Lesson**: shared Redis keys between concurrent executions cause race conditions; use `uniqid()` for uniqueness

### 2026-02-09 (Code Review Post-M3)

**Code Review Post-M3** - 5 source fixes + 9 new tests + 6 tests updated:
- Activator.php: `900` → `15 * MINUTE_IN_SECONDS`, added `__()` i18n
- ErrorLogCheck.php: `classify_line()` from 6 regex to single regex + map (reduces from 600 to 100 evaluations for 100 lines)
- HealthScreen.php: extracted `exit` → protected `do_exit()` for testability
- RedisCheck.php: `unset($e)` → `phpcs:ignore` (2 catch blocks)
- .gitignore: removed `composer.lock`
- +7 HealthScreen tests (complete process_actions), +2 DatabaseCheck, +1 Menu
- Updated 3 SchedulerTest + 3 ActivatorTest for new dependencies
- Total: 265 tests (212 unit + 53 integration), 617 assertions, PHPCS clean

### 2026-02-09 (M3)

**M3 - Redis Check** completed:
- Implemented `RedisCheck` with graceful degradation (Redis optional, all failures are `warning`)
- PHP Redis extension detection, connection, authentication, database selection, smoke test SET/GET/DEL
- Response time measurement (>100ms = warning)
- Host and errors redacted via `RedactionInterface`
- Protected methods (`is_extension_loaded`, `create_redis_instance`, `get_redis_config`) for testability
- `TestableRedisCheck` subclass for integration test (pattern from `TestableErrorLogCheck`)
- Registered in `config/bootstrap.php` via `$runner->add_check()`
- 25 unit tests + 6 integration tests
- Total: 256 tests (203 unit + 53 integration), 572 assertions, PHPCS clean

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

**M2 Code Review** (15/15 issues resolved):
- CheckRunnerInterface for decoupling (HealthScreen, Scheduler use interface)
- RedactionInterface injected in CheckRunner (redacts exceptions) and DatabaseCheck (redacts $wpdb errors)
- Container: circular dependency detection with `$resolving` array
- Storage: `autoload=false` in `update_option()`
- Scheduler: self-healing only in admin context (`is_admin()` guard), HOOK_NAME/INTERVAL constants
- ErrorLogCheck: `flock(LOCK_SH)` for safe concurrent access
- Redaction: IPv4 regex with octet validation (0-255), restrictive URL password regex
- bootstrap.php: `container->instance($wpdb)`, CheckRunnerInterface binding
- Activator: uses `Scheduler::HOOK_NAME` and `Scheduler::INTERVAL`
- CheckRunner: includes `name` key in results
- 210 tests (169 unit + 41 integration), 472 assertions, PHPCS clean
- Lesson learned: WP test suite `is_admin()` returns false; use `set_current_screen('dashboard')` to enable admin context in integration tests

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
- Fixed `--parallel` in test-matrix.sh: subshell results are now propagated via temporary files
- Added Scheduler self-healing: `register_hooks()` calls `schedule()` to re-create missing cron event
- 137 tests (104 unit + 33 integration), 275 assertions

**Post-M1 Improvements**:
- Added autoloader guard in main plugin file (admin notice instead of fatal error if vendor/ missing)
- Created `bin/build-zip.sh` for production ZIP generation (`zip` CLI with PHP ZipArchive fallback)
- Fixed PHPCS doc comment SpacingAfter in 4 source files (Storage, Scheduler, DatabaseCheck, Menu)
- Added Version and PHPCS badges to README.md, updated license badge to GPL v3
- Added `dist/` to .gitignore

**Completed M0**: Setup & Infrastructure ✅
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

- ~~**CSS 'unknown' status**: resolved in v0.6.0 with `health-screen.css` (class `.ops-health-check-unknown`)~~
- ~~**Action buttons inline style**: resolved in v0.6.0 — migrated to dedicated CSS `health-screen.css`~~
- ~~**uninstall.php**: resolved in v0.6.0~~
- No known issues at this time.

## Milestone 3: Redis Check ✅ 1/1

**Goal**: Optional Redis check with graceful degradation

### Tasks

- [x] **M3.1** - RedisCheck with TDD (extension detection, connection, auth, smoke test, response time)

### Code Review Post-M3

- ✅ **Activator MINUTE_IN_SECONDS**: uses `15 * MINUTE_IN_SECONDS` and `__()` i18n for cron interval (aligned with Scheduler)
- ✅ **ErrorLogCheck classify_line()**: optimized from 6 sequential regex to single regex with alternation + lookup map
- ✅ **HealthScreen do_exit()**: extracted `exit` into protected method for testability with Mockery partial mock
- ✅ **RedisCheck unset($e)**: removed anti-pattern, replaced with `phpcs:ignore`
- ✅ **.gitignore composer.lock**: removed entry (must be committed for reproducible builds)
- ✅ **HealthScreenTest +7 tests**: complete coverage of `process_actions()` (early returns, run_now, clear_cache, notice)
- ✅ **DatabaseCheckTest +2 tests**: warning on slow query, fallback `Unknown error`
- ✅ **MenuTest +1 test**: skip `load-hook` when `add_menu_page` returns false
- ✅ **SchedulerTest updated**: `add_filter` expectation in 3 `register_hooks` tests
- ✅ **ActivatorTest updated**: `MINUTE_IN_SECONDS` definition and `__()` mock

**Final Statistics**:
- 16 source files in `src/`
- 26 test files (16 unit + 10 integration)
- 314 total tests (215 unit + 99 integration), 744 assertions
- PHPCS 100% clean (0 errors, 0 warnings)
- PHPStan level 6: 0 errors

**Deliverable**: Dashboard shows Database + Error Log + Redis check with graceful degradation ✅

---

## Progress Log

### 2026-02-09 (Code Review 3)

**Code Review 3** - 4 source fixes + 4 tests improved + CI/config:
- CheckRunner: exception message internationalized with `__()` for i18n
- DatabaseCheck: 0.5s threshold extracted into `SLOW_QUERY_THRESHOLD` constant
- ErrorLogCheck: defensive null coalesce `?? 'other'` in `classify_line()`
- Activator: explanatory comment on duplicate `cron_schedules` filter
- HealthScreenTest: `$_POST` cleanup in `tearDown()`, 4× `assertTrue(true)` → `assertInstanceOf()`
- MenuTest: 2× `assertTrue(true)` → `assertInstanceOf()`
- ActivatorTest: 1× `assertTrue(true)` → `assertInstanceOf()`
- CheckRunnerTest: verifies `__()` in exception test
- CI: removed deprecated `--no-suggest`, added `permissions: contents: read`
- New `.gitattributes` with `export-ignore` for development files
- build-zip.sh: `mkdir -p` for custom output directory
- install-wp-tests.sh: quoted variables in critical paths
- Total: 314 tests (215 unit + 99 integration), 744 assertions, PHPCS + PHPStan clean

### 2026-02-09 (Test Matrix Stabilization)

**Test Matrix Fix** - 3 issues resolved for test stability on PHP 7.4-8.5:

1. **RedisCheckTest fatal without ext-redis**: `extends \Redis` in FakeRedis* helper classes caused `Class 'Redis' not found` on PHP without ext-redis. Fix: `extension_loaded('redis')` guard on `require_once` + `@requires extension redis` on test methods that use FakeRedis subclasses. The file-level `return;` did not work because PHPUnit bypasses the guard during test discovery.

2. **DatabaseCheckTest flaky (EINTR)**: single `usleep()` was interrupted by SIGALRM (PHPUnit php-invoker) causing duration < 0.5s despite usleep of 1.5-3s. Fix: busy-wait loop with `usleep(50000)` in increments that resumes after each interruption.

3. **test-matrix.sh count "?"**: the grep `'OK \(\K[0-9]+'` did not match PHPUnit output when there are skipped tests (`Tests: N, Assertions: M, Skipped: S.` instead of `OK (N tests, M assertions)`). Fix: grep fallback with `Tests: \K[0-9]+`.

**Improved test coverage**: 265 → 314 tests (+49), 620 → 743 assertions (+123)
- Post-mortem documentation of Redis test matrix fix integrated into changelog and progress log
- **Lesson**: `usleep()` can be interrupted by SIGALRM (EINTR); use busy-wait loop for timing tests
- **Lesson**: PHPUnit changes output format with skipped tests; grep patterns must handle both formats
- **Lesson**: `@requires extension redis` on individual methods is the correct way to skip without ext-redis
- **Lesson**: `extends \Redis` is eager (parent must exist at class definition), but `: \Redis` return type is lazy

---

## Milestone 4: Alerting System ✅ 10/10

**Goal**: Multi-channel alerting on check status changes with anti-SSRF protection

### Tasks

- [x] **M4.1** - HttpClientInterface + HttpClient (anti-SSRF: scheme/port/IP validation, DNS resolution, no redirects)
- [x] **M4.2** - AlertChannelInterface + EmailChannel (`wp_mail()`, configurable recipients)
- [x] **M4.3** - AlertManagerInterface + AlertManager (state change detection, cooldown, dispatch, alert log)
- [x] **M4.4** - WebhookChannel (generic JSON POST, optional HMAC `X-OpsHealth-Signature`)
- [x] **M4.5** - SlackChannel (Block Kit payload, color-coded attachments)
- [x] **M4.6** - TelegramChannel (Bot API `sendMessage`, HTML parse mode)
- [x] **M4.7** - WhatsAppChannel (generic webhook, phone number, Bearer auth)
- [x] **M4.8** - Scheduler modification (optional `AlertManagerInterface`, backward compatible)
- [x] **M4.9** - AlertSettings admin page + Menu submenu (PRG, nonce, capability check)
- [x] **M4.10** - Bootstrap wiring + AlertingFlowTest (end-to-end integration)

### Implementation Details

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

**Final Statistics**:
- 27 source files in `src/` (+11 from M3)
- 47 test files (27 unit + 20 integration) (+21 from M3)
- 698 total tests (438 unit + 260 integration), 1548 assertions
- Unit coverage: **100%** (1281/1281 lines, 136/136 methods, 20/20 classes)
- Integration coverage: **100%** (1280/1280 lines, 136/136 methods, 20/20 classes)
- PHPCS 100% clean (0 errors, 0 warnings)
- PHPStan level 6: 0 errors
- +196 new tests for M4 + 10 tests added in code review 1 + 120 integration tests added in coverage push + 8 tests added in code review 2 + 5 tests added in 100% coverage push

**Deliverable**: Multi-channel alerting with anti-SSRF, DNS pinning, smart cooldown, admin configuration UI ✅

---

## Progress Log (M4)

### 2026-02-10 (Code Review 2 Post-M4)

**Code Review 2 Post-M4** - 5 findings resolved with strict TDD (RED → GREEN → REFACTOR):

**High:**
- HttpClient: DNS pinning via `CURLOPT_RESOLVE` + `http_api_curl` action (prevents TOCTOU/DNS rebinding between validation and HTTP request). Extracted `validate_and_resolve()` private + `create_dns_pin()` protected.

**Medium:**
- Scheduler: `catch (\Exception)` → `catch (\Throwable)` in `run_checks()` (catches TypeError, ValueError, not just Exception)
- AlertManager: try/catch `\Throwable` per-channel in `dispatch_to_channels()` (channel isolation: one failing channel does not block the others)

**Low:**
- AlertSettings: password fields render `value=""` + `placeholder="********"` (credentials never present in HTML/DOM source). `build_settings_from_post()` preserves existing secrets when POST field is empty.
- EmailChannel: `empty($recipients)` guard after `parse_recipients()` in `send()` (prevents `wp_mail()` call without recipients)

Total: 693 tests (437 unit + 256 integration), 1529 assertions, PHPCS + PHPStan clean
- **Lesson**: `CURLOPT_RESOLVE` via `http_api_curl` WordPress hook is the standard solution for DNS pinning without breaking HTTPS/SNI
- **Lesson**: password fields must use `value=""` (never the real value) + preserve-on-empty to avoid losing the secret on save

### 2026-02-10 (Coverage 100% Push)

**Coverage push: 99.92%/98.98% → 100%/100%** — 5 new tests to close the last uncovered lines:

**Gaps identified via Clover XML parsing:**
- AlertSettings `build_settings_from_post()` line 327: `$existing = []` inside `if (!is_array($existing))` — corrupted storage
- AlertSettings lines 336/340/344: preserve existing secrets when POST field is empty — not exercised in integration
- EmailChannel `send()` lines 86-89: `empty($recipients)` guard — all recipients invalid after `parse_recipients()`
- AlertManager `dispatch_to_channels()` lines 281-285: `catch (\Throwable)` — no channel was throwing exception in integration

**Tests added:**
- Unit AlertSettingsTest: `test_process_actions_handles_corrupted_existing_settings` (storage returns non-array)
- Integration AlertSettingsTest: `test_process_actions_preserves_existing_secrets_when_empty` + `test_process_actions_handles_corrupted_existing_settings`
- Integration EmailChannelTest: `test_send_returns_error_when_all_recipients_invalid`
- Integration AlertingFlowTest: `test_dispatch_catches_throwable_from_channel` (ThrowingChannel + per-channel isolation)

Total: 698 tests (438 unit + 260 integration), 1548 assertions
Coverage: **100%** for both unit and integration, independently (1281/1281 + 1280/1280 lines)
PHPCS + PHPStan clean

### 2026-02-10 (Code Review Post-M4)

**Code Review Post-M4** - 13 findings resolved (4 Critical, 3 High, 3 Medium, 3 Low):

**Critical:**
- HttpClient.post(): returns `success: false` for non-2xx HTTP responses
- AlertManager: cooldown set BEFORE dispatch (prevents alert spam in case of channel errors)
- TelegramChannel: `htmlspecialchars()` on all interpolated variables in HTML messages
- Scheduler: `try/catch` around `alert_manager->process()` (cron resilience)

**High:**
- SlackChannel: `escape_mrkdwn()` for `*`, `_`, `~`, `` ` ``, `&`, `<`, `>`
- EmailChannel: `is_email()` in `parse_recipients()` filters invalid emails
- AlertSettings: `type="password"` + `autocomplete="off"` for tokens/secrets

**Medium:**
- AlertingFlowTest: strengthened with EmailChannel + meaningful assertions
- HttpClient: IPv6 rejection documented in `is_private_ip()` PHPDoc
- AlertManager: `STATUS_OK/WARNING/CRITICAL/UNKNOWN` constants replace hardcoded strings

**Low:**
- Interface tests: `assertTrue(interface_exists/method_exists)` → Reflection-based assertions
- WebhookChannel: `X-OpsHealth-Signature` header documented with verification instructions
- WhatsAppChannel: E.164 phone validation (`is_valid_phone`) in `is_enabled()`

Total: 556 tests (420 unit + 136 integration), 1285 assertions, PHPCS + PHPStan clean

### 2026-02-10 (Integration Test Coverage Push)

**Integration test coverage: 63.87% → 100%** — 120 new integration tests:

**7 new integration test files:**
- `HttpClientTest` (~25 tests): TestableHttpClient subclass, anti-SSRF validation, `pre_http_request` interception, tests with real HttpClient on direct IPs
- `AlertSettingsTest` (~16 tests): TestableAlertSettings subclass, PRG pattern, nonce/capability security, render with real settings
- `SlackChannelTest` (~12 tests): Block Kit payload, color-coded attachments, recovery title, corrupted settings
- `TelegramChannelTest` (~10 tests): Bot API URL, HTML parse mode, chat_id, corrupted settings
- `WebhookChannelTest` (~12 tests): HMAC signature verification, no-secret header check
- `WhatsAppChannelTest` (~13 tests): E.164 phone validation, Bearer auth header
- `EmailChannelTest` (~12 tests): `pre_wp_mail` interception, `is_email()` validation, wp_mail failure

**3 enhanced integration test files:**
- `MenuTest` (+4 tests): submenu registration, render delegation, null AlertSettings, load hook
- `SchedulerTest` (+1 test): ThrowingAlertManager resilience (try/catch around process())
- `AlertingFlowTest` (+6 tests): real home_url/bloginfo, DEFAULT_COOLDOWN, missing status key, corrupted log, disabled channels, error redaction

**Key techniques:**
- TestableHttpClient: overrides `resolve_host()` for controlled IP without DNS
- TestableAlertSettings: overrides `do_exit()` to prevent exit() in tests
- ThrowingAlertManager: implements AlertManagerInterface, throws in process() for resilience testing
- Real HttpClient with direct IPs (127.0.0.1, 172.16.0.1, 192.168.1.1): `gethostbyname()` on an IP returns the IP itself, resolves Xdebug coverage attribution limitation on subclass
- `pre_http_request` filter (2nd arg=$args, 3rd=$url) to intercept `wp_remote_post()`
- `pre_wp_mail` filter: return true=intercept, return false=simulate failure
- Admin user context: `self::factory()->user->create(['role'=>'administrator'])` for `$submenu` global

Total: 685 tests (429 unit + 256 integration), 1497 assertions
Coverage: **100%** for both unit tests and integration tests, considered separately
PHPCS + PHPStan clean

### 2026-02-10 (M4 Implementation)

**M4 - Alerting System** completed in 10 sub-tasks:
- M4.1-M4.7: HttpClient + 5 channels + AlertManager (TDD, ~170 unit tests)
- M4.8: Scheduler modification with optional AlertManager injection
- M4.9: AlertSettings admin page + Menu submenu (+22 unit, +6 menu tests)
- M4.10: Bootstrap wiring + AlertingFlowTest (10 E2E integration tests)

---

## Milestone 5: New Checks + Dashboard Widget + E2E Testing ✅

**Goal**: DiskCheck, VersionsCheck, DashboardWidget + E2E testing with Playwright

### Tasks

- [x] **M5.1** - DiskCheck with TDD (configurable thresholds, protected wrappers, RedactionInterface)
- [x] **M5.2** - VersionsCheck with TDD (WP/PHP versions, update notifications, graceful fallback)
- [x] **M5.3** - DashboardWidget with TDD (worst-status, capability check, render with escaping)
- [x] **M5.4** - DiskCheck + VersionsCheck + DashboardWidget registration in bootstrap.php + Plugin.php
- [x] **M5.5** - E2E infrastructure (package.json, .wp-env.json, playwright.config.ts, tsconfig.json)
- [x] **M5.6** - E2E helpers (login.ts, selectors.ts) + bin/e2e-setup.sh
- [x] **M5.7** - E2E spec files (navigation, health-dashboard, alert-settings, dashboard-widget, security)
- [x] **M5.8** - CI integration (e2e job in ci.yml, .gitignore, .gitattributes)
- [x] **M5.9** - Unit + integration tests for DiskCheck, VersionsCheck, DashboardWidget
- [x] **M5.10** - All quality gates pass + 138 green E2E executions

### Implementation Details

**DiskCheck:**
- `WARNING_THRESHOLD = 20`, `CRITICAL_THRESHOLD = 10` (percent free space)
- Protected wrappers: `get_disk_path()`, `get_free_space()`, `get_total_space()`
- Edge cases: functions disabled → `is_enabled()` false; functions return false → warning; total=0 → guard
- `size_format((int) $free)` cast for PHPStan compatibility
- Deps: `RedactionInterface`

**VersionsCheck:**
- `RECOMMENDED_PHP_VERSION = '8.3'`
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
- CI: desktop-only (46 tests, ~8 min), 1 worker, 60s timeout, login timeout 30s, job timeout 15 min, health check wait, `line` + `github` reporter
- Locally: 1 worker, 30s timeout, 1 retry
- `bin/e2e-setup.sh`: creates subscriber_e2e + editor_e2e test users
- 5 spec files: navigation (6), health-dashboard (14), alert-settings (14), dashboard-widget (6), security (6)

**Local Test Matrix (`bin/test-matrix.sh`):**
- E2E integrated with lifecycle management (wp-env start/stop, test users, npm/docker prerequisites)
- Flags: `--e2e-only`, `--no-e2e`, `--tests-only`, `--phpcs-only`, `--parallel`
- Dot reporter for real-time progress, ANSI stripping for result parsing
- SKIP (yellow) when npm/Docker are not available
- `composer.json` `process-timeout: 0` for long-running executions

**Final Statistics** (post code review + coverage push):
- 30 source files in `src/` (+3 from M4)
- 53 PHP test files (30 unit + 23 integration) (+6 from M4)
- 537 unit tests, 1203 assertions — **100% classes, methods, lines**
- 296 integration tests, 598 assertions — **100% classes, methods, lines**
- 46 E2E scenarios x 3 viewports = 138 test executions
- Local test matrix integrated with E2E (PHPCS + PHPStan + PHP 7.4-8.5 + E2E)
- PHPCS 100% clean, PHPStan level 6: 0 errors

**Deliverable**: Dashboard with 5 checks (Database, Error Log, Redis, Disk, Versions) + dashboard widget + complete E2E testing + local test matrix with E2E ✅

---

## M6 — WordPress.org Readiness ✅

**Goal**: Prepare the plugin for WordPress.org submission.

**Deliverable**:
1. `uninstall.php` with `Uninstaller` class for complete data cleanup (options, cron, transients)
2. `readme.txt` in WordPress.org standard format
3. ABSPATH guards on all source files + config
4. `bin/build-zip.sh` updated to include uninstall.php and readme.txt
5. `tests/bootstrap.php` defines ABSPATH for unit test compatibility

**Final Statistics** (post code review + multisite coverage):
- 31 source files in `src/`, 2 CSS files in `assets/css/`
- 55 PHP test files (31 unit + 24 integration)
- 574 unit tests, 1336 assertions — **100% classes, methods, lines**
- 322 integration tests, 655 assertions (single-site) / 684 assertions (multisite) — **100% combined**
- 46 E2E scenarios x 3 viewports = 138 test executions
- PHPCS 100% clean, PHPStan level 6: 0 errors

**Deliverable**: WordPress.org ready plugin with uninstall.php, readme.txt, ABSPATH guards, HealthScreen UI with dedicated CSS ✅

---

## Progress Log (Post-M6)

### 2026-02-15 (Code Review Post-M6 + Multisite Coverage)

**Code Review Post-M6** - 4 improvements implemented from external review:

**Fix:**
- **WebhookChannel HMAC**: body serialized only once, HMAC signature computed on pre-serialized string, passed as string to HttpClient (avoids double serialization)
- **HttpClientInterface `post()`**: accepts `array|string` body (PHPDoc `@param array|string`, no PHP type hint for 7.4 compatibility)
- **Uninstaller multisite**: `uninstall()` dispatches to `uninstall_network()` (iterates all blogs) or `uninstall_single()` based on `is_multisite()`
- **uninstall.php**: added `elseif(is_multisite())` fallback with prefixed variables
- **build-zip.sh**: removed `|| true` on `composer install`, added explicit check with exit 1
- **readme.txt**: clarified "46 scenarios; 3 viewports locally, desktop-only in CI"

**Multisite Coverage Push:**
- `tests/bootstrap.php`: support for `WP_TESTS_MULTISITE` env var → `define('WP_TESTS_MULTISITE', true)`
- 3 new multisite integration tests in `UninstallerTest`: multi-blog cleanup, cooldown transient, non-plugin data preservation
- `composer.json`: 3 new scripts (`test:integration:multisite`, `test:coverage:multisite`, `test:coverage` updated with 3 runs)
- Coverage: Uninstaller 81.25%→96.88% (multisite) / 81.25% (single-site) → **100% combined**

**New tests:**
- +7 unit tests: WebhookChannel string body, HttpClient string/array body, Uninstaller multisite
- +4 integration tests: 3 multisite Uninstaller + 1 updated

Total: 574 unit (1336 assertions) + 322 integration (655/684 assertions), PHPCS + PHPStan clean

**Lesson**: `is_multisite()` branch requires two test runs (single-site + multisite); `WP_TESTS_MULTISITE` env var enables multisite mode in WP Test Suite bootstrap

---

## Milestone 7: Extensibility API ⏳ 0/9

**Version**: 0.7.0
**Goal**: Make the plugin extensible via standard WordPress hooks/filters + integrate with WordPress Site Health.

### Tasks

- [ ] **M7.1** - Hook: `ops_health_register_checks` in `config/bootstrap.php` (third-party check registration via `$runner->add_check()`)
- [ ] **M7.2** - Hook: `ops_health_register_channels` in `config/bootstrap.php` (third-party channel registration via `$manager->add_channel()`)
- [ ] **M7.3** - Filter: `ops_health_check_results` in `CheckRunner::run_all()` (post-processing results before storage)
- [ ] **M7.4** - Action: `ops_health_checks_completed` in `CheckRunner::run_all()` (react to results after storage)
- [ ] **M7.5** - Filter: `ops_health_alert_payload` in `AlertManager::build_payload()` (customize alert messages)
- [ ] **M7.6** - Action: `ops_health_alert_sent` in `AlertManager::dispatch_to_channels()` (audit logging per channel)
- [ ] **M7.7** - Filter: `ops_health_cron_interval` in `Scheduler` (configurable check frequency)
- [ ] **M7.8** - WordPress Site Health integration (`src/Admin/SiteHealthIntegration.php`): `site_status_tests` + `debug_information` filters
- [ ] **M7.9** - Tests + documentation (unit ~45-55, integration ~20-25, pattern enforcement, wiki updates)

### New Files
- `src/Admin/SiteHealthIntegration.php`
- `tests/Unit/Admin/SiteHealthIntegrationTest.php`
- `tests/Integration/Admin/SiteHealthIntegrationTest.php`

### Modified Files
- `config/bootstrap.php` — 2 `do_action` hooks in share closures
- `src/Services/CheckRunner.php` — 1 filter + 1 action
- `src/Services/AlertManager.php` — 1 filter + 1 action
- `src/Services/Scheduler.php` — 1 filter
- `src/Core/Plugin.php` — register SiteHealthIntegration

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

**Deliverable**: Fully extensible plugin with 7 hooks/filters + WordPress Site Health integration

---

## Milestone 8: REST API + JSON Export + Check History ⏳ 0/9

**Version**: 0.8.0
**Goal**: Expose plugin data via REST API, add downloadable JSON export, and store check history.

### Tasks

- [ ] **M8.1** - `ExportServiceInterface` contract (`src/Interfaces/ExportServiceInterface.php`)
- [ ] **M8.2** - `ExportService` implementation with redaction (`src/Services/ExportService.php`)
- [ ] **M8.3** - `RestController` with 4 endpoints (`src/Api/RestController.php`)
- [ ] **M8.4** - Check history in `CheckRunner` (append to `ops_health_results_history`, capped at 24, filterable limit)
- [ ] **M8.5** - Admin UI: "Export JSON" button in `HealthScreen`
- [ ] **M8.6** - Container wiring for ExportService + RestController in `config/bootstrap.php`
- [ ] **M8.7** - `CheckRunnerInterface`: add `get_history(): array`
- [ ] **M8.8** - `Uninstaller`: add `ops_health_results_history` to cleanup
- [ ] **M8.9** - Tests + documentation (unit ~50-60, integration ~25-30, E2E ~5-8, wiki updates)

### REST API Endpoints

| Method | Endpoint | Description | Auth |
|--------|----------|-------------|------|
| GET | `/wp-json/ops-health/v1/status` | Latest check results (cached) | `manage_options` |
| POST | `/wp-json/ops-health/v1/run` | Trigger check run (rate-limited) | `manage_options` |
| GET | `/wp-json/ops-health/v1/export` | Full diagnostic JSON (redacted) | `manage_options` |
| GET | `/wp-json/ops-health/v1/history` | Check history (last 24 runs) | `manage_options` |

### New Files
- `src/Interfaces/ExportServiceInterface.php`
- `src/Services/ExportService.php`
- `src/Api/RestController.php`
- Corresponding unit and integration test files

### Modified Files
- `config/bootstrap.php` — wire ExportService + RestController
- `src/Services/CheckRunner.php` — history tracking + `get_history()`
- `src/Interfaces/CheckRunnerInterface.php` — add `get_history()`
- `src/Admin/HealthScreen.php` — Export JSON button
- `src/Core/Uninstaller.php` — cleanup `ops_health_results_history`
- `src/Core/Plugin.php` — register REST routes via `rest_api_init`

**Deliverable**: REST API with 4 endpoints, JSON diagnostic export, check history with 24-entry rolling window

---

## Milestone 9: WP-CLI Integration ⏳ 0/8

**Version**: 0.9.0
**Goal**: Full WP-CLI interface for headless/DevOps use, with monitoring-compatible exit codes.

### Tasks

- [ ] **M9.1** - `HealthCommand` class (`src/Cli/HealthCommand.php`) with DI, `WP_CLI` guard
- [ ] **M9.2** - Subcommand: `wp ops-health status` (table/json/csv, exit codes 0/1/2)
- [ ] **M9.3** - Subcommand: `wp ops-health run` (fresh check, `--quiet` mode)
- [ ] **M9.4** - Subcommand: `wp ops-health export` (JSON to stdout or `--output=<file>`)
- [ ] **M9.5** - Subcommand: `wp ops-health list-checks` (registered checks with status)
- [ ] **M9.6** - `CheckRunnerInterface`: add `get_checks(): array`
- [ ] **M9.7** - Container wiring for HealthCommand with `WP_CLI` guard
- [ ] **M9.8** - Tests + documentation (unit ~35-40, integration ~15-20, E2E ~8-10, wiki updates)

### WP-CLI Commands

| Command | Description | Exit Codes |
|---------|-------------|------------|
| `wp ops-health status` | Show latest cached results | 0=ok, 1=warning, 2=critical |
| `wp ops-health run` | Trigger fresh check run | 0=ok, 1=warning, 2=critical |
| `wp ops-health export` | JSON diagnostic export | 0=success, 1=error |
| `wp ops-health list-checks` | List registered checks | 0=success |

All commands support `--format=json|table|csv` (WP-CLI standard). `run` supports `--quiet` for monitoring scripts. `export` supports `--output=<file>`.

Exit codes are compatible with Nagios/Icinga/Zabbix monitoring systems.

### New Files
- `src/Cli/HealthCommand.php`
- Corresponding unit and integration test files

### Modified Files
- `config/bootstrap.php` — wire HealthCommand with `WP_CLI` guard
- `src/Interfaces/CheckRunnerInterface.php` — add `get_checks()`
- `src/Services/CheckRunner.php` — implement `get_checks()`
- `src/Core/Plugin.php` — register CLI command

**Deliverable**: Full WP-CLI interface with 4 subcommands, monitoring-compatible exit codes, pipe-friendly JSON export

---

## Milestone Summary

| Milestone | Version | Status | Source Files | Test Files | Tests |
|-----------|---------|--------|--------------|------------|-------|
| M0 | 0.1.0 | ✅ | 7 | 7 | ~40 |
| M1 | 0.1.0 | ✅ | 11 | 18 | 137 |
| M2 | 0.2.0 | ✅ | 15 | 24 | 210 |
| M3 | 0.3.0 | ✅ | 16 | 26 | 314 |
| M4 | 0.4.0 | ✅ | 27 | 47 | 698 |
| M5 | 0.5.0 | ✅ | 30 | 53 | 833+46 E2E |
| M6 | 0.6.2 | ✅ | 31 | 55 | 896+46 E2E |
| M7 | 0.7.0 | ⏳ | +1 | +2 | +65-80 |
| M8 | 0.8.0 | ⏳ | +3 | +4 | +80-98 |
| M9 | 0.9.0 | ⏳ | +1 | +2 | +58-70 |

Post-M9 projection: ~36 source files, ~63 test files, ~1100+ PHP tests + ~60 E2E scenarios
