import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { ApiError, api } from '../../src/api/client';
import type { User } from '../../src/api/types';
import { App } from '../../src/App';
import { SessionProvider } from '../../src/session/SessionContext';

const merchant: User = {
  id: 3,
  name: 'Morgan Merchant',
  email: 'merchant@merchant-flow.test',
  roles: ['merchant'],
  permissions: ['charges.view'],
  avatar_url: null,
};

function openProfile(user: User = merchant) {
  window.history.replaceState(null, '', '/profile');
  vi.spyOn(api, 'me').mockResolvedValue(user);
  vi.spyOn(api, 'accounts').mockResolvedValue([
    {
      id: 1,
      business_name: 'Northwind Coffee Roasters',
      mid: '420000000001',
      status: 'active',
      accepts_charges: true,
    },
  ]);
  return render(
    <SessionProvider>
      <App />
    </SessionProvider>,
  );
}

async function fillPassword(current: string, next: string, confirm = next) {
  await userEvent.type(screen.getByLabelText(/^Current password/), current);
  await userEvent.type(screen.getByLabelText(/^New password/), next);
  await userEvent.type(screen.getByLabelText(/^Confirm new password/), confirm);
  await userEvent.click(
    screen.getByRole('button', { name: 'Change password' }),
  );
}

describe('profile page', () => {
  afterEach(() => window.history.replaceState(null, '', '/'));

  it('shows the account and the merchant accounts it can see', async () => {
    openProfile();

    expect(
      await screen.findByRole('heading', { name: 'Profile' }),
    ).toBeVisible();
    const account = screen.getByRole('region', { name: 'Account' });
    expect(account).toHaveTextContent('merchant@merchant-flow.test');
    expect(
      await within(account).findByText('Northwind Coffee Roasters'),
    ).toBeVisible();
    expect(within(account).getByText('MM')).toBeVisible();
  });

  it('uploads a photo and shows it in the profile and the top bar', async () => {
    const upload = vi
      .spyOn(api, 'uploadAvatar')
      .mockResolvedValue({ ...merchant, avatar_url: '/api/v1/me/avatar?v=1' });
    const { container } = openProfile();
    await screen.findByRole('heading', { name: 'Profile' });

    const photo = new File(['png'], 'me.png', { type: 'image/png' });
    await userEvent.upload(screen.getByLabelText('Profile photo'), photo);

    expect(upload).toHaveBeenCalledWith(photo);
    expect(await screen.findByText('Profile photo updated.')).toBeVisible();
    const images = container.querySelectorAll('img.avatar');
    expect(images).toHaveLength(2);
    images.forEach((img) =>
      expect(img).toHaveAttribute('src', '/api/v1/me/avatar?v=1'),
    );
    expect(screen.getByRole('button', { name: 'Remove' })).toBeVisible();
  });

  it('refuses files that are not a supported image before uploading', async () => {
    const upload = vi.spyOn(api, 'uploadAvatar');
    openProfile();
    await screen.findByRole('heading', { name: 'Profile' });

    await userEvent.upload(
      screen.getByLabelText('Profile photo'),
      new File(['<svg/>'], 'me.svg', { type: 'image/svg+xml' }),
      { applyAccept: false },
    );

    expect(await screen.findByRole('alert')).toHaveTextContent(
      'Choose a JPEG, PNG or WebP image.',
    );
    expect(upload).not.toHaveBeenCalled();
  });

  it('shows the field error for a wrong current password', async () => {
    vi.spyOn(api, 'changePassword').mockRejectedValue(
      new ApiError(422, 'validation_failed', 'The request is invalid.', {
        current_password: 'is incorrect',
      }),
    );
    openProfile();
    await screen.findByRole('heading', { name: 'Profile' });

    await fillPassword('wrong', 'New-Passw0rd!');

    expect(await screen.findByText('is incorrect')).toBeVisible();
    expect(screen.getByLabelText(/^Current password/)).toHaveAttribute(
      'aria-invalid',
      'true',
    );
  });

  it('changes the password and clears the form', async () => {
    const change = vi.spyOn(api, 'changePassword').mockResolvedValue();
    openProfile();
    await screen.findByRole('heading', { name: 'Profile' });

    await fillPassword('Old-Passw0rd!', 'New-Passw0rd!');

    expect(change).toHaveBeenCalledWith({
      current_password: 'Old-Passw0rd!',
      password: 'New-Passw0rd!',
      password_confirmation: 'New-Passw0rd!',
    });
    expect(
      await screen.findByText(
        'Password changed. Your other sessions were signed out.',
      ),
    ).toBeVisible();
    expect(screen.getByLabelText(/^New password/)).toHaveValue('');
  });

  it('signs out from the account menu', async () => {
    const logout = vi.spyOn(api, 'logout').mockResolvedValue();
    openProfile();
    await screen.findByRole('heading', { name: 'Profile' });

    await userEvent.click(
      screen.getByRole('button', { name: 'Morgan Merchant' }),
    );
    const menu = screen.getByRole('menu', { name: 'Account' });
    expect(
      within(menu).getByRole('menuitem', { name: 'Profile' }),
    ).toHaveFocus();
    await userEvent.keyboard('{ArrowDown}{Enter}');

    expect(logout).toHaveBeenCalledOnce();
    expect(
      await screen.findByRole('heading', {
        name: 'Sign in to the merchant portal',
      }),
    ).toBeVisible();
  });
});
