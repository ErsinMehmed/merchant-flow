<?php

namespace Tests\Feature\Nova;

use App\Containers\AppSection\Authorization\Enums\Permission;
use App\Containers\AppSection\Authorization\Enums\Role;
use App\Containers\AppSection\Authorization\Models\Role as RoleModel;
use App\Containers\AppSection\User\Models\User;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class NovaAccessTest extends TestCase
{
    #[DataProvider('resources')]
    public function test_administrator_can_list_every_resource(string $uriKey): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->getJson("/nova-api/{$uriKey}")
            ->assertOk();
    }

    /** @return iterable<string, array{string}> */
    public static function resources(): iterable
    {
        foreach (['users', 'merchants', 'agents', 'charges', 'roles', 'permissions'] as $uriKey) {
            yield $uriKey => [$uriKey];
        }
    }

    #[DataProvider('nonAdministrators')]
    public function test_signed_in_non_administrators_cannot_use_nova(string $type): void
    {
        $this->actingAs(User::factory()->{$type}()->create());

        $this->getJson('/nova-api/charges')->assertForbidden();
        $this->get('/admin/dashboards/main')->assertForbidden();
    }

    /** @return iterable<string, array{string}> */
    public static function nonAdministrators(): iterable
    {
        yield 'merchant' => ['merchant'];
        yield 'agent' => ['agent'];
    }

    public function test_removing_the_admin_flag_ends_access_for_an_existing_session(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->getJson('/nova-api/charges')->assertOk();

        $admin->update(['is_admin' => false]);

        $this->actingAs($admin->fresh())->getJson('/nova-api/charges')->assertForbidden();
    }

    /**
     * Resources and menu entries follow Spatie permissions, not only the role.
     */
    public function test_resources_follow_the_role_permissions(): void
    {
        RoleModel::findByName(Role::Admin->value)->revokePermissionTo(Permission::ManageAgents->value);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->getJson('/nova-api/agents')->assertForbidden();
        $this->actingAs($admin)->getJson('/nova-api/merchants')->assertOk();
    }
}
