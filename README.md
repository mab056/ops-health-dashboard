# Ops Health Dashboard

[![CI](https://github.com/mab056/ops-health-dashboard/workflows/CI/badge.svg)](https://github.com/mab056/ops-health-dashboard/actions)
[![PHP Version](https://img.shields.io/badge/PHP-7.4%2B-blue)](https://www.php.net/)
[![WordPress](https://img.shields.io/badge/WordPress-5.8%2B-blue)](https://wordpress.org/)
[![License](https://img.shields.io/badge/License-GPL%20v2%2B-blue.svg)](LICENSE)

Production-grade WordPress health monitoring plugin with automated checks and configurable alerts.

## 🎯 Problem

**"Non so cosa sta succedendo finché non esplode."**

This plugin provides an operational dashboard in wp-admin with automatic health checks and configurable alerting (email, webhooks, Slack, Telegram, WhatsApp) to know what's happening *before* it breaks.

## ✨ Features (MVP)

### Health Checks
- **Database** - Connectivity + query performance
- **Redis** - Detection + smoke test (optional)
- **Disk Space** - Free/total with configurable thresholds
- **Error Log** - Safe aggregation with automatic redaction
- **Versions** - WordPress, PHP, themes, plugins + update notifications

### Dashboard
- Admin page: `Ops → Health Dashboard`
- Dashboard widget with global status (✅/⚠️/🛑)
- "What changed in last 24h" summary
- Manual "Run Checks Now" button

### Alerting
- Email via `wp_mail()`
- Generic webhook (POST JSON)
- Slack (opt-in with Incoming Webhook)
- Telegram (opt-in with Bot API)
- WhatsApp (via generic webhook)
- Smart cooldown to prevent alert spam

### Scheduling
- WP-Cron (default: every 15 minutes)
- Manual trigger
- Alerts only on state changes

## 🏗️ Architecture

Built with **modern OOP, TDD, and strict security hardening**.

### Core Principles

#### ⚠️ NO Singleton, NO Static, NO Final

This plugin strictly avoids anti-patterns that harm testability:

```php
// ❌ WRONG - Singleton pattern
class Plugin {
    private static $instance;
    public static function get_instance() { ... }
}

// ✅ CORRECT - Dependency injection
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
- **Best Practices**: WordPress core moved away from singletons
- **Predictability**: Explicit dependencies are easier to trace

### Directory Structure

```
ops-health-dashboard/
├── src/
│   ├── Core/           # Container, Plugin, Activator
│   ├── Interfaces/     # CheckInterface, NotifierInterface, etc.
│   ├── Checks/         # Health check implementations
│   ├── Alerts/         # Notification channels
│   ├── Services/       # Business logic (Storage, HttpClient, etc.)
│   ├── Admin/          # wp-admin UI
│   └── Utilities/      # Helpers
├── tests/
│   ├── Unit/           # Unit tests (isolated)
│   └── Integration/    # Integration tests (WordPress loaded)
├── tests-e2e/          # Playwright E2E tests
└── config/             # Bootstrap and DI configuration
```

### Key Components

- **Container** - Lightweight DI container with `share()` (not singleton)
- **Plugin** - Main orchestrator with constructor injection
- **CheckRunner** - Orchestrates health checks
- **Storage** - WordPress Options API wrapper
- **HttpClient** - Anti-SSRF protected HTTP requests
- **Redaction** - Sensitive data sanitization

## 📋 Requirements

- **PHP**: 7.4+ (declared minimum), 8.3+ (recommended)
- **WordPress**: 5.8+
- **MySQL**: 5.7+ or MariaDB 10.2+
- **Composer**: For development dependencies

## 🚀 Installation

### For Users (Production)

1. Download the latest release from [GitHub Releases](https://github.com/mab056/ops-health-dashboard/releases)
2. Upload to `/wp-content/plugins/ops-health-dashboard/`
3. Activate via WordPress admin
4. Navigate to `Ops → Health Dashboard`

### For Developers (Local Development)

```bash
# Clone repository
git clone https://github.com/mab056/ops-health-dashboard.git
cd ops-health-dashboard

# Install dependencies
php composer.phar install

# Install WordPress test suite
bash bin/install-wp-tests.sh wordpress_test root '' localhost latest

# Run tests
php composer.phar test

# Run PHPCS
php composer.phar phpcs
```

## 🧪 Testing

This project follows **strict TDD** (Test-Driven Development).

### Run All Tests

```bash
# PHPUnit
php composer.phar test

# With coverage (requires Xdebug)
XDEBUG_MODE=coverage php composer.phar test:coverage

# PHPCS (WordPress Coding Standards)
php composer.phar phpcs

# Auto-fix PHPCS issues
php composer.phar phpcbf
```

### TDD Workflow

Every feature follows: **RED → GREEN → REFACTOR**

1. **RED**: Write failing test first
2. **GREEN**: Write minimal code to pass
3. **REFACTOR**: Clean up and optimize

Example:

```php
// RED: Write test that fails
public function test_database_check_returns_ok_on_healthy_connection() {
    $check = new DatabaseCheck($storage);
    $result = $check->run();
    $this->assertEquals('ok', $result['status']);
}

// GREEN: Implement minimal code
public function run(): array {
    global $wpdb;
    $result = $wpdb->query('SELECT 1');
    return ['status' => $result !== false ? 'ok' : 'critical'];
}

// REFACTOR: Add error handling, timing, redaction
```

### Test Matrix

- **PHP Versions**: 7.4, 8.0, 8.1, 8.2, 8.3 (coverage), 8.4, 8.5
- **Coverage Target**: ≥85% on PHP 8.3
- **E2E Tests**: Mobile, Tablet, Desktop viewports

## 🔒 Security

### Hardening Features

- **Admin-only**: All features require `manage_options` capability
- **Nonces**: CSRF protection on all forms and AJAX
- **Anti-SSRF**: Multi-layer protection for webhooks
  - Schema validation (http/https only)
  - DNS rebinding prevention
  - Private IP blocking
  - No redirect following
  - 5-second timeout
- **Data Redaction**: Automatic sanitization of:
  - Credentials (passwords, API keys, tokens)
  - File paths (ABSPATH, WP_CONTENT_DIR)
  - Database credentials
  - User data (emails, IPs)
- **Input Sanitization**: All user inputs sanitized
- **Output Escaping**: All outputs escaped

### WordPress.org Ready

- ✅ Plugin Check tool passes (zero errors)
- ✅ No outbound calls without opt-in
- ✅ WordPress Coding Standards compliant
- ✅ Complete uninstall cleanup

## 📊 Development Status

Current milestone: **M0 - Setup & Infrastructure** ✅

### Roadmap

- [x] **M0**: Setup & Infrastructure (TDD, CI/CD, core classes)
- [ ] **M1**: Core Checks + Storage + Cron
- [ ] **M2**: Error Log Summary Safe
- [ ] **M3**: Redis Check
- [ ] **M4**: Alerting System
- [ ] **M5**: E2E Testing (Playwright)
- [ ] **M6**: WordPress.org Readiness

See [DEVELOPMENT_PLAN.md](DEVELOPMENT_PLAN.md) for detailed progress.

## 🤝 Contributing

We welcome contributions! Please read [CONTRIBUTING.md](CONTRIBUTING.md) for:

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
php composer.phar install

# Write tests first (TDD)
# Then implement feature
# Ensure all tests pass
php composer.phar test
php composer.phar phpcs

# Commit and push
git commit -m "feat: your feature description"
git push origin feature/your-feature-name
```

## 📝 Coding Standards

- **PHP**: WordPress Coding Standards (WPCS)
- **HTML/CSS**: Code Guide
- **JavaScript**: MDN Code Style Guide
- **Line Length**: 120 characters (soft), 150 (hard)

## 🔧 Configuration

### Composer Commands

```bash
php composer.phar test              # Run PHPUnit tests
php composer.phar test:coverage     # Run with coverage (Xdebug required)
php composer.phar phpcs             # Check coding standards
php composer.phar phpcbf            # Auto-fix coding standards
php composer.phar install-wp-tests  # Install WordPress test suite
```

### CI/CD

GitHub Actions automatically runs on push/PR:
- PHPCS check (WordPress Coding Standards)
- PHPUnit on PHP 7.4, 8.0, 8.1, 8.2, 8.3, 8.4, 8.5
- Coverage report (PHP 8.3 only)
- Codecov upload

## 📄 License

GPL-2.0-or-later - see [LICENSE](LICENSE) file.

## 👥 Authors

- **Ops Team** - [GitHub](https://github.com/mab056)
- **Co-Authored-By**: Claude Sonnet 4.5

## 🙏 Acknowledgments

- WordPress Plugin Handbook
- WordPress Coding Standards
- PHPUnit Documentation
- Brain\Monkey for WordPress testing

## 📞 Support

- **Issues**: [GitHub Issues](https://github.com/mab056/ops-health-dashboard/issues)
- **Documentation**: See [DEVELOPMENT_PLAN.md](DEVELOPMENT_PLAN.md)
- **Contributing**: See [CONTRIBUTING.md](CONTRIBUTING.md)

---

**Built with ❤️ and strict TDD**
