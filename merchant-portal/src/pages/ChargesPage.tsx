import { useCallback, useEffect, useState } from 'react';
import { api } from '../api/client';
import type { Account, Charge, ChargeAction, User } from '../api/types';
import {
  CaptureForm,
  RefundForm,
  VoidForm,
} from '../components/ChargeActionDialogs';
import { ChargeDetails } from '../components/ChargeDetails';
import { ChargesGrid, type ChargeFilters } from '../components/ChargesGrid';
import { CreateChargeDialog } from '../components/CreateChargeDialog';
import { Dialog } from '../components/Dialog';
import { EMPTY_FILTERS, FiltersBar } from '../components/FiltersBar';
import { ToastProvider, useToast } from '../components/Toasts';
import { formatMoney } from '../lib/money';
import { useSession } from '../session/SessionContext';

export function ChargesPage({ user }: { user: User }) {
  return (
    <ToastProvider>
      <Portal user={user} />
    </ToastProvider>
  );
}

type Panel =
  | { kind: 'details'; charge: Charge }
  | { kind: ChargeAction; charge: Charge }
  | { kind: 'create' }
  | null;

const DIALOG_TITLES: Record<ChargeAction, string> = {
  capture: 'Capture charge',
  refund: 'Refund charge',
  void: 'Void charge',
};

function Portal({ user }: { user: User }) {
  const { signOut, can } = useSession();
  const notify = useToast();
  const [accounts, setAccounts] = useState<Account[]>([]);
  const [filters, setFilters] = useState<ChargeFilters>(EMPTY_FILTERS);
  const [total, setTotal] = useState<number | null>(null);
  const [loadError, setLoadError] = useState<string | null>(null);
  const [refreshToken, setRefreshToken] = useState(0);
  const [panel, setPanel] = useState<Panel>(null);

  const isAdmin = user.roles.includes('admin');
  const canView = can('charges.view');
  const canCreate = can('charges.create');

  useEffect(() => {
    api
      .accounts()
      .then(setAccounts)
      .catch(() => setAccounts([]));
  }, []);

  const onLoaded = useCallback((count: number) => {
    setTotal(count);
    setLoadError(null);
  }, []);

  function afterChange(charge: Charge, message: string) {
    notify('success', message);
    setRefreshToken((n) => n + 1);
    setPanel({ kind: 'details', charge });
  }

  const scope = isAdmin
    ? 'All merchants'
    : accounts.length === 1
      ? accounts[0]?.business_name
      : `${accounts.length} merchant accounts`;

  return (
    <div className="app">
      <header className="topbar">
        <div className="brand">
          <span className="brand-mark" aria-hidden="true" />
          MerchantFlow
        </div>
        <div className="topbar-user">
          <span className="user-name">{user.name}</span>
          <span className="user-roles">
            {user.roles.join(', ') || 'no role'}
          </span>
          <button
            type="button"
            className="button button-quiet"
            onClick={() => void signOut()}
          >
            Sign out
          </button>
        </div>
      </header>

      <main className="content">
        <div className="page-head">
          <div>
            <h1>Charges</h1>
            <p className="muted">
              {scope}
              {total !== null &&
                ` · ${total.toLocaleString()} ${total === 1 ? 'charge' : 'charges'}`}
            </p>
          </div>
          {canCreate && (
            <button
              type="button"
              className="button button-primary"
              onClick={() => setPanel({ kind: 'create' })}
            >
              New charge
            </button>
          )}
        </div>

        {!canView ? (
          <p className="notice">
            Your account does not have permission to view charges. Ask an
            administrator for access.
          </p>
        ) : (
          <>
            <FiltersBar
              accounts={accounts}
              value={filters}
              onChange={setFilters}
            />
            {loadError && (
              <div className="notice notice-error" role="alert">
                {loadError}{' '}
                <button
                  type="button"
                  className="link-button"
                  onClick={() => setRefreshToken((n) => n + 1)}
                >
                  Retry
                </button>
              </div>
            )}
            <ChargesGrid
              filters={filters}
              showMerchant={isAdmin || accounts.length > 1}
              refreshToken={refreshToken}
              onSelect={(charge) => setPanel({ kind: 'details', charge })}
              onLoaded={onLoaded}
              onError={setLoadError}
            />
          </>
        )}
      </main>

      <Dialog
        title="New charge"
        open={panel?.kind === 'create'}
        onClose={() => setPanel(null)}
      >
        <CreateChargeDialog
          accounts={accounts}
          onCancel={() => setPanel(null)}
          onCreated={(charge) =>
            afterChange(
              charge,
              `${charge.status === 'captured' ? 'Charged' : 'Authorized'} ${formatMoney(charge.amount, charge.currency)}.`,
            )
          }
        />
      </Dialog>

      {panel && panel.kind !== 'create' && (
        <Dialog
          title={
            panel.kind === 'details' ? 'Charge' : DIALOG_TITLES[panel.kind]
          }
          open
          onClose={() =>
            setPanel(
              panel.kind === 'details'
                ? null
                : { kind: 'details', charge: panel.charge },
            )
          }
        >
          {panel.kind === 'details' && (
            <ChargeDetails
              charge={panel.charge}
              onAction={(action) =>
                setPanel({ kind: action, charge: panel.charge })
              }
            />
          )}
          {panel.kind === 'refund' && (
            <RefundForm
              charge={panel.charge}
              onCancel={() =>
                setPanel({ kind: 'details', charge: panel.charge })
              }
              onDone={(charge) =>
                afterChange(
                  charge,
                  `Refund processed. ${formatMoney(charge.amount_refunded, charge.currency)} refunded in total.`,
                )
              }
            />
          )}
          {panel.kind === 'void' && (
            <VoidForm
              charge={panel.charge}
              onCancel={() =>
                setPanel({ kind: 'details', charge: panel.charge })
              }
              onDone={(charge) => afterChange(charge, 'Charge voided.')}
            />
          )}
          {panel.kind === 'capture' && (
            <CaptureForm
              charge={panel.charge}
              onCancel={() =>
                setPanel({ kind: 'details', charge: panel.charge })
              }
              onDone={(charge) =>
                afterChange(
                  charge,
                  `Captured ${formatMoney(charge.amount_captured, charge.currency)}.`,
                )
              }
            />
          )}
        </Dialog>
      )}
    </div>
  );
}
