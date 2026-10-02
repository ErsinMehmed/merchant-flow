import { expect, test } from '@playwright/test';
import { DEMO_PASSWORD, signIn } from './helpers';

test('rejects invalid credentials and ends the session on sign-out', async ({
  page,
}) => {
  await page.goto('/');
  await page
    .getByRole('textbox', { name: 'Email' })
    .fill('merchant@merchant-flow.test');
  await page.getByLabel('Password').fill('wrong-password');
  await page.getByRole('button', { name: 'Sign in', exact: true }).click();
  await expect(page.getByRole('alert')).toHaveText(
    'The email or password is incorrect.',
  );

  await page.getByLabel('Password').fill(DEMO_PASSWORD);
  await page.getByRole('button', { name: 'Sign in', exact: true }).click();
  await expect(page.getByRole('heading', { name: 'Charges' })).toBeVisible();
  await page.getByRole('button', { name: 'Sign out' }).click();
  await page.reload();
  await expect(
    page.getByRole('heading', { name: 'Sign in to the merchant portal' }),
  ).toBeVisible();
});

test('restores a valid session after reloading', async ({ page }) => {
  await signIn(page, 'merchant@merchant-flow.test');
  await page.reload();
  await expect(page.getByRole('heading', { name: 'Charges' })).toBeVisible();
  await expect(page.getByText('Morgan Merchant')).toBeVisible();
});
