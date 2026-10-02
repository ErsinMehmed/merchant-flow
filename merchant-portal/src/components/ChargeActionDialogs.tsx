import { useState, type FormEvent } from 'react';
import { api, newIdempotencyKey } from '../api/client';
import type { Charge, VoidReason } from '../api/types';
import { formatMoney, parseAmount, toInputAmount } from '../lib/money';
import { useRequestKey, useSubmit } from '../lib/useSubmit';
import { Select } from './Select';

interface ActionProps {
  charge: Charge;
  onDone: (charge: Charge) => void;
  onCancel: () => void;
}

export function RefundForm({ charge, onDone, onCancel }: ActionProps) {
  const [amount, setAmount] = useState(toInputAmount(charge.amount_refundable));
  const requestKey = useRequestKey();
  const minor = parseAmount(amount);
  const tooMuch = minor !== null && minor > charge.amount_refundable;
  const invalid = minor === null || tooMuch;

  const { pending, error, submit } = useSubmit(
    () =>
      api.refund(
        charge.id,
        minor ?? 0,
        requestKey({ chargeId: charge.id, amount: minor }),
      ),
    onDone,
  );

  function onSubmit(event: FormEvent) {
    event.preventDefault();
    if (!invalid) void submit();
  }

  return (
    <form className="dialog-body" onSubmit={onSubmit} noValidate>
      <dl className="summary">
        <div>
          <dt>Captured</dt>
          <dd>{formatMoney(charge.amount_captured, charge.currency)}</dd>
        </div>
        <div>
          <dt>Already refunded</dt>
          <dd>{formatMoney(charge.amount_refunded, charge.currency)}</dd>
        </div>
        <div>
          <dt>Left to refund</dt>
          <dd>{formatMoney(charge.amount_refundable, charge.currency)}</dd>
        </div>
      </dl>

      <label className="field">
        <span>Refund amount ({charge.currency})</span>
        <input
          inputMode="decimal"
          value={amount}
          onChange={(e) => setAmount(e.target.value)}
          aria-invalid={invalid}
          autoFocus
        />
        {minor === null && (
          <small className="field-error">
            Enter an amount with at most two decimals.
          </small>
        )}
        {tooMuch && (
          <small className="field-error">
            At most {formatMoney(charge.amount_refundable, charge.currency)} can
            be refunded.
          </small>
        )}
      </label>

      {error && (
        <p className="form-error" role="alert">
          {error}
        </p>
      )}

      <div className="dialog-actions">
        <button
          type="button"
          className="button"
          onClick={onCancel}
          disabled={pending}
        >
          Cancel
        </button>
        <button
          type="submit"
          className="button button-danger"
          disabled={pending || invalid}
        >
          {pending
            ? 'Refunding…'
            : `Refund ${minor !== null && !tooMuch ? formatMoney(minor, charge.currency) : ''}`}
        </button>
      </div>
    </form>
  );
}

const VOID_REASONS: { value: VoidReason; label: string }[] = [
  { value: 'requested_by_customer', label: 'Requested by customer' },
  { value: 'duplicate', label: 'Duplicate' },
  { value: 'fraudulent', label: 'Fraudulent' },
  { value: 'abandoned', label: 'Abandoned' },
];

export function VoidForm({ charge, onDone, onCancel }: ActionProps) {
  const [reason, setReason] = useState<VoidReason>('requested_by_customer');
  const requestKey = useRequestKey();
  const { pending, error, submit } = useSubmit(
    () =>
      api.void(charge.id, reason, requestKey({ chargeId: charge.id, reason })),
    onDone,
  );

  return (
    <form
      className="dialog-body"
      onSubmit={(e) => {
        e.preventDefault();
        void submit();
      }}
    >
      <p>
        Release the authorization of{' '}
        <strong>{formatMoney(charge.amount, charge.currency)}</strong>. The
        customer is not charged.
      </p>
      <label className="field">
        <span>Reason</span>
        <Select
          value={reason}
          options={VOID_REASONS}
          onChange={(value) => setReason(value as VoidReason)}
        />
      </label>

      {error && (
        <p className="form-error" role="alert">
          {error}
        </p>
      )}

      <div className="dialog-actions">
        <button
          type="button"
          className="button"
          onClick={onCancel}
          disabled={pending}
        >
          Cancel
        </button>
        <button
          type="submit"
          className="button button-danger"
          disabled={pending}
        >
          {pending ? 'Voiding…' : 'Void charge'}
        </button>
      </div>
    </form>
  );
}

export function CaptureForm({ charge, onDone, onCancel }: ActionProps) {
  const [idempotencyKey] = useState(newIdempotencyKey);
  const { pending, error, submit } = useSubmit(
    () => api.capture(charge.id, idempotencyKey),
    onDone,
  );

  return (
    <form
      className="dialog-body"
      onSubmit={(e) => {
        e.preventDefault();
        void submit();
      }}
    >
      <p>
        Capture the authorized{' '}
        <strong>{formatMoney(charge.amount, charge.currency)}</strong> from{' '}
        {charge.customer_email}.
      </p>

      {error && (
        <p className="form-error" role="alert">
          {error}
        </p>
      )}

      <div className="dialog-actions">
        <button
          type="button"
          className="button"
          onClick={onCancel}
          disabled={pending}
        >
          Cancel
        </button>
        <button
          type="submit"
          className="button button-primary"
          disabled={pending}
        >
          {pending ? 'Capturing…' : 'Capture'}
        </button>
      </div>
    </form>
  );
}
