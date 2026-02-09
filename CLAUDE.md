# Claude.ai Instructions - Ops Health Dashboard

Questo file contiene istruzioni specifiche per Claude (o altri assistenti AI) su come lavorare con questo progetto WordPress.

## 🎯 Obiettivo del Progetto

Plugin WordPress **production-grade** per monitoraggio operativo con:
- Health checks automatici (Database, Redis, Disk, Error Logs, Versions)
- Alerting multi-canale (Email, Webhook, Slack, Telegram, WhatsApp)
- Dashboard wp-admin con stato globale
- WP-Cron scheduling (default 15 min)
- WordPress.org ready

## ⚠️ Pattern Architetturali NON NEGOZIABILI

### 🚫 NO Singleton, NO Static, NO Final

**CRITICO:** Questo progetto VIETA rigorosamente questi anti-pattern:

```php
// ❌ MAI FARE QUESTO
class BadService {
    private static $instance;
    public static function get_instance() { ... }
    private function __construct() {}
}

// ❌ MAI FARE QUESTO
class BadHelper {
    public static function doSomething() { ... }
}

// ❌ MAI FARE QUESTO
final class BadClass {
    final public function method() {}
}

// ✅ SEMPRE FARE QUESTO
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

**Motivazione:**
- **Testabilita**: singleton/static rendono il mocking molto difficile
- **Flessibilita**: `final` limita l'estensibilita
- **Best Practices**: WordPress core si è allontanato dai singleton
- **Dependency Injection**: Tutte le dipendenze devono essere esplicite

### ✅ Container Pattern

```php
// Usa Container->share() per istanze condivise (NON singleton)
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

## 🧪 Testing Strategy - APPROCCIO MISTO

### Unit Tests (tests/Unit/) - Brain\Monkey

**Quando usare:**
- Logica business pura
- Services (HttpClient, Redaction, Storage wrapper)
- Utilities (formatter, validator, sanitizer)
- Container, Plugin (logica pura senza WordPress)

**Caratteristiche:**
- ⚡ Velocissimi (~0.9s per 178 test)
- 🔒 Isolamento completo
- 🧬 Mock di funzioni WordPress con Brain\Monkey
- ❌ NO database, NO filesystem, NO WordPress

**Esempio:**
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

**Comandi:**
```bash
composer test:unit                    # Esegue solo test unitari
composer test:coverage:unit          # Unit tests con coverage
```

### Integration Tests (tests/Integration/) - WordPress Test Suite

**Quando usare:**
- Activator (Options API, WP-Cron reali)
- Admin pages (menu, dashboard widget, AJAX)
- Database checks (`$wpdb->query`)
- Hooks/filters registration
- Qualsiasi cosa che tocca WordPress core

**Caratteristiche:**
- ✅ WordPress completo caricato
- ✅ Database MySQL reale
- ✅ WP-Cron, Options API, hooks reali
- 🐢 Più lenti (~0.2s con 47 test, richiede WP install)

**Esempio:**
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

**Setup richiesto:**
```bash
composer install-wp-tests             # Una tantum
composer test:integration            # Esegue solo test di integrazione
```

### TDD Workflow - RED → GREEN → REFACTOR

**SEMPRE seguire questo ciclo:**

1. **RED**: Scrivi il test che fallisce
   ```php
   public function test_new_feature() {
       $service = new MyService();
       $result = $service->newFeature();
       $this->assertEquals('expected', $result);
   }
   ```

2. **GREEN**: Codice minimo per passare
   ```php
   public function newFeature(): string {
       return 'expected';
   }
   ```

3. **REFACTOR**: Cleanup + ottimizzazione
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

**Test di Pattern Enforcement (OBBLIGATORI):**

Ogni classe DEVE avere questi test:

```php
/**
 * Testa che la classe NON è final
 */
public function test_class_is_not_final() {
    $reflection = new \ReflectionClass(MyClass::class);
    $this->assertFalse($reflection->isFinal(), 'Class should NOT be final');
}

/**
 * Testa che NON esistono metodi static
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

### Input Sanitization (SEMPRE)

```php
// User input
$text = sanitize_text_field($_POST['field']);
$email = sanitize_email($_POST['email']);
$url = esc_url_raw($_POST['url']);
$int = absint($_POST['number']);
```

### Output Escaping (SEMPRE)

```php
// Output
echo esc_html($text);
echo esc_attr($attribute);
echo esc_url($url);
echo esc_js($javascript);
```

### Capability checks (SEMPRE sulle pagine admin)

```php
if (!current_user_can('manage_options')) {
    wp_die(__('Unauthorized', 'ops-health-dashboard'));
}
```

### Nonce (SEMPRE su form/AJAX)

```php
// Generate in form
wp_nonce_field('ops_health_action', 'ops_health_nonce');

