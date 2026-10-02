import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { ApiError, api } from '../api/client';
import type { Account, Charge } from '../api/types';
import { CreateChargeDialog } from './CreateChargeDialog';

const accounts: Account[] = [
  {
    id: 1,
    business_name: 'Northwind',
    mid: '1001',
    status: 'active',
    accepts_charges: true,
  },
  {
    id: 2,
    business_name: 'Tailspin',
    mid: '1002',
    status: 'suspended',
    accepts_charges: false,
  },
];

describe('CreateChargeDialog', () => {
  it('only offers accounts that accept charges and sends minor units', async () => {
    const create = vi
      .spyOn(api, 'createCharge')
      .mockResolvedValue({ id: 9 } as Charge);
    const onCreated = vi.fn();
    render(
      <CreateChargeDialog
        accounts={accounts}
        onCreated={onCreated}
        onCancel={() => {}}
      />,
    );

    await userEvent.click(
      screen.getByRole('combobox', { name: 'Merchant account' }),
    );
    expect(screen.getByRole('option', { name: /Northwind/ })).toHaveAttribute(
      'aria-selected',
      'true',
    );
    expect(
      screen.queryByRole('option', { name: /Tailspin/ }),
    ).not.toBeInTheDocument();
    await userEvent.keyboard('{Escape}');

    await userEvent.type(screen.getByLabelText('Amount'), '12.5');
    await userEvent.type(
      screen.getByLabelText('Customer email'),
      'jane@example.com',
    );
    await userEvent.click(screen.getByRole('button', { name: 'Charge' }));

    expect(create).toHaveBeenCalledWith(
      {
        account_id: 1,
        amount: 1250,
        currency: 'USD',
        customer_email: 'jane@example.com',
        capture: true,
      },
      expect.any(String),
    );
    expect(onCreated).toHaveBeenCalledWith({ id: 9 });
  });

  it('validates before calling the API', async () => {
    const create = vi.spyOn(api, 'createCharge');
    render(
      <CreateChargeDialog
        accounts={accounts}
        onCreated={() => {}}
        onCancel={() => {}}
      />,
    );

    await userEvent.type(screen.getByLabelText('Amount'), '1.005');
    await userEvent.click(screen.getByRole('button', { name: 'Charge' }));

    expect(create).not.toHaveBeenCalled();
    expect(screen.getByText(/Enter an amount/)).toBeInTheDocument();
    expect(screen.getByText(/valid email/)).toBeInTheDocument();
  });

  it('reuses the same idempotency key when the user retries', async () => {
    const create = vi
      .spyOn(api, 'createCharge')
      .mockRejectedValueOnce(
        new ApiError(0, 'network_error', 'Could not reach the server.'),
      )
      .mockResolvedValueOnce({ id: 3 } as Charge);
    render(
      <CreateChargeDialog
        accounts={accounts}
        onCreated={() => {}}
        onCancel={() => {}}
      />,
    );

    await userEvent.type(screen.getByLabelText('Amount'), '5');
    await userEvent.type(
      screen.getByLabelText('Customer email'),
      'jane@example.com',
    );
    await userEvent.click(screen.getByRole('button', { name: 'Charge' }));
    expect(await screen.findByRole('alert')).toHaveTextContent(
      'Could not reach the server.',
    );

    await userEvent.click(screen.getByRole('button', { name: 'Charge' }));

    expect(create).toHaveBeenCalledTimes(2);
    expect(create.mock.calls[0]![1]).toBe(create.mock.calls[1]![1]);
  });

  it('explains when no account can take charges', () => {
    render(
      <CreateChargeDialog
        accounts={[accounts[1]!]}
        onCreated={() => {}}
        onCancel={() => {}}
      />,
    );

    expect(
      screen.getByText(/None of your merchant accounts/),
    ).toBeInTheDocument();
  });
});
