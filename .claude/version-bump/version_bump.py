#!/usr/bin/env python3
"""
Version Bump Script
Supporta: Node.js (package.json), PHP (composer.json), generico (VERSION)
"""

import argparse
import json
import os
import re
import subprocess
import sys
from datetime import date
from pathlib import Path
from typing import Optional

# --- Source scanning config ---

# Directory da escludere dalla scansione
EXCLUDED_DIRS = {
    ".git", "node_modules", "vendor", "dist", "build", ".next", ".nuxt",
    "__pycache__", ".cache", ".idea", ".vscode", "coverage", ".tox",
    "venv", ".venv", "env", ".env", "bower_components", "jspm_packages",
    "target",  # Rust/Java
}

# File da escludere (sono file di versione primari o lock files)
EXCLUDED_FILES = {
    "package.json", "composer.json", "VERSION", "CHANGELOG.md",
    "package-lock.json", "composer.lock", "yarn.lock", "pnpm-lock.yaml",
    "Cargo.lock", "poetry.lock", "Pipfile.lock", "Gemfile.lock",
}

# Estensioni di file binari/non-testo da ignorare
BINARY_EXTENSIONS = {
    ".png", ".jpg", ".jpeg", ".gif", ".ico", ".svg", ".webp", ".bmp",
    ".woff", ".woff2", ".ttf", ".eot", ".otf",
    ".zip", ".tar", ".gz", ".bz2", ".rar", ".7z",
    ".pdf", ".doc", ".docx", ".xls", ".xlsx",
    ".exe", ".dll", ".so", ".dylib", ".o", ".a",
    ".pyc", ".pyo", ".class", ".jar",
    ".mp3", ".mp4", ".avi", ".mov", ".wav",
    ".sqlite", ".db", ".min.js", ".min.css", ".map",
}

# Pattern che indicano un riferimento alla versione del progetto (alta confidenza)
# Catturano il contesto attorno alla stringa versione
VERSION_CONTEXT_PATTERNS = [
    # PHP define/const
    r"""define\s*\(\s*['"][A-Z_]*VERSION[A-Z_]*['"]\s*,\s*['"]{}['"]""",
    r"""const\s+[A-Z_]*VERSION[A-Z_]*\s*=\s*['"]{}['"]""",
    # PHP variabili
    r"""\$[a-z_]*version[a-z_]*\s*=\s*['"]{}['"]""",
    # JS/TS const/let/var
    r"""(?:const|let|var)\s+[a-zA-Z_]*[Vv]ersion[a-zA-Z_]*\s*=\s*['"`]{}['"`]""",
    # Python
    r"""__version__\s*=\s*['"]{}['"]""",
    r"""VERSION\s*=\s*['"]{}['"]""",
    r"""version\s*=\s*['"]{}['"]""",
    # Docblock / header comments
    r"""[*#/]\s*@?[Vv]ersion:?\s*{}""",
    r"""[*#/]\s*Version:\s*{}""",
    # WordPress style header
    r"""Version:\s*{}""",
    # PHPDoc tags
    r"""\*\s*@version\s+{}""",
    r"""\*\s*@since\s+{}""",
    r"""\*\s*@deprecated\s+{}""",
    r"""\*\s*@package\s+\S+\s+{}""",
    # JSDoc tags
    r"""[/*]\s*@version\s+{}""",
    r"""[/*]\s*@since\s+{}""",
    r"""[/*]\s*@deprecated\s+{}""",
    # apidoc style
    r"""@apiVersion\s+{}""",
    # TypeDoc / TSDoc
    r"""@packageDocumentation.*{}""",
    # Python docstring version references (in triple-quoted blocks)
    r""":version:\s*{}""",
    r""":since:\s*{}""",
    # RDoc (Ruby)
    r"""#\s*:version:\s*{}""",
    # Javadoc
    r"""\*\s*@version\s+{}""",
    # Doxygen
    r"""[\\@]version\s+{}""",
    r"""[\\@]since\s+{}""",
    r"""[\\@]deprecated\s+{}""",
    # Markdown/text headers (README, docs)
    r"""[Cc]urrent\s+[Vv]ersion:?\s*{}""",
    r"""[Ll]atest\s+[Vv]ersion:?\s*{}""",
    # Ruby
    r"""VERSION\s*=\s*['"]{}['"]""",
    # Rust Cargo.toml (non-dependency)
    r"""^version\s*=\s*['"]{}['"]""",
    # Generic assignment con "version" nel nome
    r"""['"_-]version['"_-]\s*[:=]\s*['"]{}['"]""",
    # YAML style
    r"""version:\s*['"]?{}['"]?""",
    # .env style
    r"""[A-Z_]*VERSION[A-Z_]*\s*=\s*['"]?{}['"]?""",
    # C/C++ preprocessor define
    r"""#\s*define\s+[A-Z_]*VERSION[A-Z_]*\s+['"]{}['"]""",
]


