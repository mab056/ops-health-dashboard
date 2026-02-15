## Summary

Descrivi in modo chiaro cosa cambia questa PR e perché.

## Type of change

- [ ] `feat` - Nuova funzionalità
- [ ] `fix` - Bug fix
- [ ] `refactor` - Refactor senza cambio comportamento
- [ ] `test` - Aggiunta/modifica test
- [ ] `docs` - Solo documentazione
- [ ] `chore` - Tooling/CI/build

## Related issues

- Closes #
- Related #

## Changes made

- 
- 
- 

## Testing

### Test strategy (TDD)

- [ ] RED: test aggiunto/aggiornato e inizialmente fallito
- [ ] GREEN: implementazione minima per far passare i test
- [ ] REFACTOR: refactor eseguito mantenendo i test verdi

### Commands executed

- [ ] `composer test:unit`
- [ ] `composer test:integration`
- [ ] `composer phpcs`
- [ ] `composer analyse`

Dettagli output/errori rilevanti (obbligatorio se qualcosa non è stato eseguito):

```text
Incolla qui un output sintetico o un errore concreto (es. DB non raggiungibile)
```

## Manual test steps

1. 
2. 
3. 

## Checklist architetturale (obbligatoria)

- [ ] Nessun singleton introdotto
- [ ] Nessun metodo/proprietà statici/statiche
- [ ] Nessuna classe/metodo `final`
- [ ] Dependency injection via costruttore rispettata
- [ ] Design interface-first rispettato dove applicabile
- [ ] Nessun accesso globale diretto WordPress nella business logic

## Checklist sicurezza (obbligatoria)

- [ ] Input sanitizzati (`sanitize_text_field`, `sanitize_email`, `esc_url_raw`, `absint`, ...)
- [ ] Output escaped (`esc_html`, `esc_attr`, `esc_url`, `esc_js`)
- [ ] Capability check presente (`current_user_can('manage_options')`) se area admin
- [ ] Nonce verificato su form/AJAX
- [ ] Anti-SSRF applicata per webhook/HTTP esterni

## Checklist regressioni

- [ ] Verificato `git diff --name-only` e `git diff`
- [ ] Copertura test aggiornata sul comportamento modificato
- [ ] Nessuna regressione cron/scheduler (no duplicati, self-healing attivo)
- [ ] Compatibilità del tooling in esecuzione sequenziale/parallela verificata

## Screenshots / recordings (se UI)

<!-- Inserisci screenshot o GIF per HealthScreen, Alert Settings, Dashboard Widget, ecc. -->

## Additional notes

<!-- Rischi residui, tradeoff tecnici, follow-up -->
