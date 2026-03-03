/**
 * Alert Settings E2E Tests
 *
 * Tests for the alert settings admin page: form rendering, nonce,
 * channel sections (Email, Webhook, Slack, Telegram, WhatsApp),
 * cooldown input, save action, password field security, checkboxes,
 * collapsible sections, conditional field toggling, and status badges.
 *
 * @module tests/e2e/alert-settings
 */

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

	test('email channel section is present', async ({ page }) => {
		const summary = page.locator(ALERT_SETTINGS.CHANNEL_SUMMARY, { hasText: 'Email' });
		await expect(summary).toBeAttached();
	});

	test('webhook channel section is present', async ({ page }) => {
		const summary = page.locator(ALERT_SETTINGS.CHANNEL_SUMMARY, { hasText: 'Webhook' });
		await expect(summary).toBeAttached();
	});

	test('slack channel section is present', async ({ page }) => {
		const summary = page.locator(ALERT_SETTINGS.CHANNEL_SUMMARY, { hasText: 'Slack' });
		await expect(summary).toBeAttached();
	});

	test('telegram channel section is present', async ({ page }) => {
		const summary = page.locator(ALERT_SETTINGS.CHANNEL_SUMMARY, { hasText: 'Telegram' });
		await expect(summary).toBeAttached();
	});

	test('whatsapp channel section is present', async ({ page }) => {
		const summary = page.locator(ALERT_SETTINGS.CHANNEL_SUMMARY, { hasText: 'WhatsApp' });
		await expect(summary).toBeAttached();
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

	/* v0.6.2 — Collapsible sections */

	test('five collapsible channel sections are present', async ({ page }) => {
		const sections = page.locator(ALERT_SETTINGS.CHANNEL_SECTION);
		const count = await sections.count();
		expect(count).toBe(5);
	});

	test('each channel section has an enabled or disabled badge', async ({ page }) => {
		const badges = page.locator(ALERT_SETTINGS.CHANNEL_BADGE);
		const count = await badges.count();
		expect(count).toBe(5);

		for (let i = 0; i < count; i++) {
			const text = await badges.nth(i).textContent();
			expect(text?.trim()).toMatch(/^(Enabled|Disabled)$/);
		}
	});

	test('unchecked channel has disabled config fields', async ({ page }) => {
		// Find the first channel section.
		const section = page.locator(ALERT_SETTINGS.CHANNEL_SECTION).first();
		const checkbox = section.locator('input[type="checkbox"][name$="_enabled"]');

		// Ensure checkbox is unchecked.
		if (await checkbox.isChecked()) {
			await checkbox.uncheck();
		}

		// Wait for JS toggle to apply.
		await page.waitForTimeout(200);

		// Config fields (not the checkbox row) should be disabled.
		const disabledInputs = section.locator('tr:not(:has(input[name$="_enabled"])) input:disabled');
		const count = await disabledInputs.count();
		expect(count).toBeGreaterThanOrEqual(1);
	});
});
