# Claude.ai Instructions - Ops Health Dashboard

This file contains specific instructions for Claude (or other AI assistants) on how to work with this WordPress project.

## 🎯 Project Goal

**Production-grade** WordPress plugin for operational monitoring with:
- Automated health checks (Database, Redis, Disk, Error Logs, Versions)
- Multi-channel alerting (Email, Webhook, Slack, Telegram, WhatsApp)
- wp-admin dashboard with global status
- WP-Cron scheduling (default 15 min)
- WordPress.org ready

## ⚠️ NON-NEGOTIABLE Architectural Patterns

### 🚫 NO Singleton, NO Static, NO Final

**CRITICAL:** This project STRICTLY FORBIDS these anti-patterns:

```php
// ❌ NEVER DO THIS
class BadService {
    private static $instance;
    public static function get_instance() { ... }
    private function __construct() {}
}

// ❌ NEVER DO THIS
class BadHelper {
    public static function doSomething() { ... }
}

// ❌ NEVER DO THIS
final class BadClass {
    final public function method() {}
}

// ✅ ALWAYS DO THIS
class GoodService {
    private $dependency;

    public function __construct(DependencyInterface $dependency) {
        $this->dependency = $dependency;
    }

    public function doSomething(): string {
        return $this->dependency->process();
    }
}
```

**Rationale:**
- **Testability**: singleton/static make mocking very difficult
- **Flexibility**: `final` limits extensibility
- **Best Practices**: WordPress core has moved away from singletons
- **Dependency Injection**: All dependencies must be explicit

### ✅ Container Pattern

```php
// Use Container->share() for shared instances (NOT singleton)
$container->share(ServiceInterface::class, function($c) {
    return new Service($c->make(DependencyInterface::class));
});

// Bootstrap pattern (NO static factory)
function bootstrap(): Plugin {
    $container = new Container();
    // Configure bindings...
    return new Plugin($container);
}
```

## 🧪 Testing Strategy - MIXED APPROACH

### Unit Tests (tests/Unit/) - Brain\Monkey

**When to use:**
- Pure business logic
- Services (Redaction, Storage, Scheduler, CheckRunner)
- Utilities (formatter, validator, sanitizer)
- Container, Plugin (pure logic without WordPress)

**Characteristics:**
- ⚡ Very fast (~3-4s on the current unit suite)
- 🔒 Complete isolation
- 🧬 WordPress function mocking with Brain\Monkey
- ❌ NO database, NO filesystem, NO WordPress
- 📊 Coverage: always check the current PHPUnit output

**Example:**
```php
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

class MyServiceTest extends TestCase {
    use MockeryPHPUnitIntegration;

    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_service_uses_wordpress_function() {
        Functions\expect('get_option')
            ->once()
            ->with('my_key')
            ->andReturn('mocked_value');

        $service = new MyService();
        $result = $service->doSomething();

        $this->assertEquals('expected', $result);
    }
}
```

**Commands:**
```bash
composer test:unit                    # Run only unit tests
composer test:coverage:unit          # Unit tests with coverage
```

### Integration Tests (tests/Integration/) - WordPress Test Suite

**When to use:**
- Activator (Options API, real WP-Cron)
- Admin pages (menu, dashboard widget, AJAX)
- Database checks (`$wpdb->query`)
- Hooks/filters registration
- Anything that touches WordPress core

**Characteristics:**
- ✅ Full WordPress loaded
- ✅ Real MySQL database
- ✅ Real WP-Cron, Options API, hooks
- 🐢 Slower than unit tests (require WP Test Suite + DB)
- 📊 Coverage: always check the current PHPUnit output

**Example:**
```php
use WP_UnitTestCase;

class ActivatorTest extends WP_UnitTestCase {
    public function test_activate_sets_option() {
        $activator = new Activator();

        delete_option('my_option');
        $activator->activate();

        $value = get_option('my_option');
        $this->assertNotFalse($value);
    }
}
```

**Required setup:**
```bash
composer install-wp-tests             # One-time setup
composer test:integration            # Run only integration tests
```

### TDD Workflow - RED → GREEN → REFACTOR

**ALWAYS follow this cycle:**

1. **RED**: Write the failing test
   ```php
   public function test_new_feature() {
       $service = new MyService();
       $result = $service->newFeature();
       $this->assertEquals('expected', $result);
   }
   ```

