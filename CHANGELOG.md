# Changelog

Tutte le modifiche rilevanti a questo progetto saranno documentate in questo file.

Il formato è basato su [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
e questo progetto aderisce al [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## Unreleased

## 0.1.0 - 2026-02-08

### Added
- **StorageInterface** e **CheckInterface** - Contratti per DI e testabilità
- **Storage** - Wrapper WordPress Options API con prefisso `ops_health_`
  - Metodo `has()` con sentinel object pattern (distingue `false` da chiave mancante)
- **CheckRunner** - Orchestratore dei check con salvataggio risultati
  - Try/catch per `\Throwable` su ogni check (resilienza a errori)
  - Type safety su `get_latest_results()` (ritorna sempre array)
- **DatabaseCheck** - Check connettività database con `SELECT 1`
  - Constructor injection di `$wpdb` (NO global)
  - Misurazione durata query in millisecondi
  - Nessuna esposizione di informazioni sensibili (no db_host, db_name)
  - Messaggi internazionalizzati con `__()`
- **Scheduler** - Scheduling WP-Cron ogni 15 minuti
  - Intervallo custom `every_15_minutes`
  - Prevenzione duplicati di scheduling
- **Menu** - Registrazione pagina admin `Ops Health Dashboard`
- **HealthScreen** - Rendering pagina admin con risultati check
  - Capability check `manage_options` con `wp_die()`
  - Validazione difensiva di chiavi risultato (status, message)
  - Messaggio informativo quando nessun check è stato eseguito
- **97 unit test** (Brain\Monkey) + **31 integration test** (WP Test Suite)
- Pattern enforcement tests su ogni classe (NO final, NO static methods/properties)

### Changed
- **Activator** - Gestisce scheduling/unscheduling cron in activate/deactivate
  - Hook name corretto: `ops_health_run_checks`
  - Rimosso `flush_rewrite_rules()` (non necessario)
- **Plugin** - `init()` registra solo hooks, non schedula direttamente
- **bootstrap.php** - Inietta `$wpdb` globale in DatabaseCheck
- **composer.json** - Script `test` esegue unit e integration sequenzialmente

### Security
- Constructor injection di `$wpdb` per evitare accesso globale nei test
- Nessuna esposizione di host/nome database nei risultati dei check
- Capability check `manage_options` su pagina admin
- Output escaped con `esc_html()`, `esc_attr()`

### Development Notes
- **M1 Completed**: Core Checks + Storage + Cron
- 128 test totali, 260 assertions, PHPCS 100% clean
- Code review: 21/22 issue risolte (uninstall.php pianificato per M6)

## 0.0.0 - 2026-02-08

### Added
- Scaffolding iniziale del plugin
- Core dependency injection container (NO singleton pattern)
- Orchestratore principale del plugin con constructor injection
- Handler di attivazione/disattivazione
- Suite di test completa con approccio TDD
- GitHub Actions CI workflow (PHPCS + PHPUnit matrix)
- Configurazione WordPress Coding Standards
- Configurazione PHPUnit con supporto coverage

### Development Notes
- **M0 Completed**: Setup & Infrastruttura ✅
- Tutte le classi core seguono il pattern NO singleton, NO static, NO final
- TDD workflow applicato: RED → GREEN → REFACTOR
- Target di coverage: PHP 8.3
