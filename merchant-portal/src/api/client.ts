import type {
  Account,
  Charge,
  ChargeQuery,
  NewCharge,
  PageMeta,
  User,
  VoidReason,
} from './types';

/** An error response from the Go API: {"error": {"code", "message", "fields"}}. */
export class ApiError extends Error {
  readonly status: number;
  readonly code: string;
  readonly fields: Record<string, string>;

  constructor(
    status: number,
    code: string,
    message: string,
    fields: Record<string, string> = {},
  ) {
    super(message);
    this.name = 'ApiError';
    this.status = status;
    this.code = code;
    this.fields = fields;
  }
}

type Listener = () => void;
const unauthorizedListeners = new Set<Listener>();

/** Called when any request finds the session gone, e.g. expired or revoked. */
export function onUnauthorized(listener: Listener): () => void {
  unauthorizedListeners.add(listener);
  return () => unauthorizedListeners.delete(listener);
}

interface RequestOptions {
  method?: 'GET' | 'POST';
  body?: unknown;
  idempotencyKey?: string;
  signal?: AbortSignal;
}

/**
 * The session lives in an HttpOnly cookie set by the API, so the browser
 * attaches it and scripts never see the token.
 */
async function request<T>(
  path: string,
  { method = 'GET', body, idempotencyKey, signal }: RequestOptions = {},
): Promise<T> {
  const headers: Record<string, string> = { Accept: 'application/json' };
  if (body !== undefined) headers['Content-Type'] = 'application/json';
  if (idempotencyKey) headers['Idempotency-Key'] = idempotencyKey;

  let response: Response;
  try {
    response = await fetch(`/api/v1${path}`, {
      method,
      headers,
      body: body === undefined ? undefined : JSON.stringify(body),
      credentials: 'same-origin',
      signal,
    });
  } catch (error) {
    if (error instanceof DOMException && error.name === 'AbortError')
      throw error;
    throw new ApiError(
      0,
      'network_error',
      'Could not reach the server. Check your connection and try again.',
    );
  }

  if (response.status === 204) return undefined as T;

  const payload: unknown = await response.json().catch(() => null);
  if (!response.ok) {
    const error = (
      payload as {
        error?: {
          code?: string;
          message?: string;
          fields?: Record<string, string>;
        };
      } | null
    )?.error;
    const apiError = new ApiError(
      response.status,
      error?.code ?? 'unexpected_error',
      error?.message ?? `The server responded with ${response.status}.`,
      error?.fields,
    );
    if (response.status === 401 && path !== '/auth/login') {
      unauthorizedListeners.forEach((listener) => listener());
    }
    throw apiError;
  }
  return payload as T;
}

interface Data<T> {
  data: T;
}

export const api = {
  async login(email: string, password: string): Promise<User> {
    const res = await request<Data<{ user: User }>>('/auth/login', {
      method: 'POST',
      body: { email, password },
    });
    return res.data.user;
  },

  logout(): Promise<void> {
    return request('/auth/logout', { method: 'POST' });
  },

  async me(): Promise<User> {
    return (await request<Data<User>>('/me')).data;
  },

  async accounts(): Promise<Account[]> {
    return (await request<Data<Account[]>>('/accounts')).data;
  },

  async charges(
    query: ChargeQuery,
    signal?: AbortSignal,
  ): Promise<{ charges: Charge[]; meta: PageMeta }> {
    const params = new URLSearchParams({
      page: String(query.page),
      page_size: String(query.pageSize),
    });
    if (query.sort) params.set('sort', query.sort);
    if (query.status) params.set('status', query.status);
    if (query.accountId) params.set('account_id', String(query.accountId));
    if (query.currency) params.set('currency', query.currency);
    if (query.disputed) params.set('disputed', query.disputed);
    if (query.q) params.set('q', query.q);
    if (query.createdFrom) params.set('created_from', query.createdFrom);
    if (query.createdTo) params.set('created_to', query.createdTo);

    const res = await request<Data<Charge[]> & { meta: PageMeta }>(
      `/charges?${params}`,
      { signal },
    );
    return { charges: res.data, meta: res.meta };
  },

  async createCharge(
    charge: NewCharge,
    idempotencyKey: string,
  ): Promise<Charge> {
    return (
      await request<Data<Charge>>('/charges', {
        method: 'POST',
        body: charge,
        idempotencyKey,
      })
    ).data;
  },

  async capture(id: number, idempotencyKey: string): Promise<Charge> {
    return (
      await request<Data<Charge>>(`/charges/${id}/capture`, {
        method: 'POST',
        idempotencyKey,
      })
    ).data;
  },

  /** Without an amount the API refunds everything that is left. */
  async refund(
    id: number,
    amount: number | null,
    idempotencyKey: string,
  ): Promise<Charge> {
    const body = amount === null ? {} : { amount };
    return (
      await request<Data<Charge>>(`/charges/${id}/refund`, {
        method: 'POST',
        body,
        idempotencyKey,
      })
    ).data;
  },

  async void(
    id: number,
    reason: VoidReason,
    idempotencyKey: string,
  ): Promise<Charge> {
    return (
      await request<Data<Charge>>(`/charges/${id}/void`, {
        method: 'POST',
        body: { reason },
        idempotencyKey,
      })
    ).data;
  },
};

/** One key per user intent: a retried submit reuses it, so it cannot apply twice. */
export function newIdempotencyKey(): string {
  // randomUUID needs a secure context; plain HTTP on a LAN address is not one.
  if (typeof crypto.randomUUID === 'function') return crypto.randomUUID();
  const bytes = crypto.getRandomValues(new Uint8Array(16));
  return Array.from(bytes, (b) => b.toString(16).padStart(2, '0')).join('');
}
