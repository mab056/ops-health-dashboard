# Spec — Webhook Alert Channel

> Stato: canonica dal change `webhook-json-encode-guard`.
> Linguaggio di stato: questo documento descrive il comportamento corrente garantito dal canale Webhook, non le modifiche che lo hanno prodotto.
> Fonte attiva: `specs/channels/webhook.md`
> Sorgente principale: `src/Channels/WebhookChannel.php`, interfaccia `src/Interfaces/AlertChannelInterface.php`.

---

## Scopo

Il canale Webhook consegna le notifiche di alert effettuando una richiesta HTTP POST con corpo JSON verso un endpoint configurato dall’amministratore.

Il canale è responsabile di:

* determinare se la configurazione Webhook è sufficiente per tentare l’invio;
* serializzare il payload dell’alert in JSON;
* firmare opzionalmente il corpo serializzato con HMAC-SHA256;
* inviare il payload tramite il client HTTP del progetto;
* restituire un esito osservabile di successo o fallimento.

---

## Identità

Il canale Webhook espone questi identificativi:

* `get_id()` restituisce `webhook`;
* `get_name()` restituisce l’etichetta tradotta `Webhook`.

L’identificativo `webhook` è il valore stabile usato dal sistema per distinguere questo canale dagli altri canali di alert.

---

## Abilitazione

Il canale è abilitato quando, nelle impostazioni `alert_settings`, la sezione `webhook` soddisfa entrambe le condizioni:

* `enabled` è truthy;
* `url` è presente e non vuota.

Se una delle due condizioni manca, il canale è considerato disabilitato.

Un canale disabilitato non deve tentare l’invio di notifiche Webhook.

---

## Configurazione

La configurazione del canale è letta da `alert_settings['webhook']`.

Campi rilevanti:

* `url` — endpoint HTTP di destinazione;
* `secret` — chiave HMAC opzionale.

Quando `secret` è assente o vuoto, la richiesta viene inviata senza firma HMAC.

Quando `secret` è presente e non vuoto, la richiesta viene firmata come descritto nella sezione “Firma HMAC”.

---

## Codifica del payload

Il payload dell’alert viene serializzato in JSON prima dell’invio.

Il corpo serializzato è il corpo effettivamente consegnato al client HTTP ed è anche il corpo usato per calcolare l’eventuale firma HMAC.

### Fail-closed sulla codifica

Quando il payload non è serializzabile in JSON, il canale adotta un comportamento fail-closed.

In questo caso il canale:

* non calcola alcuna firma HMAC;
* non effettua alcuna richiesta HTTP;
* restituisce un esito di fallimento;
* include un messaggio di errore non vuoto e localizzato.

Comportamento canonico:

* payload non serializzabile in JSON;
* nessuna firma HMAC;
* nessuna richiesta HTTP;
* ritorno `success=false` con `error` non vuoto.

Un payload non serializzabile non viene mai inviato e non viene mai riportato come consegnato.

Esempi di payload non serializzabile includono:

* stringhe con byte UTF-8 non validi;
* valori float non finiti come `NAN` o `INF`.

Questi esempi non esauriscono tutti i possibili casi di fallimento della serializzazione JSON.

---

## Firma HMAC

Quando è configurato un `secret` non vuoto, il canale include nella richiesta l’header `X-OpsHealth-Signature`.

Il valore dell’header è l’HMAC-SHA256 calcolato sul corpo JSON serializzato.

Il consumatore del webhook può verificare l’autenticità della richiesta ricalcolando `hash_hmac('sha256', $raw_body, $secret)`, dove `$raw_body` è il corpo grezzo ricevuto dal webhook receiver.

La firma deve essere calcolata solo dopo una serializzazione JSON riuscita.

Il canale non deve firmare payload non serializzabili.

Il canale non verifica firme in ingresso. La verifica della firma è responsabilità del webhook receiver.

---

## Trasporto

L’invio avviene esclusivamente tramite `HttpClientInterface::post()`.

Il canale non effettua chiamate HTTP dirette.

Il canale non implementa in proprio:

* validazione URL;
* anti-SSRF;
* DNS pinning;
* timeout;
* redirection policy;
* limiti di risposta;
* gestione del trasporto sottostante.

Queste responsabilità appartengono al client HTTP del progetto.

La spec del canale Webhook dichiara quindi la delega al client HTTP, non certifica il livello effettivo delle protezioni implementate dal client.

---

## Contratto di ritorno

`send()` restituisce un array che contiene almeno:

* `success` — booleano che indica l’esito della consegna;
* `error` — `null`, assente o vuoto in caso di successo; stringa non vuota in caso di fallimento.

