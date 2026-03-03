/**
 * Centralized CSS selectors for E2E tests.
 *
 * Groups selectors by page: MENU, HEALTH_DASHBOARD, ALERT_SETTINGS,
 * DASHBOARD_WIDGET. Keeps locator strings in one place to avoid
 * duplication across spec files.
 *
 * @module tests/e2e/helpers/selectors
 */

export const MENU = {
	TOP_LEVEL: '#adminmenu a[href="admin.php?page=ops-health-dashboard"]',
	ALERT_SETTINGS: '#adminmenu a[href="admin.php?page=ops-health-alert-settings"]',
	ICON: '.dashicons-heart',
};

export const HEALTH_DASHBOARD = {
	TITLE: '.wrap h1',
	RUN_NOW_BUTTON: 'input[value="Run Now"]',
	CLEAR_CACHE_BUTTON: 'input[value="Clear Cache"]',
	NONCE_FIELD: 'input[name="_ops_health_nonce"]',
	ACTION_FIELD: 'input[name="ops_health_action"]',
	NOTICE: '.notice',
	NOTICE_SUCCESS: '.notice-success',
	NOTICE_DISMISSIBLE: '.notice.is-dismissible',
	CHECK_RESULT: '.ops-health-check',
	CHECK_STATUS: '.ops-health-check .status',
	NO_RESULTS: '.notice-info',

	/* v0.6.2 — Summary banner */
	SUMMARY_BANNER: '.ops-health-summary',
	SUMMARY_HEADER: '.ops-health-summary-header',
	SUMMARY_AFFECTED: '.ops-health-summary-affected',
	SUMMARY_ISSUES: '.ops-health-summary-issues',
	SUMMARY_META: '.ops-health-summary-meta',
	META_ITEM: '.ops-health-meta-item',

	/* v0.6.2 — Status icons and badges */
	STATUS_ICON: '.ops-health-status-icon',
	BADGE: '.ops-health-badge',

	/* v0.6.2 — Check cards */
	CHECK_HEADER: '.ops-health-check-header',
	CHECK_METRIC: '.ops-health-check-metric',
	CHECK_TIMESTAMP: '.ops-health-check-timestamp',
	CHECK_DETAILS: '.ops-health-check-details',
};

export const ALERT_SETTINGS = {
	TITLE: '.wrap h1',
	FORM: 'form[method="post"]',
	SUBMIT_BUTTON: 'input[name="ops_health_alert_submit"]',
	NONCE_FIELD: 'input[name="_ops_health_alert_nonce"]',
	COOLDOWN_INPUT: 'input[name="cooldown_minutes"]',

	/* v0.6.2 — Collapsible channel sections */
	CHANNEL_SECTION: '.ops-health-alert-section',
	CHANNEL_SUMMARY: '.ops-health-alert-section summary',
	CHANNEL_BADGE: '.ops-health-alert-status',
	BADGE_ENABLED: '.ops-health-alert-status-enabled',
	BADGE_DISABLED: '.ops-health-alert-status-disabled',
};

export const DASHBOARD_WIDGET = {
	WIDGET: '#ops_health_dashboard_widget',
	STATUS: '.ops-health-widget-status',
	CHECK_LIST: '.ops-health-widget-checks',
	DASHBOARD_LINK: '.ops-health-widget-footer a',

	/* v0.6.2 — Timing and check links */
	WIDGET_TIMING: '.ops-health-widget-timing',
	CHECK_LINK: '.ops-health-widget-checks a',
};
