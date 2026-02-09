---
name: version-bump
description: >
  Esegue version bump (major/minor/patch) nel repository corrente.
  Supporta Node.js (package.json), PHP (composer.json) e progetti generici (VERSION file).
  Genera/aggiorna CHANGELOG.md basandosi sui commit convenzionali.
  Supporta dry-run per mostrare le modifiche senza applicarle.
  Usa questa skill ogni volta che l'utente chiede di: aggiornare la versione, fare un bump,
  rilasciare una nuova versione, preparare un release, incrementare la versione,
  o qualsiasi variazione di "version bump". Triggera anche per richieste tipo
  "aggiorna il changelog", "che versione siamo?", "prepara il rilascio".
---

# Version Bump

Skill per eseguire version bump semantico (SemVer) in un repository.

## Workflow

1. **Rileva il tipo di progetto** nel repo corrente
2. **Leggi la versione attuale** dal file appropriato
3. **Chiedi all'utente** il tipo di bump: `major`, `minor`, o `patch`
4. **Mostra un riepilogo** (dry-run implicito) prima di procedere
5. **Applica le modifiche** solo dopo conferma dell'utente
6. **Aggiorna/genera CHANGELOG.md** basandosi sui commit dall'ultimo tag

## Esecuzione

Usa lo script bundled:

```bash
python3 "$(dirname "$0")/scripts/version_bump.py" --type <major|minor|patch> [--dry-run] [--no-changelog] [--no-scan]
```

### Parametri dello script

| Flag             | Descrizione                                       |
|------------------|---------------------------------------------------|
| `--type`         | **Obbligatorio.** `major`, `minor`, o `patch`     |
| `--dry-run`      | Mostra cosa farebbe senza modificare nulla         |
| `--no-changelog` | Salta la generazione/aggiornamento del CHANGELOG   |
| `--no-scan`      | Salta la scansione del codice sorgente             |

### Output dello script

Lo script stampa un JSON con i risultati:

```json
{
  "project_type": "node|php|generic",
  "version_file": "package.json",
  "old_version": "1.2.3",
  "new_version": "1.3.0",
  "changelog_updated": true,
  "dry_run": false,
  "files_modified": ["package.json", "src/constants.php", "CHANGELOG.md"],
  "source_references": {
    "total": 3,
    "high_confidence": [
      {"file": "src/constants.php", "line": 5, "content": "define('VERSION', '1.2.3');", "confidence": "high"}
    ],
    "medium_confidence": [
      {"file": "src/utils.js", "line": 12, "content": "// current version 1.2.3", "confidence": "medium"}
    ]
  },
  "source_replacements": [
    {"file": "src/constants.php", "replacements": 1}
  ]
}
```

### Scansione codice sorgente

Lo script scansiona automaticamente il repository cercando riferimenti alla versione corrente.

**Riferimenti ad alta confidenza** (aggiornati automaticamente):
- `define('VERSION', '1.2.3')` — PHP define
- `const APP_VERSION = '1.2.3'` — JS/PHP const
- `$version = '1.2.3'` — variabili PHP
- `__version__ = "1.2.3"` — Python
- `* Version: 1.2.3` — WordPress/docblock header
- `APP_VERSION="1.2.3"` — file .env
- `#define APP_VERSION "1.2.3"` — C/C++ preprocessor
- `version = "1.2.3"` — YAML/TOML/config generici
- `@version 1.2.3` — PHPDoc, JSDoc, Javadoc
- `@since 1.2.3` — PHPDoc, JSDoc
- `@deprecated 1.2.3` — PHPDoc, JSDoc
- `@apiVersion 1.2.3` — apidoc
- `\version 1.2.3`, `\since 1.2.3` — Doxygen
- `:version: 1.2.3`, `:since: 1.2.3` — Python/ReST docstring
- `Current version: 1.2.3` — Markdown/README

**Riferimenti a media confidenza** (mostrati per revisione):
- Occorrenze della stringa versione in righe che contengono la parola "version"

**Esclusi automaticamente:**
- URL contenenti la versione (es. CDN paths)
- Dipendenze in package.json, composer.json
- Lock files (package-lock.json, composer.lock, ecc.)
- Directory: node_modules, vendor, dist, build, .git, __pycache__
- File binari e minificati
- Tag `@since` / `@deprecated` che riferiscono a versioni DIVERSE da quella corrente

**IMPORTANTE**: Nella fase di anteprima, mostra SEMPRE all'utente la lista dei riferimenti trovati
con il livello di confidenza. Presta particolare attenzione a:
- Match **"medium"** — chiedi conferma esplicita prima di sostituirli
- Tag **`@since`** — indicano "introdotto nella versione X" e di norma NON vanno aggiornati
  (a meno che l'utente non lo richieda esplicitamente). Segnalali separatamente
- Tag **`@version`** — indicano la versione corrente del file/classe e vanno aggiornati
- Tag **`@deprecated`** — indicano "deprecato dalla versione X", di norma NON vanno aggiornati

## Procedura passo-passo

### 1. Rileva e mostra lo stato attuale

Esegui lo script in dry-run per mostrare all'utente la situazione:

```bash
python3 scripts/version_bump.py --type patch --dry-run
```

Comunica all'utente:
- Tipo di progetto rilevato
- Versione attuale
- File che verranno modificati

### 2. Chiedi il tipo di bump

Chiedi SEMPRE all'utente quale tipo di bump vuole:
- **patch** (x.y.Z) → bugfix, correzioni minori
- **minor** (x.Y.0) → nuove funzionalità retrocompatibili
- **major** (X.0.0) → breaking changes

Se l'utente ha già specificato il tipo nella richiesta iniziale, conferma e procedi.

### 3. Mostra anteprima e chiedi conferma

Esegui in dry-run con il tipo scelto e mostra:
- Versione attuale → nuova versione
- **Riferimenti nel codice sorgente trovati** (divisi per confidenza)
- Commit che entreranno nel CHANGELOG
- File che verranno modificati

Per i riferimenti nel sorgente:
- **High confidence**: conferma cumulativa (es. "Trovati 5 riferimenti ad alta confidenza, procedo?")
- **Medium confidence**: mostra ogni match e chiedi conferma individuale. Se l'utente vuole
  escluderli, riesegui con `--no-scan` e gestisci manualmente

Chiedi conferma esplicita prima di procedere.

### 4. Applica

Solo dopo conferma, esegui senza `--dry-run`:

```bash
python3 scripts/version_bump.py --type <tipo-scelto>
```

### 5. Riepilogo finale

Mostra:
- Versione aggiornata
- File modificati
- Suggerisci i prossimi passi (commit, tag, push) — ma NON eseguirli automaticamente

## Gestione errori

- **Nessun file di versione trovato**: suggerisci di creare un file `VERSION` con `0.1.0`
- **Versione non valida nel file**: segnala l'errore e mostra il valore trovato
- **Git non disponibile**: procedi senza CHANGELOG, avvisa l'utente
- **Nessun tag git trovato**: usa tutti i commit per il CHANGELOG
- **Working directory dirty**: avvisa ma non bloccare (l'utente decide)

## Note

- La skill NON esegue mai commit, tag o push automaticamente
- Segui sempre SemVer 2.0.0 (https://semver.org)
- Il CHANGELOG segue il formato Keep a Changelog (https://keepachangelog.com)
- Se esistono sia `package.json` che `composer.json`, chiedi all'utente quale usare
- La scansione sorgente usa `--no-scan` se l'utente vuole gestire manualmente i riferimenti
- I match "medium confidence" richiedono sempre revisione esplicita dell'utente
