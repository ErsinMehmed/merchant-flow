export type ChargeStatus =
  'authorized' | 'captured' | 'refunded' | 'voided' | 'failed';

export type ChargeAction = 'capture' | 'void' | 'refund';

export type VoidReason =
  'requested_by_customer' | 'duplicate' | 'fraudulent' | 'abandoned';

/** Amounts are integer minor units (cents). */
export interface Charge {
  id: number;
  account_id: number;
  account_name: string;
  account_mid: string;
  amount: number;
  amount_captured: number;
  amount_refunded: number;
  amount_voided: number;
  amount_refundable: number;
  currency: string;
  status: ChargeStatus;
  external_transaction_id: string;
  customer_email: string;
  refunded: boolean;
  disputed: boolean;
  void_reason: VoidReason | null;
  created_by: number;
  created_at: string;
  updated_at: string;
  /** What the signed-in user can do with this charge right now. */
  actions: ChargeAction[];
}

export interface Account {
  id: number;
  business_name: string;
  mid: string;
  status: string;
  accepts_charges: boolean;
}

export interface User {
  id: number;
  name: string;
  email: string;
  roles: string[];
  permissions: string[];
}

export interface PageMeta {
  page: number;
  page_size: number;
  total: number;
}

export interface ChargeQuery {
  page: number;
  pageSize: number;
  sort?: string;
  status?: ChargeStatus | '';
  accountId?: number | '';
  currency?: string;
  disputed?: '' | 'true' | 'false';
  q?: string;
  createdFrom?: string;
  createdTo?: string;
}

export interface NewCharge {
  account_id: number;
  amount: number;
  currency: string;
  customer_email: string;
  capture: boolean;
}
