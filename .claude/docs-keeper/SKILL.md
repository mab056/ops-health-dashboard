---
name: docs-keeper
description: Scan a repository and update or create project documentation files. Keeps README.md, CONTRIBUTING.md, DEVELOPMENT_PLAN.md, CLAUDE.md, MEMORY.md, CHANGELOG.md, SECURITY.md, .env.example, and LICENSE in sync with the actual state of the codebase. Conservative by default — proposes changes and waits for user confirmation before writing. Use when the user wants to update docs, audit documentation freshness, or bootstrap docs for a project.
---

# Docs Keeper

A skill for maintaining repository documentation in sync with the codebase. Acts as a documentation auditor: scans the repo, identifies what's outdated or missing, proposes changes, and applies them only after user approval.

## Core Principle

**Never write without asking first.** This skill is conservative by design. Every proposed change is presented to the user for review before being applied. The user always has final say.

## Trigger Conditions

- User mentions updating docs: "update the docs", "refresh documentation", "docs are stale"
- User asks to audit docs: "check my docs", "what docs am I missing"
- User wants to bootstrap docs: "create docs for this project", "set up documentation"
- User mentions specific files: "update the README", "create a CONTRIBUTING guide"
- After significant code changes: "I just refactored X, update the docs"

---

## Workflow

### Phase 1: Repo Scan

Before touching any file, build a complete picture of the repository:

1. **Project identity** — Read `package.json`, `composer.json`, `pyproject.toml`, `Cargo.toml`, or equivalent. Extract: name, version, description, scripts/commands, dependencies.
2. **Tech stack** — Identify languages, frameworks, build tools, package managers.
3. **Structure** — Map top-level directory layout. Identify entry points, source directories, config files.
4. **Git context** — Check recent commits (`git log --oneline -20`), current branch, tags. Look for conventional commits.
5. **CI/CD** — Check `.github/workflows/`, `.gitlab-ci.yml`, `Jenkinsfile`, etc.
6. **Existing docs** — List all documentation files already present, check last modified dates vs last code changes.
7. **Environment variables** — Grep for `process.env`, `$_ENV`, `os.environ`, `env()`, `getenv()` and similar patterns to map used env vars.
8. **Claude Code files** — Check for existing `CLAUDE.md` and `MEMORY.md`, read their current content.

### Phase 2: Audit & Plan

Produce a summary for the user:

```
📋 Docs Audit — [project-name]
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Stack: [detected stack]
Last code change: [date]

File                Status          Action
─────────────────────────────────────────────
README.md           ✅ exists       🔄 update (outdated sections detected)
CONTRIBUTING.md     ❌ missing      ➕ create
DEVELOPMENT_PLAN.md ❌ missing      ⏭️  skip (no explicit plan found)
CLAUDE.md           ✅ exists       🔄 update (new scripts detected)
MEMORY.md           ✅ exists       🔄 update (stale context)
CHANGELOG.md        ❌ missing      ➕ create (from git history)
SECURITY.md         ❌ missing      ➕ create
LICENSE             ❌ missing      ⚠️  warn (recommend adding one)
.env.example        ❌ missing      ➕ create (12 env vars detected)

Legend: ✅ present  ❌ missing  🔄 update  ➕ create  ⏭️ skip  ⚠️ warning
```

Wait for user confirmation before proceeding. The user may:
- Approve all proposed actions
- Cherry-pick specific files
- Change an action (e.g., skip a file, force create one marked as skip)
- Ask for more detail on what would change

### Phase 3: Execute (one file at a time)

For each approved file, follow the file-specific instructions below. After generating each file's content:

1. Show the user a **diff preview** (for updates) or **content preview** (for new files)
2. Wait for approval
3. Apply the change only after explicit confirmation
4. Move to the next file

### Phase 4: Report

After all files are processed, output a final summary:

```
✅ Docs Keeper — Done

  Updated:  README.md, CLAUDE.md, MEMORY.md
  Created:  CONTRIBUTING.md, CHANGELOG.md, .env.example
  Skipped:  DEVELOPMENT_PLAN.md (no plan found)
  Warning:  LICENSE missing — consider adding one

Next run: after significant code changes or `update docs`
```

---

## File-Specific Instructions

### README.md

The project's front door. Must be accurate and current.

**Sections to include/update:**
- **Title & description** — from package manifest or repo description
- **Badges** — CI status, version, license (if applicable)
- **Quick start** — install + run commands, copied from actual scripts
- **Prerequisites** — runtime versions, required tools
- **Configuration** — reference to `.env.example` if present
- **Project structure** — brief overview of main directories (only if project is non-trivial)
- **Scripts/Commands** — actual available commands from package manifest
- **Contributing** — link to CONTRIBUTING.md if present
- **License** — reference LICENSE file if present

**Update rules:**
- Cross-reference scripts in README with actual `package.json` scripts (or equivalent)
- Check install instructions still work with current dependencies
- Don't invent features — only document what exists in the code
- Preserve any custom sections the user has added (detect by heading names not matching standard ones)

### CONTRIBUTING.md

Guide for contributors. Only create if the project seems intended for collaboration (open source, team project, has PR templates, etc.). When in doubt, ask the user.

**Sections:**
- **Getting started** — clone, install, setup steps
- **Development workflow** — branch strategy (infer from git history if possible), PR process
- **Code style** — linters, formatters, conventions detected in config files (eslint, prettier, phpcs, etc.)
- **Testing** — how to run tests, what framework is used
- **Commit conventions** — detect if conventional commits are used, document the pattern
- **Reporting issues** — guidelines

### DEVELOPMENT_PLAN.md

