# Changelog

Tutte le modifiche rilevanti a questo progetto saranno documentate in questo file.

Il formato è basato su [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
e questo progetto aderisce al [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## 0.2.0 - 2026-02-09

### Added
- **RedactionInterface** - Contratto per il servizio di redazione dati sensibili
  - `redact( string $text ): string` - Redige un singolo testo
  - `redact_lines( array $lines ): array` - Redige un array di righe
- **Redaction** - Servizio di sanitizzazione dati sensibili con 11 pattern
  - Credenziali DB (DB_PASSWORD, DB_USER, DB_NAME, DB_HOST) -> `[REDACTED]`
  - WordPress salts (AUTH_KEY, SECURE_AUTH_KEY, ecc.) -> `[REDACTED]`
  - API key, secret, token, bearer -> `[REDACTED]`
  - Password in URL e campi generici -> `[REDACTED]`
  - Indirizzi email -> `[EMAIL_REDACTED]`
  - Indirizzi IPv4 e IPv6 -> `[IP_REDACTED]`
  - Path ABSPATH e WP_CONTENT_DIR -> placeholder
  - Directory home utenti -> `/home/[USER_REDACTED]`
  - Constructor injection di path (ABSPATH, WP_CONTENT_DIR) per testabilita'
  - Ordine chain: WP_CONTENT_DIR prima di ABSPATH (piu' specifico)
- **ErrorLogCheck** - Check riepilogo sicuro del log errori PHP
  - Risoluzione path log: WP_DEBUG_LOG (stringa) -> ini_get('error_log')
  - Validazione file: esistenza, leggibilita', anti-symlink
  - Lettura tail efficiente: max 512KB, max 100 righe
  - Aggregazione per severita': fatal, parse, warning, notice, deprecated, strict, other
  - Campioni redatti: max 5 righe critiche/warning, sanitizzate via Redaction
  - Status: critical (fatal/parse), warning (warning/deprecated/strict), ok (solo notice/other)
  - Nessuna esposizione del path raw del file di log
  - Messaggi internazionalizzati con `__()`
- **59 nuovi test** (56 unit + 3 integration) per le nuove classi
- Pattern enforcement tests su RedactionInterface, Redaction, ErrorLogCheck

### Changed
- **bootstrap.php** - Aggiunto binding per RedactionInterface e ErrorLogCheck nel container DI

### Security
- Servizio Redaction impedisce esposizione di credenziali, token, PII nei log
- ErrorLogCheck non espone path raw del filesystem nei risultati
- Protezione anti-symlink: file di log symlink vengono rifiutati
- Limite di lettura a 512KB per prevenire consumo eccessivo di memoria

### Development Notes
- **M2 Completed**: Riepilogo Error Log Sicuro
- 204 test totali (163 unit + 41 integration), 455 assertions
- PHPCS 100% clean (0 errori, 0 warning)
- TDD rigoroso: RED -> GREEN -> REFACTOR per ogni componente

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
- **104 unit test** (Brain\Monkey) + **33 integration test** (WP Test Suite)
- Pattern enforcement tests su ogni classe (NO final, NO static methods/properties)

### Changed
- **Activator** - Gestisce scheduling/unscheduling cron in activate/deactivate
  - Hook name corretto: `ops_health_run_checks`
  - Rimosso `flush_rewrite_rules()` (non necessario)
- **Plugin** - `init()` registra solo hooks, non schedula direttamente
- **bootstrap.php** - Inietta `$wpdb` globale in DatabaseCheck
- **composer.json** - Script `test` esegue unit e integration sequenzialmente
- **Scheduler** - `register_hooks()` include self-healing del cron

### Fixed
- **Scheduler self-healing** - `register_hooks()` ora chiama `schedule()` per ri-creare l'evento cron se scomparso (migrazione DB, cleanup cron, corruzione option). Idempotente grazie al guard `is_scheduled()`.
- **test-matrix.sh `--parallel`** - I risultati delle subshell ora sono propagati al processo padre tramite file temporanei. Prima la tabella riepilogativa era vuota e l'exit code era sempre 0 in modalita parallela.

### Security
- Constructor injection di `$wpdb` per evitare accesso globale nei test
- Nessuna esposizione di host/nome database nei risultati dei check
- Capability check `manage_options` su pagina admin
- Output escaped con `esc_html()`, `esc_attr()`

### Development Notes
- **M1 Completed**: Core Checks + Storage + Cron
- 137 test totali, 275 assertions, PHPCS 100% clean
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