def find_project_type(cwd: str) -> tuple[str, str]:
    """Rileva il tipo di progetto e restituisce (tipo, percorso_file).
    
    Priorità: se ci sono più file, restituisce tutti quelli trovati.
    Il chiamante gestisce il conflitto.
    """
    candidates = []
    
    pkg = os.path.join(cwd, "package.json")
    if os.path.isfile(pkg):
        candidates.append(("node", pkg))
    
    composer = os.path.join(cwd, "composer.json")
    if os.path.isfile(composer):
        candidates.append(("php", composer))
    
    version_file = os.path.join(cwd, "VERSION")
    if os.path.isfile(version_file):
        candidates.append(("generic", version_file))
    
    if not candidates:
        return ("none", "")
    
    if len(candidates) == 1:
        return candidates[0]
    
    # Se ci sono sia package.json che composer.json, segnala conflitto
    non_generic = [c for c in candidates if c[0] != "generic"]
    if len(non_generic) > 1:
        # Conflitto: restituisci il primo ma segnala
        return ("conflict", json.dumps([c[0] for c in candidates]))
    
    # Preferisci il file specifico al generico VERSION
    if non_generic:
        return non_generic[0]
    
    return candidates[0]


def read_version(project_type: str, filepath: str) -> Optional[str]:
    """Legge la versione attuale dal file."""
    try:
        if project_type in ("node", "php"):
            with open(filepath, "r", encoding="utf-8") as f:
                data = json.load(f)
            return data.get("version")
        elif project_type == "generic":
            with open(filepath, "r", encoding="utf-8") as f:
                return f.read().strip()
    except (json.JSONDecodeError, IOError) as e:
        print(json.dumps({"error": f"Impossibile leggere {filepath}: {e}"}), file=sys.stderr)
        return None
    return None


def parse_semver(version: str) -> Optional[tuple[int, int, int, str]]:
    """Parsa una versione SemVer. Restituisce (major, minor, patch, prefix)."""
    match = re.match(r'^(v?)(\d+)\.(\d+)\.(\d+)(.*)$', version)
    if not match:
        return None
    prefix = match.group(1)
    major = int(match.group(2))
    minor = int(match.group(3))
    patch = int(match.group(4))
    # Ignora pre-release/build metadata per il bump
    return (major, minor, patch, prefix)


def bump_version(current: str, bump_type: str) -> Optional[str]:
    """Calcola la nuova versione."""
    parsed = parse_semver(current)
    if not parsed:
        return None
    
    major, minor, patch, prefix = parsed
    
    if bump_type == "major":
        major += 1
        minor = 0
        patch = 0
    elif bump_type == "minor":
        minor += 1
        patch = 0
    elif bump_type == "patch":
        patch += 1
    else:
        return None
    
    return f"{prefix}{major}.{minor}.{patch}"


def write_version(project_type: str, filepath: str, new_version: str) -> bool:
    """Scrive la nuova versione nel file."""
    try:
        if project_type in ("node", "php"):
            with open(filepath, "r", encoding="utf-8") as f:
                data = json.load(f)
            data["version"] = new_version
            with open(filepath, "w", encoding="utf-8") as f:
                json.dump(data, f, indent=2, ensure_ascii=False)
                f.write("\n")  # trailing newline
        elif project_type == "generic":
            with open(filepath, "w", encoding="utf-8") as f:
                f.write(new_version + "\n")
        return True
    except IOError as e:
        print(json.dumps({"error": f"Impossibile scrivere {filepath}: {e}"}), file=sys.stderr)
        return False


