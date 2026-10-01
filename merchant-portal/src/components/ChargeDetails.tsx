import type { Charge, ChargeAction } from '../api/types';
import { formatMoney } from '../lib/money';
import { StatusBadge } from './StatusBadge';

interface Props {
  charge: Charge;
  onAction: (action: ChargeAction) => void;
}

const ACTION_LABELS: Record<ChargeAction, string> = {
  capture: 'Capture',
  refund: 'Refund',
  void: 'Void',
};

const dateTime = new Intl.DateTimeFormat(undefined, {
  dateStyle: 'medium',
  timeStyle: 'medium',
});

export function ChargeDetails({ charge, onAction }: Props) {
  const money = (minor: number) => formatMoney(minor, charge.currency);

  return (
    <div className="dialog-body">
      <div className="details-head">
        <strong className="details-amount">{money(charge.amount)}</strong>
        <StatusBadge
          status={charge.status}
          partiallyRefunded={charge.amount_refunded > 0}
        />
        {charge.disputed && (
          <span className="badge badge-disputed">Disputed</span>
        )}
      </div>

      <dl className="details">
        <dt>Merchant</dt>
        <dd>
          {charge.account_name}{' '}
          <span className="muted">MID {charge.account_mid}</span>
        </dd>
        <dt>Customer</dt>
        <dd>{charge.customer_email}</dd>
        <dt>Transaction ID</dt>
        <dd className="mono">{charge.external_transaction_id}</dd>
        <dt>Captured</dt>
        <dd>{money(charge.amount_captured)}</dd>
        <dt>Refunded</dt>
        <dd>{money(charge.amount_refunded)}</dd>
        {charge.amount_voided > 0 && (
          <>
            <dt>Voided</dt>
            <dd>
              {money(charge.amount_voided)}
              {charge.void_reason && (
                <span className="muted">
                  {' '}
                  · {charge.void_reason.replaceAll('_', ' ')}
                </span>
              )}
            </dd>
          </>
        )}
        <dt>Created</dt>
        <dd>{dateTime.format(new Date(charge.created_at))}</dd>
        <dt>Last updated</dt>
        <dd>{dateTime.format(new Date(charge.updated_at))}</dd>
      </dl>

      {charge.disputed && charge.status === 'captured' && (
        <p className="notice">
          This charge is disputed; refunds are blocked until the dispute is
          resolved.
        </p>
      )}

      <div className="dialog-actions">
        {charge.actions.length === 0 && (
          <span className="muted">No actions available for this charge.</span>
        )}
        {charge.actions.map((action) => (
          <button
            key={action}
            type="button"
            className={`button ${action === 'capture' ? 'button-primary' : 'button-danger'}`}
            onClick={() => onAction(action)}
          >
            {ACTION_LABELS[action]}
          </button>
        ))}
      </div>
    </div>
  );
}
