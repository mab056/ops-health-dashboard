# Ops Health Dashboard

[![CI](https://github.com/mab056/ops-health-dashboard/workflows/CI/badge.svg)](https://github.com/mab056/ops-health-dashboard/actions/workflows/ci.yml)
[![CI (dev)](https://github.com/mab056/ops-health-dashboard/actions/workflows/ci.yml/badge.svg?branch=dev)](https://github.com/mab056/ops-health-dashboard/actions/workflows/ci.yml)
[![codecov](https://codecov.io/gh/mab056/ops-health-dashboard/graph/badge.svg?token=OO2F0VMGQO)](https://codecov.io/gh/mab056/ops-health-dashboard)
[![PHPCS](https://img.shields.io/badge/PHPCS-WordPress%20Standards-green)](https://github.com/WordPress/WordPress-Coding-Standards)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%206-brightgreen)](phpstan.neon)
[![License](https://img.shields.io/badge/License-GPL%20v3-blue.svg)](LICENSE)
[![Security Policy](https://img.shields.io/badge/Security-Policy-blue)](SECURITY.md)
[![PHP Version](https://img.shields.io/badge/PHP-7.4%2B-blue)](https://www.php.net/)
[![WordPress](https://img.shields.io/badge/WordPress-5.8%2B-blue)](https://wordpress.org/)
[![Release](https://img.shields.io/github/v/release/mab056/ops-health-dashboard)](https://github.com/mab056/ops-health-dashboard/releases)
[![Release Date](https://img.shields.io/github/release-date/mab056/ops-health-dashboard)](https://github.com/mab056/ops-health-dashboard/releases)
[![Last Commit](https://img.shields.io/github/last-commit/mab056/ops-health-dashboard)](https://github.com/mab056/ops-health-dashboard/commits)
[![Open Issues](https://img.shields.io/github/issues/mab056/ops-health-dashboard)](https://github.com/mab056/ops-health-dashboard/issues)
[![Open PRs](https://img.shields.io/github/issues-pr/mab056/ops-health-dashboard)](https://github.com/mab056/ops-health-dashboard/pulls)
[![Downloads](https://img.shields.io/github/downloads/mab056/ops-health-dashboard/total)](https://github.com/mab056/ops-health-dashboard/releases)

Production-grade WordPress plugin for operational monitoring with automated health checks and configurable multi-channel alerting.

## Problem

**"I don't know what is happening until it breaks."**

This plugin provides an operational dashboard in wp-admin with automated health checks and configurable alerting (email, webhook, Slack, Telegram, WhatsApp), so you know what is happening *before* it breaks.

## Features

### Health Checks

- **Database** - Connectivity and query performance
- **Error Logs** - Secure aggregation with automatic redaction
- **Redis** - Extension detection + connection + smoke test with graceful degradation
- **Disk Space** - Free/total with configurable thresholds (warning <20%, critical <10%)
- **Versions** - WordPress, PHP, themes, plugins + update notifications

### Admin UI

- Admin page: `Ops -> Health Dashboard`
- Manual actions: `Run Now`, `Clear Cache` (nonce-protected)
- Dashboard widget with global worst-status in wp-admin Dashboard

### Alerting

- **Email** via `wp_mail()` with configurable recipients
- **Webhook** generic JSON POST with optional HMAC signature (on pre-serialized body)
- **Slack** via Incoming Webhook with Block Kit payload
- **Telegram** via Bot API with HTML parse mode
- **WhatsApp** via generic webhook with Bearer auth
- Smart per-check cooldown via transient (default 60 min)
- Recovery alerts bypass cooldown
- Admin configuration page: `Ops -> Alert Settings`
- Anti-SSRF on all outbound HTTP requests

### Scheduling

- WP-Cron (default: every 15 minutes)
- Manual trigger via `Run Now` button
- Automatic alerts only on status changes

## Architecture

Built with **modern OOP, TDD, and rigorous security hardening**.

### Core Principles

#### NO Singleton, NO Static, NO Final

This plugin strictly avoids anti-patterns that harm testability:

```php
// WRONG - Singleton pattern
class Plugin {
    private static $instance;
    public static function get_instance() { ... }
}

// CORRECT - Dependency injection
class Plugin {
    private $container;
    public function __construct(Container $container) {
        $this->container = $container;
    }
}
```

**Why?**
- **Testability**: Singleton and static make mocking impossible
- **Flexibility**: Final prevents extensibility
- **Best Practices**: WordPress core has moved away from singletons
- **Predictability**: Explicit dependencies are easier to trace

### Directory Structure

```text
ops-health-dashboard/
├── src/
│   ├── Core/           # Container, Plugin, Activator, Uninstaller
│   ├── Interfaces/     # DI contracts (Check, CheckRunner, Storage, Redaction, HttpClient, AlertManager, AlertChannel)
│   ├── Checks/         # Health check implementations (Database, ErrorLog, Redis, Disk, Versions)
│   ├── Services/       # Business logic (Storage, Scheduler, Redaction, CheckRunner, AlertManager, HttpClient)
│   ├── Channels/       # Notification channels (Email, Webhook, Slack, Telegram, WhatsApp)
│   └── Admin/          # wp-admin UI (Menu, HealthScreen, AlertSettings, DashboardWidget)
├── tests/
│   ├── Unit/           # Unit tests (Brain\Monkey)
│   ├── Integration/    # Integration tests (WordPress Test Suite)
│   └── e2e/            # E2E tests (Playwright + wp-env)
├── config/             # Bootstrap and DI configuration
└── bin/                # Tooling scripts (build, matrix, test setup, e2e)
```

### Key Components

- **Container** - Lightweight DI container with `share()` (not singleton), circular dependency detection
- **Plugin** - Main orchestrator with constructor injection
- **CheckRunnerInterface** - Contract to decouple Scheduler and HealthScreen from the concrete CheckRunner
- **CheckRunner** - Orchestrates health checks, redacts exception messages via RedactionInterface
- **Storage** - WordPress Options API wrapper with `autoload=false`
- **Redaction** - Sensitive data sanitization (11 patterns, IPv4 with octet validation)
- **Scheduler** - WP-Cron scheduling every 15 minutes + throttled self-healing + AlertManager integration
- **AlertManager** - Status change detection, multi-channel dispatch, per-check cooldown, alert log
- **HttpClient** - Anti-SSRF HTTP client (private IP blocking, DNS validation, scheme/port restriction)
- **5 Channels** - EmailChannel, WebhookChannel, SlackChannel, TelegramChannel, WhatsAppChannel
- **DashboardWidget** - wp-admin dashboard widget with global status (worst-status) and link to full dashboard

## Requirements

- **PHP**: 7.4+ (minimum), 8.3+ (recommended)
- **WordPress**: 5.8+
- **MySQL**: 5.7+ or MariaDB 10.2+
- **Composer**: For development dependencies

## Installation

### Production

1. Download the latest release from [GitHub Releases](https://github.com/mab056/ops-health-dashboard/releases) or build the ZIP via `bin/build-zip.sh`.
2. Upload the ZIP file via `Plugins -> Add New -> Upload Plugin` in wp-admin.
3. Activate via WordPress admin.
4. Navigate to `Ops -> Health Dashboard`.

### Local Development

```bash
# Clone repository
git clone https://github.com/mab056/ops-health-dashboard.git
cd ops-health-dashboard

# Install dependencies
composer install

# Run unit tests (fast, no WordPress needed)
composer test:unit

# Install WordPress test suite for integration tests
composer install-wp-tests

# Run all tests (unit + integration)
composer test

# Run PHPCS
composer phpcs

# Run PHPStan (static analysis level 6)
composer analyse
```

## Testing

This project follows Test-Driven Development with a **mixed approach**.

### Mixed Test Approach

**Unit Tests (Brain\Monkey)** - Fast, isolated
- Pure business logic, NO WordPress
- 574 tests, 1336 assertions, ~3 s
- Ideal for rapid TDD

**Integration Tests (WP Test Suite)** - Real WordPress
- Full WordPress, database, WP-Cron
- 322 tests, 655 assertions (single-site) / 684 assertions (multisite), ~4 s
- Verifies real WordPress integration
- Multisite support via `WP_TESTS_MULTISITE=1`

**E2E Tests (Playwright + wp-env)** - Real browser
- 46 scenarios x 3 viewports (desktop, tablet, mobile) = 138 test executions
- Full WordPress in Docker via `@wordpress/env`
- Coverage: navigation, dashboard, widget, alert settings, security

### Test Commands

```bash
# Unit tests (fast, no WordPress needed)
composer test:unit

# Integration tests (require WP test suite)
composer install-wp-tests              # One-time setup
composer test:integration

# Integration tests in multisite mode
composer test:integration:multisite

# All tests (unit + integration)
composer test

# Full PHP 7.4-8.5 matrix + PHPCS + PHPStan (like CI)
composer test:matrix

# With coverage (requires Xdebug) - runs unit + integration + multisite
composer test:coverage

# E2E tests (require Docker + Node.js)
npm ci                                # Install Node.js dependencies
npm run env:start                     # Start WordPress in Docker
bash bin/e2e-setup.sh                 # Create test users
npm run test:e2e                      # Run E2E tests
npm run env:stop                      # Stop Docker

# PHPCS (WordPress Coding Standards)
composer phpcs

# Auto-fix PHPCS issues
composer phpcbf

# Build ZIP for production
bin/build-zip.sh                      # Generates dist/ops-health-dashboard-VERSION.zip
bin/build-zip.sh --output /tmp/p.zip  # Custom output path
```

### Local Test Matrix

Run the full CI matrix locally (requires PHP 7.4-8.5 + Docker for E2E):

```bash
composer test:matrix                   # Full matrix (PHPCS + PHPStan + 7 PHP versions + E2E)
bin/test-matrix.sh --php 7.4           # Single version only
bin/test-matrix.sh --php 7.4 --php 8.3 # Specific versions
bin/test-matrix.sh --parallel          # Parallel execution
bin/test-matrix.sh --phpcs-only        # PHPCS + PHPStan only
bin/test-matrix.sh --tests-only        # PHPUnit only (skip PHPCS, PHPStan, E2E)
bin/test-matrix.sh --e2e-only          # E2E only (Playwright + wp-env)
bin/test-matrix.sh --no-e2e            # Everything except E2E
```

### TDD Workflow

Every feature follows: **RED -> GREEN -> REFACTOR**

1. **RED**: Write a failing test first (unit test with Brain\Monkey)
2. **GREEN**: Write the minimum code to pass
3. **REFACTOR**: Clean up and optimize
4. **INTEGRATION**: Add integration test to verify with real WordPress

Example:

```php
// RED: Unit test with Brain\Monkey (fast)
public function test_run_returns_ok_when_database_healthy() {
    $wpdb = Mockery::mock( 'wpdb' );
    $wpdb->shouldReceive( 'query' )->once()->with( 'SELECT 1' )->andReturn( 1 );
    $wpdb->last_error = '';

    Functions\expect( '__' )->andReturnFirstArg();

    $redaction = Mockery::mock( RedactionInterface::class );
    $redaction->shouldReceive( 'redact' )->andReturnUsing( function ( $t ) { return $t; } );

    $check  = new DatabaseCheck( $wpdb, $redaction );
    $result = $check->run();
    $this->assertEquals( 'ok', $result['status'] );
}

// GREEN: Implement minimum code
public function run(): array {
    $start  = microtime( true );
    $result = $this->wpdb->query( 'SELECT 1' );
    return [
        'status'  => false !== $result ? 'ok' : 'critical',
        'message' => __( 'Database connection healthy', 'ops-health-dashboard' ),
        'duration' => microtime( true ) - $start,
    ];
}

// INTEGRATION: Test with real WordPress
public function test_database_check_runs_successfully() {
    global $wpdb;
    $redaction = new Redaction();
    $check     = new DatabaseCheck( $wpdb, $redaction );
    $result    = $check->run();

    $this->assertEquals( 'ok', $result['status'] );
    $this->assertArrayNotHasKey( 'db_host', $result['details'] );
}
```

### Test Matrix

- **Unit Tests**: Brain\Monkey - 574 tests, 1336 assertions, all PHP versions
- **Integration Tests**: WP Test Suite - 322 tests, 655/684 assertions (single-site/multisite), all PHP versions
- **E2E Tests**: Playwright + wp-env - 46 scenarios x 3 viewports = 138 test executions
- **PHPStan**: Level 6 with szepeviktor/phpstan-wordpress, 0 errors
- **PHP Versions**: 7.4, 8.0, 8.1, 8.2, 8.3 (coverage), 8.4, 8.5
- **Target Coverage**: 95% project, 90% patch (Codecov)

## Security

### Hardening Features

- **Admin Only**: All features require `manage_options` capability
- **Nonces**: CSRF protection on all forms and AJAX
- **Anti-SSRF**: Active protection on all outbound HTTP requests
  - Scheme validation (http/https only)
  - Private/reserved IP blocking (RFC 1918, loopback, link-local)
  - DNS resolution validation (DNS rebinding prevention)
  - DNS pinning via `CURLOPT_RESOLVE` (TOCTOU/DNS rebinding prevention)
  - Port restriction (80/443 only)
  - IPv6 safe-fail rejection
  - HTTP status validation (2xx only = success)
  - No redirect following
  - 5 second timeout
- **Channel Security**: Injection protection on all channels
  - TelegramChannel: HTML escaping (`htmlspecialchars`)
  - SlackChannel: mrkdwn escaping (formatting)
  - EmailChannel: `is_email()` recipient validation
  - WhatsAppChannel: E.164 phone number validation
  - Token/secret masked (`type="password"`, credentials never in DOM)
- **Cooldown pre-dispatch**: Prevents alert spam even on channel errors
- **Channel isolation**: `try/catch \Throwable` per channel (one failing channel does not block others)
- **Scheduler resilience**: `catch (\Throwable)` on AlertManager (cron survives any error)
- **Data Redaction**: Automatic sanitization of:
  - Credentials (passwords, API keys, tokens)
  - File paths (ABSPATH, WP_CONTENT_DIR)
  - Database credentials
  - User data (email, IP)
- **Input Sanitization**: All user inputs sanitized
- **Output Escaping**: All outputs escaped

### WordPress.org Ready

- Compliant with WordPress Coding Standards
- No outbound calls without opt-in
- Clean uninstall via `uninstall.php` (single-site and multisite)
- `readme.txt` in WordPress.org standard format
- ABSPATH guards on all source files

For details, see `SECURITY.md`.

## Project Status

Current milestone: **M6 - WordPress.org Readiness**

### Statistics

- **31 source files** in `src/`, 2 CSS files in `assets/css/`
- **55 PHP test files** (31 unit + 24 integration)
- **574 unit tests**, 1336 assertions (Brain\Monkey)
- **322 integration tests**, 655 assertions single-site / 684 multisite (WP Test Suite)
- **46 E2E scenarios** x 3 viewports = 138 test executions (Playwright)
- **Coverage**: 100% classes, 100% methods, 100% lines (unit + integration + multisite combined)
- **PHPCS**: 100% compliance (0 errors, 0 warnings)
- **PHPStan**: level 6, 0 errors

### Roadmap

- [x] **M0**: Setup & Infrastructure (TDD, CI/CD, core classes)
- [x] **M1**: Core Checks + Storage + Cron
- [x] **M2**: Secure Error Log Summary
- [x] **M3**: Redis Check
- [x] **M4**: Alerting System (Email, Webhook, Slack, Telegram, WhatsApp + anti-SSRF)
- [x] **M5**: New Checks + Dashboard Widget + E2E Testing (Playwright)
- [x] **M6**: WordPress.org Readiness (uninstall.php, readme.txt, ABSPATH guards)

See [DEVELOPMENT_PLAN.md](DEVELOPMENT_PLAN.md) for detailed progress.

## Contributing

We welcome contributions! Read [CONTRIBUTING.md](CONTRIBUTING.md) for:

- TDD workflow requirements
- Pattern enforcement (NO singleton/static/final)
- Coding standards
- Pull request process
- Testing requirements

### Quick Start for Contributors

```bash
# Fork and clone
git clone https://github.com/YOUR_USERNAME/ops-health-dashboard.git
cd ops-health-dashboard

# Create feature branch
git checkout -b feature/your-feature-name

# Install dependencies
composer install

# Write tests first (TDD)
# Then implement feature
# Make sure all tests pass
composer test
composer phpcs
composer analyse

# Commit and push
git commit -m "feat: your feature description"
git push origin feature/your-feature-name
```

## Coding Standards

- **PHP**: WordPress Coding Standards (WPCS)
- **HTML/CSS**: Code Guide
- **JavaScript**: MDN Code Style Guide
- **Line Length**: 120 characters (soft), 150 (hard)

## Configuration

### Composer Commands

```bash
# Testing
composer test                    # All tests (unit + integration)
composer test:unit               # Unit tests only (Brain\Monkey, fast)
composer test:integration        # Integration tests only (WP Test Suite)
composer test:integration:multisite  # Integration tests in multisite mode
composer test:coverage           # Full coverage (unit + integration + multisite)
composer test:matrix             # PHP 7.4-8.5 matrix + PHPCS + PHPStan + E2E (like CI)

# Code Quality
composer phpcs                   # Check WordPress Coding Standards
composer phpcbf                  # Auto-fix coding standards

# Setup
composer install-wp-tests        # Install WordPress test suite (one-time)
```

### CI/CD

GitHub Actions runs automatically on push/PR:
- PHPCS check (WordPress Coding Standards)
- PHPStan (static analysis level 6)
- PHPUnit on PHP 7.4, 8.0, 8.1, 8.2, 8.3, 8.4, 8.5
- Coverage report (PHP 8.3 only)
- Codecov upload
- E2E Playwright (Chromium, desktop-only in CI; 3 viewports locally, wp-env Docker)

## Security Reporting

Do not open public issues for vulnerabilities.

- Preferred: GitHub Security Advisory
- Email: `info@mattiabondrano.dev`

See `SECURITY.md`.

## License

GPL-3.0-or-later - see [LICENSE](LICENSE).

## Authors

- Mattia Bondrano - [GitHub](https://github.com/mab056)

Developed with the support of Claude Code (Opus 4.5, 4.6, Sonnet 4.5) and Codex (Codex 5.2, 5.3).

## Support

- **Issues**: [GitHub Issues](https://github.com/mab056/ops-health-dashboard/issues)
- **Documentation**: See [DEVELOPMENT_PLAN.md](DEVELOPMENT_PLAN.md)
- **Contributing**: See [CONTRIBUTING.md](CONTRIBUTING.md)
- **AI Assistant**: See [CLAUDE.md](CLAUDE.md) for Claude Code instructions, [AGENTS.md](AGENTS.md) for Codex instructions
