import { expect, type Page } from '@playwright/test';

export const ADMIN_EMAIL = 'admin@merchant-flow.test';
export const PASSWORD = 'MerchantFlowDemo!2026';

/** Fills and submits the Nova login form the page is already on. */
export async function submitLogin(
  page: Page,
  email: string,
  password = PASSWORD,
) {
  await page.locator('input[type="email"]').fill(email);
  await page.locator('input[type="password"]').fill(password);
  await page.locator('button[type="submit"]').click();
}

export async function signInAdmin(page: Page) {
  await page.goto('/admin/login');
  await submitLogin(page, ADMIN_EMAIL);
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
