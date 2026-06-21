# WORKFLOW_HYPOTHESES.md

> Stato: backlog di ipotesi da validare.
> Queste non sono regole operative vincolanti.
> Diventano governance solo dopo aver cambiato un outcome reale in almeno un ciclo.

## Scopo

Questo file raccoglie idee utili emerse dal primo ciclo, ma non ancora provate abbastanza da diventare metodo.

Ogni ipotesi deve essere validata da un change reale.

Per promuovere un’ipotesi a regola, bisogna rispondere:

* quale problema ha prevenuto?
* quale decisione ha migliorato?
* quale errore ha intercettato?
* quale outcome è cambiato perché esisteva?

---

## H01 — Lifecycle completo a più fasi

Ipotesi:

* current-state;
* change-delta;
* plan;
* tasks;
* analyze;
* runtime RED;
* implementation;
* GREEN;
* closeout;
* archive;
* commit.

Stato:

* parzialmente provata.

Cosa è stato provato:

* RED reale prima del codice;
* closeout con spec canonica;
* archive storico;
* staging esplicito.

Cosa non è stato provato:

* se tutte le fasi servono sempre;
* se su change medi alcune fasi vanno fuse;
* se il lifecycle completo rallenta troppo.

Da validare nel secondo ciclo.

---

## H02 — Depth LIGHT / STANDARD / STRICT

Ipotesi:

* LIGHT per fix piccoli;
* STANDARD per change medi;
* STRICT per sicurezza o contratti pubblici.

Stato:

* non provata.

Il primo ciclo era vicino a LIGHT.

Non sono ancora stati validati:

* STANDARD reale;
* STRICT reale;
* threat analysis;
* contract test;
* negative test;
* più componenti coinvolti;
* spec già esistente da aggiornare.

Da validare su un change più scomodo, preferibilmente sicurezza/SSRF.

---

## H03 — Prompt riutilizzabili

Ipotesi:

* prompt standard per current-state;
* prompt standard per delta;
* prompt standard per plan;
* prompt standard per tasks;
* prompt standard per analyze;
* prompt standard per closeout;
* prompt standard per archive.

Stato:

* non provata.

Regola provvisoria:

Non creare prompt generici completi finché non derivano da artefatti reali di almeno due o tre cicli.

Azione consigliata:

Dopo ogni ciclo, estrarre solo le parti del prompt che hanno cambiato un outcome reale.

---

## H04 — Template completi degli artefatti

Ipotesi:

* template per tutti gli artefatti del workflow.

Stato:

* non provata.

Rischio:

Template troppo completi possono generare burocrazia, sezioni finte e falsa completezza.

Regola provvisoria:

Usare template minimi o sezioni libere finché non emergono pattern stabili da più change reali.

---

## H05 — Ruoli formali

Ipotesi:

* maintainer;
* implementer;
* reviewer;
* security reviewer;
* release owner.

Stato:

* non provata.

Nel primo ciclo i ruoli erano di fatto:

* maintainer umano;
* AI implementer/reviewer;
* confronto in chat.

Da validare solo quando partecipano più persone reali o quando il workflow entra in un team.

---

## H06 — Archive sempre versionato

Ipotesi:

L’archive del change vive nel repo accanto al codice.

Stato:

* provata per un change piccolo;
* da verificare su change con artefatti sensibili.

Regola provvisoria:

Versionare archive solo se non contiene dettagli sensibili, vulnerabilità live, secret, dump o report offensivi.

Per security review dettagliate, usare archivio privato separato.

---

## H07 — Spec per ogni componente toccato

Ipotesi:

Ogni componente modificato dovrebbe avere una spec canonica.

Stato:

* provata solo per un componente nuovo alla spec.

Da validare:

* merge-back su spec già esistente;
* conflitto tra due change sulla stessa spec;
* spec troppo granulari;
* spec che duplicano il codice.

---

## H08 — Decision record nella spec vs ADR

Ipotesi:

Decisione locale nella spec; decisione trasversale in ADR.

Stato:

* provata su una decisione locale al canale Webhook.

Da validare:

* decisione trasversale;
* decisione che tocca più plugin;
* decisione architetturale persistente.

---

## H09 — Packaging gate

Ipotesi:

Ogni change che aggiunge cartelle o artefatti di sviluppo potenzialmente non destinati alla distribuzione dovrebbe verificare il packaging reale.

Esempi:

* `specs/`;
* `changes/`;
* `tests/`;
* `docs/workflow/`;
* artefatti di archive;
* report interni;
* fixture di test.

Stato:

* emersa come rischio nel primo ciclo;
* non ancora provata con ZIP reale;
* non promossa a governance.

Cosa è stato osservato:

* il change `webhook-json-encode-guard` ha introdotto `specs/` e `changes/archive/`;
* è stato riconosciuto che questi file possono stare nel git repo ma non necessariamente nello ZIP distribuito;
* è stato riconosciuto che `.distignore` non basta se il packaging usa uno script custom.

Cosa non è stato ancora provato:

* ispezione di uno ZIP reale;
* errore reale di packaging intercettato;
* esclusione corretta verificata dentro `build-zip.sh` o script equivalente;
* impatto su pacchetto WordPress.org.

Criterio di promozione:

H09 può diventare regola core solo quando un ciclo reale verifica il pacchetto prodotto o intercetta un rischio concreto di distribuzione.

Esempio di outcome sufficiente:

* lo ZIP includeva `specs/`, `changes/` o `tests/` e il gate lo ha intercettato;
* il build custom ignorava `.distignore` e il workflow ha prevenuto una distribuzione sbagliata;
* una modifica allo script di packaging è stata richiesta e validata da un pacchetto reale.

Fino ad allora, il packaging gate resta ipotesi da validare.

---

## H10 — Secondo ciclo consigliato

Candidato consigliato:

Un finding SSRF/HttpClient aperto.

Perché:

* più scomodo del primo;
* security-relevant;
* probabilmente richiede depth STANDARD o STRICT;
* mette alla prova deleghe tra WebhookChannel e HttpClient;
* costringe a verificare packaging/spec già esistente;
* può richiedere negative test e decision record più seria.

Rischio:

Potrebbe essere troppo grande se include insieme:

* IP range;
* DNS pinning;
* redirection;
* response size;
* logging;
* compatibilità transport.

Taglio consigliato:

Scegliere un solo sotto-finding SSRF, con acceptance criteria chiari e test negativi mirati.

---

## Regola di promozione

Un’ipotesi passa da questo file a `WORKFLOW_CORE.md` solo se, in un ciclo reale:

* intercetta un errore;
* previene uno scope drift;
* migliora una decisione;
* protegge un rischio;
* riduce ambiguità;
* evita un commit o pacchetto sbagliato.

Se produce solo documentazione in più, resta ipotesi o viene rimossa.
