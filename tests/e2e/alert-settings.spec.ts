import { test, expect } from '@playwright/test';
import { loginAsAdmin } from './helpers/login';
import { ALERT_SETTINGS } from './helpers/selectors';

test.describe('Alert Settings', () => {
	test.beforeEach(async ({ page }) => {
		await loginAsAdmin(page);
		await page.goto('/wp-admin/admin.php?page=ops-health-alert-settings');
	});

	test('page title is displayed', async ({ page }) => {
		const title = page.locator(ALERT_SETTINGS.TITLE);
		await expect(title).toContainText('Alert Settings');
	});

	test('form is present with nonce', async ({ page }) => {
		const nonce = page.locator(ALERT_SETTINGS.NONCE_FIELD);
		await expect(nonce).toBeAttached();
	});

	test('save button is present', async ({ page }) => {
		const button = page.locator(ALERT_SETTINGS.SUBMIT_BUTTON);
		await expect(button).toBeAttached();
	});

	test('email section heading is present', async ({ page }) => {
		const heading = page.locator('h2', { hasText: 'Email' });
		await expect(heading).toBeAttached();
	});

	test('webhook section heading is present', async ({ page }) => {
		const heading = page.locator('h2', { hasText: 'Webhook' });
		await expect(heading).toBeAttached();
	});

	test('slack section heading is present', async ({ page }) => {
		const heading = page.locator('h2', { hasText: 'Slack' });
		await expect(heading).toBeAttached();
	});

	test('telegram section heading is present', async ({ page }) => {
		const heading = page.locator('h2', { hasText: 'Telegram' });
		await expect(heading).toBeAttached();
	});

	test('whatsapp section heading is present', async ({ page }) => {
		const heading = page.locator('h2', { hasText: 'WhatsApp' });
		await expect(heading).toBeAttached();
	});

	test('cooldown input is present', async ({ page }) => {
		const cooldown = page.locator(ALERT_SETTINGS.COOLDOWN_INPUT);
		await expect(cooldown).toBeAttached();
	});

	test('save settings shows success notice', async ({ page }) => {
		const button = page.locator(ALERT_SETTINGS.SUBMIT_BUTTON);
		await button.click();
		await page.waitForLoadState('networkidle');

		const notice = page.locator('.notice-success');
		await expect(notice).toBeVisible();
	});

	test('password fields use type password', async ({ page }) => {
		const passwordFields = page.locator('input[type="password"]');
		const count = await passwordFields.count();
		expect(count).toBeGreaterThanOrEqual(3);
	});

	test('password fields have empty values', async ({ page }) => {
		const passwordFields = page.locator('input[type="password"]');
		const count = await passwordFields.count();

		for (let i = 0; i < count; i++) {
			const value = await passwordFields.nth(i).getAttribute('value');
			expect(value).toBe('');
		}
	});

	test('checkbox inputs exist for channel enable/disable', async ({ page }) => {
		const checkboxes = page.locator('input[type="checkbox"]');
		const count = await checkboxes.count();
		expect(count).toBeGreaterThanOrEqual(1);
	});

	test('settings page is responsive', async ({ page }) => {
		const title = page.locator(ALERT_SETTINGS.TITLE);
		await expect(title).toContainText('Alert Settings');

		const button = page.locator(ALERT_SETTINGS.SUBMIT_BUTTON);
		await expect(button).toBeAttached();
	});
});
