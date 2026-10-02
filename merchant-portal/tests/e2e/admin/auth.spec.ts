import { expect, test } from '@playwright/test';
import { signInAdmin } from './helpers';

test('Nova redirects guests and rejects merchant accounts', async ({
  page,
}) => {
  await page.goto('/admin/resources/charges');
  await expect(page).toHaveURL(/\/admin\/login/);

  await page.locator('input[type="email"]').fill('merchant@merchant-flow.test');
  await page.locator('input[type="password"]').fill('MerchantFlowDemo!2026');
  await page.locator('button[type="submit"]').click();
  await expect(page).toHaveURL(/\/admin\/login/);
  await expect(page.getByText(/credentials do not match/i)).toBeVisible();
});

test('administrator signs in and navigates to payments', async ({ page }) => {
  await signInAdmin(page);
  await page
    .getByRole('link', { name: 'Charges', exact: true })
    .first()
    .click();
  await expect(page).toHaveURL(/\/admin\/resources\/charges$/);
  await expect(page.getByRole('link', { name: /Create Charge/ })).toBeVisible();
});
