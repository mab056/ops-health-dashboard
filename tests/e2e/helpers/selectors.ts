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
};

export const ALERT_SETTINGS = {
	TITLE: '.wrap h1',
	FORM: 'form[method="post"]',
	SUBMIT_BUTTON: 'input[name="ops_health_alert_submit"]',
	NONCE_FIELD: 'input[name="_ops_health_alert_nonce"]',
	COOLDOWN_INPUT: 'input[name="cooldown_minutes"]',
};

export const DASHBOARD_WIDGET = {
	WIDGET: '#ops_health_dashboard_widget',
	STATUS: '.ops-health-widget-status',
	CHECK_LIST: '.ops-health-widget-checks',
	DASHBOARD_LINK: 'a[href*="ops-health-dashboard"]',
};
