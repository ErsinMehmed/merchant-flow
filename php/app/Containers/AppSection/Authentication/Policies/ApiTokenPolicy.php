<?php

namespace App\Containers\AppSection\Authentication\Policies;

use App\Containers\AppSection\Authentication\Models\ApiToken;
use App\Containers\AppSection\Authorization\Enums\Permission;
use App\Containers\AppSection\User\Models\User;
use App\Ship\Parents\Policies\Policy as ParentPolicy;

/**
 * Portal sessions can be listed and revoked, never created or edited here.
 */
final class ApiTokenPolicy extends ParentPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission(Permission::ManageUsers);
    }

    public function view(User $actor, ApiToken $token): bool
    {
        return $actor->hasPermission(Permission::ManageUsers);
    }

    public function create(): bool
    {
        return false;
    }

    public function update(): bool
    {
        return false;
    }

    public function delete(User $actor, ApiToken $token): bool
    {
        return $actor->hasPermission(Permission::ManageUsers);
    }

    public function replicate(): bool
    {
        return false;
    }
}
