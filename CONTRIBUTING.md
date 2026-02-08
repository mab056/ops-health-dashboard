# Contribuire a Ops Health Dashboard

Grazie per il tuo interesse nel contribuire! Questo documento fornisce linee guida e requisiti per contribuire a questo progetto.

## 🎯 Principi Fondamentali

Questo progetto segue **standard di sviluppo rigorosi**:

1. **TDD (Test-Driven Development)** - Test prima del codice, sempre
2. **NO Singleton Pattern** - Usa dependency injection
3. **NO Static Methods/Properties** - Solo dipendenze esplicite
4. **NO Final Classes/Methods** - Garantisci testabilità ed estensibilità
5. **Security First** - L'hardening non è negoziabile
6. **WordPress Coding Standards** - Conformità WPCS richiesta

## 📋 Prerequisiti

Prima di contribuire, assicurati di avere:

- PHP 7.4+ installato (8.3+ raccomandato)
- Composer installato
- Git configurato
- Ambiente di sviluppo WordPress locale
- Familiarità con PHPUnit e TDD

## 🚀 Iniziare

### 1. Fork e Clone

```bash
# Fai il fork del repository su GitHub
# Poi clona il tuo fork
git clone https://github.com/YOUR_USERNAME/ops-health-dashboard.git
cd ops-health-dashboard

# Aggiungi il remote upstream
git remote add upstream https://github.com/mab056/ops-health-dashboard.git
```

### 2. Installa le Dipendenze

```bash
# Installa le dipendenze Composer
composer install

# Installa la suite di test WordPress
composer install-wp-tests
```

### 3. Crea il Feature Branch

```bash
# Aggiorna i branch principali
git checkout dev
git pull upstream dev

# Crea il feature branch
git checkout -b feature/your-feature-name

# Oppure per bug fix
git checkout -b fix/bug-description
```

## 🧪 TDD Workflow (OBBLIGATORIO)

**Ogni feature DEVE seguire il ciclo RED → GREEN → REFACTOR.**

### Step 1: RED - Scrivi un Test che Fallisce

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
			return strpos($method->getName(), '__') !== 0;
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

**Esegui il test - dovrebbe FALLIRE:**

```bash
composer test
# Expected: FAILURES! (Tests: X, Assertions: Y, Failures: Z)
```

### Step 2: GREEN - Implementa il Codice Minimo

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

**Esegui il test - dovrebbe PASSARE:**

```bash
composer test
# Expected: OK (X tests, Y assertions)
```

### Step 3: REFACTOR - Migliora la Qualità del Codice

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

**Esegui nuovamente i test - devono ancora passare:**

```bash
composer test
composer phpcs
```

## 🚫 Applicazione dei Pattern

### ❌ NON Usare il Singleton Pattern

```php
// ❌ SBAGLIATO - Questo farà fallire i test
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

### ✅ USA la Dependency Injection

```php
// ✅ CORRETTO
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

### ❌ NON Usare Static Methods

```php
// ❌ SBAGLIATO
class BadHelper {
	public static function doSomething() {
		return 'value';
	}
}
```

### ✅ USA Instance Methods

```php
// ✅ CORRETTO
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

### ❌ NON Usare il Modificatore Final

```php
// ❌ SBAGLIATO - Impedisce il testing con mock
final class BadClass {
	public function method() {}
}

class AnotherBadClass {
	final public function method() {}
}
```

### ✅ Mantieni le Classi Estensibili

```php
// ✅ CORRETTO - Testabile ed estensibile
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

## 🔒 Requisiti di Sicurezza

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

### Anti-SSRF per Webhooks

```php
// Use HttpClient service (includes anti-SSRF)
$client = $container->make(HttpClientInterface::class);

if (!$client->is_safe_url($url)) {
	throw new \Exception('Unsafe URL detected');
}

$response = $client->request($url, ['method' => 'POST']);
```

## 📝 Standard di Codifica

### PHP - WordPress Coding Standards

```bash
# Check standards
composer phpcs

# Auto-fix
composer phpcbf
```

### Regole Principali

- **Indentazione**: Tab (non spazi)
- **Lunghezza riga**: 120 caratteri (soft), 150 (hard)
- **Stile parentesi**: Allman style
- **Nomenclatura**:
  - Classi: `PascalCase`
  - Metodi: `snake_case`
  - Costanti: `UPPER_SNAKE_CASE`
  - Globali: prefisso `ops_health_`

### Documentazione

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

