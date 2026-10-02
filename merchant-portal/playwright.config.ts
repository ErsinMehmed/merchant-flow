import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
  testDir: './tests/e2e',
  fullyParallel: false,
  workers: 1,
  forbidOnly: process.env.CI === 'true',
  timeout: 60_000,
  expect: { timeout: 10_000 },
  reporter: process.env.CI === 'true' ? 'github' : 'list',
  outputDir: '../artifacts/playwright/results',
  use: {
    ...devices['Desktop Chrome'],
    baseURL: process.env.PLAYWRIGHT_BASE_URL ?? 'http://localhost:8080',
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
  },
});
