import { expect, test } from '@playwright/test';
import { chooseFilter, openResource, signInAdmin } from './helpers';

test.beforeEach(async ({ page }) => {
  await signInAdmin(page);
});

test('charge filters and page size update the index', async ({ page }) => {
  await openResource(page, 'charges');
  await chooseFilter(page, 'Status', 'Authorized');
  await chooseFilter(
    page,
    'Merchant',
    'Northwind Coffee Roasters (MID 420000000001)',
  );
  await expect(page.locator('table tbody tr').first()).toContainText(
    'Northwind Coffee Roasters',
  );
  await expect(page.locator('table tbody tr').first()).toContainText(
    /authorized/i,
  );

  await chooseFilter(page, 'Per page', '50');
  await expect(page).toHaveURL(/charges_per_page=50/);
  await page.getByRole('button', { name: 'Reset filters' }).click();
  await expect(page.getByRole('combobox', { name: 'Status' })).toContainText(
    'All',
  );
});

test('merchant status and processing filters identify inactive accounts', async ({
  page,
}) => {
  await openResource(page, 'merchants');
  await chooseFilter(page, 'Status', 'Suspended');
  await expect(page.locator('table tbody tr')).toHaveCount(1);
  await expect(page.locator('table tbody tr').first()).toContainText(
    'Tailspin Toys',
  );
  await chooseFilter(page, 'Processing', 'Disabled');
  await expect(page.locator('table tbody tr').first()).toContainText(
    'Tailspin Toys',
  );
});

test('agent hierarchy and user type filters narrow their lists', async ({
  page,
}) => {
  await openResource(page, 'agents');
  await chooseFilter(page, 'Parent agent', 'Harbor Payments (#1)');
  await expect(page.locator('table tbody tr')).toHaveCount(1);
  await expect(page.locator('table tbody tr').first()).toContainText(
    'Summit Referral',
  );

  await openResource(page, 'users');
  await chooseFilter(page, 'Type', 'Merchant');
  await expect(page.locator('table')).toContainText(
    'merchant@merchant-flow.test',
  );
  await expect(page.locator('table')).not.toContainText(
    'admin@merchant-flow.test',
  );
});