def git_available() -> bool:
    """Verifica se git è disponibile e siamo in un repo."""
    try:
        result = subprocess.run(
            ["git", "rev-parse", "--is-inside-work-tree"],
            capture_output=True, text=True, timeout=5
        )
        return result.returncode == 0
    except (FileNotFoundError, subprocess.TimeoutExpired):
        return False


def get_last_tag() -> Optional[str]:
    """Trova l'ultimo tag di versione."""
    try:
        result = subprocess.run(
            ["git", "describe", "--tags", "--abbrev=0"],
            capture_output=True, text=True, timeout=5
        )
        if result.returncode == 0:
            return result.stdout.strip()
    except (FileNotFoundError, subprocess.TimeoutExpired):
        pass
    return None


def get_commits_since(tag: Optional[str]) -> list[dict]:
    """Recupera i commit dal tag indicato (o tutti se tag è None)."""
    try:
        cmd = ["git", "log", "--pretty=format:%H|%s", "--no-merges"]
        if tag:
            cmd.append(f"{tag}..HEAD")
        
        result = subprocess.run(cmd, capture_output=True, text=True, timeout=10)
        if result.returncode != 0 or not result.stdout.strip():
            return []
        
        commits = []
        for line in result.stdout.strip().split("\n"):
            if "|" in line:
                hash_val, subject = line.split("|", 1)
                commits.append({"hash": hash_val[:8], "subject": subject})
        return commits
    except (FileNotFoundError, subprocess.TimeoutExpired):
        return []


def categorize_commits(commits: list[dict]) -> dict[str, list[dict]]:
    """Categorizza i commit secondo Conventional Commits."""
    categories = {
        "Added": [],
        "Fixed": [],
        "Changed": [],
        "Removed": [],
        "Other": [],
    }
    
    for commit in commits:
        subject = commit["subject"]
        lower = subject.lower()
        
        if lower.startswith("feat"):
            categories["Added"].append(commit)
        elif lower.startswith("fix"):
            categories["Fixed"].append(commit)
        elif lower.startswith(("refactor", "perf", "style")):
            categories["Changed"].append(commit)
        elif lower.startswith(("remove", "deprecat")):
            categories["Removed"].append(commit)
        else:
            categories["Other"].append(commit)
    
    return {k: v for k, v in categories.items() if v}


def generate_changelog_entry(new_version: str, commits: list[dict]) -> str:
    """Genera una entry per il CHANGELOG."""
    today = date.today().isoformat()
    lines = [f"## [{new_version}] - {today}", ""]
    
    if not commits:
        lines.append("- Nessun commit trovato")
        lines.append("")
        return "\n".join(lines)
    
    categorized = categorize_commits(commits)
    
    for category, cat_commits in categorized.items():
        lines.append(f"### {category}")
        lines.append("")
        for c in cat_commits:
            # Rimuovi il prefisso convenzionale per pulizia
            subject = re.sub(r'^(feat|fix|refactor|perf|style|remove|deprecat)\w*(\(.+?\))?:\s*', '', c["subject"], flags=re.IGNORECASE)
            if not subject:
                subject = c["subject"]
            lines.append(f"- {subject} ({c['hash']})")
        lines.append("")
    
    return "\n".join(lines)


