# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Initial plugin scaffolding
- Core dependency injection container (NO singleton pattern)
- Main plugin orchestrator with constructor injection
- Activation/deactivation handler
- Complete test suite with TDD approach
- GitHub Actions CI workflow (PHPCS + PHPUnit matrix)
- WordPress Coding Standards configuration
- PHPUnit configuration with coverage support

### Development Notes
- **M0 Completed**: Setup & Infrastruttura ✅
- All core classes follow NO singleton, NO static, NO final pattern
- TDD workflow enforced: RED → GREEN → REFACTOR
- Coverage target: PHP 8.3

## [1.0.0] - TBD

Initial release.

[Unreleased]: https://github.com/ops-team/ops-health-dashboard/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/ops-team/ops-health-dashboard/releases/tag/v1.0.0
