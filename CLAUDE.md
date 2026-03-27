# Claude.ai Instructions - Ops Health Dashboard

## Project Goal

**Production-grade** WordPress plugin for operational monitoring with:
- Automated health checks (Database, Redis, Disk, Error Logs, Versions)
- Multi-channel alerting (Email, Webhook, Slack, Telegram, WhatsApp)
- wp-admin dashboard with global status
- WP-Cron scheduling (default 15 min)
- WP Consent API compatible, WordPress.org ready

## Non-Negotiable Rules

### NO Singleton, NO Static, NO Final

**CRITICAL:** This project strictly forbids these anti-patterns. Pattern-enforcement tests are mandatory for every class.

```php
// ❌ NEVER: singleton, static methods/properties, final classes/methods
// ✅ ALWAYS: constructor injection, Container->share() for shared instances

class GoodService {
    private $dependency;

    public function __construct(DependencyInterface $dependency) {
        $this->dependency = $dependency;
    }
}

// Container pattern (config/bootstrap.php)
$container->share(ServiceInterface::class, function($c) {
    return new Service($c->make(DependencyInterface::class));
});
```

**Pattern enforcement tests (MANDATORY on every class):**

```php
public function test_class_is_not_final() {
    $reflection = new \ReflectionClass(MyClass::class);
    $this->assertFalse($reflection->isFinal());
}

public function test_no_static_methods() {
    $reflection = new \ReflectionClass(MyClass::class);
    $methods = $reflection->getMethods(\ReflectionMethod::IS_STATIC);
    $static_methods = array_filter($methods, function($method) {
        return strpos($method->getName(), '__') !== 0;
    });
    $this->assertEmpty($static_methods);
}
```

### TDD Always — RED -> GREEN -> REFACTOR

Write tests FIRST. Unit tests in `tests/Unit/` (Brain\Monkey), integration tests in `tests/Integration/` (WP Test Suite) when touching WordPress core (Options API, hooks, $wpdb, admin pages).

### Security — ALWAYS

- **Sanitize input**: `sanitize_text_field()`, `sanitize_email()`, `esc_url_raw()`, `absint()`
- **Escape output**: `esc_html()`, `esc_attr()`, `esc_url()`
- **Capability check**: `current_user_can('manage_options')` on all admin pages
- **Nonce**: `wp_nonce_field()` / `wp_verify_nonce()` on all forms/AJAX
- **Anti-SSRF**: Use `HttpClientInterface` for all outbound HTTP (DNS pinning, private IP blocking)

### WPCS Compliance

- Indentation: **Tab** (not spaces). Line length: 120 soft, 150 hard. Allman braces.
- Naming: `PascalCase` classes, `snake_case` methods, `UPPER_SNAKE_CASE` constants, `ops_health_` prefix for globals.
- PHPDoc on all public methods with `@param`, `@return`, `@throws`.
- `composer phpcs` MUST pass with 0 errors.

## Commands

```bash
# Testing
composer test:unit                   # Unit tests (Brain\Monkey, ~4s)
composer test:integration            # Integration tests (WP Test Suite)
composer test                        # All tests
composer test:matrix                 # Full PHP 7.4-8.5 matrix + PHPCS + PHPStan + E2E

# Code Quality
composer phpcs                       # WordPress Coding Standards
composer phpcbf                      # Auto-fix
composer analyse                     # PHPStan level 6

# Build
bin/build-zip.sh                     # Production ZIP
```

## File Structure

```
src/
  Core/        Container, Plugin, Activator, Uninstaller, ConsentIntegration
  Interfaces/  CheckInterface, CheckRunnerInterface, StorageInterface,
               RedactionInterface, HttpClientInterface, AlertManagerInterface,
               AlertChannelInterface
  Services/    Storage, CheckRunner, Scheduler, Redaction, AlertManager, HttpClient
  Checks/      DatabaseCheck, ErrorLogCheck, RedisCheck, DiskCheck, VersionsCheck
  Channels/    EmailChannel, WebhookChannel, SlackChannel, TelegramChannel,
               WhatsAppChannel
  Admin/       Menu, HealthScreen, AlertSettings, DashboardWidget
tests/
  Unit/        Brain\Monkey tests (fast, isolated)
  Integration/ WP Test Suite tests (real WordPress + MySQL)
  e2e/         Playwright E2E tests (wp-env Docker)
config/
  bootstrap.php  DI container configuration
```

## Conventional Commits

Format: `<type>(<scope>): <description>` with `Co-Authored-By:` footer.
Types: `feat`, `fix`, `docs`, `style`, `refactor`, `test`, `chore`.

## Quality Gates (MUST PASS)

- PHPCS: 0 errors, 0 warnings
- PHPStan: level 6, 0 errors
- Tests: 100% passing
- Coverage: 95% project, 90% patch (Codecov)

## Current Status

**Current baseline:** v0.6.2. Milestones **M0-M6** are complete, and **M7 - Extensibility API** is next.

- **637 unit tests** (Brain\Monkey), 1502 assertions
- **326 integration tests** (WP Test Suite), 655 assertions (single-site) / 684 (multisite)
- **57 E2E scenarios** x 3 viewports = 171 local runs; CI desktop-only
- Coverage: **100%** classes, methods, lines
- 32 source files, 56 PHP test files (32 unit + 24 integration), 5 E2E spec files, 4 CSS files, 1 JS file
- WordPress.org ready: uninstall.php, readme.txt, ABSPATH guards, multisite support
- WP Consent API: ConsentIntegration with `__return_true` filter + Privacy Policy content

## CI/CD

GitHub Actions runs PHPCS, PHPStan, PHPUnit on PHP 7.4-8.5, and Playwright E2E (desktop-only in CI). Codecov uses unit/integration flags with 95%/90% thresholds and carryforward.

Local: `composer test:matrix` replicates CI. Requires PHP 7.4-8.5 (PPA sury) + Docker + Node.js.

## Need Help

- **Issues**: https://github.com/mab056/ops-health-dashboard/issues
- **Documentation**: `README.md`, `CONTRIBUTING.md`, `DEVELOPMENT_PLAN.md`
- **CI Status**: https://github.com/mab056/ops-health-dashboard/actions
