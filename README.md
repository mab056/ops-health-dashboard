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
[![Version](https://img.shields.io/badge/Version-0.4.0-green)](https://github.com/mab056/ops-health-dashboard/releases)

Plugin WordPress di monitoraggio operativo production-grade con controlli automatici e alerting multi-canale configurabile.

## 🎯 Problema

**"Non so cosa sta succedendo finché non esplode."**

Questo plugin fornisce una dashboard operativa in wp-admin con controlli automatici di salute e alerting configurabile (email, webhook, Slack, Telegram, WhatsApp) per sapere cosa sta succedendo *prima* che si rompa.

## ✨ Funzionalità (MVP)

### Controlli di Salute
- **Database** - Connettività e performance delle query
- **Log Errori** - Aggregazione sicura con redaction automatica
- **Redis** - Rilevamento estensione + connessione + smoke test con graceful degradation
- **Spazio Disco** - Libero/totale con soglie configurabili *(pianificato)*
- **Versioni** - WordPress, PHP, temi, plugin + notifiche aggiornamenti *(pianificato)*

### Dashboard
- Pagina admin: `Ops → Health Dashboard`
- Bottoni manuali "Run Now" e "Clear Cache" con protezione nonce
- Widget dashboard con stato globale *(pianificato M5+)*

### Alerting *(implementato M4)*
- **Email** via `wp_mail()` con destinatari configurabili
- **Webhook** generico POST JSON con firma HMAC opzionale
- **Slack** via Incoming Webhook con Block Kit payload
- **Telegram** via Bot API con parse mode HTML
- **WhatsApp** via webhook generico con auth Bearer
- Cooldown intelligente per-check via transient (default 60 min)
- Recovery alert bypassano il cooldown
- Pagina admin configurazione: `Ops → Alert Settings`
- Anti-SSRF su tutte le richieste HTTP outbound

### Scheduling
- WP-Cron (default: ogni 15 minuti)
- Trigger manuale dei check via bottone "Run Now"
- Alert automatici solo su cambiamenti di stato

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
│   ├── Interfaces/     # Contratti DI (Check, CheckRunner, Storage, Redaction, HttpClient, AlertManager, AlertChannel)
│   ├── Checks/         # Implementazioni controlli salute (Database, ErrorLog, Redis)
│   ├── Services/       # Logica business (Storage, Scheduler, Redaction, CheckRunner, AlertManager, HttpClient)
│   ├── Channels/       # Canali di notifica (Email, Webhook, Slack, Telegram, WhatsApp)
│   └── Admin/          # UI wp-admin (Menu, HealthScreen, AlertSettings)
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
- **Scheduler** - Scheduling WP-Cron ogni 15 minuti + self-healing throttled + AlertManager integration
- **AlertManager** - Rilevamento cambiamenti stato, dispatch multi-canale, cooldown per-check, alert log
- **HttpClient** - Client HTTP anti-SSRF (blocco IP privati, validazione DNS, restrizione schema/porta)
- **5 Channels** - EmailChannel, WebhookChannel, SlackChannel, TelegramChannel, WhatsAppChannel

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

Questo progetto segue Test-Driven Development con un **approccio misto**:

### Approccio Test Misto

**Unit Tests (Brain\Monkey)** - Veloce, isolato
- Logica business pura, NO WordPress
- 437 test, ~4 s
- Perfetto per TDD rapido

**Integration Tests (WP Test Suite)** - WordPress reale
- Test con WordPress completo, database, WP-Cron
- 256 test, ~4 s
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
bin/build-zip.sh --output /tmp/p.zip  # Percorso output personalizzato
```

### Test Matrix Locale

Esegui l'intera matrice CI in locale (richiede PHP 7.4-8.5):

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

- **Unit Tests**: Brain\Monkey - 437 test, tutte le versioni PHP - 99.92% coverage (1280/1281 lines)
- **Integration Tests**: WP Test Suite - 256 test, tutte le versioni PHP - 98.98% coverage (1267/1280 lines)
- **PHPStan**: Level 6 con szepeviktor/phpstan-wordpress, 0 errori
- **Versioni PHP**: 7.4, 8.0, 8.1, 8.2, 8.3 (coverage), 8.4, 8.5
- **Target Coverage**: 95% progetto, 90% patch (Codecov)
- **Test E2E**: Viewport Mobile, Tablet, Desktop (futuro M5)

## 🔒 Sicurezza

### Funzionalità Hardening

- **Solo Admin**: Tutte le funzionalità richiedono capability `manage_options`
- **Nonces**: Protezione CSRF su tutti i form e AJAX
- **Anti-SSRF**: Protezione attiva su tutte le richieste HTTP outbound
  - Validazione schema (solo http/https)
  - Blocco IP privati e riservati (RFC 1918, loopback, link-local)
  - Validazione DNS resolution (prevenzione DNS rebinding)
  - DNS pinning via `CURLOPT_RESOLVE` (prevenzione TOCTOU/DNS rebinding)
  - Restrizione porte (solo 80/443)
  - Rifiuto IPv6 (safe-fail)
  - Validazione HTTP status (solo 2xx = successo)
  - No redirect following
  - Timeout 5 secondi
- **Channel Security**: Protezione injection su tutti i canali
  - TelegramChannel: escape HTML (`htmlspecialchars`)
  - SlackChannel: escape mrkdwn (formattazione)
  - EmailChannel: validazione `is_email()` destinatari
  - WhatsAppChannel: validazione E.164 phone number
  - Token/secret mascherati (`type="password"`, credenziali mai nel DOM)
- **Cooldown pre-dispatch**: Previene alert spam anche su failure canali
- **Channel isolation**: `try/catch \Throwable` per-canale (un canale che fallisce non blocca gli altri)
- **Scheduler resilience**: `catch (\Throwable)` su AlertManager (cron sopravvive a qualsiasi errore)
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

Milestone corrente: **M5 - E2E Testing** 🚧

### Statistiche

- **27 file sorgente** in `src/`
- **47 file di test** (27 unit + 20 integration)
- **693 test totali** (437 unit + 256 integration), 1529 assertions
- **Coverage unit**: 99.92% lines (1280/1281), 99.26% methods (135/136)
- **Coverage integration**: 98.98% lines (1267/1280), 97.79% methods (133/136)
- **PHPCS**: 100% compliance (0 errori, 0 warning)
- **PHPStan**: level 6, 0 errori

### Roadmap

- [x] **M0**: Setup & Infrastruttura (TDD, CI/CD, classi core)
- [x] **M1**: Core Checks + Storage + Cron
- [x] **M2**: Riepilogo Error Log Sicuro
- [x] **M3**: Check Redis
- [x] **M4**: Sistema Alerting (Email, Webhook, Slack, Telegram, WhatsApp + anti-SSRF)
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