2. **GREEN**: Minimal code to pass
   ```php
   public function newFeature(): string {
       return 'expected';
   }
   ```

3. **REFACTOR**: Cleanup + optimization
   ```php
   public function newFeature(): string {
       try {
           $data = $this->dependency->fetch();
           return $this->process($data);
       } catch (\Exception $e) {
           return $this->getDefaultValue();
       }
   }
   ```

**Pattern Enforcement Tests (MANDATORY):**

Every class MUST have these tests:

```php
/**
 * Tests that the class is NOT final
 */
public function test_class_is_not_final() {
    $reflection = new \ReflectionClass(MyClass::class);
    $this->assertFalse($reflection->isFinal(), 'Class should NOT be final');
}

/**
 * Tests that NO static methods exist
 */
public function test_no_static_methods() {
    $reflection = new \ReflectionClass(MyClass::class);
    $methods = $reflection->getMethods(\ReflectionMethod::IS_STATIC);

    $static_methods = array_filter($methods, function($method) {
        return strpos($method->getName(), '__') !== 0;
    });

    $this->assertEmpty($static_methods, 'Class should have NO static methods');
}
```

## 🔒 Security Requirements

### Input Sanitization (ALWAYS)

```php
// User input
$text = sanitize_text_field($_POST['field']);
$email = sanitize_email($_POST['email']);
$url = esc_url_raw($_POST['url']);
$int = absint($_POST['number']);
```

### Output Escaping (ALWAYS)

```php
// Output
echo esc_html($text);
echo esc_attr($attribute);
echo esc_url($url);
echo esc_js($javascript);
```

### Capability checks (ALWAYS on admin pages)

```php
if (!current_user_can('manage_options')) {
    wp_die(__('Unauthorized', 'ops-health-dashboard'));
}
```

### Nonce (ALWAYS on forms/AJAX)

```php
// Generate in form
wp_nonce_field('ops_health_action', 'ops_health_nonce');

// Verify in handler
if (!wp_verify_nonce($_POST['ops_health_nonce'], 'ops_health_action')) {
    wp_die(__('Invalid nonce', 'ops-health-dashboard'));
}
```

### Anti-SSRF for Webhooks (CRITICAL, milestone M4)

```php
// Example HTTP service with anti-SSRF protections (M4).
$client = $container->make(HttpClientInterface::class);

if (!$client->is_safe_url($url)) {
    throw new \Exception('Unsafe URL detected');
}

$response = $client->post($url, ['example' => 'payload']);
```

## 📝 Coding Standards

### WordPress Coding Standards (WPCS)

```bash
composer phpcs              # Check standards
composer phpcbf             # Auto-fix
composer analyse            # PHPStan static analysis (level 6)
```

**Main rules:**
- Indentation: **Tab** (not spaces)
- Line length: 120 soft, 150 hard
- Brace style: Allman style
- Naming:
  - Classes: `PascalCase`
  - Methods: `snake_case`
  - Constants: `UPPER_SNAKE_CASE`
  - Globals: `ops_health_` prefix

### PHPDoc (ALWAYS)

```php
/**
 * Method description
 *
 * Longer description if needed.
 *
 * @param string $param1 Parameter description.
 * @param int    $param2 Parameter description.
 * @return array {
 *     @type string $key1 Description.
 *     @type int    $key2 Description.
 * }
 * @throws \Exception If error condition.
 */
public function method_name(string $param1, int $param2): array {
    // Implementation
}
```

## 🚀 Development Workflow

### Initial Setup

```bash
git clone https://github.com/mab056/ops-health-dashboard.git
cd ops-health-dashboard
composer install
```

### Common Commands

