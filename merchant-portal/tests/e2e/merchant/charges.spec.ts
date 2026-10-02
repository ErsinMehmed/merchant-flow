import { expect, test } from '@playwright/test';
import { chargeRows, createCharge, signIn } from './helpers';

test('create form rejects invalid data before submitting', async ({ page }) => {
  await signIn(page, 'merchant@merchant-flow.test');
  await page.getByRole('button', { name: 'New charge' }).click();
  const dialog = page.getByRole('dialog', { name: 'New charge' });
  await dialog.getByRole('button', { name: 'Charge', exact: true }).click();
  await expect(
    dialog.getByText('Enter an amount between 0.01 and 999,999.99.'),
  ).toBeVisible();
  await expect(dialog.getByText('Enter a valid email address.')).toBeVisible();
  await expect(dialog).toBeVisible();
});

test('merchant creates, partially refunds and finds a charge', async ({
  page,
}) => {
  await signIn(page, 'merchant@merchant-flow.test');
  const { details, email } = await createCharge(page, true);
  await expect(details.locator('.badge')).toHaveText('Captured');

  await details.getByRole('button', { name: 'Refund', exact: true }).click();
  const refund = page.getByRole('dialog', { name: 'Refund charge' });
  await refund
    .getByRole('textbox', { name: 'Refund amount (USD)' })
    .fill('2.50');
  await refund.getByRole('button', { name: /Refund \$2\.50/ }).click();
  await expect(details.locator('.badge')).toHaveText('Partially refunded');
  await details.getByRole('button', { name: 'Close' }).click();

  await page.getByRole('searchbox', { name: 'Search' }).fill(email);
  const row = chargeRows(page).filter({ hasText: email });
  await expect(row).toHaveCount(1);
  await expect(row).toContainText('Northwind Coffee Roasters');
});

test('merchant captures an authorized charge', async ({ page }) => {
  await signIn(page, 'merchant@merchant-flow.test');
  const { details } = await createCharge(page, false);
  await expect(details.locator('.badge')).toHaveText('Authorized');

  await details.getByRole('button', { name: 'Capture', exact: true }).click();
  await page
    .getByRole('dialog', { name: 'Capture charge' })
    .getByRole('button', { name: 'Capture', exact: true })
    .click();
  await expect(details.locator('.badge')).toHaveText('Captured');
});

test('merchant voids an authorized charge', async ({ page }) => {
  await signIn(page, 'merchant@merchant-flow.test');
  const { details } = await createCharge(page, false);
  await expect(details.locator('.badge')).toHaveText('Authorized');

  await details.getByRole('button', { name: 'Void', exact: true }).click();
  await page
    .getByRole('dialog', { name: 'Void charge' })
    .getByRole('button', { name: 'Void charge' })
    .click();
  await expect(details.locator('.badge')).toHaveText('Voided');
});
