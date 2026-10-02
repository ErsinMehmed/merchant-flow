import { expect, test } from '@playwright/test';
import { openResource, signInAdmin } from './helpers';

test.beforeEach(async ({ page }) => {
  await signInAdmin(page);
});

test('merchant, agent and user records expose their seeded relationships', async ({
  page,
}) => {
  await openResource(page, 'merchants');
  await expect(page.locator('table')).toContainText(
    'Northwind Coffee Roasters',
  );
  await page.goto('/admin/resources/merchants/1');
  const merchant = page.locator('[dusk="merchants-detail-component"]');
  await expect(merchant).toContainText('Northwind Coffee Roasters');
  await expect(merchant).toContainText('Agents');
  await expect(merchant).toContainText('Users');

  await openResource(page, 'agents');
  await expect(page.locator('table')).toContainText('Harbor Payments');
  await openResource(page, 'users');
  await expect(page.locator('table')).toContainText(
    'merchant@merchant-flow.test',
  );
});

test('roles and permissions are visible in the access section', async ({
  page,
}) => {
  await openResource(page, 'roles');
  await expect(page.locator('table')).toContainText('merchant');
  await expect(page.locator('table')).toContainText('agent');
  await page.goto('/admin/resources/roles/2');
  await expect(page.locator('[dusk="roles-detail-component"]')).toContainText(
    'Permissions',
  );

  await openResource(page, 'permissions');
  await expect(page.locator('table')).toContainText('charges.refund');
  await expect(page.locator('table')).toContainText('users.manage');
});

test('admin creates a captured charge through Nova', async ({ page }) => {
  await page.goto('/admin/resources/charges/new');
  const email = `nova-e2e-${Date.now()}@example.test`;

  await page.getByRole('combobox', { name: 'Merchant' }).click();
  await page.getByRole('option', { name: 'Northwind Coffee Roasters' }).click();
  await page.locator('input[placeholder="Customer Email"]').fill(email);
  await page.locator('input[placeholder="Amount"]').fill('4.25');
  await page
    .getByRole('button', { name: 'Create Charge', exact: true })
    .click();

  await expect(page).toHaveURL(/\/admin\/resources\/charges\/\d+$/);
  const charge = page.locator('[dusk="charges-detail-component"]');
  await expect(charge).toContainText(email);
  await expect(charge).toContainText(/captured/i);
});
