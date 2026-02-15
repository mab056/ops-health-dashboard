# Security Policy

## Supported Versions

| Version | Supported |
|---------|-----------|
| 0.6.x   | :white_check_mark: (current) |
| 0.5.x   | :white_check_mark: |
| 0.4.x   | :white_check_mark: |
| < 0.4   | :x: |

## Reporting a Vulnerability

Security is a top priority for Ops Health Dashboard. If you discover a vulnerability, please report it responsibly.

**Do not open a public GitHub issue for security vulnerabilities.**

### How to Report

1. **GitHub Security Advisory** (preferred):
   <https://github.com/mab056/ops-health-dashboard/security/advisories/new>
2. **Email**: `info@mattiabondrano.dev`

### What to Include

- Vulnerability type (e.g. XSS, SQL injection, SSRF, information disclosure)
- Full source file paths involved
- Reproduction steps
- Proof of concept/exploit (if available)
- Potential impact

### Response Times

- **Acknowledgement**: within 48 hours
- **Initial assessment**: within 7 days
- **Fix and release**: within 30 days after confirmation

## Implemented Security Controls

### Access Control

- Capability checks: all admin pages require `manage_options`
- CSRF protection: nonce checks on all forms/POST actions (`ops_health_admin_action`, `ops_health_alert_settings`)
- PRG pattern (Post-Redirect-Get) to prevent duplicate submissions (HealthScreen + AlertSettings)

### Input Sanitization and Output Escaping

- Input sanitization: `sanitize_text_field()`, `sanitize_email()`, `esc_url_raw()`, `absint()`
- Output escaping: `esc_html()`, `esc_attr()`, `esc_url()`
- Parameterized DB queries via `$wpdb->prepare()` where applicable

### Sensitive Data Redaction

- Central redaction service with 11 automatic sanitization patterns applied to all diagnostic outputs
  - Database credentials (DB_PASSWORD, DB_USER, DB_NAME, DB_HOST)
  - WordPress salts and security keys
  - API keys, secrets, tokens, bearer
  - Passwords in URLs and generic fields
  - Email addresses, IPv4, IPv6
  - Filesystem paths (ABSPATH, WP_CONTENT_DIR, home directory)
- `DatabaseCheck`: no host/database name exposure in results
- `ErrorLogCheck`: log samples redacted before inclusion; raw paths not exposed
- `RedisCheck`: host and error messages redacted via RedactionInterface

### Filesystem Protections

- Symlink rejection for log files (directory traversal mitigation)
- Read-size limit (512KB max) to avoid excessive memory usage
- Shared lock with `flock(LOCK_SH)` for safe concurrent log access

### Anti-SSRF Protections

All outbound alerting HTTP requests (Webhook, Slack, Telegram, WhatsApp) go through `HttpClient` with:

- Strict scheme allowlist (`http`/`https` only)
- Private/reserved IP blocking (RFC 1918: 10.0.0.0/8, 172.16.0.0/12, 192.168.0.0/16; loopback 127.0.0.0/8; link-local 169.254.0.0/16; unspecified 0.0.0.0)
- IPv6 safe-fail rejection (because `gethostbyname()` returns IPv4 only)
- DNS validation: hostname resolution and verification that the resolved IP is not private (DNS rebinding prevention)
- DNS pinning via `CURLOPT_RESOLVE` through `http_api_curl` action, forcing cURL to use the already-validated IP (prevents TOCTOU/DNS rebinding between validation and actual request)
- Port restrictions (80/443 only)
- Redirect disabled (`redirection => 0` on `wp_remote_post()`)
- 2xx-only success validation
- Timeout set to 5 seconds
- Protected `resolve_host()`: wraps `gethostbyname()` for testability via partial mock

Implemented in `src/Services/HttpClient.php` via `HttpClientInterface`.

### Notification Channel Safeguards

- `TelegramChannel`: `htmlspecialchars()` on interpolated values
- `SlackChannel`: mrkdwn escaping for user-controlled values
- `EmailChannel`: recipient validation with `is_email()`
- `WhatsAppChannel`: E.164 phone validation (`/^\+[1-9]\d{6,14}$/`)
- `WebhookChannel`: optional HMAC SHA-256 signature via `X-OpsHealth-Signature` header (body pre-serialized to avoid signature discrepancies)
- `AlertSettings`: tokens and secrets use `type="password"` + `autocomplete="off"`, `value=""` + `placeholder="********"` (credentials never present in the DOM source)
- `AlertManager`: cooldown set before dispatch, per-channel isolation via `try/catch \Throwable`
- `Scheduler`: wraps `alert_manager->process()` in `catch (\Throwable)` for cron resilience

### Safe Uninstallation

- Single-site: removes plugin options, cron hooks, fixed transients, and dynamic cooldown transients
- Multisite: iterates every site with `switch_to_blog()`/`restore_current_blog()` and applies full cleanup
- Dynamic cooldown transient cleanup via `$wpdb` LIKE query (`ops_health_alert_cooldown_*`)
- `WP_UNINSTALL_PLUGIN` guard in `uninstall.php`
- Multisite fallback path in `uninstall.php` without autoloader

### Architecture Security Posture

- No singleton pattern
- No static methods in business logic
- Constructor dependency injection for explicit, auditable dependencies

## Scope

### In Scope

- Source code in `src/`
- Configuration in `config/bootstrap.php`
- Uninstall logic in `uninstall.php`
- Build/distribution scripts in `bin/`
- Main plugin bootstrap file `ops-health-dashboard.php`

### Out of Scope

- WordPress core vulnerabilities
- Third-party themes/plugins
- Web server configuration (Apache/Nginx)
- PHP/MySQL runtime configuration
- Dev dependencies in `vendor/`

## Security Quality Gates

Each release must pass:

- PHPCS (WordPress Coding Standards): clean
- PHPStan level 6: zero errors
- Pattern-enforcement tests (no singleton/static/final violations)
- Test matrix across supported PHP versions
- Code review before merge

## Acknowledgements

Thank you to everyone who reports vulnerabilities responsibly. Security researchers who help improve Ops Health Dashboard may be acknowledged here (with consent).
