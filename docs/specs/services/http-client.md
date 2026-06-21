# Spec — HTTP Client (anti-SSRF)

> Stato: canonica.
> Linguaggio di stato: questo documento descrive il comportamento corrente garantito del client HTTP,
> non le modifiche che lo hanno prodotto.
> Fonte attiva: `docs/specs/services/http-client.md`
> Sorgente principale: `src/Services/HttpClient.php`, interfaccia `src/Interfaces/HttpClientInterface.php`.

---

## Scopo

`OpsHealthDashboard\Services\HttpClient` è il client HTTP attraverso cui i canali di alert effettuano
le richieste outbound. Espone un'operazione di invio (`post()`) e una verifica di sicurezza dell'URL
(`is_safe_url()`).

## Responsabilità

I canali di alert non effettuano chiamate HTTP dirette: delegano l'invio a `HttpClient`. Le richieste
effettuate tramite `HttpClient` sono validate prima dell'invio. La validazione è una proprietà del
client HTTP, non dei singoli canali.

## Validazione delle richieste

Prima di inviare, `HttpClient` valida la richiesta secondo il comportamento corrente:

* **Scheme:** sono ammessi `http` e `https`.
* **Porta:** sono ammesse `80` e `443`.
* **Host e indirizzo:** il nome host viene risolto e l'indirizzo risultante è validato contro la
  denylist anti-SSRF attualmente supportata.

Se una richiesta non supera la validazione, `HttpClient` la considera non sicura.

## Denylist anti-SSRF

Le richieste effettuate tramite `HttpClient` sono validate contro la denylist anti-SSRF attualmente
supportata. Sono rifiutati di default gli indirizzi appartenenti a categorie non instradabili o
interne, tra cui:

* loopback;
* indirizzi privati RFC1918;
* link-local;
* l'indirizzo non specificato `0.0.0.0`;
* indirizzi non IPv4;
* **CGNAT `100.64.0.0/10`** (RFC 6598 shared address space).

Questo elenco descrive la copertura **attualmente supportata**. La spec **non** afferma copertura
completa di tutti i range reserved/special-use: l'assenza di un range da questo elenco non costituisce
una garanzia.

Non esiste, nel comportamento corrente, alcun override, filtro, configurazione o allowlist per
consentire indirizzi altrimenti rifiutati.

## Comportamento su URL non sicuro

Quando un URL è considerato non sicuro, `HttpClient` **non** effettua alcuna richiesta HTTP e
restituisce l'esito di fallimento già previsto dal contratto del client (esito non riuscito con un
messaggio d'errore). I canali che usano `HttpClient` osservano questo come un fallimento di invio.

## Non-obiettivi

La spec del client HTTP **non** dichiara:

* copertura completa di tutti i range reserved/special-use;
* supporto a richieste verso endpoint interni;
* override, filtro, configurazione o allowlist per indirizzi rifiutati;
* una nuova policy IPv6;
* garanzie aggiuntive su DNS pinning, validazione multi-record, limiti di dimensione della risposta o
  compatibilità di trasporto, che restano fuori dall'ambito di questo documento.

## Decision record

### DR-HTTP-001 — CGNAT denylist

**Stato:** active
**Data:** 2026-06-21
**Origine:** `http-client-cgnat-denylist`

Il client HTTP rifiuta come **default di sicurezza** gli indirizzi del range CGNAT `100.64.0.0/10`.

Il range era precedentemente consentito. Il blocco può interrompere endpoint interni raggiungibili
tramite tailnet o overlay network — in particolare le configurazioni che usano Tailscale, che assegna
ai nodi della tailnet indirizzi di questo range. Il maintainer ha scelto consapevolmente la postura
security-first, allineando CGNAT alla stessa postura già applicata a RFC1918 e link-local.

#### Cosa non viene introdotto

Non viene introdotto alcun override specifico per CGNAT (né filtro, né configurazione, né allowlist).

#### Alternativa rinviata

È stata valutata l'introduzione di un **override generale e controllato per i range interni/privati**.
Questa alternativa **non** è CGNAT-specifica: dovrebbe coprire in modo coerente RFC1918, link-local e
CGNAT come un'unica policy, non come eccezione puntuale. È rinviata e, se emergesse una richiesta di
prodotto esplicita, andrebbe progettata come change dedicato.

#### Ambito

Altri range reserved/special-use restano rinviati. DNS pinning, validazione multi-record, limiti di
dimensione della risposta e compatibilità di trasporto non-cURL sono fuori ambito per questa decisione.

#### Test che proteggono il comportamento

* `test_is_safe_url_blocks_cgnat_internal`
* `test_is_safe_url_blocks_cgnat_lower_boundary`
* `test_is_safe_url_blocks_cgnat_upper_boundary`
* `test_is_safe_url_allows_cgnat_lower_adjacent`
* `test_is_safe_url_allows_cgnat_upper_adjacent`
