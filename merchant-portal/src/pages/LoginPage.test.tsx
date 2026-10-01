import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { ApiError, api } from '../api/client';
import { App } from '../App';
import { SessionProvider } from '../session/SessionContext';

function renderApp() {
  return render(
    <SessionProvider>
      <App />
    </SessionProvider>,
  );
}

describe('sign-in', () => {
  it('shows the API message for bad credentials', async () => {
    vi.spyOn(api, 'me').mockRejectedValue(
      new ApiError(401, 'unauthenticated', 'Sign in to continue.'),
    );
    vi.spyOn(api, 'login').mockRejectedValue(
      new ApiError(
        401,
        'invalid_credentials',
        'The email or password is incorrect.',
      ),
    );
    renderApp();

    await userEvent.type(
      await screen.findByLabelText('Email'),
      'merchant@merchant-flow.test',
    );
    await userEvent.type(screen.getByLabelText('Password'), 'wrong');
    await userEvent.click(screen.getByRole('button', { name: 'Sign in' }));

    expect(await screen.findByRole('alert')).toHaveTextContent(
      'The email or password is incorrect.',
    );
  });

  it('fills the form from the demo account list', async () => {
    vi.spyOn(api, 'me').mockRejectedValue(
      new ApiError(401, 'unauthenticated', ''),
    );
    renderApp();

    await userEvent.click(await screen.findByText('Demo accounts'));
    await userEvent.click(
      screen.getByRole('button', { name: 'agent@merchant-flow.test' }),
    );

    expect(screen.getByLabelText('Email')).toHaveValue(
      'agent@merchant-flow.test',
    );
    expect(screen.getByLabelText('Password')).not.toHaveValue('');
  });
});
