import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { ApiError, api } from '../api/client';
import type { Charge } from '../api/types';
import { RefundForm } from './ChargeActionDialogs';

const charge = {
  id: 4,
  amount: 10_000,
  amount_captured: 10_000,
  amount_refunded: 2_500,
  amount_refundable: 7_500,
  currency: 'USD',
  status: 'captured',
} as Charge;

describe('RefundForm', () => {
  it('prefills what is left to refund', () => {
    render(
      <RefundForm charge={charge} onDone={() => {}} onCancel={() => {}} />,
    );

    expect(screen.getByLabelText(/Refund amount/)).toHaveValue('75.00');
    expect(screen.getByRole('button', { name: 'Refund $75.00' })).toBeEnabled();
  });

  it('blocks refunding more than is left', async () => {
    const refund = vi.spyOn(api, 'refund');
    render(
      <RefundForm charge={charge} onDone={() => {}} onCancel={() => {}} />,
    );

    const input = screen.getByLabelText(/Refund amount/);
    await userEvent.clear(input);
    await userEvent.type(input, '75.01');

    expect(
      screen.getByText(/At most \$75\.00 can be refunded/),
    ).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /Refund/ })).toBeDisabled();
    expect(refund).not.toHaveBeenCalled();
  });

  it('sends a partial refund in minor units', async () => {
    const refund = vi
      .spyOn(api, 'refund')
      .mockResolvedValue({ ...charge, amount_refunded: 3_000 });
    const onDone = vi.fn();
    render(<RefundForm charge={charge} onDone={onDone} onCancel={() => {}} />);

    const input = screen.getByLabelText(/Refund amount/);
    await userEvent.clear(input);
    await userEvent.type(input, '5');
    await userEvent.click(screen.getByRole('button', { name: 'Refund $5.00' }));

    expect(refund).toHaveBeenCalledWith(4, 500, expect.any(String));
    expect(onDone).toHaveBeenCalled();
  });

  it('uses a new idempotency key when a failed refund amount is edited', async () => {
    const refund = vi
      .spyOn(api, 'refund')
      .mockRejectedValue(new ApiError(0, 'network_error', 'Try again.'));
    render(
      <RefundForm charge={charge} onDone={() => {}} onCancel={() => {}} />,
    );

    const input = screen.getByLabelText(/Refund amount/);
    await userEvent.click(
      screen.getByRole('button', { name: 'Refund $75.00' }),
    );
    await screen.findByRole('alert');
    await userEvent.clear(input);
    await userEvent.type(input, '5');
    await userEvent.click(screen.getByRole('button', { name: 'Refund $5.00' }));

    expect(refund).toHaveBeenCalledTimes(2);
    expect(refund.mock.calls[0]![2]).not.toBe(refund.mock.calls[1]![2]);
  });
});
