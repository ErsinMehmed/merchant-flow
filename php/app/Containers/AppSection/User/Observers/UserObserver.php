<?php

namespace App\Containers\AppSection\User\Observers;

use App\Containers\AppSection\User\Models\User;

/** Keep Spatie roles in sync with the admin-editable user type flags. */
final class UserObserver
{
    public function saved(User $user): void
    {
        if ($user->wasRecentlyCreated || $user->wasChanged(['is_admin', 'is_agent', 'is_merchant'])) {
            $user->syncRoles($user->rolesFromFlags());
        }
    }
}