def update_changelog(cwd: str, new_version: str, commits: list[dict]) -> bool:
    """Aggiorna o crea CHANGELOG.md."""
    changelog_path = os.path.join(cwd, "CHANGELOG.md")
    entry = generate_changelog_entry(new_version, commits)
    
    try:
        if os.path.isfile(changelog_path):
            with open(changelog_path, "r", encoding="utf-8") as f:
                content = f.read()
            
            # Inserisci dopo l'header principale
            # Cerca il pattern "# Changelog" o simile
            header_pattern = re.compile(r'^(# .+\n(?:\n|>.*\n)*)', re.MULTILINE)
            match = header_pattern.match(content)
            
            if match:
                insert_pos = match.end()
                new_content = content[:insert_pos] + "\n" + entry + "\n" + content[insert_pos:]
            else:
                new_content = entry + "\n" + content
        else:
            header = "# Changelog\n\nTutte le modifiche rilevanti a questo progetto sono documentate in questo file.\n\nIl formato è basato su [Keep a Changelog](https://keepachangelog.com/it/1.1.0/),\ne questo progetto aderisce a [Semantic Versioning](https://semver.org/lang/it/spec/v2.0.0.html).\n\n"
            new_content = header + entry + "\n"
        
        with open(changelog_path, "w", encoding="utf-8") as f:
            f.write(new_content)
        
        return True
    except IOError as e:
        print(json.dumps({"error": f"Impossibile aggiornare CHANGELOG.md: {e}"}), file=sys.stderr)
        return False


def check_working_directory() -> Optional[str]:
    """Verifica se ci sono modifiche non committate."""
    try:
        result = subprocess.run(
            ["git", "status", "--porcelain"],
            capture_output=True, text=True, timeout=5
        )
        if result.returncode == 0 and result.stdout.strip():
            return "Working directory con modifiche non committate"
    except (FileNotFoundError, subprocess.TimeoutExpired):
        pass
    return None


# --- Source code scanning ---

def _should_skip_path(path: Path, cwd: str) -> bool:
    """Verifica se un path deve essere escluso dalla scansione."""
    for part in path.relative_to(cwd).parts:
        if part in EXCLUDED_DIRS:
            return True
    return False


def _is_scannable_file(filepath: Path) -> bool:
    """Verifica se un file è scansionabile (non binario, non escluso)."""
    if filepath.name in EXCLUDED_FILES:
        return False
    if filepath.suffix.lower() in BINARY_EXTENSIONS:
        return False
    # Ignora file nascosti (tranne .env-like)
    if filepath.name.startswith(".") and not filepath.name.startswith(".env"):
        return False
    return True


def _escape_version_for_regex(version: str) -> str:
    """Escapa la stringa versione per uso in regex (i punti sono letterali)."""
    return re.escape(version)


def scan_source_for_version(cwd: str, version: str, version_file: str) -> list[dict]:
    """
    Scansiona il codice sorgente cercando riferimenti alla versione corrente.
    
    Restituisce una lista di match con:
    - file: percorso relativo
    - line: numero riga (1-based)
    - content: contenuto della riga (trimmato)
    - confidence: "high" se matcha un pattern noto, "medium" altrimenti
    - pattern: descrizione del pattern matchato (solo per high)
    """
    matches = []
    escaped_version = _escape_version_for_regex(version)
    
    # Compila i pattern di contesto
    compiled_patterns = []
    for pattern_template in VERSION_CONTEXT_PATTERNS:
        try:
            compiled = re.compile(pattern_template.format(escaped_version), re.IGNORECASE)
            compiled_patterns.append((compiled, pattern_template))
        except re.error:
            continue
    
    # Pattern generico: la stringa versione appare nella riga
    generic_pattern = re.compile(re.escape(version))
    
    # Nomi file del version file principale (da escludere)
    primary_basename = os.path.basename(version_file)
    
    for root, dirs, files in os.walk(cwd):
        root_path = Path(root)
        
        # Escludi directory
        if _should_skip_path(root_path, cwd):
            dirs.clear()
            continue
        
        # Rimuovi directory escluse dalla ricorsione
        dirs[:] = [d for d in dirs if d not in EXCLUDED_DIRS]
        
        for filename in files:
            filepath = root_path / filename
            
            if not _is_scannable_file(filepath):
                continue
            
            # Salta il file di versione primario
            rel_path = str(filepath.relative_to(cwd))
            if filename == primary_basename and root == cwd:
                continue
            
            try:
                with open(filepath, "r", encoding="utf-8", errors="ignore") as f:
                    for line_num, line in enumerate(f, 1):
                        if version not in line:
                            continue
                        
                        stripped = line.rstrip("\n\r")
                        
                        # Controlla pattern ad alta confidenza
                        matched_high = False
                        for compiled, template in compiled_patterns:
                            if compiled.search(stripped):
                                matches.append({
                                    "file": rel_path,
                                    "line": line_num,
                                    "content": stripped.strip(),
                                    "confidence": "high",
                                })
                                matched_high = True
                                break
                        
                        if not matched_high:
                            # Match generico — medium confidence
                            # Filtra falsi positivi ovvi
                            if _is_likely_version_reference(stripped, version):
                                matches.append({
                                    "file": rel_path,
                                    "line": line_num,
                                    "content": stripped.strip(),
                                    "confidence": "medium",
                                })
            except (IOError, OSError):
                continue
    
    return matches


