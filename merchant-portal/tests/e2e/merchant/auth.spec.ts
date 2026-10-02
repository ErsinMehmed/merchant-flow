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
  await page.getByRole('button', { name: 'Morgan Merchant' }).click();
  await page.getByRole('menuitem', { name: 'Sign out' }).click();
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

test('opens the profile from the account menu', async ({ page }) => {
  await signIn(page, 'merchant@merchant-flow.test');
  await page.getByRole('button', { name: 'Morgan Merchant' }).click();
  await page.getByRole('menuitem', { name: 'Profile' }).click();

  await expect(page).toHaveURL(/\/profile$/);
  const account = page.getByRole('region', { name: 'Account' });
  await expect(account).toContainText('merchant@merchant-flow.test');
  await expect(account).toContainText('Northwind Coffee Roasters');
  // The seeded demo photo is served by the API.
  const photo = account.locator('img.avatar');
  await expect(photo).toHaveAttribute('src', /^\/api\/v1\/me\/avatar\?v=\d+$/);
  expect(
    await photo.evaluate((img: HTMLImageElement) => img.naturalWidth),
  ).toBeGreaterThan(0);

  await page.reload();
  await expect(page.getByRole('heading', { name: 'Profile' })).toBeVisible();
  await page.getByRole('button', { name: 'Charges' }).click();
  await expect(page.getByRole('heading', { name: 'Charges' })).toBeVisible();
});
