import { useCallback, useEffect, useState } from 'react';

export type Route = 'charges' | 'profile';

const PATHS: Record<Route, string> = { charges: '/', profile: '/profile' };

function routeOf(pathname: string): Route {
  return pathname === PATHS.profile ? 'profile' : 'charges';
}

/**
 * The portal's pages, kept in the URL so reload and the back button work.
 * nginx answers every path with index.html, so /profile loads the app too.
 */
export function useRoute(): [Route, (route: Route) => void] {
  const [route, setRoute] = useState(() => routeOf(window.location.pathname));

  useEffect(() => {
    const onPopState = () => setRoute(routeOf(window.location.pathname));
    window.addEventListener('popstate', onPopState);
    return () => window.removeEventListener('popstate', onPopState);
  }, []);

  const navigate = useCallback((next: Route) => {
    if (window.location.pathname !== PATHS[next]) {
      window.history.pushState(null, '', PATHS[next]);
    }
    setRoute(next);
  }, []);

  return [route, navigate];
}
