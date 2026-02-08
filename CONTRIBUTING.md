# Contributing to Ops Health Dashboard

Thank you for your interest in contributing! This document provides guidelines and requirements for contributing to this project.

## 🎯 Core Principles

This project follows **strict development standards**:

1. **TDD (Test-Driven Development)** - Tests before code, always
2. **NO Singleton Pattern** - Use dependency injection
3. **NO Static Methods/Properties** - Explicit dependencies only
4. **NO Final Classes/Methods** - Ensure testability and extensibility
5. **Security First** - Hardening is non-negotiable
6. **WordPress Coding Standards** - WPCS compliance required

## 📋 Prerequisites

Before contributing, ensure you have:

- PHP 7.4+ installed (8.3+ recommended)
- Composer installed
- Git configured
- WordPress local development environment
- Familiarity with PHPUnit and TDD

## 🚀 Getting Started

### 1. Fork and Clone

```bash
# Fork the repository on GitHub
# Then clone your fork
git clone https://github.com/YOUR_USERNAME/ops-health-dashboard.git
cd ops-health-dashboard

# Add upstream remote
git remote add upstream https://github.com/mab056/ops-health-dashboard.git
```

### 2. Install Dependencies

```bash
# Install Composer dependencies
php composer.phar install

# Install WordPress test suite
bash bin/install-wp-tests.sh wordpress_test root '' localhost latest
```

### 3. Create Feature Branch

```bash
# Update main branches
git checkout dev
git pull upstream dev

# Create feature branch
git checkout -b feature/your-feature-name

# Or for bug fixes
git checkout -b fix/bug-description
```

## 🧪 TDD Workflow (MANDATORY)

**Every feature MUST follow the RED → GREEN → REFACTOR cycle.**

### Step 1: RED - Write Failing Test

```php
// tests/Unit/Services/MyNewServiceTest.php
<?php
namespace OpsHealthDashboard\Tests\Unit\Services;

use OpsHealthDashboard\Services\MyNewService;
use PHPUnit\Framework\TestCase;

class MyNewServiceTest extends TestCase {

    /**
     * Test new feature behavior
     *
     * This test will FAIL until implementation exists
     */
    public function test_my_new_feature_returns_expected_result() {
        $service = new MyNewService();
        $result = $service->doSomething();

        $this->assertEquals('expected', $result);
    }

    /**
     * CRITICAL: Test NO static methods
     */
    public function test_no_static_methods() {
        $reflection = new \ReflectionClass(MyNewService::class);
        $methods = $reflection->getMethods(\ReflectionMethod::IS_STATIC);

        $static_methods = array_filter($methods, function($method) {
            return !str_starts_with($method->getName(), '__');
        });

        $this->assertEmpty($static_methods, 'Class should have NO static methods');
    }

    /**
     * CRITICAL: Test class is NOT final
     */
    public function test_class_is_not_final() {
        $reflection = new \ReflectionClass(MyNewService::class);
        $this->assertFalse($reflection->isFinal(), 'Class should NOT be final');
    }
}
```

**Run test - it should FAIL:**

```bash
php composer.phar test
# Expected: FAILURES! (Tests: X, Assertions: Y, Failures: Z)
```

### Step 2: GREEN - Implement Minimal Code

```php
// src/Services/MyNewService.php
<?php
namespace OpsHealthDashboard\Services;

/**
 * Class MyNewService
 *
 * NO singleton, NO static, NO final
 */
class MyNewService {

    /**
     * Do something
     *
     * @return string
     */
    public function doSomething(): string {
        return 'expected';
    }
}
```

**Run test - it should PASS:**

```bash
php composer.phar test
# Expected: OK (X tests, Y assertions)
```

### Step 3: REFACTOR - Improve Code Quality