def _is_likely_version_reference(line: str, version: str) -> bool:
    """
    Filtra falsi positivi per match a media confidenza.
    Restituisce True se la riga sembra un riferimento alla versione del progetto.
    """
    lower = line.lower().strip()
    
    # Esclude righe che sembrano dipendenze
    # es: "require": { "some-pkg": "^1.2.3" }
    if re.search(r'["\']\s*:\s*["\'][~^>=<]?' + re.escape(version), line):
        # Potrebbe essere una dipendenza — controlla se c'è "version" nel contesto
        if "version" not in lower:
            return False
    
    # Esclude righe di lock file inline
    if '"integrity"' in lower or '"resolved"' in lower:
        return False
    
    # Esclude URL che contengono la versione come parte del path
    if re.search(r'https?://.*' + re.escape(version), line):
        return False
    
    return True


def replace_version_in_source(cwd: str, matches: list[dict], old_version: str, new_version: str) -> list[dict]:
    """
    Sostituisce la versione nei file sorgente per i match forniti.
    Restituisce la lista dei file effettivamente modificati con dettagli.
    """
    # Raggruppa match per file
    by_file: dict[str, list[dict]] = {}
    for m in matches:
        by_file.setdefault(m["file"], []).append(m)
    
    replaced = []
    
    for rel_path, file_matches in by_file.items():
        filepath = os.path.join(cwd, rel_path)
        try:
            with open(filepath, "r", encoding="utf-8") as f:
                lines = f.readlines()
            
            modified = False
            replacements_in_file = 0
            for m in file_matches:
                idx = m["line"] - 1  # 0-based
                if idx < len(lines) and old_version in lines[idx]:
                    lines[idx] = lines[idx].replace(old_version, new_version)
                    modified = True
                    replacements_in_file += 1
            
            if modified:
                with open(filepath, "w", encoding="utf-8") as f:
                    f.writelines(lines)
                replaced.append({
                    "file": rel_path,
                    "replacements": replacements_in_file,
                })
        except (IOError, OSError) as e:
            replaced.append({
                "file": rel_path,
                "error": str(e),
            })
    
    return replaced