// Verify in handler
if (!wp_verify_nonce($_POST['ops_health_nonce'], 'ops_health_action')) {
    wp_die(__('Invalid nonce', 'ops-health-dashboard'));
}
```

### Anti-SSRF per Webhooks (CRITICO)

```php
// Use HttpClient service (multi-layer protection)
$client = $container->make(HttpClientInterface::class);

if (!$client->is_safe_url($url)) {
    throw new \Exception('Unsafe URL detected');
}

$response = $client->request($url, ['method' => 'POST']);
```

## 📝 Coding Standards

### WordPress Coding Standards (WPCS)

```bash
composer phpcs              # Check standards
composer phpcbf             # Auto-fix
```

**Regole principali:**
- Indentazione: **Tab** (non spazi)
- Lunghezza riga: 120 soft, 150 hard
- Stile parentesi: Allman style
- Nomenclatura:
  - Classi: `PascalCase`
  - Metodi: `snake_case`
  - Costanti: `UPPER_SNAKE_CASE`
  - Globali: prefisso `ops_health_`

### PHPDoc (SEMPRE)

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

### Setup Iniziale

```bash
git clone https://github.com/mab056/ops-health-dashboard.git
cd ops-health-dashboard
composer install
```

### Comandi Comuni

```bash
# Testing
composer test                        # Tutti i test (unit + integration)
composer test:unit                   # Solo test unitari (veloce)
composer test:integration           # Solo test di integrazione (con WP)
composer test:coverage              # Tutti con coverage
composer test:matrix                # Matrice completa PHP 7.4-8.5 + PHPCS (come CI)

# Test Matrix (opzioni)
bin/test-matrix.sh --php 7.4        # Solo una versione
bin/test-matrix.sh --parallel       # Tutte le versioni in parallelo
bin/test-matrix.sh --phpcs-only     # Solo PHPCS
bin/test-matrix.sh --tests-only     # Solo PHPUnit, salta PHPCS

# Code Quality
composer phpcs                      # Check WordPress Coding Standards
composer phpcbf                     # Auto-fix coding standards

# WordPress Test Suite
composer install-wp-tests           # Installa WP test suite (una tantum)

# Build (produzione)
bin/build-zip.sh                    # Genera dist/ops-health-dashboard-VERSION.zip
bin/build-zip.sh --output /tmp/p.zip  # Output path custom
```

### Creare una Nuova Feature

1. **Crea feature branch:**
   ```bash
   git checkout -b feature/nome-feature
   ```

2. **TDD - Scrivi test PRIMA:**
   - Unit test in `tests/Unit/` (Brain\Monkey)
   - Integration test in `tests/Integration/` (WP Test Suite) se necessario

3. **Implementa feature:**
   - NO singleton, NO static, NO final
   - Constructor injection per dipendenze
   - Seguire WPCS

4. **Verifica qualità:**
   ```bash
   composer test
   composer phpcs
   ```

5. **Commit con conventional commits:**
   ```bash
   git commit -m "feat(scope): descrizione feature

   - Dettaglio 1
   - Dettaglio 2

   Tests: X passing
   Coverage: Y%

   Co-Authored-By: Claude Sonnet 4.5 <noreply@anthropic.com>"
   ```

## 📂 File Structure

```
ops-health-dashboard/
├── src/
│   ├── Core/              # Container, Plugin, Activator (NO singleton/static/final)
│   ├── Interfaces/        # Interface contracts (CheckInterface, NotifierInterface, etc.)
│   ├── Services/          # Business logic (Storage, HttpClient, Redaction, CheckRunner)
│   ├── Checks/            # Health checks (DatabaseCheck, RedisCheck, DiskCheck, etc.)
│   ├── Alerts/            # Notifiers (EmailNotifier, WebhookNotifier, SlackNotifier, etc.)
│   ├── Admin/             # UI wp-admin (Menu, HealthScreen, DashboardWidget, AjaxHandler)
│   └── Utilities/         # Helpers
├── tests/
│   ├── Unit/              # Brain\Monkey tests (veloce, isolato)
│   └── Integration/       # WP Test Suite tests (WordPress reale)
├── config/
│   └── bootstrap.php      # DI container configuration
├── bin/
│   ├── build-zip.sh       # Genera ZIP produzione per WordPress upload
│   ├── install-wp-tests.sh # Setup WP test suite
│   └── test-matrix.sh     # Matrice locale PHP 7.4-8.5
├── dist/                  # Output build ZIP (gitignored)
└── .github/workflows/     # CI/CD pipelines
```

## 🎭 Conventional Commits

```
<type>(<scope>): <description>