**Conditional creation** — Only create or update if there's a clear, explicit development plan found in:
- Existing DEVELOPMENT_PLAN.md or ROADMAP.md
- GitHub/GitLab milestones or project boards (if mentioned)
- TODO comments in code (only if substantial and structured)
- An explicit plan in CLAUDE.md or MEMORY.md

If none found, **skip** and report why. Never invent a development plan.

**If found, structure as:**
- **Current status** — what's done, what's in progress
- **Next steps** — immediate priorities
- **Backlog** — future items
- **Completed** — recently finished milestones

### CLAUDE.md

Claude Code's project instruction file. This tells Claude Code how to work in this repo.

**Sections to include/update:**
- **Project overview** — one-liner about what this is
- **Tech stack** — languages, frameworks, key dependencies
- **Key commands** — build, test, lint, dev server, deploy (from actual scripts)
- **Project structure** — brief map of important directories and files
- **Code conventions** — naming, patterns, architecture decisions detected
- **Important notes** — gotchas, non-obvious patterns, things Claude should know
- **Do NOT** — antipatterns specific to this project (preserve existing ones)

**Update rules:**
- Never remove existing "Do NOT" rules or conventions unless the user explicitly says to
- Add newly detected scripts/commands
- Update structure if directories have changed
- Preserve custom instructions the user has written — treat them as sacred

### MEMORY.md

Claude Code's auto-memory file. Contains persistent notes across sessions.

**Update rules:**
- **Read existing content carefully** — this file contains accumulated context from past sessions
- **Never delete existing entries** unless they're clearly outdated (reference to deleted files, completed tasks, etc.)
- **Add new context** based on current repo state:
  - Recent significant changes (from git log)
  - Current project state (what's working, what's broken)
  - Active decisions or in-progress work
- **Mark stale entries** — if something seems outdated, don't delete it; mark it with `[STALE?]` and let the user decide
- **Preserve the file's existing format** — if the user has a specific structure, follow it

### CHANGELOG.md

Track of notable changes. Follow [Keep a Changelog](https://keepachangelog.com/) format.

**If creating from scratch:**
- Parse `git log` for conventional commits or meaningful commit messages
- Group by version tags if present, otherwise by date ranges
- Categories: Added, Changed, Deprecated, Removed, Fixed, Security
- Don't include every commit — summarize and group related changes
- Start from the most recent tag or last ~50 meaningful commits

**If updating:**
- Add entries since the last documented version/date
- Match the existing format and style exactly
- Place new entries at the top (under `## [Unreleased]` if using that pattern)

### SECURITY.md

Basic security policy. Create if the project is or could be public-facing.

**Sections:**
- **Reporting vulnerabilities** — email or process for responsible disclosure
- **Supported versions** — which versions get security updates
- **Security practices** — relevant practices detected (dependency scanning, etc.)

Ask the user for a security contact email if not found in existing config.

### .env.example

Template environment file with all detected variables, without real values.

**Rules:**
- Grep the entire codebase for environment variable access patterns
- List every unique variable found
- Group by purpose (database, API keys, app config, etc.)
- Use placeholder values: `your_database_host`, `your_api_key_here`, etc.
- Add comments explaining each variable or group
- **Never include real values** even if found in committed files (flag this as a security warning instead)
- Cross-reference with existing `.env.example` — add missing vars, flag removed ones

**Format:**
```bash
# ──────────────────────────────
# Database
# ──────────────────────────────
DB_HOST=your_database_host
DB_PORT=3306
DB_NAME=your_database_name

# ──────────────────────────────
# API Keys
# ──────────────────────────────
API_KEY=your_api_key_here
```

### LICENSE

**Don't create automatically.** Only check for presence and warn if missing.

If missing, suggest common options based on project context:
- Open source → suggest MIT, Apache 2.0, or GPL
- Private/commercial → note that no license means all rights reserved by default
- Ask the user what they want

---

## Decision Rules

### When to CREATE a file

| File | Create if... |
|------|-------------|
| README.md | Always — every repo needs one |
| CONTRIBUTING.md | Project has multiple contributors, is open source, or user requests it |
| DEVELOPMENT_PLAN.md | Explicit plan exists somewhere in the repo |
| CLAUDE.md | Always — any repo using Claude Code benefits from this |
| MEMORY.md | Always — Claude Code uses this for persistent context |
| CHANGELOG.md | Project has version tags or conventional commits, or user requests it |
| SECURITY.md | Project is public-facing or user requests it |
| .env.example | Environment variables detected in codebase |
| LICENSE | Never auto-create — warn and suggest |

### When to UPDATE a file

- Code has changed since the doc was last modified
- New scripts, dependencies, or structure detected that aren't documented
- Existing content references files/dirs/commands that no longer exist
- Git history shows significant changes not reflected in CHANGELOG

### When to SKIP

- File not needed (e.g., DEVELOPMENT_PLAN.md with no plan)
- File was recently updated and appears current
- User explicitly excludes it

---

## Important Guardrails

1. **Never invent information** — only document what exists in the codebase. If unsure, ask.
2. **Preserve user content** — custom sections, personal notes, specific instructions are sacred. Never delete or overwrite them without explicit approval.
3. **No secrets in docs** — if real credentials are found in committed files, flag this as a security issue instead of documenting them.
4. **Match existing style** — if a file already exists, follow its formatting conventions, heading style, and tone.
5. **Be honest about gaps** — if you can't determine something (e.g., deploy process), say so rather than guessing.
6. **One file at a time** — show the preview, get approval, then move on. Never batch-apply changes.
7. **MEMORY.md is append-friendly** — treat it like a living document. Add, mark stale, but rarely delete.
8. **CLAUDE.md custom rules are immutable** — never remove the user's "Do NOT" rules or custom instructions unless explicitly told to.
