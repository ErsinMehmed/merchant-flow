<?php

namespace App\Containers\AppSection\Authorization\Policies;

use App\Containers\AppSection\Authorization\Enums\Permission as PermissionName;
use App\Containers\AppSection\Authorization\Models\Permission;
use App\Containers\AppSection\User\Models\User;
use App\Ship\Parents\Policies\Policy as ParentPolicy;

/**
 * Permission names are defined in code (see Enums\Permission) and checked by
 * both applications, so they are read-only here.
 */
final class PermissionPolicy extends ParentPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission(PermissionName::ManageUsers);
    }

    public function view(User $actor, Permission $permission): bool
    {
        return $actor->hasPermission(PermissionName::ManageUsers);
    }

    public function create(): bool
    {
        return false;
    }

    public function update(): bool
    {
        return false;
    }

    public function delete(): bool
    {
        return false;
    }

    public function replicate(): bool
    {
        return false;
    }

    /**
     * Role permissions are managed from the role page, where the admin
     * lock-out guard in RolePolicy applies.
     */
    public function attachAnyRole(): bool
    {
        return false;
    }

    public function detachRole(): bool
    {
        return false;
    }

    public function attachAnyUser(): bool
    {
        return false;
    }
}
