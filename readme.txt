=== Ops Health Dashboard ===
Contributors: mattiabondrano
Tags: health check, monitoring, dashboard, alerting, devops
Requires at least: 5.8
Tested up to: 6.7
Stable tag: 0.6.0
Requires PHP: 7.4
License: GPL-3.0-or-later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Plugin WordPress production-grade per monitoraggio operativo con health check automatici e alerting multi-canale configurabile.

== Description ==

Ops Health Dashboard fornisce una dashboard di monitoraggio operativo in wp-admin con health check automatici e alerting configurabile (email, webhook, Slack, Telegram, WhatsApp), per sapere cosa succede *prima* che qualcosa si rompa.

= Health Checks =

* **Database** — Connettività e performance delle query
* **Error Log** — Aggregazione sicura con redazione automatica dei dati sensibili
* **Redis** — Rilevamento estensione, test connessione e smoke test con graceful degradation
* **Disk Space** — Spazio libero/totale con soglie configurabili (warning <20%, critical <10%)
* **Versions** — WordPress, PHP, temi e plugin con notifiche aggiornamenti

= Dashboard =

* Pagina admin: Ops > Health Dashboard
* Pulsanti manuali "Run Now" e "Clear Cache" con protezione nonce
* Widget dashboard che mostra lo stato globale

= Alerting =

* Email via wp_mail() con destinatari configurabili
* Webhook generico JSON POST con firma HMAC opzionale
* Slack via Incoming Webhook con payload Block Kit
* Telegram via Bot API con HTML parse mode
* WhatsApp via webhook generico con autenticazione Bearer
* Cooldown per-check via transient (default 60 minuti)
* Alert di recovery bypassano il cooldown
* Protezione anti-SSRF su tutte le richieste HTTP in uscita

= Scheduling =

* WP-Cron (default: ogni 15 minuti)
* Trigger manuale via pulsante "Run Now"
* Alert automatici solo su cambio stato

= Architettura =

Costruito con OOP moderno, TDD e hardening di sicurezza rigoroso. Nessun singleton, nessun metodo statico, nessuna classe final. Dependency injection completa tramite container DI leggero.

== Installation ==

1. Caricare i file del plugin in `/wp-content/plugins/ops-health-dashboard/` o installare direttamente dalla schermata plugin di WordPress.
2. Attivare il plugin dalla schermata "Plugin" in WordPress.
3. Navigare su Ops > Health Dashboard per visualizzare gli health check.
4. Configurare l'alerting su Ops > Alert Settings.

== Frequently Asked Questions ==

= Quale versione di PHP è richiesta? =

PHP 7.4 o superiore è richiesto. PHP 8.1+ è raccomandato per migliori performance e sicurezza.

= Il plugin supporta Redis? =

Sì. Se l'estensione PHP Redis è installata e configurata (tramite le costanti WP_REDIS_HOST, WP_REDIS_PORT), il check Redis monitorerà connettività e performance. Se Redis non è disponibile, il check degrada con grazia a uno stato warning.

= Ogni quanto vengono eseguiti gli health check? =

Di default, i check vengono eseguiti ogni 15 minuti via WP-Cron. È possibile anche triggerare i check manualmente usando il pulsante "Run Now" nella dashboard.

= Quali dati vengono puliti quando disinstallo il plugin? =

Tutte le opzioni, i transient e gli eventi cron schedulati del plugin vengono rimossi quando si cancella il plugin tramite l'admin di WordPress. Nessun dato rimane nel database.

= I dati sensibili sono esposti nei risultati degli health check? =

No. Tutti i risultati degli health check vengono processati attraverso un servizio di redazione che sanitizza automaticamente credenziali, percorsi file, indirizzi email, indirizzi IP e altre informazioni sensibili prima dello storage o della visualizzazione.

== Screenshots ==

1. Health Dashboard con tutti i risultati dei check
2. Pagina di configurazione Alert Settings
3. Widget dashboard con stato globale

== Changelog ==

= 0.5.0 =
* Aggiunto DiskCheck con soglie configurabili (warning <20%, critical <10%)
* Aggiunto VersionsCheck per monitoraggio versioni WordPress/PHP con notifiche aggiornamenti
* Aggiunto DashboardWidget che mostra lo stato globale nella dashboard wp-admin
* Aggiunto E2E testing con Playwright e wp-env (46 scenari x 3 viewport)
* Migliorato RedisCheck con gestione errori catch Throwable
* Aggiunto uninstall.php con classe Uninstaller per pulizia completa dati
* Aggiunti guard ABSPATH su tutti i file sorgente per WordPress.org readiness
* Aggiunto readme.txt in formato WordPress.org

= 0.4.1 =
* DNS pinning via CURLOPT_RESOLVE nell'HttpClient (anti-TOCTOU)
* Isolamento per-canale con try/catch Throwable
* Sicurezza: i campi password non espongono mai i valori reali nel DOM

= 0.4.0 =
* Aggiunto sistema alerting multi-canale (Email, Webhook, Slack, Telegram, WhatsApp)
* Aggiunto HttpClient anti-SSRF con validazione DNS e blocco IP privati
* Aggiunta pagina admin Alert Settings con configurazione per-canale
* Aggiunto cooldown per-check con bypass per recovery

Per il changelog completo, vedi [CHANGELOG.md](https://github.com/mab056/ops-health-dashboard/blob/main/CHANGELOG.md).

== Upgrade Notices ==

= 0.5.0 =
Nuovi health check (Disk Space, Versions) e Dashboard Widget. Nessuna breaking change.