def main():
    parser = argparse.ArgumentParser(description="Version Bump")
    parser.add_argument("--type", required=True, choices=["major", "minor", "patch"],
                        help="Tipo di bump: major, minor, o patch")
    parser.add_argument("--dry-run", action="store_true",
                        help="Mostra cosa farebbe senza modificare nulla")
    parser.add_argument("--no-changelog", action="store_true",
                        help="Salta la generazione del CHANGELOG")
    parser.add_argument("--no-scan", action="store_true",
                        help="Salta la scansione del codice sorgente")
    
    args = parser.parse_args()
    cwd = os.getcwd()
    
    # 1. Rileva progetto
    project_type, version_file = find_project_type(cwd)
    
    if project_type == "none":
        result = {
            "error": "Nessun file di versione trovato (package.json, composer.json, VERSION)",
            "suggestion": "Crea un file VERSION con contenuto: 0.1.0"
        }
        print(json.dumps(result, indent=2))
        sys.exit(1)
    
    if project_type == "conflict":
        result = {
            "error": "Trovati più file di versione",
            "found": json.loads(version_file),
            "suggestion": "Specifica quale file usare"
        }
        print(json.dumps(result, indent=2))
        sys.exit(1)
    
    # 2. Leggi versione attuale
    current_version = read_version(project_type, version_file)
    if not current_version:
        result = {
            "error": f"Impossibile leggere la versione da {version_file}",
            "project_type": project_type
        }
        print(json.dumps(result, indent=2))
        sys.exit(1)
    
    # 3. Calcola nuova versione
    new_version = bump_version(current_version, args.type)
    if not new_version:
        result = {
            "error": f"Versione non valida: '{current_version}' — deve essere SemVer (es: 1.2.3)",
            "current_version": current_version
        }
        print(json.dumps(result, indent=2))
        sys.exit(1)
    
    # 4. Git info
    has_git = git_available()
    warnings = []
    commits = []
    
    if has_git:
        dirty = check_working_directory()
        if dirty:
            warnings.append(dirty)
        
        last_tag = get_last_tag()
        commits = get_commits_since(last_tag)
    else:
        warnings.append("Git non disponibile — CHANGELOG non generabile dai commit")
    
    # 5. Scansione codice sorgente
    source_matches = []
    if not args.no_scan:
        # Rimuovi prefisso 'v' per la ricerca (cerca sia "1.2.3" che "v1.2.3")
        clean_version = current_version.lstrip("v")
        source_matches = scan_source_for_version(cwd, clean_version, version_file)
        # Se la versione originale ha il prefisso v, cerca anche con quello
        if current_version != clean_version:
            v_matches = scan_source_for_version(cwd, current_version, version_file)
            # Dedup per (file, line)
            seen = {(m["file"], m["line"]) for m in source_matches}
            for m in v_matches:
                if (m["file"], m["line"]) not in seen:
                    source_matches.append(m)
    
    # 6. Build risultato
    files_modified = []
    changelog_updated = False
    source_replacements = []
    
    if not args.dry_run:
        # Aggiorna versione nel file principale
        if write_version(project_type, version_file, new_version):
            files_modified.append(os.path.basename(version_file))
        else:
            print(json.dumps({"error": "Errore nella scrittura della versione"}))
            sys.exit(1)
        
        # Aggiorna riferimenti nel sorgente
        if source_matches:
            clean_old = current_version.lstrip("v")
            clean_new = new_version.lstrip("v")
            source_replacements = replace_version_in_source(cwd, source_matches, clean_old, clean_new)
            # Se c'era il prefisso v, gestisci anche quello
            if current_version != clean_old:
                v_matches = [m for m in source_matches if current_version in m.get("content", "")]
                if v_matches:
                    v_replacements = replace_version_in_source(cwd, v_matches, current_version, new_version)
                    source_replacements.extend(v_replacements)
            for sr in source_replacements:
                if "error" not in sr and sr["file"] not in files_modified:
                    files_modified.append(sr["file"])
        
        # Aggiorna CHANGELOG
        if not args.no_changelog and has_git:
            if update_changelog(cwd, new_version, commits):
                changelog_updated = True
                files_modified.append("CHANGELOG.md")
    else:
        files_modified.append(os.path.basename(version_file))
        if source_matches:
            for m in source_matches:
                if m["file"] not in files_modified:
                    files_modified.append(m["file"])
        if not args.no_changelog and has_git:
            files_modified.append("CHANGELOG.md")
            changelog_updated = True
    
    # 7. Output
    result = {
        "project_type": project_type,
        "version_file": os.path.basename(version_file),
        "old_version": current_version,
        "new_version": new_version,
        "bump_type": args.type,
        "changelog_updated": changelog_updated,
        "dry_run": args.dry_run,
        "files_modified": files_modified,
    }
    
    if warnings:
        result["warnings"] = warnings
    
    if source_matches:
        result["source_references"] = {
            "total": len(source_matches),
            "high_confidence": [m for m in source_matches if m["confidence"] == "high"],
            "medium_confidence": [m for m in source_matches if m["confidence"] == "medium"],
        }
    
    if source_replacements and not args.dry_run:
        result["source_replacements"] = source_replacements
    
    if commits and not args.no_changelog:
        categorized = categorize_commits(commits)
        result["changelog_preview"] = {
            cat: [c["subject"] for c in cat_commits]
            for cat, cat_commits in categorized.items()
        }
    
    print(json.dumps(result, indent=2))


if __name__ == "__main__":
    main()