```php
// Add error handling, validation, dependencies, etc.
class MyNewService {

    private $dependency;

    /**
     * Constructor injection (NO singleton)
     */
    public function __construct(DependencyInterface $dependency) {
        $this->dependency = $dependency;
    }

    /**
     * Do something with proper error handling
     */
    public function doSomething(): string {
        try {
            $result = $this->dependency->process();
            return $this->validate($result);
        } catch (\Exception $e) {
            // Handle error
            return 'default';
        }
    }

    private function validate(string $result): string {
        return sanitize_text_field($result);
    }
}
```

**Run tests again - still passing:**

```bash
php composer.phar test
php composer.phar phpcs
```

## 🚫 Pattern Enforcement

### ❌ DO NOT Use Singleton Pattern

```php
// ❌ WRONG - This will fail tests
class BadService {
    private static $instance;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {}
}
```

### ✅ DO Use Dependency Injection

```php
// ✅ CORRECT
class GoodService {
    private $container;

    // Constructor injection
    public function __construct(Container $container) {
        $this->container = $container;
    }
}

// Bootstrap configuration
function bootstrap(): Plugin {
    $container = new Container();

    // Register shared instance (NOT singleton)
    $container->share(GoodService::class, function($c) {
        return new GoodService($c);
    });

    return new Plugin($container);
}
```

### ❌ DO NOT Use Static Methods

```php
// ❌ WRONG
class BadHelper {
    public static function doSomething() {
        return 'value';
    }
}
```

### ✅ DO Use Instance Methods

```php
// ✅ CORRECT
class GoodHelper {
    public function doSomething(): string {
        return 'value';
    }
}

// Inject where needed
class Consumer {
    private $helper;

    public function __construct(GoodHelper $helper) {
        $this->helper = $helper;
    }
}
```

### ❌ DO NOT Use Final Modifier

```php
// ❌ WRONG - Prevents testing with mocks
final class BadClass {
    public function method() {}
}

class AnotherBadClass {
    final public function method() {}
}
```

### ✅ DO Keep Classes Extensible

```php
// ✅ CORRECT - Testable and extensible
class GoodClass {
    public function method() {}
}

// Can be mocked in tests
class GoodClassMock extends GoodClass {
    public function method() {
        return 'mocked';
    }
}
```

## 🔒 Security Requirements

### Input Sanitization

```php
// Always sanitize user input
$text = sanitize_text_field($_POST['field']);
$email = sanitize_email($_POST['email']);
$url = esc_url_raw($_POST['url']);
$int = absint($_POST['number']);
```

### Output Escaping

```php
// Always escape output
echo esc_html($text);
echo esc_attr($attribute);
echo esc_url($url);
echo esc_js($javascript);
```

### Capability Checks

```php
// Check user capabilities
if (!current_user_can('manage_options')) {
    wp_die(__('Unauthorized', 'ops-health-dashboard'));
}
```

### Nonces

```php
// Generate nonce in form
wp_nonce_field('ops_health_action', 'ops_health_nonce');

// Verify nonce in handler
if (!wp_verify_nonce($_POST['ops_health_nonce'], 'ops_health_action')) {
    wp_die(__('Invalid nonce', 'ops-health-dashboard'));
}
```

### Anti-SSRF for Webhooks

```php
// Use HttpClient service (includes anti-SSRF)
$client = $container->make(HttpClientInterface::class);

if (!$client->is_safe_url($url)) {
    throw new \Exception('Unsafe URL detected');
}

$response = $client->request($url, ['method' => 'POST']);
```

## 📝 Coding Standards

### PHP - WordPress Coding Standards

```bash
# Check standards
php composer.phar phpcs

# Auto-fix
php composer.phar phpcbf
```

### Key Rules

- **Indentation**: Tabs (not spaces)
- **Line length**: 120 chars (soft), 150 (hard)
- **Brace style**: Allman style
- **Naming**:
  - Classes: `PascalCase`
  - Methods: `snake_case`
  - Constants: `UPPER_SNAKE_CASE`
  - Globals: `ops_health_` prefix

### Documentation

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

## 🔄 Pull Request Process

### 1. Before Opening PR

