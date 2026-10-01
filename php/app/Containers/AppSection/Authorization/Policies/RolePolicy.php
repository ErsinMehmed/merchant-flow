<?php

namespace App\Containers\AppSection\Authorization\Policies;

use App\Containers\AppSection\Authorization\Enums\Permission as PermissionName;
use App\Containers\AppSection\Authorization\Enums\Role as RoleName;
use App\Containers\AppSection\Authorization\Models\Permission;
use App\Containers\AppSection\Authorization\Models\Role;
use App\Containers\AppSection\User\Models\User;
use App\Ship\Parents\Policies\Policy as ParentPolicy;

/**
 * The role set is fixed because both applications refer to roles by name.
 * Administrators change what a role may do by attaching or detaching
 * permissions; the Go API sees the change on its next request.
 */
final class RolePolicy extends ParentPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission(PermissionName::ManageUsers);
    }

    public function view(User $actor, Role $role): bool
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

    public function attachAnyPermission(User $actor): bool
    {
        return $actor->hasPermission(PermissionName::ManageUsers);
    }

    public function attachPermission(User $actor): bool
    {
        return $actor->hasPermission(PermissionName::ManageUsers);
    }

    /**
     * Removing user management from the admin role would leave nobody able to
     * undo it through the portal.
     */
    public function detachPermission(User $actor, Role $role, Permission $permission): bool
    {
        $locksAdminsOut = $role->name === RoleName::Admin->value
            && $permission->name === PermissionName::ManageUsers->value;

        return $actor->hasPermission(PermissionName::ManageUsers) && ! $locksAdminsOut;
    }

    public function attachAnyUser(): bool
    {
        return false;
    }
}