```bash
# Testing
composer test                        # All tests (unit + integration)
composer test:unit                   # Unit tests only (fast)
composer test:integration           # Integration tests only (with WP)
composer test:integration:multisite # Integration tests in multisite mode
composer test:coverage              # All with coverage (unit + integration + multisite)
composer test:coverage:multisite    # Multisite coverage only
composer test:matrix                # Full matrix PHP 7.4-8.5 + PHPCS + PHPStan + E2E (like CI)

# Test Matrix (options)
bin/test-matrix.sh --php 7.4        # Single version only
bin/test-matrix.sh --parallel       # All versions in parallel
bin/test-matrix.sh --phpcs-only     # PHPCS + PHPStan only
bin/test-matrix.sh --tests-only     # PHPUnit only, skip PHPCS, PHPStan and E2E
bin/test-matrix.sh --e2e-only       # E2E only (Playwright + wp-env)
bin/test-matrix.sh --no-e2e         # Everything except E2E

# Code Quality
composer phpcs                      # Check WordPress Coding Standards
composer phpcbf                     # Auto-fix coding standards
composer analyse                    # PHPStan static analysis (level 6)

# WordPress Test Suite
composer install-wp-tests           # Install WP test suite (one-time)

# Build (production)
bin/build-zip.sh                    # Generate dist/ops-health-dashboard-VERSION.zip
bin/build-zip.sh --output /tmp/p.zip  # Custom output path
```

### Creating a New Feature

1. **Create feature branch:**
   ```bash
   git checkout -b feature/feature-name
   ```

2. **TDD - Write tests FIRST:**
   - Unit test in `tests/Unit/` (Brain\Monkey)
   - Integration test in `tests/Integration/` (WP Test Suite) if needed

3. **Implement feature:**
   - NO singleton, NO static, NO final
   - Constructor injection for dependencies
   - Follow WPCS

4. **Verify quality:**
   ```bash
   composer test
   composer phpcs
   composer analyse
   ```

5. **Commit with conventional commits:**
   ```bash
   git commit -m "feat(scope): feature description

   - Detail 1
   - Detail 2

   Tests: X passing
   Coverage: Y%

   Co-Authored-By: Claude Sonnet 4.5 <noreply@anthropic.com>"
   ```

## 📂 File Structure

```
ops-health-dashboard/
├── src/
│   ├── Core/              # Container, Plugin, Activator (NO singleton/static/final)
│   ├── Interfaces/        # Interface contracts (Check, CheckRunner, Storage, Redaction, HttpClient, AlertManager, AlertChannel)
│   ├── Services/          # Business logic (Storage, Scheduler, Redaction, CheckRunner, AlertManager, HttpClient)
│   ├── Checks/            # Health checks (DatabaseCheck, ErrorLogCheck, RedisCheck, DiskCheck, VersionsCheck)
│   ├── Channels/          # Notification channels (EmailChannel, WebhookChannel, SlackChannel, TelegramChannel, WhatsAppChannel)
│   └── Admin/             # UI wp-admin (Menu, HealthScreen, AlertSettings, DashboardWidget)
├── tests/
│   ├── Unit/              # Brain\Monkey tests (fast, isolated)
│   ├── Integration/       # WP Test Suite tests (real WordPress)
│   └── e2e/               # Playwright E2E tests (wp-env Docker)
├── config/
│   └── bootstrap.php      # DI container configuration
├── bin/
│   ├── build-zip.sh       # Generate production ZIP for WordPress upload
│   ├── e2e-setup.sh       # Create E2E test users
│   ├── install-wp-tests.sh # Setup WP test suite
│   └── test-matrix.sh     # Local matrix PHP 7.4-8.5
├── dist/                  # Build ZIP output (gitignored)
└── .github/workflows/     # CI/CD pipelines
```

## 🎭 Conventional Commits

```
<type>(<scope>): <description>

[optional body]

[optional footer]
```

**Types:**
- `feat`: New feature
- `fix`: Bug fix
- `docs`: Documentation only
- `style`: Formatting (no logic change)
- `refactor`: Code refactoring
- `test`: Adding/updating tests
- `chore`: Build/configuration

**Examples:**
```bash
feat(checks): add Redis health check with TDD

Implements RedisCheck class with:
- Extension detection
- Connection test
- Smoke test (SET/GET)
- Graceful degradation

Tests: 8 passing, 95% coverage

Co-Authored-By: Claude Sonnet 4.5 <noreply@anthropic.com>
```

## 🔍 Common Mistakes to AVOID

### ❌ DON'T: Use Singleton

```php
// WRONG - This will FAIL tests
class Service {
    private static $instance;
    public static function get_instance() { ... }
}
```

### ❌ DON'T: Use Static Methods

```php
// WRONG - This will FAIL tests
class Helper {
    public static function doSomething() { ... }
}
```

### ❌ DON'T: Use Final

