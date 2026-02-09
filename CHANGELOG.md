# Changelog

Tutte le modifiche rilevanti a questo progetto saranno documentate in questo file.

Il formato è basato su [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
e questo progetto aderisce al [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## 0.3.0 - 2026-02-09

### Added
- **PHPStan** - Analisi statica livello 6 con `szepeviktor/phpstan-wordpress`
  - Configurazione `phpstan.neon` (level 6, `missingType.iterableValue` ignorato)
  - Script `composer analyse` per esecuzione locale
  - Job dedicato in GitHub Actions CI
  - Integrazione in `bin/test-matrix.sh` (eseguito insieme a PHPCS)
- **RedisCheck** - Health check per Redis con graceful degradation (M3)
  - Rilevamento estensione PHP Redis (`extension_loaded`)
  - Test di connessione con costanti WordPress (`WP_REDIS_HOST`, `WP_REDIS_PORT`)
  - Autenticazione opzionale (`WP_REDIS_PASSWORD`)
  - Selezione database (`WP_REDIS_DATABASE`)
  - Smoke test SET/GET/DEL con misurazione tempo di risposta
  - Soglia slow response (>100ms = warning)
  - Tutti i fallimenti sono `warning` (Redis è opzionale, mai `critical`)
  - Host e errori redatti via `RedactionInterface`
- 25 unit test + 6 integration test per RedisCheck
- Registrazione RedisCheck in `config/bootstrap.php`
- **HealthScreenTest** - +7 test: `process_actions()` early returns (3), `run_now` action, `clear_cache` action, notice transient display; helper `create_testable_screen()` e `mock_process_functions()`
- **DatabaseCheckTest** - +2 test: warning su query lenta (>0.5s), fallback `Unknown error` con `last_error` vuoto
- **MenuTest** - +1 test: skip `load-hook` quando `add_menu_page` ritorna false; aggiornato test esistente con asserzione `add_action` per `load-{$page_hook}`
- **SchedulerTest** - Aggiornati 3 test `register_hooks` con `get_transient`/`set_transient` mocks (sostituito `is_admin`)
- **ActivatorTest** - Aggiornati 3 test con definizione `MINUTE_IN_SECONDS` e mock `__()`
- **RedisCheckTest** - Aggiornato `test_returns_ok_when_smoke_test_passes` con `Mockery::on()` pattern matcher per chiave dinamica
- **Copertura test migliorata** - 49 nuovi test (215 unit + 99 integration), 743 assertions
- **Documentazione post-mortem** - `docs/postmortem-redis-test-matrix.md` per analisi fix test matrix Redis

### Changed
- **Activator** - Usa `MINUTE_IN_SECONDS` e `__()` i18n per intervallo cron (allineato con Scheduler)
- **ErrorLogCheck::classify_line()** - Ottimizzato da 6 regex sequenziali a singola regex con alternazione + mappa di lookup
- **HealthScreen** - Estratto `exit` in metodo protetto `do_exit()` per testabilità con Mockery
- **RedisCheck** - Rimosso anti-pattern `unset($e)` nei catch block, sostituito con `phpcs:ignore`
- **.gitignore** - Rimosso `composer.lock` (deve essere committato per build riproducibili)
- **Scheduler** - Self-healing usa transient throttle (ogni ora) invece di `is_admin()` guard; funziona anche su frontend
- **RedisCheck** - Smoke test usa chiave unica per run (`uniqid()`) per evitare race condition tra cron e run manuale
- **cleanup_and_close()** - Accetta `$smoke_key` come parametro per cleanup preciso
- **ErrorLogCheck::resolve_log_path()** - `WP_DEBUG_LOG` assegnato a variabile locale con `@phpstan-ignore` per compatibilità PHPStan (stubs WordPress tipano `bool`, ma WordPress accetta anche stringhe)
- **.phpcs.xml.dist** - Aggiunto exclude per `.phpstan-cache`
- **RedisCheckTest integration** - `@requires extension redis` su test individuali (non `return;` a livello file); `extension_loaded('redis')` guard su `require_once FakeRedisHelpers.php`
- **DatabaseCheckTest** - Timing test usa busy-wait loop (resiste a EINTR da SIGALRM) invece di `usleep()` singolo
- **test-matrix.sh** - Grep fallback per output PHPUnit con test skipped (`Tests: N, ...` oltre a `OK (N tests, ...)`)

### Fixed
- **Integration HealthScreenTest** - Corretta option key da `ops_health_results` a `ops_health_latest_results` (allineata con Storage prefix `ops_health_` + CheckRunner key `latest_results`)
- **test-matrix.sh** - Integration test count mostrava "?" quando PHPUnit aveva test skipped (formato output diverso da `OK (N tests, ...)`)
- **DatabaseCheckTest flaky** - `usleep()` interrotto da SIGALRM (PHPUnit php-invoker) causava test timing intermittentemente fallimentari; sostituito con busy-wait loop resistente a EINTR
- **RedisCheckTest fatale senza ext-redis** - `extends \Redis` nelle classi helper causava fatal error su PHP senza ext-redis; risolto con guard `extension_loaded('redis')` e `@requires extension redis`

### Development Notes
- 314 test totali (215 unit + 99 integration), 743 assertions
- PHPCS 100% clean (0 errori, 0 warning)
- PHPStan level 6: 0 errori
- 16 file sorgente, 26 file di test (16 unit + 10 integration)
- Code review post-M3: 5 fix sorgente + 9 nuovi test + 6 test aggiornati
- Code review 2: 3 fix sorgente + 4 test aggiornati
- Test matrix stabilization: fix EINTR, fix grep, fix ext-redis guard

## 0.2.1 - 2026-02-09

### Added
- **Admin Action Buttons** - Bottoni "Run Now" e "Clear Cache" nella pagina Ops Health Dashboard
  - "Run Now": esegue tutti i check immediatamente via `CheckRunnerInterface::run_all()`
  - "Clear Cache": cancella i risultati in cache via `CheckRunnerInterface::clear_results()`
  - Protezione nonce (`ops_health_admin_action`) e capability check (`manage_options`)
  - Pattern PRG (Post-Redirect-Get) via hook `load-{$page_hook}` per evitare "headers already sent"
  - Notice di conferma con transient auto-cancellante
- **CheckRunnerInterface::clear_results()** - Nuovo metodo per cancellare risultati dallo storage
- Integration test per HealthScreen (render output, no-checks message, capability check, action buttons)
- Integration test per Menu::render_page() (delega a HealthScreen)
- Unit test per Scheduler::add_custom_cron_interval() (aggiunta intervallo + no-overwrite)
- Unit test per priorita' campioni critical vs warning in ErrorLogCheck
- Unit test per bottoni Run Now/Clear Cache e nonce fields in HealthScreen

### Fixed
- **ErrorLogCheck::validate_log_file()** - Distingue "non configurato" (warning) da "configurato ma file non esiste ancora" (ok); prima mostrava erroneamente warning quando `WP_DEBUG_LOG=true` ma `debug.log` non era ancora stato creato
- **ErrorLogCheck::collect_samples()** - I campioni critical ora hanno priorita' sui warning; prima `array_slice(..., -max)` scartava i critical quando c'erano molti warning
- **ErrorLogCheck::resolve_log_path()** - Gestisce `WP_DEBUG_LOG === true` (WordPress scrive in `wp-content/debug.log`)
- **ErrorLogCheck::read_tail()** - Verifica return value di `flock()`; `flock(LOCK_UN)` esplicito prima di `fclose()`
- **DatabaseCheck::get_name()** - Wrappato con `__()` per i18n (era l'unico check senza traduzione)
- **install-wp-tests.sh** - `grep -o` restituiva tutte le versioni WordPress dall'API invece della sola ultima; `svn export` riceveva argomenti multipli e falliva in CI. Aggiunto `head -1` per prendere solo la prima (latest). Rimossa riga `grep` morta senza effetto.

### Changed
- **HealthScreen::process_actions()** - Metodo ora public, invocato da `load-{$page_hook}` hook (non piu' dentro `render()`)
- **Menu::add_menu()** - Registra hook `load-{$page_hook}` per processare azioni prima dell'output
- **build-zip.sh** - Copia condizionale di `languages/` e `assets/` (non fallisce se mancanti)
- **install-wp-tests.sh** - Endpoint API WordPress usa HTTPS invece di HTTP (hardening anti-MITM)

### Removed
- `tests/bootstrap-integration.php` - File orfano non referenziato in phpunit.xml.dist

### Development Notes
- 225 test totali (178 unit + 47 integration), 497 assertions
- PHPCS 100% clean (0 errori, 0 warning)
- ActivatorTest integration usa costante `OPS_HEALTH_DASHBOARD_VERSION` invece di stringa hardcoded

## 0.2.0 - 2026-02-09

### Added
- **RedactionInterface** - Contratto per il servizio di redazione dati sensibili
  - `redact( string $text ): string` - Redige un singolo testo
  - `redact_lines( array $lines ): array` - Redige un array di righe
- **CheckRunnerInterface** - Contratto per disaccoppiare HealthScreen e Scheduler da CheckRunner concreto
  - `add_check()`, `run_all()`, `get_latest_results()`
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
  - Lettura tail efficiente: max 512KB, max 100 righe con `flock(LOCK_SH)`
  - Aggregazione per severita': fatal, parse, warning, notice, deprecated, strict, other
  - Campioni redatti: max 5 righe critiche/warning, sanitizzate via Redaction
  - Status: critical (fatal/parse), warning (warning/deprecated/strict), ok (solo notice/other)
  - Nessuna esposizione del path raw del file di log
  - Messaggi internazionalizzati con `__()`
- **73 nuovi test** (65 unit + 8 integration) per le nuove classi
- Pattern enforcement tests su RedactionInterface, CheckRunnerInterface, Redaction, ErrorLogCheck

### Changed
- **CheckRunner** - Riceve `RedactionInterface` in constructor, redige messaggi eccezione; include `name` nei risultati
- **DatabaseCheck** - Riceve `RedactionInterface` in constructor, redige `$wpdb->last_error`
- **Scheduler** - Usa `CheckRunnerInterface` (non classe concreta); costanti `HOOK_NAME`/`INTERVAL`; self-healing solo in contesto admin (`is_admin()` guard)
- **HealthScreen** - Usa `CheckRunnerInterface`; mostra `result['name']` con `ucfirst()` fallback
- **Activator** - Usa costanti `Scheduler::HOOK_NAME` e `Scheduler::INTERVAL`
- **Container** - Aggiunta rilevazione dipendenze circolari con array `$resolving`
- **Storage** - `update_option()` con `autoload=false` per dati grandi
- **Redaction** - IPv4 regex con validazione ottetti (0-255); URL password regex restrittiva (no whitespace)
- **bootstrap.php** - Usa `container->instance()` per `$wpdb`; binding CheckRunnerInterface; RedactionInterface iniettata in CheckRunner e DatabaseCheck

### Security
- Servizio Redaction impedisce esposizione di credenziali, token, PII nei log
- ErrorLogCheck non espone path raw del filesystem nei risultati
- Protezione anti-symlink: file di log symlink vengono rifiutati
- Limite di lettura a 512KB per prevenire consumo eccessivo di memoria
- `flock(LOCK_SH)` su lettura log per sicurezza in accesso concorrente
- RedactionInterface iniettata in CheckRunner per redazione messaggi eccezione
- RedactionInterface iniettata in DatabaseCheck per redazione errori `$wpdb`
- Storage `autoload=false` previene caricamento dati grandi ad ogni richiesta
- Container rileva dipendenze circolari (previene loop infiniti)

### Development Notes
- **M2 Completed**: Riepilogo Error Log Sicuro + Code Review (15/15 issue risolte)
- 210 test totali (169 unit + 41 integration), 472 assertions
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
