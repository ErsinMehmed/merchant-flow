import { useEffect, useRef, useState, type ChangeEvent } from 'react';
import { ApiError, api } from '../api/client';
import type { Account, PasswordChange, User } from '../api/types';
import { ChevronLeftIcon } from '../components/icons';
import { useToast } from '../components/Toasts';
import { UserAvatar } from '../components/UserAvatar';
import { useSubmit } from '../lib/useSubmit';
import { useSession } from '../session/SessionContext';

// Checked here for a quick answer; the API applies the same limits.
const PHOTO_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
const MAX_PHOTO_BYTES = 2 * 1024 * 1024;

export function photoProblem(file: File): string | null {
  if (!PHOTO_TYPES.includes(file.type)) {
    return 'Choose a JPEG, PNG or WebP image.';
  }
  if (file.size > MAX_PHOTO_BYTES) {
    return 'The photo must be 2 MB or smaller.';
  }
  return null;
}

export function ProfilePage({
  user,
  onBack,
}: {
  user: User;
  onBack: () => void;
}) {
  return (
    <>
      <div className="page-head">
        <div>
          <button type="button" className="back-link" onClick={onBack}>
            <ChevronLeftIcon />
            Charges
          </button>
          <h1>Profile</h1>
          <p className="muted">Your account, profile photo and password.</p>
        </div>
      </div>
      <div className="profile-grid">
        <AccountCard user={user} />
        <PasswordCard user={user} />
      </div>
    </>
  );
}

function AccountCard({ user }: { user: User }) {
  const { updateUser } = useSession();
  const notify = useToast();
  const fileRef = useRef<HTMLInputElement>(null);
  const [pending, setPending] = useState(false);
  const [photoError, setPhotoError] = useState<string | null>(null);
  const [accounts, setAccounts] = useState<Account[] | null>(null);
  const isAdmin = user.roles.includes('admin');

  useEffect(() => {
    if (isAdmin) return;
    api
      .accounts()
      .then(setAccounts)
      .catch(() => setAccounts([]));
  }, [isAdmin]);

  async function changePhoto(action: () => Promise<User>, message: string) {
    setPending(true);
    setPhotoError(null);
    try {
      updateUser(await action());
      notify('success', message);
    } catch (err) {
      const photo = err instanceof ApiError ? err.fields.photo : undefined;
      setPhotoError(
        photo
          ? `The photo ${photo}.`
          : err instanceof ApiError
            ? err.message
            : 'Something went wrong. Try again.',
      );
    } finally {
      setPending(false);
    }
  }

  function onFileChosen(event: ChangeEvent<HTMLInputElement>) {
    const file = event.target.files?.[0];
    // Lets the same file be chosen again after an error.
    event.target.value = '';
    if (!file) return;

    const problem = photoProblem(file);
    if (problem) {
      setPhotoError(problem);
      return;
    }
    void changePhoto(() => api.uploadAvatar(file), 'Profile photo updated.');
  }

  return (
    <section className="card" aria-labelledby="account-heading">
      <h2 id="account-heading">Account</h2>

      <div className="profile-photo">
        <UserAvatar user={user} size="large" />
        <div>
          <div className="profile-photo-actions">
            <button
              type="button"
              className="button"
              disabled={pending}
              onClick={() => fileRef.current?.click()}
            >
              {user.avatar_url ? 'Change photo' : 'Upload photo'}
            </button>
            {user.avatar_url && (
              <button
                type="button"
                className="button button-quiet"
                disabled={pending}
                onClick={() =>
                  void changePhoto(
                    () => api.removeAvatar(),
                    'Profile photo removed.',
                  )
                }
              >
                Remove
              </button>
            )}
          </div>
          <small className="muted">JPEG, PNG or WebP, up to 2 MB.</small>
          <input
            ref={fileRef}
            type="file"
            accept={PHOTO_TYPES.join(',')}
            aria-label="Profile photo"
            hidden
            onChange={onFileChosen}
          />
        </div>
      </div>
      {photoError && (
        <p className="form-error" role="alert">
          {photoError}
        </p>
      )}

      <dl className="details">
        <dt>Name</dt>
        <dd>{user.name}</dd>
        <dt>Email</dt>
        <dd>{user.email}</dd>
        <dt>Role</dt>
        <dd className="capitalize">{user.roles.join(', ') || 'No role'}</dd>
        <dt>Merchant accounts</dt>
        <dd>
          {isAdmin
            ? 'All merchants'
            : accounts === null
              ? '…'
              : accounts.map((a) => a.business_name).join(', ') || 'None'}
        </dd>
      </dl>
      <p className="muted small">
        Your name, email and access are managed by an administrator.
      </p>
    </section>
  );
}

const NO_CHANGE: PasswordChange = {
  current_password: '',
  password: '',
  password_confirmation: '',
};

function PasswordCard({ user }: { user: User }) {
  const notify = useToast();
  const [form, setForm] = useState(NO_CHANGE);
  const { pending, error, fieldErrors, submit } = useSubmit(
    () => api.changePassword(form),
    () => {
      setForm(NO_CHANGE);
      notify(
        'success',
        'Password changed. Your other sessions were signed out.',
      );
    },
  );

  function field(name: keyof PasswordChange) {
    return {
      type: 'password',
      value: form[name],
      onChange: (event: ChangeEvent<HTMLInputElement>) =>
        setForm({ ...form, [name]: event.target.value }),
      'aria-invalid': !!fieldErrors[name],
    };
  }

  const complete =
    form.current_password !== '' &&
    form.password !== '' &&
    form.password_confirmation !== '';

  return (
    <section className="card" aria-labelledby="password-heading">
      <h2 id="password-heading">Change password</h2>
      <form
        onSubmit={(event) => {
          event.preventDefault();
          void submit();
        }}
        noValidate
      >
        {/* Tells password managers which account the new password is for. */}
        <input
          type="email"
          autoComplete="username"
          value={user.email}
          readOnly
          hidden
        />
        <label className="field">
          <span>Current password</span>
          <input
            {...field('current_password')}
            autoComplete="current-password"
          />
          {fieldErrors.current_password && (
            <small className="field-error">
              {fieldErrors.current_password}
            </small>
          )}
        </label>
        <label className="field">
          <span>New password</span>
          <input {...field('password')} autoComplete="new-password" />
          {fieldErrors.password ? (
            <small className="field-error">{fieldErrors.password}</small>
          ) : (
            <small className="muted">
              At least 8 characters, with upper and lower case letters, a number
              and a symbol.
            </small>
          )}
        </label>
        <label className="field">
          <span>Confirm new password</span>
          <input
            {...field('password_confirmation')}
            autoComplete="new-password"
          />
          {fieldErrors.password_confirmation && (
            <small className="field-error">
              {fieldErrors.password_confirmation}
            </small>
          )}
        </label>

        {error && Object.keys(fieldErrors).length === 0 && (
          <p className="form-error" role="alert">
            {error}
          </p>
        )}

        <button
          type="submit"
          className="button button-primary"
          disabled={pending || !complete}
        >
          {pending ? 'Changing…' : 'Change password'}
        </button>
      </form>
    </section>
  );
}