La spec non garantisce la presenza di chiavi diagnostiche aggiuntive salvo dove esplicitamente documentato.

### Successo

Un risultato con `success=true` indica che:

* il payload era serializzabile;
* l’eventuale firma è stata calcolata;
* l’invio è stato delegato al client HTTP;
* il client HTTP ha riportato un esito compatibile con il successo.

### Fallimento

Un risultato con `success=false` indica che il canale non ha completato la consegna.

Il fallimento può derivare da:

* payload non serializzabile;
* configurazione insufficiente o non valida;
* errore di trasporto;
* risposta HTTP non considerata riuscita dal client.

Quando `success=false`, il risultato deve contenere un `error` non vuoto.

Il messaggio `error` deve essere sicuro da mostrare in contesto amministrativo e non deve includere payload grezzi o segreti.

---

## Note di sicurezza

Il canale Webhook tratta il `secret` come dato sensibile.

Il `secret` viene usato solo per generare la firma HMAC e non deve essere incluso nel payload.

Il messaggio di errore restituito quando la serializzazione JSON fallisce è intenzionalmente generico:

* non include il payload;
* non include dati grezzi;
* non include output diagnostici dettagliati;
* non include il messaggio interno di errore JSON.

La configurazione del canale, incluso il `secret`, è gestita tramite le superfici amministrative del plugin e deve restare protetta dai controlli di capability del layer admin.

---

## Non-obiettivi del canale

Il canale Webhook non possiede queste responsabilità:

* gestione delle impostazioni persistite;
* capability check amministrativi;
* nonce/CSRF delle schermate admin;
* redazione globale dei dati sensibili;
* validazione anti-SSRF;
* DNS pinning;
* response-size limiting;
* retry policy;
* deduplicazione alert;
* cooldown alert;
* verifica delle firme ricevute dal webhook receiver;
* replay protection lato receiver.

Queste responsabilità appartengono ad altri layer del sistema o al consumer esterno.

---

## Decision record

### DR-WEBHOOK-001 — Payload non serializzabile: fail-closed

**Stato:** active
**Data:** 2026-06-21
**Origine:** `webhook-json-encode-guard`

Il canale Webhook adotta un comportamento fail-closed quando il payload dell’alert non può essere serializzato in JSON.

Comportamento canonico:

* payload non serializzabile in JSON;
* nessuna firma HMAC;
* nessuna richiesta HTTP;
* ritorno `success=false` con `error` non vuoto.

Questa è una decisione di prodotto del canale, non solo un dettaglio tecnico.

### Alternativa valutata

È stata valutata l’alternativa di normalizzare o coercire i dati non serializzabili, per esempio correggendo byte UTF-8 non validi prima dell’invio.

L’alternativa è stata rinviata.

### Motivazione

Il comportamento fail-closed è stato scelto perché:

* evita di riportare come consegnato un payload che il sistema non può rappresentare correttamente in JSON;
* evita di firmare un corpo diverso dal payload originale atteso;
* evita modifiche implicite al contenuto dell’alert;
* mantiene il fix localizzato al canale Webhook;
* mantiene una semantica semplice e verificabile con test red/green.

### Regola di revisione futura

Se in futuro il prodotto decidesse di consegnare comunque alert con contenuti normalizzati o coerci, quella modifica dovrà essere trattata come nuovo change esplicito.

Quel change dovrà aggiornare:

* questa decision record;
* la sezione “Codifica del payload”;
* la sezione “Contratto di ritorno”, se necessario;
* i test che oggi garantiscono il comportamento fail-closed.

Fino a quel momento, il comportamento canonico resta:

* payload non serializzabile;
* fail-closed;
* nessuna richiesta inviata.

---

## Verifica richiesta

Il comportamento del canale Webhook deve essere coperto da test significativi per questi rischi:

* payload valido inviato correttamente;
* payload valido con `secret` firmato correttamente;
* payload non serializzabile rifiutato con `success=false`;
* payload non serializzabile non inviato al client HTTP;
* errore del client HTTP propagato come `success=false`;
* assenza di regressioni sul contratto minimo di ritorno.

Un test è significativo solo se protegge un rischio concreto.

---

## Fonti attive e storiche

Fonte attiva:

* `specs/channels/webhook.md`

Artefatti storici del change che ha estratto questa spec:

* `changes/archive/2026-06-21-webhook-json-encode-guard/`

Gli artefatti archiviati documentano il percorso decisionale e l’evidenza red/green, ma non sostituiscono questa spec come fonte corrente del comportamento del canale Webhook.