[optional body]

[optional footer]
```

**Types:**
- `feat`: Nuova funzionalità
- `fix`: Correzione bug
- `docs`: Solo documentazione
- `style`: Formattazione (no logic change)
- `refactor`: Refactoring codice
- `test`: Aggiunta/aggiornamento test
- `chore`: Build/configurazione

**Esempi:**
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
    private $http_client;

    public function __construct(
        StorageInterface $storage,
        HttpClientInterface $http_client
    ) {
        $this->storage = $storage;
        $this->http_client = $http_client;
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

    $container->share(HttpClientInterface::class, function($c) {
        return new HttpClient();
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

GitHub Actions esegue automaticamente:
- ✅ PHPCS check (WordPress Coding Standards)
- ✅ PHPUnit su PHP 7.4, 8.0, 8.1, 8.2, 8.3, 8.4, 8.5
- ✅ Unit tests (Brain\Monkey) su tutte le versioni
- ✅ Integration tests (WP Test Suite) su tutte le versioni
- ✅ Coverage report (solo PHP 8.3)
- ✅ Upload Codecov

**Test Matrix Locale** (replica CI localmente):
```bash
composer test:matrix                # PHPCS + PHPUnit su tutte le 7 versioni PHP
```
Richiede PHP 7.4-8.5 installati (via PPA sury). Vedi `bin/test-matrix.sh --help`.

**Quality Gates (DEVONO PASSARE):**
- PHPCS: 100% compliance
- Tests: 100% passing
- Coverage: ≥85% (target)

## 🎯 Current Status

**Milestone M3 - Check Redis** ✅ COMPLETATO

**Stato Attuale:**
- ✅ 203 test unitari (Brain\Monkey)
- ✅ 53 test di integrazione (WP Test Suite)
- ✅ 256 test totali passing, 572 assertions
- ✅ PHPCS 100% compliance (0 errori, 0 warning)
- ✅ CI/CD completo con PHP 7.4-8.5
- ✅ 16 file sorgente, 27 file di test (16 unit + 11 integration)
- ✅ Pattern enforcement (NO singleton/static/final)

**Componenti Implementati (M1+M2+M3):**
- StorageInterface, CheckInterface, RedactionInterface, CheckRunnerInterface (contratti DI)
- Storage (Options API, sentinel pattern in `has()`, autoload=false)
- CheckRunner (try/catch, type safety, RedactionInterface, clear_results)
- DatabaseCheck ($wpdb injection, no info disclosure, i18n, RedactionInterface per $wpdb errors)
- Redaction (11 pattern: credenziali DB, salts, API key, token, password, email, IP, path; IPv4 validazione ottetti)
- ErrorLogCheck (tail log, aggregazione severità, campioni redatti, anti-symlink, flock LOCK_SH)
- RedisCheck (graceful degradation, estensione+connessione+auth+smoke test, response time, RedactionInterface)
- Scheduler (WP-Cron 15 min, prevenzione duplicati, self-healing admin-only, costanti HOOK_NAME/INTERVAL)
- Container (DI con rilevazione dipendenze circolari)
- Menu (capability check, `load-{$page_hook}` per process_actions PRG)
- HealthScreen (capability check, bottoni Run Now/Clear Cache con nonce, validazione difensiva, CheckRunnerInterface)
- Activator (usa costanti Scheduler::HOOK_NAME/INTERVAL)

**Next: M4 - Alerting System**

## 📞 Per Aiuto

- **Issues**: https://github.com/mab056/ops-health-dashboard/issues
- **Documentazione**: README.md, CONTRIBUTING.md, DEVELOPMENT_PLAN.md
- **CI Status**: https://github.com/mab056/ops-health-dashboard/actions

## 🙏 Remember

1. **TDD SEMPRE** - Test prima del codice
2. **NO SINGLETON** - Usa Container->share()
3. **NO STATIC** - Usa dependency injection
4. **NO FINAL** - Assicura testabilità
5. **SECURITY FIRST** - Input sanitized, output escaped, capabilities checked
6. **WPCS COMPLIANCE** - composer phpcs deve passare

---

**Built with ❤️ and strict TDD**
