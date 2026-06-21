# WORKFLOW_CORE.md

> Stato: provvisorio, basato su evidenza del primo ciclo `webhook-json-encode-guard`.
> Ambito: regole minime già validate da un change reale.
> Non è una constitution completa. Non generalizza oltre ciò che è stato osservato.

## Principio guida

Il workflow non deve produrre consenso documentale.

Deve produrre punti verificabili di contatto con la verità di terra:

* test reali eseguiti;
* RED reali osservati;
* GREEN reali osservati;
* artefatti reali letti;
* working tree reale verificato;
* contenuto effettivo dei file controllato dal maintainer.

Il consenso tra reviewer, AI o tool non è validazione.

Il maintainer resta il gate finale, soprattutto quando più reviewer concordano.

---

## Regola 1 — Analyze non autorizza implementazione

`analyze` verifica coerenza documentale.

Può dire:

* `READY FOR RED VALIDATION`

Non deve dire:

* `READY FOR IMPLEMENTATION`

Il codice produttivo può essere modificato solo dopo un RED reale eseguito e fallito per il motivo giusto.

### Perché questa regola esiste

Nel primo ciclo, un’ipotesi tecnica errata su `json_encode()` è stata corretta solo dal probe runtime.

Il gate utile non è stato il consenso tra reviewer.

Il gate utile è stato:

* eseguire il probe reale;
* eseguire il RED reale;
* osservare che falliva per `post() was called`, non per warning/exception.

---

## Regola 2 — RED per il motivo giusto

Un RED è valido solo se dimostra il rischio che il change vuole correggere.

Un RED non è valido se fallisce per:

* fixture errata;
* harness;
* warning inatteso;
* mock sbagliato;
* test mal costruito;
* errore non collegato al bug;
* assunzione non verificata.

Se il RED fallisce per il motivo sbagliato, ci si ferma e si correggono piano o task prima di toccare codice produttivo.

---

## Regola 3 — Merge-back nello stato corrente

Il closeout non è completo quando “i test sono verdi”.

Il closeout è completo solo quando il comportamento corrente e le decisioni ancora rilevanti sono nel layer attivo.

Il layer attivo può includere:

* spec canoniche;
* decision record locali;
* ADR, se la decisione è trasversale;
* test versionati.

Gli artefatti di delta e piano diventano storici.

Non devono restare l’unico posto dove vive una decisione ancora rilevante.

### Perché questa regola esiste

Nel primo ciclo, la decisione “payload non serializzabile → fail-closed” rischiava di vivere solo nel delta/archive.

È stata resa attiva inserendo `DR-WEBHOOK-001` nella spec canonica del canale Webhook.

---

## Regola 4 — Spec canonica in linguaggio di stato

La spec canonica descrive il comportamento corrente garantito.

Deve dire cosa il sistema fa oggi.

Non deve raccontare il change.

Esempio corretto:

* Il canale rifiuta payload non serializzabili e non invia richieste HTTP.

Esempio non corretto:

* Abbiamo aggiunto un guard dopo `json_encode()`.

I dettagli implementativi possono stare nei test, nel codice o nell’archive, ma non devono sostituire il contratto osservabile.

---

## Regola 5 — Decisioni di prodotto nel layer attivo

Una decisione va mantenuta nel layer attivo quando:

* spiega un comportamento non ovvio;
* registra un’alternativa scartata;
* condiziona modifiche future;
* è una scelta di prodotto mascherata da scelta tecnica.

Il delta può documentare come si è arrivati alla decisione.

La fonte attiva deve documentare perché quella decisione è ancora valida.

---

## Regola 6 — Archive storico, non fonte primaria

Dopo il closeout, gli artefatti del change possono essere archiviati in:

`changes/archive/YYYY-MM-DD-change-slug/`

L’archive conserva:

* current state;
* change delta;
* plan;
* tasks;
* analyze;
* runtime probe;
* closeout;
* retrospettiva.

L’archive non sostituisce la spec canonica.

La fonte attiva deve essere indicata chiaramente.

---

## Regola 7 — Sicurezza degli artefatti

Non tutti gli artefatti utili al workflow devono vivere nel product tree.

Non vanno committati nel repo del prodotto:

* review dettagliate di vulnerabilità non ancora corrette;
* report con file:riga di finding live;
* candidate report che espongono percorsi d’attacco;
* log o dump sensibili;
* token, endpoint, configurazioni reali;
* file generati fuori scope.

Questi materiali devono stare fuori dal repo di prodotto o in un archivio privato separato.

### Perché questa regola esiste

Nel primo ciclo, i report root di review contenevano vulnerabilità aperte e non dovevano essere versionati nel repo del plugin.

---

## Regola 8 — Staging esplicito

Per change sensibili o con file fuori scope nel working tree, non usare `git add .`.

Usare staging con path espliciti.

Prima del commit verificare:

* diff staged;
* file esclusi;
* lockfile fuori scope;
* report sensibili.

---

## Regola 9 — Gate inter-fase delle concern

A ogni transizione di fase, il maintainer verifica esplicitamente che ogni concern emersa nella fase
precedente sia stata trasformata in decisione, rischio, acceptance criterion o rejection motivata.
Se una concern resta sospesa o sparisce dagli artefatti, la fase non avanza.

Owner: maintainer. Momento: gate inter-fase.

Transizioni minime coperte:

* candidate selection → current-state;
* current-state → change-delta;
* change-delta → plan;
* plan → tasks;
* tasks → analyze;
* analyze → RED validation;
* RED validation → implementation;
* implementation → closeout.

Il gate copre anche l'esistenza reale di path e artefatti: i path e gli artefatti citati devono
esistere davvero dove dichiarato. Se un path risulta stale o spostato, la fase non avanza finché non
viene aggiornato o motivatamente respinto.

### Perché questa regola esiste

Nel ciclo 2 il gate ha cambiato outcome tre volte: una concern di compatibilità (Tailscale/CGNAT)
trasformata in decisione (`A3`); una concern di completezza (grep outbound) trasformata in un recon
esteso; un path stale (`specs/...` vs la verità di terra `docs/specs/...`) rilevato e corretto.

Limite dichiarato: il ciclo 2 prova che il gate intercetta concern reali e cambia outcome, ma non
prova ancora che il maintainer lo applichi in modo affidabile da solo — l'intercettazione è stata
sostenuta da reviewer esterni e check a terra. La portabilità a maintainer-solo va validata nel ciclo 3.

---

## Retrospettiva minima obbligatoria

Ogni ciclo deve rispondere a una domanda:

Quale outcome è cambiato perché questa regola o questo artefatto esisteva?

Se la risposta è “non lo so”, quella regola non diventa governance.

Va nel backlog delle ipotesi.
