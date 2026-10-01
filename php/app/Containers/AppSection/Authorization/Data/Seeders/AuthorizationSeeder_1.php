<?php

namespace App\Containers\AppSection\Authorization\Data\Seeders;

use App\Containers\AppSection\Authorization\Enums\Permission as PermissionName;
use App\Containers\AppSection\Authorization\Enums\Role as RoleName;
use App\Containers\AppSection\Authorization\Models\Permission;
use App\Containers\AppSection\Authorization\Models\Role;
use App\Ship\Parents\Seeders\Seeder as ParentSeeder;

/**
 * Runs on every start. New permissions and roles are added; permissions of an
 * existing role are left alone so changes made in Nova survive restarts.
 */
final class AuthorizationSeeder_1 extends ParentSeeder
{
    public function run(): void
    {
        foreach (PermissionName::cases() as $permission) {
            Permission::findOrCreate($permission->value);
        }

        foreach (RoleName::cases() as $roleName) {
            $role = Role::findOrCreate($roleName->value);

            if ($role->wasRecentlyCreated) {
                $role->syncPermissions(array_map(
                    static fn (PermissionName $permission): string => $permission->value,
                    $roleName->defaultPermissions(),
                ));
            }
        }
    }
}
