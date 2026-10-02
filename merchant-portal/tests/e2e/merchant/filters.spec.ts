import { expect, test, type Page } from '@playwright/test';
import { chargeRows, signIn } from './helpers';

async function chooseFilter(page: Page, name: string, option: string) {
  await page.getByRole('combobox', { name, exact: true }).click();
  await page.getByRole('option', { name: option, exact: true }).click();
}

test('status, merchant, currency and dispute filters narrow the grid', async ({
  page,
}) => {
  await signIn(page, 'merchant@merchant-flow.test');
  await chooseFilter(page, 'Status', 'Authorized');
  await expect(chargeRows(page).nth(1)).toContainText('Authorized');

  await chooseFilter(page, 'Merchant', 'Northwind Coffee Roasters');
  await chooseFilter(page, 'Currency', 'USD');
  await chooseFilter(page, 'Disputes', 'Not disputed');
  await expect(chargeRows(page).nth(1)).toContainText(
    'Northwind Coffee Roasters',
  );

  await page.getByRole('button', { name: 'Clear filters' }).click();
  await expect(page.getByRole('combobox', { name: 'Status' })).toContainText(
    'All statuses',
  );
  await expect(page.getByRole('combobox', { name: 'Merchant' })).toContainText(
    'All merchants',
  );
});

test('date picker filters the grid and can be cleared', async ({ page }) => {
  await signIn(page, 'merchant@merchant-flow.test');
  const from = page.getByRole('combobox', { name: 'From' });
  await from.click();
  await page
    .getByRole('dialog', { name: 'Choose date' })
    .locator('button[aria-current="date"]')
    .click();
  await expect(from).not.toContainText('Any date');
  await expect(chargeRows(page).nth(1)).toBeVisible();

  await page.getByRole('button', { name: 'Clear filters' }).click();
  await expect(from).toContainText('Any date');
});
