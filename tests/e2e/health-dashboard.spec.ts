/**
 * Health Dashboard E2E Tests
 *
 * Tests for the main health dashboard page: page load, Run Now,
 * Clear Cache, check results display, status classes, summary
 * banner, badges, timing, expandable details, and anchor IDs.
 *
 * @module tests/e2e/health-dashboard
 */

import { test, expect } from '@playwright/test';
import { loginAsAdmin } from './helpers/login';
import { HEALTH_DASHBOARD } from './helpers/selectors';

test.describe('Health Dashboard', () => {
	test.beforeEach(async ({ page }) => {
		await loginAsAdmin(page);
		await page.goto('/wp-admin/admin.php?page=ops-health-dashboard');
	});

	test('page title is displayed', async ({ page }) => {
		const title = page.locator(HEALTH_DASHBOARD.TITLE);
		await expect(title).toContainText('Ops Health Dashboard');
	});

	test('Run Now button is present', async ({ page }) => {
		const button = page.locator(HEALTH_DASHBOARD.RUN_NOW_BUTTON);
		await expect(button).toBeAttached();
	});

	test('Clear Cache button is present', async ({ page }) => {
		const button = page.locator(HEALTH_DASHBOARD.CLEAR_CACHE_BUTTON);
		await expect(button).toBeAttached();
	});

	test('nonce field is present in form', async ({ page }) => {
		const nonce = page.locator(HEALTH_DASHBOARD.NONCE_FIELD).first();
		await expect(nonce).toBeAttached();
		const value = await nonce.getAttribute('value');
		expect(value).toBeTruthy();
	});

	test('shows no results message initially', async ({ page }) => {
		// Clear any existing results first.
		await page.locator(HEALTH_DASHBOARD.CLEAR_CACHE_BUTTON).click();
		await page.waitForLoadState('networkidle');

		const notice = page.locator(HEALTH_DASHBOARD.NO_RESULTS);
		await expect(notice).toContainText('No health checks');
	});

	test('Run Now executes health checks', async ({ page }) => {
		await page.locator(HEALTH_DASHBOARD.RUN_NOW_BUTTON).click();
		await page.waitForLoadState('networkidle');

		const notice = page.locator(HEALTH_DASHBOARD.NOTICE_SUCCESS);
		await expect(notice).toBeVisible();
	});

	test('check results are displayed after run', async ({ page }) => {
		await page.locator(HEALTH_DASHBOARD.RUN_NOW_BUTTON).click();
		await page.waitForLoadState('networkidle');

		const checks = page.locator(HEALTH_DASHBOARD.CHECK_RESULT);
		const count = await checks.count();
		expect(count).toBeGreaterThanOrEqual(3);
	});

	test('Database check result is shown', async ({ page }) => {
		await page.locator(HEALTH_DASHBOARD.RUN_NOW_BUTTON).click();
		await page.waitForLoadState('networkidle');

		const heading = page.locator('.ops-health-check h3', { hasText: 'Database' });
		await expect(heading).toBeAttached();
	});

	test('Error Log check result is shown', async ({ page }) => {
		await page.locator(HEALTH_DASHBOARD.RUN_NOW_BUTTON).click();
		await page.waitForLoadState('networkidle');

		const heading = page.locator('.ops-health-check h3', { hasText: 'Error Log' });
		await expect(heading).toBeAttached();
	});

	test('Disk Space check result is shown', async ({ page }) => {
		await page.locator(HEALTH_DASHBOARD.RUN_NOW_BUTTON).click();
		await page.waitForLoadState('networkidle');

		const heading = page.locator('.ops-health-check h3', { hasText: 'Disk' });
		await expect(heading).toBeAttached();
	});

	test('Versions check result is shown', async ({ page }) => {
		await page.locator(HEALTH_DASHBOARD.RUN_NOW_BUTTON).click();
		await page.waitForLoadState('networkidle');

		const heading = page.locator('.ops-health-check h3', { hasText: 'Versions' });
		await expect(heading).toBeAttached();
	});

	test('Clear Cache removes results', async ({ page }) => {
		await page.locator(HEALTH_DASHBOARD.RUN_NOW_BUTTON).click();
		await page.waitForLoadState('networkidle');

		await page.locator(HEALTH_DASHBOARD.CLEAR_CACHE_BUTTON).click();
		await page.waitForLoadState('networkidle');

		const notice = page.locator(HEALTH_DASHBOARD.NOTICE_SUCCESS);
		await expect(notice).toContainText('Cached results cleared');
	});

	test('success notice is dismissible', async ({ page }) => {
		await page.locator(HEALTH_DASHBOARD.RUN_NOW_BUTTON).click();
		await page.waitForLoadState('networkidle');

		const notice = page.locator(HEALTH_DASHBOARD.NOTICE_DISMISSIBLE);
		await expect(notice).toBeAttached();
	});

	test('each check result has a valid status class', async ({ page }) => {
		await page.locator(HEALTH_DASHBOARD.RUN_NOW_BUTTON).click();
		await page.waitForLoadState('networkidle');

		const checks = page.locator(HEALTH_DASHBOARD.CHECK_RESULT);
		const count = await checks.count();

		for (let i = 0; i < count; i++) {
			const check = checks.nth(i);
			const classAttr = await check.getAttribute('class');
			expect(classAttr).toMatch(/ops-health-check-(ok|warning|critical|unknown)/);
		}
	});

	/* v0.6.2 — Summary banner */

	test('summary banner shows overall status after run', async ({ page }) => {
		await page.locator(HEALTH_DASHBOARD.RUN_NOW_BUTTON).click();
		await page.waitForLoadState('networkidle');

		const banner = page.locator(HEALTH_DASHBOARD.SUMMARY_BANNER);
		await expect(banner).toBeAttached();

		const header = page.locator(HEALTH_DASHBOARD.SUMMARY_HEADER);
		await expect(header).toBeVisible();
	});

	test('summary banner shows timing meta', async ({ page }) => {
		await page.locator(HEALTH_DASHBOARD.RUN_NOW_BUTTON).click();
		await page.waitForLoadState('networkidle');

		const meta = page.locator(HEALTH_DASHBOARD.SUMMARY_META);
		await expect(meta).toBeAttached();

		const metaItems = page.locator(HEALTH_DASHBOARD.META_ITEM);
		const count = await metaItems.count();
		expect(count).toBeGreaterThanOrEqual(1);
	});

	test('summary banner has Alert Settings link', async ({ page }) => {
		await page.locator(HEALTH_DASHBOARD.RUN_NOW_BUTTON).click();
		await page.waitForLoadState('networkidle');

		const link = page.locator('.ops-health-summary-meta a[href*="ops-health-alert-settings"]');
		await expect(link).toBeAttached();
	});

	test('each check has a status badge', async ({ page }) => {
		await page.locator(HEALTH_DASHBOARD.RUN_NOW_BUTTON).click();
		await page.waitForLoadState('networkidle');

		const badges = page.locator(HEALTH_DASHBOARD.BADGE);
		const count = await badges.count();
		expect(count).toBeGreaterThanOrEqual(3);
	});

	test('each check card has an anchor ID', async ({ page }) => {
		await page.locator(HEALTH_DASHBOARD.RUN_NOW_BUTTON).click();
		await page.waitForLoadState('networkidle');

		const checks = page.locator(HEALTH_DASHBOARD.CHECK_RESULT);
		const count = await checks.count();

		for (let i = 0; i < count; i++) {
			const id = await checks.nth(i).getAttribute('id');
			expect(id).toMatch(/^check-/);
		}
	});

	test('check cards have expandable details', async ({ page }) => {
		await page.locator(HEALTH_DASHBOARD.RUN_NOW_BUTTON).click();
		await page.waitForLoadState('networkidle');

		const details = page.locator(HEALTH_DASHBOARD.CHECK_DETAILS);
		const count = await details.count();
		expect(count).toBeGreaterThanOrEqual(1);
	});
});
