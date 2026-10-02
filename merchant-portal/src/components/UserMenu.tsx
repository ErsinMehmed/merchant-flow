import {
  useCallback,
  useEffect,
  useId,
  useRef,
  useState,
  type KeyboardEvent,
} from 'react';
import type { User } from '../api/types';
import { useAnchoredPanel } from '../lib/useAnchoredPanel';
import { useSession } from '../session/SessionContext';
import { ChevronDownIcon } from './icons';
import { UserAvatar } from './UserAvatar';

function menuItems(menu: HTMLElement | null): HTMLElement[] {
  return Array.from(
    menu?.querySelectorAll<HTMLElement>('[role="menuitem"]') ?? [],
  );
}

/**
 * The account menu in the top bar (WAI-ARIA APG menu button): opening it
 * focuses the first item, arrow keys move between items and Escape closes it.
 */
export function UserMenu({
  user,
  onProfile,
}: {
  user: User;
  onProfile: () => void;
}) {
  const { signOut } = useSession();
  const [open, setOpen] = useState(false);
  const triggerRef = useRef<HTMLButtonElement>(null);
  const menuRef = useRef<HTMLDivElement>(null);
  const menuId = useId();

  const close = useCallback(() => setOpen(false), []);
  useAnchoredPanel(open, triggerRef, menuRef, close);

  useEffect(() => {
    if (open) menuItems(menuRef.current)[0]?.focus();
  }, [open]);

  function choose(action: () => void) {
    setOpen(false);
    triggerRef.current?.focus();
    action();
  }

  function onKeyDown(event: KeyboardEvent<HTMLDivElement>) {
    const list = menuItems(menuRef.current);
    const index = list.indexOf(document.activeElement as HTMLElement);
    switch (event.key) {
      case 'Escape':
        event.preventDefault();
        choose(() => {});
        break;
      case 'ArrowDown':
        event.preventDefault();
        list[(index + 1) % list.length]?.focus();
        break;
      case 'ArrowUp':
        event.preventDefault();
        list[(index - 1 + list.length) % list.length]?.focus();
        break;
      case 'Tab':
        setOpen(false);
        break;
    }
  }

  return (
    <>
      <button
        ref={triggerRef}
        type="button"
        className="user-menu-trigger"
        aria-haspopup="menu"
        aria-expanded={open}
        aria-controls={open ? menuId : undefined}
        onClick={() => setOpen((current) => !current)}
      >
        <UserAvatar user={user} />
        <span className="user-name">{user.name}</span>
        <ChevronDownIcon />
      </button>
      {open && (
        <div
          ref={menuRef}
          id={menuId}
          role="menu"
          aria-label="Account"
          popover="manual"
          className="panel user-menu"
          onKeyDown={onKeyDown}
        >
          <div className="user-menu-head">
            <strong>{user.name}</strong>
            <span className="muted">{user.email}</span>
          </div>
          <button
            type="button"
            role="menuitem"
            className="menu-item"
            onClick={() => choose(onProfile)}
          >
            Profile
          </button>
          <button
            type="button"
            role="menuitem"
            className="menu-item"
            onClick={() => choose(() => void signOut())}
          >
            Sign out
          </button>
        </div>
      )}
    </>
  );
}
