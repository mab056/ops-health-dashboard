import { defineConfig, devices } from '@playwright/test';

const allProjects = [
	{
		name: 'desktop',
		use: {
			...devices['Desktop Chrome'],
			viewport: { width: 1280, height: 720 },
		},
	},
	{
		name: 'tablet',
		use: {
			...devices['Desktop Chrome'],
			viewport: { width: 768, height: 1024 },
		},
	},
	{
		name: 'mobile',
		use: {
			...devices['Desktop Chrome'],
			viewport: { width: 375, height: 812 },
		},
	},
];

export default defineConfig({
	testDir: './tests/e2e',
	fullyParallel: false,
	forbidOnly: !!process.env.CI,
	retries: process.env.CI ? 2 : 1,
	workers: 1,
	timeout: process.env.CI ? 60_000 : 30_000,
	reporter: process.env.CI ? [['github'], ['line']] : 'html',
	use: {
		baseURL: 'http://localhost:8888',
		trace: 'on-first-retry',
		screenshot: 'only-on-failure',
	},
	projects: process.env.CI ? [allProjects[0]] : allProjects,
});
