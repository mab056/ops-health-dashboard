/**
 * Dashboard Widget E2E Tests
 *
 * Tests for the wp-admin dashboard widget: visibility for admin,
 * hidden for subscriber, status display, check list, navigation
 * link, timing display, and clickable check links with anchors.
 *
 * @module tests/e2e/dashboard-widget
 */

import { test, expect } from '@playwright/test';
import { loginAsAdmin, loginAsSubscriber } from './helpers/login';
import { DASHBOARD_WIDGET } from './helpers/selectors';

test.describe('Dashboard Widget', () => {
	test('widget is visible on wp-admin dashboard for admin', async ({ page }) => {
		await loginAsAdmin(page);
		await page.goto('/wp-admin/');
		const widget = page.locator(DASHBOARD_WIDGET.WIDGET);
		await expect(widget).toBeAttached();
	});

	test('widget shows status after checks are run', async ({ page }) => {
		await loginAsAdmin(page);

		// Run checks first via health dashboard.
		await page.goto('/wp-admin/admin.php?page=ops-health-dashboard');
		await page.locator('input[value="Run Now"]').click();
		await page.waitForLoadState('networkidle');

		// Go to main dashboard.
		await page.goto('/wp-admin/');
		const status = page.locator(DASHBOARD_WIDGET.STATUS);
		await expect(status).toBeVisible();
	});

	test('widget shows individual check list', async ({ page }) => {
		await loginAsAdmin(page);

		// Run checks first.
		await page.goto('/wp-admin/admin.php?page=ops-health-dashboard');
		await page.locator('input[value="Run Now"]').click();
		await page.waitForLoadState('networkidle');

		await page.goto('/wp-admin/');
		const checkList = page.locator(DASHBOARD_WIDGET.CHECK_LIST);
		await expect(checkList).toBeAttached();

		const items = checkList.locator('li');
		const count = await items.count();
		expect(count).toBeGreaterThanOrEqual(3);
	});

	test('widget has link to full dashboard', async ({ page }) => {
		await loginAsAdmin(page);
		await page.goto('/wp-admin/');
		const link = page.locator('#ops_health_dashboard_widget a[href*="ops-health-dashboard"]');
		await expect(link).toBeAttached();
	});

	test('widget is not visible for subscriber', async ({ page }) => {
		await loginAsSubscriber(page);
		await page.goto('/wp-admin/');
		const widget = page.locator(DASHBOARD_WIDGET.WIDGET);
		await expect(widget).not.toBeAttached();
	});

	test('widget link navigates to health dashboard', async ({ page }) => {
		await loginAsAdmin(page);
		await page.goto('/wp-admin/');
		const link = page.locator('#ops_health_dashboard_widget a[href*="ops-health-dashboard"]');
		await link.click();
		await expect(page).toHaveURL(/page=ops-health-dashboard/);
	});

	/* v0.6.2 — Timing and check links */

	test('widget shows timing after checks are run', async ({ page }) => {
		await loginAsAdmin(page);

		// Run checks first.
		await page.goto('/wp-admin/admin.php?page=ops-health-dashboard');
		await page.locator('input[value="Run Now"]').click();
		await page.waitForLoadState('networkidle');

		await page.goto('/wp-admin/');
		const timing = page.locator(DASHBOARD_WIDGET.WIDGET_TIMING);
		await expect(timing).toBeAttached();
		await expect(timing).toContainText('Last run');
	});

	test('check names are clickable links with anchor', async ({ page }) => {
		await loginAsAdmin(page);

		// Run checks first.
		await page.goto('/wp-admin/admin.php?page=ops-health-dashboard');
		await page.locator('input[value="Run Now"]').click();
		await page.waitForLoadState('networkidle');

		await page.goto('/wp-admin/');
		const links = page.locator(DASHBOARD_WIDGET.CHECK_LINK);
		const count = await links.count();
		expect(count).toBeGreaterThanOrEqual(3);

		for (let i = 0; i < count; i++) {
			const href = await links.nth(i).getAttribute('href');
			expect(href).toMatch(/ops-health-dashboard#check-/);
		}
	});
});