```php
// WRONG - This will FAIL tests
final class Service { ... }
class Service {
    final public function method() { ... }
}
```

### ❌ DON'T: Direct Global Access

```php
// WRONG - Use dependency injection
function my_function() {
    global $wpdb;
    $wpdb->query(...);
}
```

### ❌ DON'T: Skip Tests

```php
// WRONG - Always write tests FIRST (TDD)
// 1. Write implementation
// 2. Write tests <-- NO!

// CORRECT - TDD workflow
// 1. Write test (RED)
// 2. Write minimal code (GREEN)
// 3. Refactor
```

## ✅ Best Practices

### Constructor Injection

```php
class Service {
    private $storage;
    private $redaction;

    public function __construct(
        StorageInterface $storage,
        RedactionInterface $redaction
    ) {
        $this->storage = $storage;
        $this->redaction = $redaction;
    }
}
```

### Container Configuration

```php
// config/bootstrap.php
function bootstrap(): Plugin {
    $container = new Container();

    // Shared instances (NOT singleton)
    $container->share(StorageInterface::class, function($c) {
        return new Storage();
    });

    $container->share(CheckRunnerInterface::class, function($c) {
        return new CheckRunner(
            $c->make(StorageInterface::class),
            $c->make(RedactionInterface::class)
        );
    });

    return new Plugin($container);
}
```

### Interface-First Design

```php
// 1. Define interface
interface CheckInterface {
    public function run(): array;
    public function get_id(): string;
    public function get_name(): string;
    public function is_enabled(): bool;
}

// 2. Implement concrete class
class DatabaseCheck implements CheckInterface {
    public function run(): array { ... }
    public function get_id(): string { ... }
    public function get_name(): string { ... }
    public function is_enabled(): bool { ... }
}

// 3. Inject via interface
class CheckRunner {
    private $checks = [];

    public function add_check(CheckInterface $check): void {
        $this->checks[] = $check;
    }
}
```

## 📊 CI/CD Pipeline

GitHub Actions runs automatically:
- ✅ PHPCS check (WordPress Coding Standards)
- ✅ PHPStan level 6 (Static Analysis with szepeviktor/phpstan-wordpress)
- ✅ PHPUnit on PHP 7.4, 8.0, 8.1, 8.2, 8.3, 8.4, 8.5
- ✅ Unit tests (Brain\Monkey) on all versions
- ✅ Integration tests (WP Test Suite) on all versions
- ✅ Coverage report (PHP 8.3 only)
- ✅ Codecov upload with separate flags (`unit`, `integration`) via `codecov-action@v5`
- ✅ `codecov.yml`: project threshold 95%, patch 90%, `carryforward: true`
- ✅ E2E Playwright (Chromium; desktop-only in CI, 3 viewports locally, wp-env Docker)

**Local Test Matrix** (replicate CI locally):
```bash
composer test:matrix                # PHPCS + PHPStan + PHPUnit on 7 PHP versions + E2E Playwright
```
Requires PHP 7.4-8.5 installed (via PPA sury) + Docker + Node.js for E2E. See `bin/test-matrix.sh --help`.

**Quality Gates (MUST PASS):**
- PHPCS: 100% compliance
- PHPStan: level 6, 0 errors
- Tests: 100% passing
- Coverage: 95% project, 90% patch (Codecov)

## 🎯 Current Status

**Milestone M6 - WordPress.org Readiness** ✅ COMPLETED

**Current State:**
- ✅ **574 unit tests** (Brain\Monkey), 1336 assertions
- ✅ **322 integration tests** (WP Test Suite), 655 assertions (single-site) / 684 assertions (multisite)
- ✅ **46 E2E scenarios** x 3 viewports = 138 local runs; CI desktop-only (46 tests)
- ✅ PHPCS 100% compliance (0 errors, 0 warnings)
- ✅ PHPStan level 6: 0 errors (szepeviktor/phpstan-wordpress)
- ✅ Full CI/CD with PHP 7.4-8.5 + E2E Playwright (desktop-only in CI)
- ✅ Coverage: **100%** classes, methods, lines (unit + integration single-site + multisite combined)
- ✅ 31 source files, 55 PHP test files (31 unit + 24 integration), 5 E2E spec files, 2 CSS files
- ✅ Pattern enforcement (NO singleton/static/final)
- ✅ WordPress.org ready: uninstall.php, readme.txt, ABSPATH guards
- ✅ HealthScreen UI: card grid, summary banner, dedicated CSS with native WordPress palette
- ✅ Multisite: Uninstaller supports multisite, uninstall.php with multisite fallback

