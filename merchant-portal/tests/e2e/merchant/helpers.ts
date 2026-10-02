import { expect, type Page } from '@playwright/test';

export const DEMO_PASSWORD = 'MerchantFlowDemo!2026';

export function chargeRows(page: Page) {
  return page.getByRole('grid').getByRole('row');
}

export async function signIn(page: Page, email: string) {
  await page.goto('/');
  await expect(
    page.getByRole('heading', { name: 'Sign in to the merchant portal' }),
  ).toBeVisible();
  await page.getByRole('textbox', { name: 'Email' }).fill(email);
  await page.getByLabel('Password').fill(DEMO_PASSWORD);
  await page.getByRole('button', { name: 'Sign in', exact: true }).click();
  await expect(page.getByRole('heading', { name: 'Charges' })).toBeVisible();
  await expect(chargeRows(page).nth(1)).toContainText('@');
}

export async function createCharge(page: Page, capture: boolean) {
  await expect(page.getByRole('combobox', { name: 'Merchant' })).toBeVisible();
  await page.getByRole('button', { name: 'New charge' }).click();
  const dialog = page.getByRole('dialog', { name: 'New charge' });
  const email = `e2e-${Date.now()}-${Math.floor(Math.random() * 1_000_000)}@example.test`;

  await dialog.getByRole('combobox', { name: 'Merchant account' }).click();
  await dialog
    .getByRole('option', { name: /Northwind Coffee Roasters/ })
    .click();
  await dialog.getByRole('textbox', { name: 'Amount' }).fill('10.50');
  await dialog.getByRole('textbox', { name: 'Customer email' }).fill(email);
  if (!capture) {
    await dialog.getByRole('checkbox', { name: /Capture now/ }).uncheck();
  }
  await dialog
    .getByRole('button', {
      name: capture ? 'Charge' : 'Authorize',
      exact: true,
    })
    .click();

  const details = page.getByRole('dialog', { name: 'Charge', exact: true });
  await expect(details.getByText(email)).toBeVisible();
  return { details, email };
}
