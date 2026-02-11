import { Page } from '@playwright/test';

/**
 * Login as WordPress admin user.
 */
export async function loginAsAdmin(page: Page): Promise<void> {
	await page.goto('/wp-login.php');
	await page.fill('#user_login', 'admin');
	await page.fill('#user_pass', 'password');
	await page.click('#wp-submit');
	await page.waitForURL('**/wp-admin/**', { timeout: 30000 });
}

/**
 * Login as subscriber test user.
 */
export async function loginAsSubscriber(page: Page): Promise<void> {
	await page.goto('/wp-login.php');
	await page.fill('#user_login', 'subscriber_e2e');
	await page.fill('#user_pass', 'subscriber_e2e_pass');
	await page.click('#wp-submit');
	await page.waitForURL('**/wp-admin/**', { timeout: 30000 });
}

/**
 * Login as editor test user.
 */
export async function loginAsEditor(page: Page): Promise<void> {
	await page.goto('/wp-login.php');
	await page.fill('#user_login', 'editor_e2e');
	await page.fill('#user_pass', 'editor_e2e_pass');
	await page.click('#wp-submit');
	await page.waitForURL('**/wp-admin/**', { timeout: 30000 });
}
