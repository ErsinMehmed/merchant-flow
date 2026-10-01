<?php

namespace App\Containers\AppSection\Authorization\Tests\Functional;

use App\Containers\AppSection\Authorization\Data\Seeders\AuthorizationSeeder_1;
use App\Containers\AppSection\Authorization\Enums\Permission;
use App\Containers\AppSection\Authorization\Enums\Role;
use App\Containers\AppSection\Authorization\Models\Role as RoleModel;
use App\Ship\Parents\Tests\TestCase;
use Illuminate\Support\Facades\DB;

final class AuthorizationSeederTest extends TestCase
{
    public function test_seeds_the_role_permission_matrix_on_the_shared_guard(): void
    {
        $matrix = DB::table('roles')
            ->join('role_has_permissions', 'role_has_permissions.role_id', '=', 'roles.id')
            ->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
            ->where('roles.guard_name', 'web')
            ->where('permissions.guard_name', 'web')
            ->orderBy('permissions.name')
            ->get(['roles.name as role', 'permissions.name as permission'])
            ->groupBy('role')
            ->map(static fn ($rows) => $rows->pluck('permission')->all())
            ->all();

        $this->assertSame([
            'agents.manage', 'charges.capture', 'charges.create', 'charges.refund',
            'charges.view', 'charges.void', 'merchants.manage', 'users.manage',
        ], $matrix['admin']);
        $this->assertSame([
            'charges.capture', 'charges.create', 'charges.refund', 'charges.view', 'charges.void',
        ], $matrix['merchant']);
        $this->assertSame(['charges.view'], $matrix['agent']);
        $this->assertSame(0, DB::table('roles')->where('guard_name', '!=', 'web')->count());
    }

    public function test_reseeding_keeps_permission_changes_made_by_an_administrator(): void
    {
        $merchant = RoleModel::findByName(Role::Merchant->value);
        $merchant->revokePermissionTo(Permission::RefundCharges->value);

        $this->seed(AuthorizationSeeder_1::class);

        $this->assertFalse($merchant->fresh()?->hasPermissionTo(Permission::RefundCharges->value));
        $this->assertSame(count(Permission::cases()), DB::table('permissions')->count());
        $this->assertSame(count(Role::cases()), DB::table('roles')->count());
    }
}