## 🔄 Processo di Pull Request

### 1. Prima di Aprire una PR

```bash
# Assicurati che tutti i test passino su tutte le versioni PHP
composer test:matrix

# Oppure almeno sulla versione corrente
composer test
composer phpcs

# Aggiorna DEVELOPMENT_PLAN.md se stai completando task di milestone

# Commit con formato conventional commit
git commit -m "feat: add new feature"
# oppure
git commit -m "fix: resolve bug"
```

### 2. Formato del Messaggio di Commit

Segui [Conventional Commits](https://www.conventionalcommits.org/):

```
<type>(<scope>): <description>

[optional body]

[optional footer]

Co-Authored-By: Your Name <your.email@example.com>
```

**Tipi:**
- `feat`: Nuova funzionalità
- `fix`: Correzione di bug
- `docs`: Solo documentazione
- `style`: Stile del codice (formattazione, nessun cambio di logica)
- `refactor`: Refactoring del codice
- `test`: Aggiunta/aggiornamento test
- `chore`: Modifiche di build/configurazione

**Esempi:**

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

### 3. Apri la Pull Request

- **Titolo**: Uguale all'oggetto del messaggio di commit
- **Descrizione**:
  - Quali modifiche sono state fatte
  - Perché (link all'issue se applicabile)
  - Come testare
  - Screenshot (se ci sono modifiche UI)
- **Branch di base**: `dev` (non `main`)

### 4. Checklist della PR

- [ ] Test scritti prima (TDD)
- [ ] Tutti i test passano su tutte le versioni PHP (`composer test:matrix`)
- [ ] PHPCS passa (`composer phpcs`)
- [ ] NON è stato usato il singleton pattern
- [ ] NON sono stati usati static methods
- [ ] NON ci sono classi/metodi final
- [ ] Sicurezza: input sanitizzati, output escaped
- [ ] Documentazione aggiornata (se necessario)
- [ ] CHANGELOG.md aggiornato
- [ ] Formato conventional commit

### 5. Processo di Review

- I maintainer effettueranno la review entro 3-5 giorni lavorativi
- Rispondi al feedback con nuovi commit
- Una volta approvato, squash e merge su `dev`

## 🐛 Segnalazione Bug

### Prima di Aprire un Issue

1. Cerca negli issue esistenti
2. Prova l'ultima versione
3. Riproduci con un test case minimo

### Template dell'Issue

```markdown
**Descrivi il bug**
Descrizione chiara di cosa sia il bug.

**Come Riprodurre**
Passi per riprodurre:
1. Vai a '...'
2. Clicca su '...'
3. Vedi l'errore

**Comportamento atteso**
Cosa ti aspettavi che accadesse.

**Ambiente:**
- Versione PHP: [es. 8.3]
- Versione WordPress: [es. 6.4]
- Versione Plugin: [es. 1.0.0]

**Contesto aggiuntivo**
Qualsiasi altro contesto sul problema.
```

## 💡 Richieste di Funzionalità

### Prima di Richiedere

1. Controlla la roadmap in [DEVELOPMENT_PLAN.md](DEVELOPMENT_PLAN.md)
2. Cerca negli issue esistenti
3. Considera la conformità con WordPress.org

### Template della Richiesta

```markdown
**Descrizione della Funzionalità**
Descrizione chiara della funzionalità.

**Caso d'Uso**
Perché questa funzionalità è necessaria? Quale problema risolve?

**Soluzione Proposta**
Come la implementeresti?

**Alternative Considerate**
Altri approcci a cui hai pensato.
```

## 📚 Risorse

- [WordPress Plugin Handbook](https://developer.wordpress.org/plugins/)
- [WordPress Coding Standards](https://make.wordpress.org/core/handbook/best-practices/coding-standards/php/)
- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- [Conventional Commits](https://www.conventionalcommits.org/)
- [TDD Best Practices](https://testdriven.io/)

## ❓ Domande

- **Domande generali**: Apri una [GitHub Discussion](https://github.com/mab056/ops-health-dashboard/discussions)
- **Segnalazione bug**: Apri un [Issue](https://github.com/mab056/ops-health-dashboard/issues)
- **Problemi di sicurezza**: Invia un'email direttamente (NON aprire un issue pubblico)

## 📄 Licenza

Contribuendo, accetti che i tuoi contributi saranno rilasciati sotto licenza GPL-3.0-or-later.

---

**Grazie per aver contribuito! 🙏**
