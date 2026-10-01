<?php

namespace App\Containers\AppSection\User\Policies;

use App\Containers\AppSection\Account\Models\Account;
use App\Containers\AppSection\Agent\Models\Agent;
use App\Containers\AppSection\Authorization\Enums\Permission;
use App\Containers\AppSection\User\Models\User;
use App\Ship\Parents\Policies\Policy as ParentPolicy;

/**
 * Nova checks these abilities on every request, including attach/detach of
 * relationships, which Nova would otherwise allow when no method exists.
 */
final class UserPolicy extends ParentPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission(Permission::ManageUsers);
    }

    public function view(User $actor, User $user): bool
    {
        return $actor->hasPermission(Permission::ManageUsers);
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission(Permission::ManageUsers);
    }

    public function update(User $actor, User $user): bool
    {
        return $actor->hasPermission(Permission::ManageUsers);
    }

    /**
     * Users referenced by accounts or charges they created are kept for the
     * audit trail; their access can still be removed by clearing the flags.
     */
    public function delete(User $actor, User $user): bool
    {
        return $actor->hasPermission(Permission::ManageUsers)
            && ! $actor->is($user)
            && ! $user->hasCreatedRecords();
    }

    public function replicate(): bool
    {
        return false;
    }

    /**
     * Only merchant users can be linked to merchant accounts, so the attach
     * button is hidden for everyone else.
     */
    public function attachAnyAccount(User $actor, ?User $user = null): bool
    {
        return $actor->hasPermission(Permission::ManageUsers) && ($user === null || $user->is_merchant);
    }

    public function attachAccount(User $actor, User $user, Account $account): bool
    {
        return $actor->hasPermission(Permission::ManageUsers) && $user->is_merchant;
    }

    public function detachAccount(User $actor, User $user, Account $account): bool
    {
        return $actor->hasPermission(Permission::ManageUsers);
    }

    public function attachAnyAgent(User $actor, ?User $user = null): bool
    {
        return $actor->hasPermission(Permission::ManageUsers) && ($user === null || $user->is_agent);
    }

    public function attachAgent(User $actor, User $user, Agent $agent): bool
    {
        return $actor->hasPermission(Permission::ManageUsers) && $user->is_agent;
    }

    public function detachAgent(User $actor, User $user, Agent $agent): bool
    {
        return $actor->hasPermission(Permission::ManageUsers);
    }

    public function attachAnyPermission(User $actor): bool
    {
        return $actor->hasPermission(Permission::ManageUsers);
    }

    public function attachPermission(User $actor): bool
    {
        return $actor->hasPermission(Permission::ManageUsers);
    }

    public function detachPermission(User $actor): bool
    {
        return $actor->hasPermission(Permission::ManageUsers);
    }

    public function addApiToken(): bool
    {
        return false;
    }
}
