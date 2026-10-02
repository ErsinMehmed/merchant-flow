import { useEffect, useState } from 'react';
import type { Account, ChargeStatus } from '../api/types';
import { CURRENCIES } from '../lib/money';
import type { ChargeFilters } from './ChargesGrid';
import { DatePicker } from './DatePicker';
import { Select } from './Select';
import { STATUS_OPTIONS } from './StatusBadge';

interface Props {
  accounts: Account[];
  value: ChargeFilters;
  onChange: (filters: ChargeFilters) => void;
}

export const EMPTY_FILTERS: ChargeFilters = {
  status: '',
  accountId: '',
  currency: '',
  disputed: '',
  q: '',
  createdFrom: '',
  createdTo: '',
};

const STATUSES = [
  { value: '', label: 'All statuses' },
  ...STATUS_OPTIONS.map(([value, label]) => ({ value, label })),
];

const CURRENCY_OPTIONS = [
  { value: '', label: 'All currencies' },
  ...CURRENCIES.map((currency) => ({ value: currency, label: currency })),
];

const DISPUTES = [
  { value: '', label: 'All charges' },
  { value: 'true', label: 'Disputed only' },
  { value: 'false', label: 'Not disputed' },
];

export function FiltersBar({ accounts, value, onChange }: Props) {
  const [search, setSearch] = useState(value.q ?? '');

  // Typing should not send a request per keystroke.
  useEffect(() => {
    const trimmed = search.trim();
    if (trimmed === (value.q ?? '')) return;
    const timer = window.setTimeout(
      () => onChange({ ...value, q: trimmed }),
      350,
    );
    return () => window.clearTimeout(timer);
  }, [search, value, onChange]);

  const active = Object.values(value).some((v) => v !== '' && v !== undefined);

  return (
    <div className="filters" role="search" aria-label="Filter charges">
      <label className="field field-search">
        <span>Search</span>
        <input
          type="search"
          placeholder="Email or transaction ID"
          value={search}
          onChange={(e) => setSearch(e.target.value)}
        />
      </label>

      <label className="field">
        <span>Status</span>
        <Select
          value={value.status ?? ''}
          options={STATUSES}
          onChange={(status) =>
            onChange({ ...value, status: status as ChargeStatus | '' })
          }
        />
      </label>

      {accounts.length > 1 && (
        <label className="field">
          <span>Merchant</span>
          <Select
            value={String(value.accountId ?? '')}
            options={[
              { value: '', label: 'All merchants' },
              ...accounts.map((a) => ({
                value: String(a.id),
                label: a.business_name,
              })),
            ]}
            onChange={(id) =>
              onChange({ ...value, accountId: id ? Number(id) : '' })
            }
          />
        </label>
      )}

      <label className="field">
        <span>Currency</span>
        <Select
          value={value.currency ?? ''}
          options={CURRENCY_OPTIONS}
          onChange={(currency) => onChange({ ...value, currency })}
        />
      </label>

      <label className="field">
        <span>Disputes</span>
        <Select
          value={value.disputed ?? ''}
          options={DISPUTES}
          onChange={(disputed) =>
            onChange({
              ...value,
              disputed: disputed as ChargeFilters['disputed'],
            })
          }
        />
      </label>

      <label className="field">
        <span>From</span>
        <DatePicker
          value={value.createdFrom ?? ''}
          max={value.createdTo || undefined}
          onChange={(createdFrom) => onChange({ ...value, createdFrom })}
        />
      </label>
      <label className="field">
        <span>To</span>
        <DatePicker
          value={value.createdTo ?? ''}
          min={value.createdFrom || undefined}
          onChange={(createdTo) => onChange({ ...value, createdTo })}
        />
      </label>

      {active && (
        <button
          type="button"
          className="button button-quiet filters-clear"
          onClick={() => {
            setSearch('');
            onChange(EMPTY_FILTERS);
          }}
        >
          Clear filters
        </button>
      )}
    </div>
  );
}
