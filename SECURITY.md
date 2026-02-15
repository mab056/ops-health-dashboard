# Security Policy

## Versioni Supportate

| Versione | Supportata |
|----------|------------|
| 0.6.x    | :white_check_mark: (corrente) |
| 0.5.x    | :white_check_mark: |
| 0.4.x    | :white_check_mark: |
| < 0.4    | :x: |

## Segnalare una vulnerabilità

La sicurezza di Ops Health Dashboard è considerata una priorità. Se hai trovato una vulnerabilità, ti chiediamo di segnalarla in modo responsabile.

**NON aprire un issue pubblico su GitHub per vulnerabilità di sicurezza.**

### Come Segnalare

1. **GitHub Security Advisory** (preferito): apri una [draft security advisory](https://github.com/mab056/ops-health-dashboard/security/advisories/new) sul repository
2. **Email**: invia una segnalazione dettagliata a `info@mattiabondrano.dev`

### Cosa Includere

- Tipo di vulnerabilità (es. XSS, SQL injection, SSRF, information disclosure)
- Percorso completo dei file sorgente coinvolti
- Passi per riprodurre il problema
- Proof of concept o exploit (se disponibile)
- Impatto potenziale

### Tempi di Risposta

- **Conferma ricezione**: entro 48 ore
- **Valutazione iniziale**: entro 7 giorni
- **Fix e rilascio**: entro 30 giorni dalla conferma

## Misure di Sicurezza Implementate

### Controllo accessi

- **Capability check**: tutte le pagine admin richiedono `manage_options`
- **Nonce CSRF**: protezione su tutti i form e azioni POST (`ops_health_admin_action`, `ops_health_alert_settings`)
- **Pattern PRG**: Post-Redirect-Get per prevenire invii duplicati (HealthScreen + AlertSettings)

### Sanitizzazione e Escaping

- **Input**: `sanitize_text_field()`, `sanitize_email()`, `esc_url_raw()`, `absint()` su tutti gli input utente
- **Output**: `esc_html()`, `esc_attr()`, `esc_url()` su tutti gli output renderizzati
- **Query DB**: `$wpdb->prepare()` per query parametrizzate (dove applicabile)

### Protezione dati sensibili

- **Redaction service**: 11 pattern di sanitizzazione automatica applicati a tutti gli output diagnostici
  - Credenziali database (DB_PASSWORD, DB_USER, DB_NAME, DB_HOST)
  - WordPress salts e chiavi di sicurezza
  - API key, secret, token, bearer
  - Password in URL e campi generici
  - Indirizzi email, IPv4, IPv6
  - Path filesystem (ABSPATH, WP_CONTENT_DIR, home directory)
- **DatabaseCheck**: nessuna esposizione di host/nome database nei risultati
- **ErrorLogCheck**: campioni di log redatti prima dell'inclusione; path raw non esposti
- **RedisCheck**: host e messaggi di errore redatti via RedactionInterface

### Protezione filesystem

- **Anti-symlink**: file di log symlink rifiutati (prevenzione directory traversal)
- **Limite lettura**: max 512KB per prevenire consumo eccessivo di memoria
- **Lock condiviso**: `flock(LOCK_SH)` per accesso concorrente sicuro ai log

### Anti-SSRF

Il sistema di alerting utilizza `HttpClient` con protezioni anti-SSRF complete per tutte le richieste HTTP outbound (Webhook, Slack, Telegram, WhatsApp):

- **Validazione schema**: solo http/https accettati
- **Blocco IP privati e riservati**: RFC 1918 (10.0.0.0/8, 172.16.0.0/12, 192.168.0.0/16), loopback (127.0.0.0/8), link-local (169.254.0.0/16), unspecified (0.0.0.0)
- **Rifiuto IPv6**: trattato come non sicuro (safe-fail) perché `gethostbyname()` restituisce solo IPv4
- **Validazione DNS**: risoluzione hostname e verifica che l'IP risolto non sia privato (prevenzione DNS rebinding)
- **DNS pinning**: `CURLOPT_RESOLVE` via `http_api_curl` action forza cURL a usare l'IP già validato, prevenendo attacchi TOCTOU/DNS rebinding tra validazione e richiesta effettiva
- **Restrizione porte**: solo porta 80 e 443 consentite
- **No redirect following**: `redirection => 0` su `wp_remote_post()`
- **Validazione HTTP status**: solo risposte 2xx trattate come successo
- **Timeout**: 5 secondi massimo
- **Protected `resolve_host()`**: wrappa `gethostbyname()` per testabilità con partial mock
- Implementato in `src/Services/HttpClient.php` con interfaccia `HttpClientInterface`

### Protezione canali di notifica

- **TelegramChannel**: `htmlspecialchars()` su tutte le variabili interpolate nei messaggi HTML (prevenzione HTML injection)
- **SlackChannel**: escape mrkdwn (`*`, `_`, `~`, `` ` ``, `&`, `<`, `>`) nei valori utente (prevenzione injection formattazione)
- **EmailChannel**: validazione `is_email()` sui destinatari (prevenzione header injection)
- **WhatsAppChannel**: validazione E.164 sul numero di telefono (regex `/^\+[1-9]\d{6,14}$/`)
- **WebhookChannel**: firma HMAC SHA-256 opzionale via header `X-OpsHealth-Signature` (body pre-serializzato per evitare discrepanze di firma)
- **AlertSettings**: token e secret con `type="password"` + `autocomplete="off"`, `value=""` + `placeholder="********"` (credenziali mai presenti nel sorgente DOM)
- **AlertManager**: cooldown impostato PRIMA del dispatch (prevenzione alert spam in caso di errori dei canali)
- **AlertManager**: isolamento per-canale con `try/catch \Throwable` in `dispatch_to_channels()` (un canale che fallisce non blocca gli altri)
- **Scheduler**: `catch (\Throwable)` attorno a `alert_manager->process()` (il cron sopravvive a qualsiasi tipo di errore, inclusi TypeError e ValueError)

### Disinstallazione sicura

- **Single-site**: pulizia completa di opzioni, cron hook, transient fissi e cooldown transient dinamici
- **Multisite**: iterazione di tutti i blog della rete con `switch_to_blog()`/`restore_current_blog()`, pulizia per-blog identica a single-site
- **Cooldown transient cleanup**: query `$wpdb` LIKE per cancellare transient dinamici `ops_health_alert_cooldown_*` (futuro-proof)
- **Guard `WP_UNINSTALL_PLUGIN`**: `uninstall.php` verifica la costante WordPress prima di procedere
- **Fallback multisite senza autoloader**: `uninstall.php` gestisce multisite anche quando l'autoloader non e' disponibile

### Dependency Injection

- **NO singleton**: previene stato globale condiviso e imprevedibile
- **NO static methods**: tutte le dipendenze sono esplicite e tracciabili
- **Constructor injection**: facilita audit delle dipendenze per ogni classe

## Scope

### Cosa copre questa policy

- Codice sorgente in `src/`
- File di configurazione (`config/bootstrap.php`)
- File di disinstallazione (`uninstall.php`)
- Script di build e distribuzione (`bin/build-zip.sh`)
- Plugin file principale (`ops-health-dashboard.php`)

### Cosa non copre

- WordPress core e le sue vulnerabilità
- Temi e plugin di terze parti
- Configurazione del server web (Apache, Nginx)
- Configurazione PHP e MySQL
- Dipendenze di sviluppo (`vendor/` in ambiente dev)

## Quality Gates di Sicurezza

Ogni release deve superare:

- **PHPCS**: 100% compliance WordPress Coding Standards
- **PHPStan**: level 6, 0 errori (analisi statica con `szepeviktor/phpstan-wordpress`)
- **Pattern enforcement**: test automatici verificano assenza di singleton, static methods, final classes
- **Test matrix**: PHP 7.4-8.5 su tutte le versioni supportate
- **Code review**: ogni PR richiede revisione prima del merge

## Ringraziamenti

Ringraziamo chiunque segnali vulnerabilità in modo responsabile. I ricercatori di sicurezza che contribuiscono a migliorare Ops Health Dashboard saranno riconosciuti in questa sezione, previo consenso.
