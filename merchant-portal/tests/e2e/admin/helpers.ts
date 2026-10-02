import { expect, type Page } from '@playwright/test';

const PASSWORD = 'MerchantFlowDemo!2026';

export async function signInAdmin(page: Page) {
  await page.goto('/admin/login');
  await page.locator('input[type="email"]').fill('admin@merchant-flow.test');
  await page.locator('input[type="password"]').fill(PASSWORD);
  await page.locator('button[type="submit"]').click();
  await expect(page).toHaveURL(/\/admin\/dashboards\/main$/);
  await expect(
    page.getByRole('link', { name: 'Charges' }).first(),
  ).toBeVisible();
}

export async function openResource(page: Page, slug: string) {
  await page.goto(`/admin/resources/${slug}`);
  await expect(page.locator('table').first()).toBeVisible();
}

export async function chooseFilter(page: Page, name: string, option: string) {
  await page.getByRole('combobox', { name, exact: true }).click();
  await page.getByRole('option', { name: option, exact: true }).click();
}
