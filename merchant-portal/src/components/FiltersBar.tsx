import { useEffect, useState } from 'react';
import type { Account, ChargeStatus } from '../api/types';
import type { ChargeFilters } from './ChargesGrid';
import { STATUS_OPTIONS } from './StatusBadge';

interface Props {
  accounts: Account[];
  value: ChargeFilters;
  onChange: (filters: ChargeFilters) => void;
}

export const EMPTY_FILTERS: ChargeFilters = {
  status: '',
  accountId: '',
  q: '',
  createdFrom: '',
  createdTo: '',
};

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
          placeholder="Customer email or transaction ID"
          value={search}
          onChange={(e) => setSearch(e.target.value)}
        />
      </label>

      <label className="field">
        <span>Status</span>
        <select
          value={value.status ?? ''}
          onChange={(e) =>
            onChange({ ...value, status: e.target.value as ChargeStatus | '' })
          }
        >
          <option value="">All statuses</option>
          {STATUS_OPTIONS.map(([status, label]) => (
            <option key={status} value={status}>
              {label}
            </option>
          ))}
        </select>
      </label>

      {accounts.length > 1 && (
        <label className="field">
          <span>Merchant</span>
          <select
            value={value.accountId ?? ''}
            onChange={(e) =>
              onChange({
                ...value,
                accountId: e.target.value ? Number(e.target.value) : '',
              })
            }
          >
            <option value="">All merchants</option>
            {accounts.map((a) => (
              <option key={a.id} value={a.id}>
                {a.business_name}
              </option>
            ))}
          </select>
        </label>
      )}

      <label className="field">
        <span>From</span>
        <input
          type="date"
          value={value.createdFrom ?? ''}
          max={value.createdTo || undefined}
          onChange={(e) => onChange({ ...value, createdFrom: e.target.value })}
        />
      </label>
      <label className="field">
        <span>To</span>
        <input
          type="date"
          value={value.createdTo ?? ''}
          min={value.createdFrom || undefined}
          onChange={(e) => onChange({ ...value, createdTo: e.target.value })}
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
