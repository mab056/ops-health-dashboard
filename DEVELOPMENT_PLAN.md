# Development Plan - Ops Health Dashboard

**Current Milestone**: M0 - Setup & Infrastruttura
**Started**: 2026-02-08
**Status**: 🚧 In Progress

---

## Milestone 0: Setup & Infrastruttura ✅ 8/8

**Obiettivo**: Scaffolding completo con CI verde

### Tasks

- [x] **M0.1** - Struttura directory completa
- [x] **M0.2** - Setup composer.json con dipendenze
- [x] **M0.3** - Configurazione PHPCS (WPCS)
- [x] **M0.4** - Setup PHPUnit (config + bootstrap)
- [x] **M0.5** - GitHub Actions workflows
- [x] **M0.6** - File bootstrap (main plugin + config)
- [x] **M0.7** - Core classes (Container, Plugin, Activator) - TDD
- [x] **M0.8** - Script bin/install-wp-tests.sh

**Pattern Enforcement**:
- ✅ Container usa `share()` per shared instances, NON `singleton()`
- ✅ Plugin riceve Container via constructor, NO `get_instance()`
- ✅ Bootstrap function crea e configura, NO static factories
- ✅ Nessuna classe final, nessun metodo final

**Deliverable**: CI verde con PHPCS + PHPUnit matrix + coverage 8.3

---

## Progress Log

### 2026-02-08

**Completed M0**: Setup & Infrastruttura ✅
- Created complete directory structure
- Setup composer.json with all dependencies (PHPUnit, WPCS, Brain\Monkey, etc.)
- Configured PHPCS for WordPress Coding Standards
- Setup PHPUnit configuration with coverage support
- Created GitHub Actions CI workflow (PHPCS + PHPUnit matrix PHP 7.4-8.5)
- Implemented core classes with TDD:
  - `Container` class - DI container with NO singleton pattern
  - `Plugin` class - Main orchestrator with constructor injection
  - `Activator` class - Activation/deactivation handler
- Created main plugin file and bootstrap function
- All tests: ✅ GREEN
- Pattern enforcement: ✅ NO singleton, NO static, NO final

**Documentation Completed**:
- Comprehensive README.md with architecture, TDD workflow, security
- CONTRIBUTING.md with strict TDD requirements and pattern enforcement
- Clear examples of correct vs incorrect patterns
- Security guidelines (sanitization, escaping, nonces, anti-SSRF)

**Git Commits**:
- `1b0944c` - feat(M0): Complete setup & infrastructure with TDD
- `4e2d182` - docs: add comprehensive README and CONTRIBUTING guides

---

## Next Milestones (Preview)

- **M1**: Core Checks + Storage + Cron
- **M2**: Error Log Summary Safe
- **M3**: Redis Check
- **M4**: Alerting System
- **M5**: E2E Testing
- **M6**: WordPress.org Readiness
