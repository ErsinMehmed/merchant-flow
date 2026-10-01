import type { ChargeStatus } from '../api/types';

const LABELS: Record<ChargeStatus, string> = {
  authorized: 'Authorized',
  captured: 'Captured',
  refunded: 'Refunded',
  voided: 'Voided',
  failed: 'Failed',
};

export function StatusBadge({
  status,
  partiallyRefunded = false,
}: {
  status: ChargeStatus;
  partiallyRefunded?: boolean;
}) {
  return (
    <span className={`badge badge-${status}`}>
      {partiallyRefunded && status === 'captured'
        ? 'Partially refunded'
        : LABELS[status]}
    </span>
  );
}

export const STATUS_OPTIONS = Object.entries(LABELS) as [
  ChargeStatus,
  string,
][];
