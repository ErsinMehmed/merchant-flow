<?php

namespace Tests\Feature\Nova;

use App\Containers\AppSection\Account\Models\Account;
use App\Containers\AppSection\Agent\Models\Agent;
use App\Containers\AppSection\Authorization\Enums\Permission;
use App\Containers\AppSection\Authorization\Enums\Role;
use App\Containers\AppSection\Authorization\Models\Permission as PermissionModel;
use App\Containers\AppSection\Authorization\Models\Role as RoleModel;
use App\Containers\AppSection\Charge\Models\Charge;
use App\Containers\AppSection\User\Models\User;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

final class RelationshipRulesTest extends TestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    public function test_only_merchant_users_can_be_linked_to_a_merchant_account(): void
    {
        $account = Account::factory()->create();
        $merchant = User::factory()->merchant()->create();
        $agent = User::factory()->agent()->create();

        $this->attach('merchants', $account->id, 'users', $agent->id)->assertUnprocessable();
        $this->attach('merchants', $account->id, 'users', $merchant->id)->assertOk();

        $this->assertSame([$merchant->id], $account->users()->pluck('users.id')->all());
    }

    public function test_only_agent_users_can_be_linked_to_an_agent(): void
    {
        $agent = Agent::factory()->create();
        $merchant = User::factory()->merchant()->create();
        $agentUser = User::factory()->agent()->create();

        $this->attach('agents', $agent->id, 'users', $merchant->id)->assertUnprocessable();
        $this->attach('agents', $agent->id, 'users', $agentUser->id)->assertOk();

        $this->assertSame([$agentUser->id], $agent->users()->pluck('users.id')->all());
    }

    /**
     * Nova filters the candidates; the policies hold the same rule for any
     * request that gets past the form.
     */
    public function test_policies_refuse_links_to_the_wrong_user_type(): void
    {
        $merchant = User::factory()->merchant()->create();
        $agentUser = User::factory()->agent()->create();
        $account = Account::factory()->create();
        $agent = Agent::factory()->create();

        $this->assertFalse($this->admin->can('attachUser', [$account, $agentUser]));
        $this->assertTrue($this->admin->can('attachUser', [$account, $merchant]));
        $this->assertFalse($this->admin->can('attachUser', [$agent, $merchant]));
        $this->assertFalse($this->admin->can('attachAccount', [$agentUser, $account]));
        $this->assertFalse($this->admin->can('attachAgent', [$merchant, $agent]));
        $this->assertFalse($this->admin->can('attachAnyAccount', $agentUser));
        $this->assertTrue($this->admin->can('attachAnyAccount', $merchant));
        $this->assertFalse($this->admin->can('attachAnyAgent', $merchant));
    }

    public function test_agents_can_serve_merchants(): void
    {
        $agent = Agent::factory()->create();
        $account = Account::factory()->create();

        $this->attach('agents', $agent->id, 'merchants', $account->id, 'accounts')->assertOk();

        $this->assertDatabaseHas('agent_account', ['agent_id' => $agent->id, 'account_id' => $account->id]);
    }

    public function test_agent_hierarchy_cannot_loop(): void
    {
        $parent = Agent::factory()->create();
        $child = Agent::factory()->create(['parent_agent_id' => $parent->id]);

        $this->actingAs($this->admin)
            ->putJson("/nova-api/agents/{$parent->id}", [...$this->agentPayload($parent), 'parentAgent' => $child->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('parentAgent');
    }

    public function test_administrator_cannot_remove_their_own_admin_flag(): void
    {
        $this->actingAs($this->admin)
            ->putJson("/nova-api/users/{$this->admin->id}", [
                'name' => $this->admin->name,
                'email' => $this->admin->email,
                'is_admin' => false,
                'is_agent' => false,
                'is_merchant' => false,
            ])
            ->assertOk();

        $this->assertTrue($this->admin->fresh()?->isAdmin());
    }

    public function test_user_type_edited_in_nova_updates_the_roles(): void
    {
        $user = User::factory()->merchant()->create();

        $this->actingAs($this->admin)
            ->putJson("/nova-api/users/{$user->id}", [
                'name' => $user->name,
                'email' => $user->email,
                'is_admin' => false,
                'is_agent' => true,
                'is_merchant' => false,
            ])
            ->assertOk();

        $this->assertSame(['agent'], $user->fresh()?->getRoleNames()->all());
    }

    public function test_users_with_financial_history_are_not_deleted(): void
    {
        $merchant = User::factory()->merchant()->create();
        Charge::factory()->create(['created_by' => $merchant->id]);
        $unused = User::factory()->merchant()->create();

        $this->actingAs($this->admin)
            ->deleteJson('/nova-api/users', ['resources' => [$merchant->id, $unused->id, $this->admin->id]])
            ->assertOk();

        $this->assertModelExists($merchant);
        $this->assertModelExists($this->admin);
        $this->assertModelMissing($unused);
    }

    public function test_accounts_with_charges_are_not_deleted(): void
    {
        $charge = Charge::factory()->create();

        $this->actingAs($this->admin)
            ->deleteJson('/nova-api/merchants', ['resources' => [$charge->account_id]])
            ->assertOk();

        $this->assertDatabaseHas('accounts', ['id' => $charge->account_id]);
    }

    public function test_role_permissions_can_be_changed_except_admin_user_management(): void
    {
        $merchantRole = RoleModel::findByName(Role::Merchant->value);
        $adminRole = RoleModel::findByName(Role::Admin->value);
        $refund = PermissionModel::findByName(Permission::RefundCharges->value);
        $manageUsers = PermissionModel::findByName(Permission::ManageUsers->value);

        $this->detach('roles', $merchantRole->id, 'permissions', $refund->id)->assertOk();
        $this->detach('roles', $adminRole->id, 'permissions', $manageUsers->id)->assertOk();

        $this->assertFalse($merchantRole->fresh()?->hasPermissionTo($refund));
        $this->assertTrue($adminRole->fresh()?->hasPermissionTo($manageUsers));
    }

    public function test_roles_cannot_be_created_or_renamed(): void
    {
        $role = RoleModel::findByName(Role::Merchant->value);

        $this->actingAs($this->admin)->postJson('/nova-api/roles', ['name' => 'support'])->assertForbidden();
        $this->actingAs($this->admin)->putJson("/nova-api/roles/{$role->id}", ['name' => 'seller'])->assertForbidden();
    }

    /** @return TestResponse<Response> */
    private function attach(string $resource, int $id, string $related, int $relatedId, ?string $relationship = null): TestResponse
    {
        return $this->actingAs($this->admin)->postJson("/nova-api/{$resource}/{$id}/attach/{$related}", [
            $related => $relatedId,
            'viaRelationship' => $relationship ?? $related,
        ]);
    }

    /** @return TestResponse<Response> */
    private function detach(string $resource, int $id, string $related, int $relatedId): TestResponse
    {
        return $this->actingAs($this->admin)->deleteJson("/nova-api/{$related}/detach", [
            'resources' => [$relatedId],
            'viaResource' => $resource,
            'viaResourceId' => $id,
            'viaRelationship' => $related,
        ]);
    }

    /** @return array<string, mixed> */
    private function agentPayload(Agent $agent): array
    {
        return [
            'company_dba' => $agent->company_dba,
            'company_legal_name' => $agent->company_legal_name,
            'first_name' => $agent->first_name,
            'last_name' => $agent->last_name,
            'email' => $agent->email,
            'type' => $agent->type->value,
            'default_split' => '15.00',
        ];
    }
}
