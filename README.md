# Ops Health Dashboard

Production-grade WordPress plugin for operational monitoring with automated health checks and configurable multi-channel alerting.

## Status

### `main`

[![CI](https://github.com/mab056/ops-health-dashboard/workflows/CI/badge.svg)](https://github.com/mab056/ops-health-dashboard/actions/workflows/ci.yml)
[![codecov](https://codecov.io/gh/mab056/ops-health-dashboard/graph/badge.svg?token=OO2F0VMGQO)](https://codecov.io/gh/mab056/ops-health-dashboard)

### `dev`

[![CI (dev)](https://github.com/mab056/ops-health-dashboard/actions/workflows/ci.yml/badge.svg?branch=dev)](https://github.com/mab056/ops-health-dashboard/actions/workflows/ci.yml)

### Requirements

[![PHPCS](https://img.shields.io/badge/PHPCS-WordPress%20Standards-green)](https://github.com/WordPress/WordPress-Coding-Standards)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%206-brightgreen)](phpstan.neon)
[![PHP Version](https://img.shields.io/badge/PHP-7.4%2B-blue)](https://www.php.net/)
[![WordPress](https://img.shields.io/badge/WordPress-5.8%2B-blue)](https://wordpress.org/)

### Repo

[![License](https://img.shields.io/badge/License-GPL%20v3-blue.svg)](LICENSE)
[![Security Policy](https://img.shields.io/badge/Security-Policy-blue)](SECURITY.md)
[![Release](https://img.shields.io/github/v/release/mab056/ops-health-dashboard)](https://github.com/mab056/ops-health-dashboard/releases)
[![Release Date](https://img.shields.io/github/release-date/mab056/ops-health-dashboard)](https://github.com/mab056/ops-health-dashboard/releases)
[![Last Commit](https://img.shields.io/github/last-commit/mab056/ops-health-dashboard)](https://github.com/mab056/ops-health-dashboard/commits)
[![Open Issues](https://img.shields.io/github/issues/mab056/ops-health-dashboard)](https://github.com/mab056/ops-health-dashboard/issues)
[![Open PRs](https://img.shields.io/github/issues-pr/mab056/ops-health-dashboard)](https://github.com/mab056/ops-health-dashboard/pulls)
[![Downloads](https://img.shields.io/github/downloads/mab056/ops-health-dashboard/total)](https://github.com/mab056/ops-health-dashboard/releases)

## Problem

**"I don't know what's happening until it breaks."**

This plugin provides an operational dashboard in wp-admin with automated health checks and configurable alerting (email, webhook, Slack, Telegram, WhatsApp), so you know what's happening *before* it breaks.

## Features

- **5 Health Checks** - Database, Error Logs, Redis, Disk Space, Versions (WP/PHP) | [details](https://github.com/mab056/ops-health-dashboard/wiki/Health-Checks)
- **5 Alert Channels** - Email, Webhook (HMAC), Slack (Block Kit), Telegram, WhatsApp | [details](https://github.com/mab056/ops-health-dashboard/wiki/Alerting)
- **Admin UI** - Health Dashboard page, Alert Settings page, Dashboard widget | [details](https://github.com/mab056/ops-health-dashboard/wiki/Admin-UI)
- **WP-Cron Scheduling** - Automated checks every 15 minutes with status-change alerting
- **Anti-SSRF** - DNS pinning, private IP blocking, scheme/port restriction on all outbound HTTP

## Architecture

Built with dependency injection, interface-first design, and strict TDD. No singleton, no static, no final.

See the [Architecture](https://github.com/mab056/ops-health-dashboard/wiki/Architecture) wiki page for design patterns, directory structure, component graph, and core principles.

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
git clone https://github.com/mab056/ops-health-dashboard.git
cd ops-health-dashboard
composer install
composer test:unit     # Fast, no WordPress needed
```

For the full development workflow (integration tests, E2E, test matrix, build), see the [Development Workflow](https://github.com/mab056/ops-health-dashboard/wiki/Development-Workflow) wiki page.

## Testing

Three-layer test strategy: unit (Brain\Monkey), integration (WP Test Suite), E2E (Playwright + wp-env). TDD workflow: RED -> GREEN -> REFACTOR.

For commands, test matrix, TDD examples, and coverage details, see the [Testing and Quality](https://github.com/mab056/ops-health-dashboard/wiki/Testing-and-Quality) wiki page.

## Security

Defense-in-depth: capability checks, nonces, input sanitization, output escaping, data redaction (11 patterns), anti-SSRF with DNS pinning, per-channel injection protection, cooldown pre-dispatch, `catch (\Throwable)` isolation.

For the full security model, see the [Security](https://github.com/mab056/ops-health-dashboard/wiki/Security) wiki page and [SECURITY.md](SECURITY.md).

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

See the [Roadmap](https://github.com/mab056/ops-health-dashboard/wiki/Roadmap) wiki page for future milestones (M7-M9).

## Contributing

We welcome contributions! See [CONTRIBUTING.md](CONTRIBUTING.md) and the [Development Workflow](https://github.com/mab056/ops-health-dashboard/wiki/Development-Workflow) wiki page for TDD requirements, coding standards, and pull request process.

## Security Reporting

Do not open public issues for vulnerabilities.

- Preferred: GitHub Security Advisory
- Email: `info@mattiabondrano.dev`

See [SECURITY.md](SECURITY.md).

## License

GPL-3.0-or-later - see [LICENSE](LICENSE).

## Authors

- Mattia Bondrano - [GitHub](https://github.com/mab056)

Developed with the support of Claude Code (Opus 4.5, 4.6, Sonnet 4.5) and Codex (Codex 5.2, 5.3).

## Support

- **Wiki**: [Project Wiki](https://github.com/mab056/ops-health-dashboard/wiki/)
- **Issues**: [GitHub Issues](https://github.com/mab056/ops-health-dashboard/issues)
- **Contributing**: [CONTRIBUTING.md](CONTRIBUTING.md)