```bash
# Ensure all tests pass
php composer.phar test

# Ensure PHPCS passes
php composer.phar phpcs

# Update DEVELOPMENT_PLAN.md if completing milestone tasks

# Commit with conventional commit format
git commit -m "feat: add new feature"
# or
git commit -m "fix: resolve bug"
```

### 2. Commit Message Format

Follow [Conventional Commits](https://www.conventionalcommits.org/):

```
<type>(<scope>): <description>

[optional body]

[optional footer]

Co-Authored-By: Your Name <your.email@example.com>
```

**Types:**
- `feat`: New feature
- `fix`: Bug fix
- `docs`: Documentation only
- `style`: Code style (formatting, no logic change)
- `refactor`: Code refactoring
- `test`: Adding/updating tests
- `chore`: Build/config changes

**Examples:**

```bash
feat(checks): add Redis health check with TDD

Implements RedisCheck class with:
- Extension detection
- Connection test
- Smoke test (SET/GET)
- Graceful degradation

Tests: 8 passing
Coverage: 95%

Co-Authored-By: Your Name <your.email@example.com>
```

```bash
fix(container): resolve make() with circular dependencies

Adds detection for circular dependencies and throws
descriptive exception instead of infinite loop.

Tests: 3 new tests
```

### 3. Open Pull Request

- **Title**: Same as commit message subject
- **Description**:
  - What changes were made
  - Why (link to issue if applicable)
  - How to test
  - Screenshots (if UI changes)
- **Base branch**: `dev` (not `main`)

### 4. PR Checklist

- [ ] Tests written first (TDD)
- [ ] All tests passing (`php composer.phar test`)
- [ ] PHPCS passing (`php composer.phar phpcs`)
- [ ] NO singleton pattern used
- [ ] NO static methods used
- [ ] NO final classes/methods
- [ ] Security: inputs sanitized, outputs escaped
- [ ] Documentation updated (if needed)
- [ ] CHANGELOG.md updated
- [ ] Conventional commit format

### 5. Review Process

- Maintainers will review within 3-5 business days
- Address feedback in new commits
- Once approved, squash and merge to `dev`

## 🐛 Bug Reports

### Before Opening Issue

1. Search existing issues
2. Try latest version
3. Reproduce with minimal test case

### Issue Template

```markdown
**Describe the bug**
Clear description of what the bug is.

**To Reproduce**
Steps to reproduce:
1. Go to '...'
2. Click on '...'
3. See error

**Expected behavior**
What you expected to happen.

**Environment:**
- PHP Version: [e.g. 8.3]
- WordPress Version: [e.g. 6.4]
- Plugin Version: [e.g. 1.0.0]

**Additional context**
Any other context about the problem.
```

## 💡 Feature Requests

### Before Requesting

1. Check roadmap in [DEVELOPMENT_PLAN.md](DEVELOPMENT_PLAN.md)
2. Search existing issues
3. Consider WordPress.org compliance

### Request Template

```markdown
**Feature Description**
Clear description of the feature.

**Use Case**
Why is this feature needed? What problem does it solve?

**Proposed Solution**
How would you implement this?

**Alternatives Considered**
Other approaches you've thought about.
```

## 📚 Resources

- [WordPress Plugin Handbook](https://developer.wordpress.org/plugins/)
- [WordPress Coding Standards](https://make.wordpress.org/core/handbook/best-practices/coding-standards/php/)
- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- [Conventional Commits](https://www.conventionalcommits.org/)
- [TDD Best Practices](https://testdriven.io/)

## ❓ Questions

- **General questions**: Open a [GitHub Discussion](https://github.com/mab056/ops-health-dashboard/discussions)
- **Bug reports**: Open an [Issue](https://github.com/mab056/ops-health-dashboard/issues)
- **Security issues**: Email directly (do NOT open public issue)

## 📄 License

By contributing, you agree that your contributions will be licensed under GPL-2.0-or-later.

---

**Thank you for contributing! 🙏**
