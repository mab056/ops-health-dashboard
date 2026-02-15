/**
 * Security E2E Tests
 *
 * Tests capability-based access control: subscriber and editor cannot
 * access health dashboard or alert settings, logged out users are
 * redirected to login.
 *
 * @module tests/e2e/security
 */

import { test, expect } from '@playwright/test';
import { loginAsSubscriber, loginAsEditor } from './helpers/login';

test.describe('Security', () => {
	test('subscriber cannot access health dashboard', async ({ page }) => {
		await loginAsSubscriber(page);
		await page.goto('/wp-admin/admin.php?page=ops-health-dashboard');

		// Should not see the dashboard content.
		const title = page.locator('.wrap h1');
		// WordPress will show an error or redirect; the title should not be "Ops Health Dashboard".
		const content = await page.content();
		expect(content).not.toContain('Run Now');
	});

	test('editor cannot access health dashboard', async ({ page }) => {
		await loginAsEditor(page);
		await page.goto('/wp-admin/admin.php?page=ops-health-dashboard');

		const content = await page.content();
		expect(content).not.toContain('Run Now');
	});

	test('subscriber cannot access alert settings', async ({ page }) => {
		await loginAsSubscriber(page);
		await page.goto('/wp-admin/admin.php?page=ops-health-alert-settings');

		const content = await page.content();
		expect(content).not.toContain('Alert Settings');
	});

	test('editor cannot access alert settings', async ({ page }) => {
		await loginAsEditor(page);
		await page.goto('/wp-admin/admin.php?page=ops-health-alert-settings');

		const content = await page.content();
		expect(content).not.toContain('Alert Settings');
	});

	test('logged out user cannot access health dashboard', async ({ page }) => {
		await page.goto('/wp-admin/admin.php?page=ops-health-dashboard');
		// Should be redirected to login page.
		await expect(page).toHaveURL(/wp-login\.php/);
	});

	test('logged out user cannot access alert settings', async ({ page }) => {
		await page.goto('/wp-admin/admin.php?page=ops-health-alert-settings');
		await expect(page).toHaveURL(/wp-login\.php/);
	});
});
