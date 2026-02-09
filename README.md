# Ops Health Dashboard

[![CI](https://github.com/mab056/ops-health-dashboard/workflows/CI/badge.svg)](https://github.com/mab056/ops-health-dashboard/actions)
[![codecov](https://codecov.io/gh/mab056/ops-health-dashboard/graph/badge.svg?token=OO2F0VMGQO)](https://codecov.io/gh/mab056/ops-health-dashboard)
[![PHP Version](https://img.shields.io/badge/PHP-7.4%2B-blue)](https://www.php.net/)
[![WordPress](https://img.shields.io/badge/WordPress-5.8%2B-blue)](https://wordpress.org/)
[![License](https://img.shields.io/badge/License-GPL%20v3-blue.svg)](LICENSE)
[![Version](https://img.shields.io/badge/Version-0.3.1-green)](https://github.com/mab056/ops-health-dashboard/releases)
[![PHPCS](https://img.shields.io/badge/PHPCS-WordPress%20Standards-green)](https://github.com/WordPress/WordPress-Coding-Standards)

Plugin WordPress di monitoraggio operativo production-grade con controlli automatici e roadmap di alerting configurabile.

## 🎯 Problema

**"Non so cosa sta succedendo finché non esplode."**

Questo plugin fornisce una dashboard operativa in wp-admin con controlli automatici di salute e alerting configurabile (email, webhook, Slack, Telegram, WhatsApp) per sapere cosa sta succedendo *prima* che si rompa.

## ✨ Funzionalità (MVP)

### Controlli di Salute
- **Database** - Connettività e performance delle query *(implementato)*
- **Log Errori** - Aggregazione sicura con redaction automatica *(implementato)*
- **Redis** - Rilevamento estensione + connessione + smoke test con graceful degradation *(implementato)*
- **Spazio Disco** - Libero/totale con soglie configurabili *(pianificato)*
- **Versioni** - WordPress, PHP, temi, plugin + notifiche aggiornamenti *(pianificato)*

### Dashboard
- Pagina admin: `Ops → Health Dashboard` *(implementato)*
- Bottoni manuali "Run Now" e "Clear Cache" con protezione nonce *(implementato)*
- Widget dashboard con stato globale *(pianificato M4+)*

### Alerting *(pianificato M4)*
- Email via `wp_mail()`
- Webhook generico (POST JSON)
- Slack (opt-in con Incoming Webhook)
- Telegram (opt-in con Bot API)
- WhatsApp (via webhook generico)
- Cooldown intelligente per prevenire spam di alert

### Scheduling
- WP-Cron (default: ogni 15 minuti) *(implementato)*
- Trigger manuale dei check via bottone "Run Now" *(implementato)*
- Alert solo su cambiamenti di stato *(pianificato M4)*

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
│   ├── Interfaces/     # Contratti (CheckInterface, CheckRunnerInterface, ecc.)
│   ├── Checks/         # Implementazioni controlli salute
│   ├── Services/       # Logica business (Storage, Scheduler, Redaction, CheckRunner)
│   └── Admin/          # UI wp-admin
├── tests/
│   ├── Unit/           # Test unitari (Brain\Monkey)
│   └── Integration/    # Test integrazione (WordPress Test Suite)
├── config/             # Bootstrap e configurazione DI
└── bin/                # Script tooling (build, matrix, setup test)
```

### Componenti Chiave

- **Container** - Container DI lightweight con `share()` (non singleton), rilevazione dipendenze circolari
- **Plugin** - Orchestratore principale con constructor injection
- **CheckRunnerInterface** - Contratto per disaccoppiare Scheduler e HealthScreen dal CheckRunner concreto
- **CheckRunner** - Orchestra i controlli di salute, redige messaggi eccezione via RedactionInterface
- **Storage** - Wrapper WordPress Options API con `autoload=false`
- **Redaction** - Sanitizzazione dati sensibili (11 pattern, IPv4 con validazione ottetti)
- **Scheduler** - Scheduling WP-Cron ogni 15 minuti + self-healing throttled

## 📋 Requisiti

- **PHP**: 7.4+ (minimo dichiarato), 8.3+ (raccomandato)
- **WordPress**: 5.8+
- **MySQL**: 5.7+ o MariaDB 10.2+
- **Composer**: Per dipendenze di sviluppo

## 🚀 Installazione

### Per Utenti (Produzione)

1. Scarica l'ultima release da [GitHub Releases](https://github.com/mab056/ops-health-dashboard/releases) oppure genera lo ZIP con `bin/build-zip.sh`
2. Carica il file ZIP tramite `Plugin → Aggiungi Nuovo → Carica Plugin` in wp-admin
3. Attiva tramite admin WordPress
4. Naviga su `Ops → Health Dashboard`

### Per Sviluppatori (sviluppo locale)

```bash
# Clona repository
git clone https://github.com/mab056/ops-health-dashboard.git
cd ops-health-dashboard

# Installa dipendenze
composer install

# Esegui test unitari (veloci, senza WordPress)
composer test:unit

# Installa la suite di test WordPress per i test di integrazione
composer install-wp-tests

# Esegui tutti i test (unit + integrazione)
composer test

# Esegui PHPCS
composer phpcs

# Esegui PHPStan (analisi statica level 6)
composer analyse
```

## 🧪 Testing

Questo progetto segue **TDD rigoroso** (Test-Driven Development) con un **approccio misto**:

### Approccio Test Misto

**Unit Tests (Brain\Monkey)** - Veloce, isolato
- Logica business pura, NO WordPress
- 227 test, ~3 s, coverage 99.50%
- Perfetto per TDD rapido

**Integration Tests (WP Test Suite)** - WordPress reale
- Test con WordPress completo, database, WP-Cron
- 123 test, ~3 s, coverage 100%
- Verifica integrazione reale con WordPress

### Comandi Test

```bash
# Unit tests (veloci, WordPress non richiesto)
composer test:unit

# Integration tests (richiedono WP test suite)
composer install-wp-tests              # Setup una tantum
composer test:integration

# Tutti i test (unit + integration)
composer test

# Matrice completa PHP 7.4-8.5 + PHPCS + PHPStan (come CI)
composer test:matrix

# Con coverage (richiede Xdebug)
composer test:coverage

# PHPCS (WordPress Coding Standards)
composer phpcs

# Auto-fix problemi PHPCS
composer phpcbf

# Build ZIP per la produzione
bin/build-zip.sh                      # Genera dist/ops-health-dashboard-VERSION.zip
bin/build-zip.sh --output /tmp/p.zip  # Output path custom
```

### Test Matrix Locale

Esegui l'intera matrice CI in locale (richiede PHP 7.4-8.5 via PPA sury):

```bash
composer test:matrix                   # Matrice completa (PHPCS + PHPStan + 7 versioni PHP)
bin/test-matrix.sh --php 7.4           # Solo una versione
bin/test-matrix.sh --php 7.4 --php 8.3 # Versioni specifiche
bin/test-matrix.sh --parallel          # Esecuzione parallela
bin/test-matrix.sh --phpcs-only        # Solo PHPCS
bin/test-matrix.sh --tests-only        # Solo PHPUnit
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

// GREEN: Implementa codice minimo
public function run(): array {
    $start  = microtime( true );
    $result = $this->wpdb->query( 'SELECT 1' );
    return [
        'status'  => false !== $result ? 'ok' : 'critical',
        'message' => __( 'Database connection healthy', 'ops-health-dashboard' ),
        'duration' => microtime( true ) - $start,
    ];
}

// INTEGRATION: Test con WordPress reale
public function test_database_check_runs_successfully() {
    global $wpdb;
    $redaction = new Redaction();
    $check     = new DatabaseCheck( $wpdb, $redaction );
    $result    = $check->run();

    $this->assertEquals( 'ok', $result['status'] );
    $this->assertArrayNotHasKey( 'db_host', $result['details'] );
}
```

### Matrice Test

- **Unit Tests**: Brain\Monkey - 227 test, tutte le versioni PHP
- **Integration Tests**: WP Test Suite - 123 test, tutte le versioni PHP
- **PHPStan**: Level 6 con szepeviktor/phpstan-wordpress, 0 errori
- **Versioni PHP**: 7.4, 8.0, 8.1, 8.2, 8.3 (coverage), 8.4, 8.5
- **Target Coverage**: ≥85% su PHP 8.3
- **Test E2E**: Viewport Mobile, Tablet, Desktop (futuro)

## 🔒 Sicurezza

### Funzionalità Hardening

- **Solo Admin**: Tutte le funzionalità richiedono capability `manage_options`
- **Nonces**: Protezione CSRF su tutti i form e AJAX
- **Anti-SSRF**: Requisito di progetto per webhook *(pianificato M4)*
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

### WordPress.org Ready *(pianificato M6)*

- ✅ Conforme WordPress Coding Standards
- ✅ Nessuna chiamata outbound senza opt-in
- Plugin Check tool *(da verificare in M6)*
- Cleanup in disinstallazione via `uninstall.php` *(pianificato M6)*

## 📊 Stato Sviluppo

Milestone corrente: **M4 - Alerting System** 🚧

### Statistiche

- **16 file sorgente** in `src/`
- **28 file di test** (16 unit + 12 integration)
- **350 test totali** (227 unit + 123 integration), 810 assertions
- **Coverage**: Unit 99.50%, Integration 100%
- **PHPCS**: 100% compliance (0 errori, 0 warning)
- **PHPStan**: level 6, 0 errori

### Roadmap

- [x] **M0**: Setup & Infrastruttura (TDD, CI/CD, classi core)
- [x] **M1**: Core Checks + Storage + Cron
- [x] **M2**: Riepilogo Error Log Sicuro
- [x] **M3**: Check Redis
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
composer install

# Scrivi prima i test (TDD)
# Poi implementa feature
# Assicurati che tutti i test passino
composer test
composer phpcs
composer analyse

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
composer test:unit               # Solo test unitari (Brain\Monkey, veloce)
composer test:integration        # Solo test di integrazione (WP Test Suite)
composer test:coverage           # Coverage completa
composer test:matrix             # Matrice PHP 7.4-8.5 + PHPCS + PHPStan (come CI)

# Code Quality
composer phpcs                   # Controlla WordPress Coding Standards
composer phpcbf                  # Auto-fix standard codifica

# Setup
composer install-wp-tests        # Installa la suite di test WordPress (una tantum)
```

### CI/CD

GitHub Actions esegue automaticamente su push/PR:
- Check PHPCS (WordPress Coding Standards)
- PHPStan (analisi statica livello 6)
- PHPUnit su PHP 7.4, 8.0, 8.1, 8.2, 8.3, 8.4, 8.5
- Report coverage (solo PHP 8.3)
- Upload Codecov

## 📄 Licenza

GPL-3.0-or-later - vedi file [LICENSE](LICENSE).

## 👥 Autori

- Mattia Bondrano - [GitHub](https://github.com/mab056)

Sviluppato con il supporto di Claude Code (Opus 4.5, 4.6, Sonnet 4.5) e Codex (Codex 5.2, 5.3).

## 📞 Supporto

- **Issues**: [GitHub Issues](https://github.com/mab056/ops-health-dashboard/issues)
- **Documentazione**: Vedi [DEVELOPMENT_PLAN.md](DEVELOPMENT_PLAN.md)
- **Contribuire**: Vedi [CONTRIBUTING.md](CONTRIBUTING.md)
- **AI Assistant**: Vedi [CLAUDE.md](CLAUDE.md) per istruzioni Claude Code, [AGENTS.md](AGENTS.md) per istruzioni Codex

---

**Costruito con ❤️ e TDD rigoroso**
