<?php

namespace App\Containers\AppSection\Account\Policies;

use App\Containers\AppSection\Account\Models\Account;
use App\Containers\AppSection\Agent\Models\Agent;
use App\Containers\AppSection\Authorization\Enums\Permission;
use App\Containers\AppSection\User\Models\User;
use App\Ship\Parents\Policies\Policy as ParentPolicy;

final class AccountPolicy extends ParentPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission(Permission::ManageMerchants);
    }

    public function view(User $actor, Account $account): bool
    {
        return $actor->hasPermission(Permission::ManageMerchants);
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission(Permission::ManageMerchants);
    }

    public function update(User $actor, Account $account): bool
    {
        return $actor->hasPermission(Permission::ManageMerchants);
    }

    /**
     * Charges reference their account with a restricting key; an account with
     * history is suspended or closed instead of deleted.
     */
    public function delete(User $actor, Account $account): bool
    {
        return $actor->hasPermission(Permission::ManageMerchants)
            && ! $account->charges()->exists();
    }

    public function replicate(): bool
    {
        return false;
    }

    public function attachAnyAgent(User $actor): bool
    {
        return $actor->hasPermission(Permission::ManageMerchants);
    }

    public function attachAgent(User $actor, Account $account, Agent $agent): bool
    {
        return $actor->hasPermission(Permission::ManageMerchants);
    }

    public function detachAgent(User $actor, Account $account, Agent $agent): bool
    {
        return $actor->hasPermission(Permission::ManageMerchants);
    }

    public function attachAnyUser(User $actor): bool
    {
        return $actor->hasPermission(Permission::ManageMerchants);
    }

    public function attachUser(User $actor, Account $account, User $user): bool
    {
        return $actor->hasPermission(Permission::ManageMerchants) && $user->is_merchant;
    }

    public function detachUser(User $actor, Account $account, User $user): bool
    {
        return $actor->hasPermission(Permission::ManageMerchants);
    }

    public function addCharge(User $actor, Account $account): bool
    {
        return $actor->hasPermission(Permission::CreateCharges) && $account->acceptsNewCharges();
    }
}
