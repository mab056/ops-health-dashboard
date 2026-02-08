# Ops Health Dashboard

[![CI](https://github.com/mab056/ops-health-dashboard/workflows/CI/badge.svg)](https://github.com/mab056/ops-health-dashboard/actions)
[![PHP Version](https://img.shields.io/badge/PHP-7.4%2B-blue)](https://www.php.net/)
[![WordPress](https://img.shields.io/badge/WordPress-5.8%2B-blue)](https://wordpress.org/)
[![License](https://img.shields.io/badge/License-GPL%20v2%2B-blue.svg)](LICENSE)

Plugin WordPress di monitoraggio operativo production-grade con controlli automatici e alert configurabili.

## 🎯 Problema

**"Non so cosa sta succedendo finché non esplode."**

Questo plugin fornisce una dashboard operativa in wp-admin con controlli automatici di salute e alerting configurabile (email, webhook, Slack, Telegram, WhatsApp) per sapere cosa sta succedendo *prima* che si rompa.

## ✨ Funzionalità (MVP)

### Controlli di Salute
- **Database** - Connettività + performance query
- **Redis** - Rilevamento + smoke test (opzionale)
- **Spazio Disco** - Libero/totale con soglie configurabili
- **Log Errori** - Aggregazione sicura con redaction automatica
- **Versioni** - WordPress, PHP, temi, plugin + notifiche aggiornamenti

### Dashboard
- Pagina admin: `Ops → Health Dashboard`
- Widget dashboard con stato globale (✅/⚠️/🛑)
- Riepilogo "Cosa è cambiato nelle ultime 24h"
- Pulsante manuale "Esegui Check Ora"

### Alerting
- Email via `wp_mail()`
- Webhook generico (POST JSON)
- Slack (opt-in con Incoming Webhook)
- Telegram (opt-in con Bot API)
- WhatsApp (via webhook generico)
- Cooldown intelligente per prevenire spam di alert

### Scheduling
- WP-Cron (default: ogni 15 minuti)
- Trigger manuale
- Alert solo su cambiamenti di stato

## 🏗️ Architettura

Costruito con **OOP moderno, TDD e hardening di sicurezza rigoroso**.

### Principi Core

#### ⚠️ NO Singleton, NO Static, NO Final

Questo plugin evita rigorosamente anti-pattern che danneggiano la testabilità:

```php
// ❌ SBAGLIATO - Pattern singleton
class Plugin {
    private static $instance;
    public static function get_instance() { ... }
}

// ✅ CORRETTO - Dependency injection
class Plugin {
    private $container;
    public function __construct(Container $container) {
        $this->container = $container;
    }
}
```

**Perché?**
- **Testabilità**: Singleton e static rendono impossibile il mocking
- **Flessibilità**: Final impedisce l'estensibilità
- **Best Practices**: WordPress core si è allontanato dai singleton
- **Prevedibilità**: Dipendenze esplicite sono più facili da tracciare

### Struttura Directory

```
ops-health-dashboard/
├── src/
│   ├── Core/           # Container, Plugin, Activator
│   ├── Interfaces/     # CheckInterface, NotifierInterface, ecc.
│   ├── Checks/         # Implementazioni controlli salute
│   ├── Alerts/         # Canali di notifica
│   ├── Services/       # Logica business (Storage, HttpClient, ecc.)
│   ├── Admin/          # UI wp-admin
│   └── Utilities/      # Helper
├── tests/
│   ├── Unit/           # Test unitari (isolati)
│   └── Integration/    # Test integrazione (WordPress caricato)
├── tests-e2e/          # Test E2E Playwright
└── config/             # Bootstrap e configurazione DI
```

### Componenti Chiave

- **Container** - Container DI lightweight con `share()` (non singleton)
- **Plugin** - Orchestratore principale con constructor injection
- **CheckRunner** - Orchestra i controlli di salute
- **Storage** - Wrapper WordPress Options API
- **HttpClient** - Richieste HTTP protette anti-SSRF
- **Redaction** - Sanitizzazione dati sensibili

## 📋 Requisiti

- **PHP**: 7.4+ (minimo dichiarato), 8.3+ (raccomandato)
- **WordPress**: 5.8+
- **MySQL**: 5.7+ o MariaDB 10.2+
- **Composer**: Per dipendenze di sviluppo

## 🚀 Installazione

### Per Utenti (Produzione)

1. Scarica l'ultima release da [GitHub Releases](https://github.com/mab056/ops-health-dashboard/releases)
2. Carica in `/wp-content/plugins/ops-health-dashboard/`
3. Attiva tramite admin WordPress
4. Naviga su `Ops → Health Dashboard`

### Per Sviluppatori (Sviluppo Locale)

```bash
# Clona repository
git clone https://github.com/mab056/ops-health-dashboard.git
cd ops-health-dashboard

# Installa dipendenze
composer install

# Esegui test unitari (veloce, no WordPress)
composer test:unit

# Installa suite test WordPress per integration tests
composer install-wp-tests

# Esegui tutti i test (unit + integration)
composer test

# Esegui PHPCS
composer phpcs
```

## 🧪 Testing

Questo progetto segue **TDD rigoroso** (Test-Driven Development) con **approccio misto**:

### Approccio Test Misto

**Unit Tests (Brain\Monkey)** - Veloce, isolato
- Logica business pura, NO WordPress
- ~24 test, ~0.3s
- Perfetto per TDD rapido

**Integration Tests (WP Test Suite)** - WordPress reale
- Test con WordPress completo, database, WP-Cron
- ~8 test, ~3-5s
- Verifica integrazione reale con WordPress

### Comandi Test

```bash
# Unit tests (veloce, no WordPress required)
composer test:unit

# Integration tests (richiede WP test suite)
composer install-wp-tests              # Setup una tantum
composer test:integration

# Tutti i test (unit + integration)
composer test

# Con coverage (richiede Xdebug)
composer test:coverage

# PHPCS (WordPress Coding Standards)
composer phpcs

# Auto-fix problemi PHPCS
composer phpcbf
```

### Workflow TDD

Ogni funzionalità segue: **RED → GREEN → REFACTOR**

1. **RED**: Scrivi prima il test che fallisce (unit test con Brain\Monkey)
2. **GREEN**: Scrivi codice minimo per passare
3. **REFACTOR**: Pulisci e ottimizza
4. **INTEGRATION**: Aggiungi integration test per verificare con WordPress reale

Esempio:

```php
// RED: Unit test con Brain\Monkey (veloce)
public function test_database_check_calls_wpdb() {
    Functions\expect('sanitize_text_field')
        ->once()
        ->andReturnFirstArg();

    $check = new DatabaseCheck($storage);
    $result = $check->run();
    $this->assertEquals('ok', $result['status']);
}

// GREEN: Implementa codice minimo
public function run(): array {
    global $wpdb;
    $result = $wpdb->query('SELECT 1');
    return ['status' => $result !== false ? 'ok' : 'critical'];
}

// REFACTOR + INTEGRATION: Aggiungi test con WordPress reale
public function test_database_check_with_real_wordpress() {
    $check = new DatabaseCheck($storage);
    $result = $check->run();

    // Test con database MySQL reale
    $this->assertArrayHasKey('status', $result);
    $this->assertContains($result['status'], ['ok', 'warning', 'critical']);
}
```

### Matrice Test

- **Unit Tests**: Brain\Monkey - 24 test, tutte le versioni PHP
- **Integration Tests**: WP Test Suite - 8 test, tutte le versioni PHP
- **Versioni PHP**: 7.4, 8.0, 8.1, 8.2, 8.3 (coverage), 8.4, 8.5
- **Target Coverage**: ≥85% su PHP 8.3
- **Test E2E**: Viewport Mobile, Tablet, Desktop (future)

## 🔒 Sicurezza

### Funzionalità Hardening

- **Solo Admin**: Tutte le funzionalità richiedono capability `manage_options`
- **Nonces**: Protezione CSRF su tutti i form e AJAX
- **Anti-SSRF**: Protezione multi-layer per webhook
  - Validazione schema (solo http/https)
  - Prevenzione DNS rebinding
  - Blocco IP privati
  - No redirect following
  - Timeout 5 secondi
- **Redaction Dati**: Sanitizzazione automatica di:
  - Credenziali (password, API key, token)
  - Path file (ABSPATH, WP_CONTENT_DIR)
  - Credenziali database
  - Dati utente (email, IP)
- **Sanitizzazione Input**: Tutti gli input utente sanitizzati
- **Escaping Output**: Tutti gli output escaped

### WordPress.org Ready

- ✅ Plugin Check tool passa (zero errori)
- ✅ Nessuna chiamata outbound senza opt-in
- ✅ Conforme WordPress Coding Standards
- ✅ Cleanup completo in disinstallazione

## 📊 Stato Sviluppo

Milestone corrente: **M0 - Setup & Infrastruttura** ✅

### Roadmap

- [x] **M0**: Setup & Infrastruttura (TDD, CI/CD, classi core)
- [ ] **M1**: Check Core + Storage + Cron
- [ ] **M2**: Riepilogo Error Log Sicuro
- [ ] **M3**: Check Redis
- [ ] **M4**: Sistema Alerting
- [ ] **M5**: Testing E2E (Playwright)
- [ ] **M6**: Readiness WordPress.org

Vedi [DEVELOPMENT_PLAN.md](DEVELOPMENT_PLAN.md) per progressi dettagliati.

## 🤝 Contribuire

Accogliamo contributi! Leggi [CONTRIBUTING.md](CONTRIBUTING.md) per:

- Requisiti workflow TDD
- Pattern enforcement (NO singleton/static/final)
- Standard di codifica
- Processo pull request
- Requisiti testing

### Quick Start per Contributori

```bash
# Fork e clone
git clone https://github.com/TUO_USERNAME/ops-health-dashboard.git
cd ops-health-dashboard

# Crea feature branch
git checkout -b feature/nome-tua-feature

# Installa dipendenze
php composer.phar install

# Scrivi prima i test (TDD)
# Poi implementa feature
# Assicurati che tutti i test passino
php composer.phar test
php composer.phar phpcs

# Commit e push
git commit -m "feat: descrizione tua feature"
git push origin feature/nome-tua-feature
```

## 📝 Standard di Codifica

- **PHP**: WordPress Coding Standards (WPCS)
- **HTML/CSS**: Code Guide
- **JavaScript**: MDN Code Style Guide
- **Lunghezza Riga**: 120 caratteri (soft), 150 (hard)

## 🔧 Configurazione

### Comandi Composer

```bash
# Testing
composer test                    # Tutti i test (unit + integration)
composer test:unit               # Solo unit tests (Brain\Monkey, veloce)
composer test:integration        # Solo integration tests (WP Test Suite)
composer test:coverage           # Coverage completa

# Code Quality
composer phpcs                   # Controlla WordPress Coding Standards
composer phpcbf                  # Auto-fix standard codifica

# Setup
composer install-wp-tests        # Installa suite test WordPress (una tantum)
```

### CI/CD

GitHub Actions esegue automaticamente su push/PR:
- Check PHPCS (WordPress Coding Standards)
- PHPUnit su PHP 7.4, 8.0, 8.1, 8.2, 8.3, 8.4, 8.5
- Report coverage (solo PHP 8.3)
- Upload Codecov

## 📄 Licenza

GPL-2.0-or-later - vedi file [LICENSE](LICENSE).

## 👥 Autori

- **Ops Team** - [GitHub](https://github.com/mab056)
- **Co-Authored-By**: Claude Sonnet 4.5

## 🙏 Ringraziamenti

- WordPress Plugin Handbook
- WordPress Coding Standards
- Documentazione PHPUnit
- Brain\Monkey per testing WordPress

## 📞 Supporto

- **Issues**: [GitHub Issues](https://github.com/mab056/ops-health-dashboard/issues)
- **Documentazione**: Vedi [DEVELOPMENT_PLAN.md](DEVELOPMENT_PLAN.md)
- **Contribuire**: Vedi [CONTRIBUTING.md](CONTRIBUTING.md)
- **AI Assistant**: Vedi [CLAUDE.md](CLAUDE.md) per istruzioni Claude/AI

---

**Costruito con ❤️ e TDD rigoroso**
