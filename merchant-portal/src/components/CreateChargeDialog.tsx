import { useState, type FormEvent } from 'react';
import { api, newIdempotencyKey } from '../api/client';
import type { Account, Charge } from '../api/types';
import { CURRENCIES, parseAmount } from '../lib/money';
import { useSubmit } from '../lib/useSubmit';
import { Select } from './Select';

interface Props {
  accounts: Account[];
  onCreated: (charge: Charge) => void;
  onCancel: () => void;
}

const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

const CURRENCY_OPTIONS = CURRENCIES.map((c) => ({ value: c, label: c }));

export function CreateChargeDialog({ accounts, onCreated, onCancel }: Props) {
  const usable = accounts.filter((a) => a.accepts_charges);
  const [accountId, setAccountId] = useState(
    usable.length === 1 ? String(usable[0]?.id) : '',
  );
  const [amount, setAmount] = useState('');
  const [currency, setCurrency] = useState<string>('USD');
  const [email, setEmail] = useState('');
  const [capture, setCapture] = useState(true);
  const [touched, setTouched] = useState(false);
  // Generated once per dialog: retrying after a network error cannot create
  // a second charge.
  const [idempotencyKey] = useState(newIdempotencyKey);

  const minorAmount = parseAmount(amount);
  const localErrors: Record<string, string> = {};
  if (!accountId) localErrors.account_id = 'Choose a merchant account.';
  if (minorAmount === null)
    localErrors.amount = 'Enter an amount between 0.01 and 999,999.99.';
  if (!EMAIL_PATTERN.test(email.trim()))
    localErrors.customer_email = 'Enter a valid email address.';

  const { pending, error, fieldErrors, submit } = useSubmit(
    () =>
      api.createCharge(
        {
          account_id: Number(accountId),
          amount: minorAmount ?? 0,
          currency,
          customer_email: email.trim(),
          capture,
        },
        idempotencyKey,
      ),
    onCreated,
  );

  const errors = touched ? { ...localErrors, ...fieldErrors } : fieldErrors;

  function onSubmit(event: FormEvent) {
    event.preventDefault();
    setTouched(true);
    if (Object.keys(localErrors).length === 0) void submit();
  }

  if (usable.length === 0) {
    return (
      <div className="dialog-body">
        <p>None of your merchant accounts can accept new charges right now.</p>
        <div className="dialog-actions">
          <button type="button" className="button" onClick={onCancel}>
            Close
          </button>
        </div>
      </div>
    );
  }

  return (
    <form className="dialog-body" onSubmit={onSubmit} noValidate>
      <label className="field">
        <span>Merchant account</span>
        <Select
          value={accountId}
          options={usable.map((a) => ({
            value: String(a.id),
            label: `${a.business_name} · MID ${a.mid}`,
          }))}
          onChange={setAccountId}
          invalid={!!errors.account_id}
        />
        {errors.account_id && (
          <small className="field-error">{errors.account_id}</small>
        )}
      </label>

      <div className="field-row">
        <label className="field field-grow">
          <span>Amount</span>
          <input
            inputMode="decimal"
            placeholder="0.00"
            value={amount}
            onChange={(e) => setAmount(e.target.value)}
            aria-invalid={!!errors.amount}
          />
          {errors.amount && (
            <small className="field-error">{errors.amount}</small>
          )}
        </label>
        <label className="field">
          <span>Currency</span>
          <Select
            value={currency}
            options={CURRENCY_OPTIONS}
            onChange={setCurrency}
          />
        </label>
      </div>

      <label className="field">
        <span>Customer email</span>
        <input
          type="email"
          placeholder="customer@example.com"
          value={email}
          onChange={(e) => setEmail(e.target.value)}
          aria-invalid={!!errors.customer_email}
        />
        {errors.customer_email && (
          <small className="field-error">{errors.customer_email}</small>
        )}
      </label>

      <label className="checkbox">
        <input
          type="checkbox"
          checked={capture}
          onChange={(e) => setCapture(e.target.checked)}
        />
        <span>
          Capture now
          <small className="muted">
            Unchecked: only authorize; capture or void it later.
          </small>
        </span>
      </label>

      {error && Object.keys(fieldErrors).length === 0 && (
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
          {pending ? 'Creating…' : capture ? 'Charge' : 'Authorize'}
        </button>
      </div>
    </form>
  );
}
