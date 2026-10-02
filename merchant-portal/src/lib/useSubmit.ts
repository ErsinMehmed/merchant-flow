import { useCallback, useRef, useState } from 'react';
import { ApiError, newIdempotencyKey } from '../api/client';

interface SubmitState {
  pending: boolean;
  error: string | null;
  fieldErrors: Record<string, string>;
}

/** Reuse a key for an identical retry, including after editing and reverting. */
export function useRequestKey() {
  const keys = useRef(new Map<string, string>());

  return (body: unknown): string => {
    const serialized = JSON.stringify(body);
    let key = keys.current.get(serialized);
    if (!key) {
      key = newIdempotencyKey();
      keys.current.set(serialized, key);
    }
    return key;
  };
}

/**
 * Runs one submission at a time. A second click while a request is in flight
 * is ignored, and errors from the API are split into a message and per-field
 * messages for the form.
 */
export function useSubmit<T>(
  action: () => Promise<T>,
  onSuccess: (result: T) => void,
) {
  const [state, setState] = useState<SubmitState>({
    pending: false,
    error: null,
    fieldErrors: {},
  });
  const inFlight = useRef(false);

  const submit = useCallback(async () => {
    if (inFlight.current) return;
    inFlight.current = true;
    setState({ pending: true, error: null, fieldErrors: {} });
    try {
      const result = await action();
      setState({ pending: false, error: null, fieldErrors: {} });
      onSuccess(result);
    } catch (err) {
      const apiError = err instanceof ApiError ? err : null;
      setState({
        pending: false,
        error: apiError?.message ?? 'Something went wrong. Try again.',
        fieldErrors: apiError?.fields ?? {},
      });
    } finally {
      inFlight.current = false;
    }
  }, [action, onSuccess]);

  return { ...state, submit };
}