**Implemented Components (M1+M2+M3+M4+M5+M6):**
- StorageInterface, CheckInterface, RedactionInterface, CheckRunnerInterface (DI contracts)
- HttpClientInterface (`post()` accepts `array|string` body), AlertManagerInterface, AlertChannelInterface (M4 contracts)
- Storage (Options API, sentinel pattern in `has()`, autoload=false)
- CheckRunner (try/catch, type safety, RedactionInterface, clear_results)
- DatabaseCheck ($wpdb injection, no info disclosure, i18n, RedactionInterface for $wpdb errors)
- Redaction (11 patterns: DB credentials, salts, API key, token, password, email, IP, path; IPv4 octet validation)
- ErrorLogCheck (tail log, severity aggregation, redacted samples, anti-symlink, flock LOCK_SH)
- RedisCheck (graceful degradation, extension+connection+auth+smoke test, response time, RedactionInterface)
- DiskCheck (configurable thresholds WARNING 20%/CRITICAL 10%, protected wrappers, RedactionInterface)
- VersionsCheck (WP/PHP versions, update notifications, graceful fallback, RECOMMENDED_PHP_VERSION = '8.3')
- HttpClient (anti-SSRF: private IP blocking, DNS validation, DNS pinning CURLOPT_RESOLVE anti-TOCTOU, schema/port restriction, no redirect, IPv6 rejection, HTTP 2xx validation)
- AlertManager (state change detection, cooldown pre-dispatch via transient, multi-channel dispatch with per-channel try/catch \Throwable isolation, alert log limited to 50 entries, STATUS_OK/WARNING/CRITICAL/UNKNOWN constants)
- EmailChannel (wp_mail, configurable recipients with is_email validation)
- WebhookChannel (JSON POST, HMAC signature on pre-serialized body with documented X-OpsHealth-Signature header)
- SlackChannel (Block Kit, color attachments, escape mrkdwn)
- TelegramChannel (Bot API, HTML parse mode with htmlspecialchars)
- WhatsAppChannel (generic webhook, Bearer auth, phone number with E.164 validation)
- Scheduler (WP-Cron 15 min, duplicate prevention, throttled self-healing, optional AlertManager integration, catch \Throwable)
- Container (DI with circular dependency detection)
- Menu (capability check, `load-{$page_hook}` for process_actions PRG, Alert Settings submenu)
- HealthScreen (capability check, Run Now/Clear Cache buttons with nonce, defensive validation, CheckRunnerInterface, CSS card grid + summary banner, enqueue_styles with screen guard)
- AlertSettings (alert configuration admin page, PRG, nonce, per-channel enable/disable + credentials with secret non-prefill + preserve-on-empty, cooldown)
- DashboardWidget (wp-admin dashboard widget, worst-status, capability check, CheckRunnerInterface)
- Activator (uses Scheduler::HOOK_NAME/INTERVAL constants)
- Uninstaller ($wpdb injection, multisite support with `uninstall_network()`, options/cron/transient cleanup, bulk delete cooldown via LIKE query)

**WordPress.org Readiness (M6):**
- uninstall.php with WP_UNINSTALL_PLUGIN guard + multisite fallback
- readme.txt in WordPress.org standard format
- ABSPATH guards on all source files with @codeCoverageIgnore
- Uninstaller with full multisite support (`uninstall_network()` + blog iteration)

## 📞 For Help

- **Issues**: https://github.com/mab056/ops-health-dashboard/issues
- **Documentation**: README.md, CONTRIBUTING.md, DEVELOPMENT_PLAN.md
- **CI Status**: https://github.com/mab056/ops-health-dashboard/actions

## 🙏 Remember

1. **TDD ALWAYS** - Tests before code
2. **NO SINGLETON** - Use Container->share()
3. **NO STATIC** - Use dependency injection
4. **NO FINAL** - Ensure testability
5. **SECURITY FIRST** - Sanitized inputs, escaped outputs, verified capabilities
6. **WPCS COMPLIANCE** - composer phpcs must pass

---

**Built with ❤️ and strict TDD**
