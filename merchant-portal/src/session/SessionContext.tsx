import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
  type ReactNode,
} from 'react';
import { ApiError, api, onUnauthorized } from '../api/client';
import type { User } from '../api/types';

type SessionState =
  | { status: 'loading' }
  | { status: 'signed-out'; notice?: string }
  | { status: 'signed-in'; user: User };

interface SessionValue {
  state: SessionState;
  signIn(email: string, password: string): Promise<void>;
  signOut(): Promise<void>;
  can(permission: string): boolean;
}

const SessionContext = createContext<SessionValue | null>(null);

export function SessionProvider({ children }: { children: ReactNode }) {
  const [state, setState] = useState<SessionState>({ status: 'loading' });

  useEffect(() => {
    let active = true;
    api
      .me()
      .then((user) => active && setState({ status: 'signed-in', user }))
      .catch((error: unknown) => {
        if (!active) return;
        const notice =
          error instanceof ApiError && error.status !== 401
            ? error.message
            : undefined;
        setState({ status: 'signed-out', notice });
      });
    return () => {
      active = false;
    };
  }, []);

  // A revoked or expired session can surface on any request.
  useEffect(
    () =>
      onUnauthorized(() =>
        setState((current) =>
          current.status === 'signed-in'
            ? {
                status: 'signed-out',
                notice: 'Your session has ended. Please sign in again.',
              }
            : current,
        ),
      ),
    [],
  );

  const signIn = useCallback(async (email: string, password: string) => {
    const user = await api.login(email, password);
    setState({ status: 'signed-in', user });
  }, []);

  const signOut = useCallback(async () => {
    try {
      await api.logout();
    } finally {
      setState({ status: 'signed-out' });
    }
  }, []);

  const value = useMemo<SessionValue>(
    () => ({
      state,
      signIn,
      signOut,
      can: (permission) =>
        state.status === 'signed-in' &&
        state.user.permissions.includes(permission),
    }),
    [state, signIn, signOut],
  );

  return (
    <SessionContext.Provider value={value}>{children}</SessionContext.Provider>
  );
}

export function useSession(): SessionValue {
  const value = useContext(SessionContext);
  if (!value) throw new Error('useSession must be used inside SessionProvider');
  return value;
}
