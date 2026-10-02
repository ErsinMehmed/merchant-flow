import type { User } from '../api/types';
import type { Route } from '../lib/useRoute';
import { UserMenu } from './UserMenu';

export function TopBar({
  user,
  onNavigate,
}: {
  user: User;
  onNavigate: (route: Route) => void;
}) {
  return (
    <header className="topbar">
      <a
        href="/"
        className="brand"
        onClick={(event) => {
          event.preventDefault();
          onNavigate('charges');
        }}
      >
        <span className="brand-mark" aria-hidden="true" />
        Merchant Flow
      </a>
      <UserMenu user={user} onProfile={() => onNavigate('profile')} />
    </header>
  );
}
