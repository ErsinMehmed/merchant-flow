import { useState, type FormEvent } from 'react';
import { ApiError } from '../api/client';
import { useSession } from '../session/SessionContext';

/** Fictional accounts created by the local seeders; see the README. */
const DEMO_ACCOUNTS = [
  {
    email: 'merchant@merchant-flow.test',
    label: 'Merchant',
    detail: 'Northwind Coffee, Fabrikam Fitness',
  },
  {
    email: 'merchant-two@merchant-flow.test',
    label: 'Merchant',
    detail: 'Contoso Books, Tailspin Toys (suspended)',
  },
  {
    email: 'merchant-three@merchant-flow.test',
    label: 'Merchant',
    detail: 'Three merchants, USD/EUR/GBP/CAD',
  },
  {
    email: 'agent@merchant-flow.test',
    label: 'Agent',
    detail: 'Read-only: the four Harbor Payments merchants',
  },
  {
    email: 'admin@merchant-flow.test',
    label: 'Admin',
    detail: 'Every merchant',
  },
];
const DEMO_PASSWORD = 'MerchantFlowDemo!2026';

export function LoginPage({ notice }: { notice?: string }) {
  const { signIn } = useSession();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);

  async function submit(event: FormEvent) {
    event.preventDefault();
    if (submitting) return;
    setSubmitting(true);
    setError(null);
    try {
      await signIn(email, password);
    } catch (err) {
      setError(
        err instanceof ApiError ? err.message : 'Sign-in failed. Try again.',
      );
      setSubmitting(false);
    }
  }

  return (
    <main className="login">
      <section className="login-card" aria-labelledby="login-title">
        <div className="brand brand-large">
          <span className="brand-mark" aria-hidden="true" />
          Merchant Flow
        </div>
        <h1 id="login-title">Sign in to the merchant portal</h1>

        {notice && <p className="notice">{notice}</p>}

        <form onSubmit={submit} noValidate>
          <label className="field">
            <span>Email</span>
            <input
              type="email"
              autoComplete="username"
              required
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              autoFocus
            />
          </label>
          <label className="field">
            <span>Password</span>
            <input
              type="password"
              autoComplete="current-password"
              required
              value={password}
              onChange={(e) => setPassword(e.target.value)}
            />
          </label>

          {error && (
            <p className="form-error" role="alert">
              {error}
            </p>
          )}

          <button
            type="submit"
            className="button button-primary button-block"
            disabled={submitting || !email || !password}
          >
            {submitting ? 'Signing in…' : 'Sign in'}
          </button>
        </form>

        <details className="demo-accounts">
          <summary>Demo accounts</summary>
          <p>
            Fictional local data. All demo users share the password{' '}
            <code>{DEMO_PASSWORD}</code>.
          </p>
          <ul>
            {DEMO_ACCOUNTS.map((account) => (
              <li key={account.email}>
                <button
                  type="button"
                  className="link-button"
                  onClick={() => {
                    setEmail(account.email);
                    setPassword(DEMO_PASSWORD);
                  }}
                >
                  {account.email}
                </button>
                <span className="muted">
                  {account.label} · {account.detail}
                </span>
              </li>
            ))}
          </ul>
        </details>
      </section>
    </main>
  );
}
