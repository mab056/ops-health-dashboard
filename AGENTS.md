# AGENTS.md - Ops Health Dashboard

Operational instructions for AI agents working on this repository.

## Project Goal

Production-grade WordPress plugin for operational monitoring with health checks, admin dashboard, WP-Cron scheduling, and alerting.

## Non-Negotiable Architecture Rules

1. No singleton pattern.
2. No static methods/properties.
3. No `final` classes/methods.
4. Use constructor dependency injection.
5. Use DI container `share()` for shared instances (not global singletons).

## Required Patterns

1. Interface-first design (`*Interface` + concrete implementations).
2. Function-based bootstrap (`config/bootstrap.php`), not static factory bootstrap.
3. Inject WordPress dependencies (for example `$wpdb`) and avoid direct global access in business logic.

## Testing (Mandatory)

1. Always follow TDD: RED -> GREEN -> REFACTOR.
2. Unit tests in `tests/Unit/` with Brain\Monkey for isolated logic.
3. Integration tests in `tests/Integration/` when real WordPress behavior is touched.
4. Add pattern-enforcement tests for `final`/`static` violations.
5. Before closing changes, run:
   - `composer test`
   - `composer phpcs`
   - `composer analyse`

## Security (Mandatory)

1. Sanitize input (`sanitize_text_field`, `sanitize_email`, `esc_url_raw`, `absint`, etc.).
2. Escape output (`esc_html`, `esc_attr`, `esc_url`, `esc_js`).
3. Verify admin capabilities (`current_user_can('manage_options')`).
4. Use and verify nonce in forms/AJAX.
5. Apply anti-SSRF protections for outbound webhook/HTTP requests.

## Coding Standards

1. Follow WordPress Coding Standards (WPCS).
2. Use tab indentation.
3. Use Allman brace style.
4. Complete PHPDoc for classes/public methods.
5. Naming:
   - Classes: PascalCase
   - Methods: snake_case
   - Constants: UPPER_SNAKE_CASE
   - Global keys/options: `ops_health_` prefix

## Recommended Workflow

1. Write/update tests.
2. Implement the minimum code needed.
3. Refactor while keeping tests green.
4. Run `composer test`, `composer phpcs`, `composer analyse`.
5. Use conventional commits (`feat`, `fix`, `docs`, `refactor`, `test`, `chore`).

## Project Status (Reference)

Milestones M1-M6 completed (Core Checks + Storage + Cron + Error Log + Redis + Alerting + DiskCheck + VersionsCheck + DashboardWidget + E2E Testing + WordPress.org Readiness + HealthScreen UI). 574 unit tests, 1336 assertions. 322 integration tests, 655 assertions (single-site) / 684 assertions (multisite). Coverage: 100% classes, methods, lines (unit + integration + multisite combined). 46 E2E scenarios x 3 viewports = 138 test executions (Playwright + wp-env). PHPCS 100% clean, PHPStan level 6: 0 errors. 31 source files, 55 PHP test files (31 unit + 24 integration), 5 E2E spec files, 2 CSS files. Plugin WordPress.org ready with uninstall.php, readme.txt, ABSPATH guards. HealthScreen with card grid, summary banner, and dedicated CSS. See `DEVELOPMENT_PLAN.md` and `CHANGELOG.md` for up-to-date status.

## Current Baseline (v0.6.2)

Architecture points to preserve:

