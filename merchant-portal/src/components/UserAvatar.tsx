import type { User } from '../api/types';

function initials(name: string): string {
  return name
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((word) => word[0])
    .join('')
    .toUpperCase();
}

/** The profile photo, or the user's initials without one. Decorative: the
 * name is always shown or announced next to it. */
export function UserAvatar({
  user,
  size = 'small',
}: {
  user: Pick<User, 'name' | 'avatar_url'>;
  size?: 'small' | 'large';
}) {
  const className = `avatar avatar-${size}`;
  return user.avatar_url ? (
    <img className={className} src={user.avatar_url} alt="" />
  ) : (
    <span className={className} aria-hidden="true">
      {initials(user.name)}
    </span>
  );
}
