import { expect, test } from '@playwright/test';
import { chargeRows, signIn } from './helpers';

test('agents can inspect charges but have no write actions', async ({
  page,
}) => {
  await signIn(page, 'agent@merchant-flow.test');
  await expect(page.getByRole('button', { name: 'New charge' })).toHaveCount(0);

  await chargeRows(page).nth(1).getByRole('gridcell', { name: /@/ }).click();
  const details = page.getByRole('dialog', { name: 'Charge', exact: true });
  await expect(
    details.getByText('No actions available for this charge.'),
  ).toBeVisible();
});

test('merchant sees only assigned accounts and cannot charge a suspended one', async ({
  page,
}) => {
  await signIn(page, 'merchant-two@merchant-flow.test');
  await page.getByRole('combobox', { name: 'Merchant' }).click();
  await expect(
    page.getByRole('option', { name: 'Contoso Books' }),
  ).toBeVisible();
  await expect(
    page.getByRole('option', { name: 'Tailspin Toys' }),
  ).toBeVisible();
  await expect(
    page.getByRole('option', { name: 'Northwind Coffee Roasters' }),
  ).toHaveCount(0);
  await page.getByRole('option', { name: 'Contoso Books' }).click();
  await expect(chargeRows(page).nth(1)).toContainText('Contoso Books');

  await page.getByRole('button', { name: 'New charge' }).click();
  const dialog = page.getByRole('dialog', { name: 'New charge' });
  await expect(
    dialog.getByRole('combobox', { name: 'Merchant account' }),
  ).toContainText('Contoso Books');
  await dialog.getByRole('combobox', { name: 'Merchant account' }).click();
  await expect(
    dialog.getByRole('option', { name: /Tailspin Toys/ }),
  ).toHaveCount(0);
});