1. Plugin bootstrap in `ops-health-dashboard.php` with fail-safe autoloader and admin notice when `vendor/autoload.php` is missing.
2. Main orchestration in `src/Core/Plugin.php` with idempotent init.
3. Custom DI container in `src/Core/Container.php` (`bind`, `share`, `instance`, `make`).
4. Activation/deactivation lifecycle in `src/Core/Activator.php` with options setup and cron hook `ops_health_run_checks`.
5. Periodic scheduling in `src/Services/Scheduler.php` (every 15 minutes) with throttled self-healing via transient `ops_health_cron_check`.
6. Checks registered in `config/bootstrap.php`: `DatabaseCheck`, `ErrorLogCheck`, `RedisCheck`, `DiskCheck`, `VersionsCheck`.
7. Admin action flow in `src/Admin/HealthScreen.php` via `process_actions()` with nonce + capability checks and PRG redirect; isolated `do_exit()` for testability.
8. Sensitive data redaction centralized in `src/Services/Redaction.php`, injected into `CheckRunner`, `DatabaseCheck`, `ErrorLogCheck`, `RedisCheck`.
9. `RedisCheck` uses per-run unique smoke-test key (`ops_health_smoke_test_<uniqid>`) to avoid race conditions.
10. `CheckRunnerInterface` includes `clear_results()` used by admin flow (`Run Now`/`Clear Cache`).
11. Alerting pipeline via `AlertManager` with state-change detection, multi-channel dispatch, per-check cooldown, max 50 alert log entries, integrated in `Scheduler::run_checks()`.
12. Anti-SSRF `HttpClient` protections: private IP blocking, DNS validation, DNS pinning (`CURLOPT_RESOLVE`), scheme/port restrictions, no redirects, 5s timeout, IPv6 safe-fail rejection, 2xx validation.
13. Alert settings page at `Ops -> Alert Settings` with PRG, nonce `ops_health_alert_settings`, per-channel enable/disable and masked credentials not exposed in DOM.
14. Channel-level security: Telegram HTML escaping, Slack mrkdwn escaping, Email `is_email()`, WhatsApp E.164 validation.
15. AlertManager resilience: cooldown set before dispatch, status constants, per-channel `try/catch \Throwable`, Scheduler wrapping `process()` with `catch (\Throwable)`.
16. Dashboard widget in `src/Admin/DashboardWidget.php` registered in `Plugin::init()` with capability check and worst-status logic.
17. Health screen UI with `register_hooks()`, guarded `enqueue_styles()`, `SCREEN_ID`, overall-status priority map, card-grid + summary banner CSS.
18. Disk check thresholds (`WARNING_THRESHOLD = 20`, `CRITICAL_THRESHOLD = 10`) and wrappers for testability.
19. Versions check with `RECOMMENDED_PHP_VERSION = '8.3'`; core updates critical, plugin/theme updates warning.
20. Local quality gates: `composer test`, `composer phpcs`, `composer analyse` (PHPStan level 6 with `phpstan.neon`).
21. CI in `.github/workflows/ci.yml`: dedicated PHPCS, PHPStan, PHPUnit matrix (PHP 7.4-8.5, coverage on 8.3), E2E Playwright (Chromium, desktop-only in CI, wp-env, `timeout-minutes: 15`). Codecov with separate flags `unit`/`integration` (`codecov.yml`, `CODECOV_TOKEN` secret).
22. E2E Testing: Playwright + `@wordpress/env` in Docker. 46 scenarios x 3 viewports locally (desktop/tablet/mobile), desktop-only in CI (46 tests, ~8 min). CI: 1 worker, timeout 60s, login timeout 30s, health check wait, `line` + `github` reporter. Centralized selectors in `tests/e2e/helpers/selectors.ts`. Login helpers for admin/subscriber/editor. `bin/e2e-setup.sh` creates test users.
23. Local Test Matrix: `bin/test-matrix.sh` replicates CI locally: PHPCS + PHPStan + PHPUnit (PHP 7.4-8.5) + E2E Playwright. Flags `--e2e-only`, `--no-e2e`, `--tests-only`, `--phpcs-only`, `--parallel`. E2E integrated with lifecycle management (wp-env start/stop, test users, npm/docker prerequisites). `composer.json` `process-timeout: 0` for long-running executions.

Operational note:
1. Avoid dated sections or "one-shot" checklists in this file.
2. Update this section only when the technical baseline changes (version, bootstrap, scheduler, main contracts).

## Change Review Checklist

Before closing PR/commit:

1. Review `git diff --name-only` and `git diff` for touched files.
2. Ensure test coverage reflects every changed behavior.
3. Confirm no cron/scheduler regressions.
4. Confirm tooling compatibility in sequential and parallel runs.
5. Execute:
   - `composer test:unit`
   - `composer test:integration` (with DB available)
   - `composer phpcs`
   - `composer analyse`
6. If integration tests cannot run in sandbox/local env, explicitly report concrete failure (for example DB unreachable), not as passed.

## Reference Docs

1. `README.md`
2. `CONTRIBUTING.md`
3. `DEVELOPMENT_PLAN.md`
4. `CHANGELOG.md`
5. `CLAUDE.md`
6. `SECURITY.md`
