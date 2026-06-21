# Key lesson

I gate che hanno funzionato sono stati quelli ancorati alla verità di terra:

- eseguire il test reale;
- osservare il RED reale;
- leggere l'artefatto realmente prodotto;
- verificare il working tree reale.

Il consenso tra reviewer o tra AI non è validazione.

Il maintainer resta il gate, soprattutto quando più reviewer concordano: modelli diversi
possono condividere lo stesso punto cieco.

Questa lezione va considerata parte del workflow:

- `analyze` verifica coerenza documentale;
- il RED eseguito verifica il comportamento reale;
- la review del file prodotto verifica l'artefatto reale;
- nessun passaggio AI↔AI sostituisce il giudizio del maintainer.

---

# Pilot retrospective — webhook-json-encode-guard

## Cosa è andato bene

- Il pilota era piccolo, localizzato e a basso rischio: un solo metodo di produzione, due test, una spec nuova.
- La Runtime RED Probe ha smontato un'assunzione teorica (il timore che `convertWarningsToExceptions` rendesse il RED "sbagliato"): il probe reale ha mostrato che `json_encode()` non emette warning, quindi il guard `json_encode` semplice era sufficiente, senza `@`/`set_error_handler`/`wp_json_encode`.
- Il RED ha fallito per il motivo giusto ("post() was called"), confermato sul codice reale prima del fix.
- I gate di qualità (phpcs, phpstan, suite unit completa) erano già a baseline pulita, rendendo il GREEN inequivocabile.

## Cosa ha richiesto correzione in closeout

- La spec iniziale "certificava" le protezioni del client HTTP nella sezione Trasporto: corretta in linguaggio di **delega**, non di certificazione.
- La spec doveva contenere una **Decision record attiva** (`DR-WEBHOOK-001`), non solo prosa descrittiva.
- Necessità di mantenere il Markdown render-safe (nessun fence annidato).

## Punto cieco condiviso

Più passaggi di analisi (incluse revisioni AI) possono concordare e restare comunque
incompleti. La validazione è arrivata dall'esecuzione reale e dal giudizio del maintainer,
non dall'accordo tra revisori.

## Follow-up debt

- **Risky test pre-esistente:** `OpsHealthDashboard\Tests\Unit\Services\CheckRunnerTest::test_clear_results_deletes_from_storage`
  (`tests/Unit/Services/CheckRunnerTest.php:309`) — segnalato da PHPUnit come "This test did not
  perform any assertions". **Pre-esistente**, in un file non toccato da questo change.
  **Non corretto qui** (fuori scope). Da affrontare come change separato.
- **Disallineamento versione `package-lock.json`** (`0.5.0` → `0.6.2`, sincronizzato da
  `npm install`): sintomo del finding di review sul disallineamento delle versioni tra
  `package.json`, `composer.json`, header plugin, `readme.txt`. Da trattare separatamente.
