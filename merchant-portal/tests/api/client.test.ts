import { ApiError, api, onUnauthorized } from '../../src/api/client';

function respond(status: number, body?: unknown) {
  return vi.spyOn(globalThis, 'fetch').mockResolvedValue(
    new Response(body === undefined ? null : JSON.stringify(body), {
      status,
      headers: { 'Content-Type': 'application/json' },
    }),
  );
}

describe('api client', () => {
  it('turns error responses into ApiError with code and field messages', async () => {
    respond(422, {
      error: {
        code: 'validation_failed',
        message: 'The request is invalid.',
        fields: { amount: 'is required' },
      },
    });

    const error = await api
      .createCharge(
        {
          account_id: 1,
          amount: 0,
          currency: 'USD',
          customer_email: 'a@b.c',
          capture: true,
        },
        'key-1',
      )
      .catch((e: unknown) => e);

    expect(error).toBeInstanceOf(ApiError);
    expect(error).toMatchObject({
      status: 422,
      code: 'validation_failed',
      fields: { amount: 'is required' },
    });
  });

  it('sends the idempotency key and JSON body with the session cookie', async () => {
    const fetchMock = respond(200, { data: { id: 7 } });

    await api.refund(7, 1250, 'key-2');

    const [url, init] = fetchMock.mock.calls[0]!;
    expect(url).toBe('/api/v1/charges/7/refund');
    expect(init).toMatchObject({
      method: 'POST',
      credentials: 'same-origin',
      body: JSON.stringify({ amount: 1250 }),
    });
    expect((init?.headers as Record<string, string>)['Idempotency-Key']).toBe(
      'key-2',
    );
  });

  it('uploads a profile photo as the raw body with its own type', async () => {
    const fetchMock = respond(200, { data: { id: 7, avatar_url: '/x' } });
    const photo = new File(['jpeg'], 'me.jpg', { type: 'image/jpeg' });

    await api.uploadAvatar(photo);

    const [url, init] = fetchMock.mock.calls[0]!;
    expect(url).toBe('/api/v1/me/avatar');
    expect(init).toMatchObject({ method: 'PUT', body: photo });
    expect((init?.headers as Record<string, string>)['Content-Type']).toBe(
      'image/jpeg',
    );
  });

  it('builds the list query from the filters', async () => {
    const fetchMock = respond(200, {
      data: [],
      meta: { page: 2, page_size: 25, total: 0 },
    });

    await api.charges({
      page: 2,
      pageSize: 25,
      sort: '-amount',
      status: 'captured',
      accountId: 3,
      currency: 'EUR',
      disputed: 'true',
      q: 'jane',
      createdFrom: '2026-09-01',
    });

    expect(fetchMock.mock.calls[0]![0]).toBe(
      '/api/v1/charges?page=2&page_size=25&sort=-amount&status=captured&account_id=3&currency=EUR&disputed=true&q=jane&created_from=2026-09-01',
    );
  });

  it('reports an expired session to listeners, but not a failed login', async () => {
    const listener = vi.fn();
    const unsubscribe = onUnauthorized(listener);

    respond(401, { error: { code: 'invalid_credentials', message: 'nope' } });
    await api.login('a@b.c', 'wrong').catch(() => undefined);
    expect(listener).not.toHaveBeenCalled();

    respond(401, { error: { code: 'unauthenticated', message: 'expired' } });
    await api.me().catch(() => undefined);
    expect(listener).toHaveBeenCalledOnce();

    unsubscribe();
  });

  it('maps network failures to a readable error', async () => {
    vi.spyOn(globalThis, 'fetch').mockRejectedValue(
      new TypeError('Failed to fetch'),
    );

    await expect(api.me()).rejects.toMatchObject({ code: 'network_error' });
  });
});
