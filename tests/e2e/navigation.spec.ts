import { test, expect } from '@playwright/test';
import { loginAsAdmin } from './helpers/login';
import { MENU } from './helpers/selectors';

test.describe('Admin Navigation', () => {
	test.beforeEach(async ({ page }) => {
		await loginAsAdmin(page);
	});

	test('menu item exists in admin sidebar', async ({ page }) => {
		await page.goto('/wp-admin/');
		const menuItem = page.locator(MENU.TOP_LEVEL).first();
		await expect(menuItem).toBeAttached();
	});

	test('menu item has heart dashicon', async ({ page }) => {
		await page.goto('/wp-admin/');
		const icon = page.locator('#adminmenu .dashicons-heart').first();
		await expect(icon).toBeAttached();
	});

	test('health dashboard page loads correctly', async ({ page }) => {
		await page.goto('/wp-admin/admin.php?page=ops-health-dashboard');
		await expect(page.locator('.wrap h1')).toContainText('Ops Health Dashboard');
	});

	test('alert settings submenu exists', async ({ page }) => {
		await page.goto('/wp-admin/admin.php?page=ops-health-dashboard');
		const submenu = page.locator(MENU.ALERT_SETTINGS);
		await expect(submenu).toBeAttached();
	});

	test('alert settings page loads correctly', async ({ page }) => {
		await page.goto('/wp-admin/admin.php?page=ops-health-alert-settings');
		await expect(page.locator('.wrap h1')).toContainText('Alert Settings');
	});

	test('active menu state is set on dashboard page', async ({ page }) => {
		await page.goto('/wp-admin/admin.php?page=ops-health-dashboard');
		const activeMenu = page.locator('#adminmenu .current a[href="admin.php?page=ops-health-dashboard"]');
		await expect(activeMenu).toBeAttached();
	});
});
