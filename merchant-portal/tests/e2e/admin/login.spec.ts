import { expect, test, type Page } from '@playwright/test';
import { ADMIN_EMAIL, signInAdmin, submitLogin } from './helpers';

/**
 * Every Nova screen, with the heading that shows it rendered. Records are
 * from the demo seeder (merchant 1 is Northwind Coffee Roasters).
 */
const SCREENS: { name: string; path: string; heading: string | RegExp }[] = [
  { name: 'dashboard', path: '/admin/dashboards/main', heading: 'New Charges' },
  { name: 'charges', path: '/admin/resources/charges', heading: 'Charges' },
  {
    name: 'merchants',
    path: '/admin/resources/merchants',
    heading: 'Merchants',
  },
  { name: 'agents', path: '/admin/resources/agents', heading: 'Agents' },
  { name: 'users', path: '/admin/resources/users', heading: 'Users' },
  { name: 'roles', path: '/admin/resources/roles', heading: 'Roles' },
  {
    name: 'permissions',
    path: '/admin/resources/permissions',
    heading: 'Permissions',
  },
  {
    name: 'portal sessions',
    path: '/admin/resources/api-tokens',
    heading: 'Portal sessions',
  },
  {
    name: 'new charge',
    path: '/admin/resources/charges/new',
    heading: 'Create Charge',
  },
  {
    name: 'merchant detail',
    path: '/admin/resources/merchants/1',
    heading: /^Merchant Details: /,
  },
  {
    name: 'merchant edit',
    path: '/admin/resources/merchants/1/edit',
    heading: /^Update Merchant: /,
  },
  {
    name: 'user security',
    path: '/admin/user-security',
    heading: 'User Security',
  },
];

const LOGIN_URL = /\/admin\/login$/;

function urlOf(path: string) {
  return new RegExp(`${path.replace(/[/.]/g, '\\$&')}$`);
}

async function expectScreen(page: Page, screen: (typeof SCREENS)[number]) {
  await expect(page).toHaveURL(urlOf(screen.path));
  await expect(
    page.getByRole('heading', { name: screen.heading, exact: true }).first(),
  ).toBeVisible();
}

for (const screen of SCREENS) {
  test(`${screen.name}: guests sign in first and land on the screen they asked for`, async ({
    page,
  }) => {
    await page.goto(screen.path);
    await expect(page).toHaveURL(LOGIN_URL);

    await submitLogin(page, ADMIN_EMAIL);
    await expectScreen(page, screen);
  });
}

test('a signed-in administrator opens every screen without the login form', async ({
  page,
}) => {
  await signInAdmin(page);
  for (const screen of SCREENS) {
    await page.goto(screen.path);
    await expectScreen(page, screen);
  }
});

test('a wrong password keeps the administrator on the login form', async ({
  page,
}) => {
  await page.goto('/admin/resources/charges');
  await submitLogin(page, ADMIN_EMAIL, 'not-the-password');
  await expect(page).toHaveURL(LOGIN_URL);
  await expect(page.getByText(/credentials do not match/i)).toBeVisible();

  // The right password still works and keeps the requested screen.
  await submitLogin(page, ADMIN_EMAIL);
  await expectScreen(page, {
    name: 'charges',
    path: '/admin/resources/charges',
    heading: 'Charges',
  });
});

test('agent accounts are turned away at the login form', async ({ page }) => {
  await page.goto('/admin/dashboards/main');
  await submitLogin(page, 'agent@merchant-flow.test');
  await expect(page).toHaveURL(LOGIN_URL);
  await expect(page.getByText(/credentials do not match/i)).toBeVisible();

  await page.goto('/admin/resources/users');
  await expect(page).toHaveURL(LOGIN_URL);
});

test('after logging out every screen asks for the login again', async ({
  page,
}) => {
  await signInAdmin(page);
  await page.getByRole('button', { name: 'Avery Admin' }).click();
  await page.getByRole('button', { name: 'Logout' }).click();
  await page
    .getByRole('alertdialog', { name: 'Logout' })
    .getByRole('button', { name: 'Logout' })
    .click();
  await expect(page).toHaveURL(LOGIN_URL);

  for (const screen of SCREENS) {
    await page.goto(screen.path);
    await expect(page, screen.name).toHaveURL(LOGIN_URL);
  }
});
