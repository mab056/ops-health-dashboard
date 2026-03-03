=== Ops Health Dashboard ===
Contributors: mattiabondrano
Tags: health check, monitoring, dashboard, alerting, devops
Requires at least: 5.8
Tested up to: 6.9
Stable tag: 0.6.2
Requires PHP: 7.4
License: GPL-3.0-or-later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Production-grade WordPress plugin for operational monitoring with automated health checks and configurable multi-channel alerting.

== Description ==

Ops Health Dashboard provides an operational monitoring dashboard in wp-admin with automated health checks and configurable alerting (email, webhook, Slack, Telegram, WhatsApp), so you know what's happening *before* something breaks.

= Health Check =

* **Database** — Connectivity and query performance
* **Error Log** — Safe aggregation with automatic sensitive data redaction
* **Redis** — Extension detection, connection test and smoke test with graceful degradation
* **Disk Space** — Free/total space with configurable thresholds (warning <20%, critical <10%)
* **Versions** — WordPress, PHP, themes and plugins with update notifications

= Dashboard =

* Admin page: Ops > Health Dashboard
* Manual "Run Now" and "Clear Cache" buttons with nonce protection
* Dashboard widget showing global status

= Alerting =

* Email via wp_mail() with configurable recipients
* Generic JSON POST webhook with optional HMAC signature
* Slack via Incoming Webhook with Block Kit payload
* Telegram via Bot API with HTML parse mode
* WhatsApp via generic webhook with Bearer authentication
* Per-check cooldown via transient (default 60 minutes)
* Recovery alerts bypass cooldown
* Anti-SSRF protection on all outbound HTTP requests

= Scheduling =

* WP-Cron (default: every 15 minutes)
* Manual trigger via "Run Now" button
* Automatic alerts only on status change

= Architecture =

Built with modern OOP, TDD and rigorous security hardening. No singletons, no static methods, no final classes. Full dependency injection via lightweight DI container.

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/ops-health-dashboard/` or install directly from the WordPress plugin screen.
2. Activate the plugin from the "Plugins" screen in WordPress.
3. Navigate to Ops > Health Dashboard to view health checks.
4. Configure alerting at Ops > Alert Settings.

== Frequently Asked Questions ==

= What PHP version is required? =

PHP 7.4 or higher is required. PHP 8.3+ is recommended for better performance and security.

= Does the plugin support Redis? =

Yes. If the PHP Redis extension is installed and configured (via WP_REDIS_HOST, WP_REDIS_PORT constants), the Redis check will monitor connectivity and performance. If Redis is not available, the check degrades gracefully to a warning state.

= How often are health checks run? =

By default, checks run every 15 minutes via WP-Cron. You can also trigger checks manually using the "Run Now" button in the dashboard.

= What data is cleaned up when I uninstall the plugin? =

All plugin options, transients and scheduled cron events are removed when you delete the plugin through the WordPress admin. On multisite networks, data is cleaned across all sites. No data remains in the database.

= Is sensitive data exposed in health check results? =

No. All health check results are processed through a redaction service that automatically sanitizes credentials, file paths, email addresses, IP addresses and other sensitive information before storage or display.

== Screenshots ==

1. Health Dashboard with all check results
2. Alert Settings configuration page
3. Dashboard widget with global status

== Changelog ==

= 0.6.1 =
* Fixed WebhookChannel HMAC signature: body is now serialized once and signed on the pre-serialized string
* Added multisite support in Uninstaller (iterates all blogs on network uninstall)
* Added multisite fallback in uninstall.php
* Improved build-zip.sh error handling (explicit failure on composer install errors)
* Added multisite integration tests with WP_TESTS_MULTISITE support

= 0.6.0 =
* Added uninstall.php with Uninstaller class for complete data cleanup
* Added dedicated CSS for Health Dashboard and Dashboard Widget
* Added ABSPATH guards on all source files and config files
* Added readme.txt in WordPress.org format
* Updated recommended PHP version in VersionsCheck to 8.3+

= 0.5.0 =
* Added DiskCheck with configurable thresholds (warning <20%, critical <10%)
* Added VersionsCheck for WordPress/PHP version monitoring with update notifications
* Added DashboardWidget showing global status in the wp-admin dashboard
* Added E2E testing with Playwright and wp-env (46 scenarios; 3 viewports locally, desktop-only in CI)
* Improved RedisCheck with catch Throwable error handling

= 0.4.1 =
* DNS pinning via CURLOPT_RESOLVE in HttpClient (anti-TOCTOU)
* Per-channel isolation with try/catch Throwable
* Security: password fields never expose real values in the DOM

= 0.4.0 =
* Added multi-channel alerting system (Email, Webhook, Slack, Telegram, WhatsApp)
* Added anti-SSRF HttpClient with DNS validation and private IP blocking
* Added Alert Settings admin page with per-channel configuration
* Added per-check cooldown with recovery bypass

For the full changelog, see [CHANGELOG.md](https://github.com/mab056/ops-health-dashboard/blob/main/CHANGELOG.md).

== Upgrade Notices ==

= 0.6.1 =
HMAC webhook signature fix, multisite uninstall support, improved build reliability. No breaking changes.

= 0.6.0 =
WordPress.org readiness improvements (uninstall cleanup, ABSPATH guards, docs) and UI styling updates. No breaking changes.

= 0.5.0 =
New health checks (Disk Space, Versions) and Dashboard Widget. No breaking changes.
