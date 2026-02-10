# Security Policy

## Versioni Supportate

| Versione | Supportata |
|----------|------------|
| 0.4.x    | :white_check_mark: |
| 0.3.x    | :white_check_mark: |
| < 0.3    | :x: |

## Segnalare una vulnerabilità

La sicurezza di Ops Health Dashboard è considerata una priorità. Se hai trovato una vulnerabilità, ti chiediamo di segnalarla in modo responsabile.

**NON aprire un issue pubblico su GitHub per vulnerabilità di sicurezza.**

### Come Segnalare

1. **GitHub Security Advisory** (preferito): apri una [draft security advisory](https://github.com/mab056/ops-health-dashboard/security/advisories/new) sul repository
2. **Email**: invia una segnalazione dettagliata a `dev@example.com`

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
- **Pattern PRG**: Post-Redirect-Get per prevenire doppie sottomissioni (HealthScreen + AlertSettings)

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

### Anti-SSRF (implementato M4)

Il sistema di alerting utilizza `HttpClient` con protezioni anti-SSRF complete per tutte le richieste HTTP outbound (Webhook, Slack, Telegram, WhatsApp):

- **Validazione schema**: solo http/https accettati
- **Blocco IP privati e riservati**: RFC 1918 (10.0.0.0/8, 172.16.0.0/12, 192.168.0.0/16), loopback (127.0.0.0/8), link-local (169.254.0.0/16), unspecified (0.0.0.0)
- **Validazione DNS**: risoluzione hostname e verifica che l'IP risolto non sia privato (prevenzione DNS rebinding)
- **Restrizione porte**: solo porta 80 e 443 consentite
- **No redirect following**: `redirection => 0` su `wp_remote_post()`
- **Timeout**: 5 secondi massimo
- **Protected `resolve_host()`**: wrappa `gethostbyname()` per testabilità con partial mock
- Implementato in `src/Services/HttpClient.php` con interfaccia `HttpClientInterface`

### Dependency Injection

- **NO singleton**: previene stato globale condiviso e imprevedibile
- **NO static methods**: tutte le dipendenze sono esplicite e tracciabili
- **Constructor injection**: facilita audit delle dipendenze per ogni classe

## Scope

### Cosa copre questa policy

- Codice sorgente in `src/`
- File di configurazione (`config/bootstrap.php`)
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
