<?php

namespace Tests\Feature\Nova;

use App\Containers\AppSection\Account\Models\Account;
use App\Containers\AppSection\Authorization\Enums\Permission;
use App\Containers\AppSection\Authorization\Enums\Role;
use App\Containers\AppSection\Authorization\Models\Role as RoleModel;
use App\Containers\AppSection\Charge\Models\Charge;
use App\Containers\AppSection\User\Models\User;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

final class ChargeResourceTest extends TestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    public function test_creating_a_charge_applies_the_charge_rules(): void
    {
        $account = Account::factory()->create();

        $this->actingAs($this->admin)
            ->postJson('/nova-api/charges', [
                'account' => $account->id,
                'amount' => '125.40',
                'currency' => 'EUR',
                'customer_email' => 'Customer@Example.com',
                'capture_immediately' => false,
            ])
            ->assertCreated();

        $charge = Charge::query()->sole();
        $this->assertSame(12_540, $charge->amount);
        $this->assertSame(0, $charge->amount_captured);
        $this->assertSame('authorized', $charge->status->value);
        $this->assertSame('EUR', $charge->currency);
        $this->assertSame('customer@example.com', $charge->customer_email);
        $this->assertSame($this->admin->id, $charge->created_by);
        $this->assertStringStartsWith('txn_', $charge->external_transaction_id);
    }

    public function test_charges_cannot_be_created_for_an_inactive_account(): void
    {
        $account = Account::factory()->suspended()->create();

        $this->actingAs($this->admin)
            ->postJson('/nova-api/charges', $this->payload($account))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('account');

        $this->assertSame(0, Charge::query()->count());
    }

    public function test_amounts_must_be_positive_with_at_most_two_decimals(): void
    {
        $account = Account::factory()->create();

        foreach (['0', '0.00', '-1', '1.005', 'abc', '1000000'] as $amount) {
            $this->actingAs($this->admin)
                ->postJson('/nova-api/charges', [...$this->payload($account), 'amount' => $amount])
                ->assertUnprocessable();
        }

        $this->assertSame(0, Charge::query()->count());
    }

    public function test_charges_are_never_edited_or_deleted_directly(): void
    {
        $charge = Charge::factory()->create();

        $this->actingAs($this->admin)
            ->putJson("/nova-api/charges/{$charge->id}", ['customer_email' => 'other@example.com'])
            ->assertForbidden();
        $this->actingAs($this->admin)
            ->deleteJson('/nova-api/charges', ['resources' => [$charge->id]])
            ->assertOk();

        $this->assertDatabaseHas('charges', ['id' => $charge->id, 'customer_email' => $charge->customer_email]);
    }

    public function test_refund_action_applies_partial_refunds(): void
    {
        $charge = Charge::factory()->create(['amount' => 10_000, 'amount_captured' => 10_000]);

        $this->runAction('refund-charge', $charge, ['amount' => '30.00'])->assertOk();
        $this->assertDatabaseHas('charges', ['id' => $charge->id, 'amount_refunded' => 3_000, 'status' => 'captured']);

        $this->runAction('refund-charge', $charge, ['amount' => ''])->assertOk();
        $this->assertDatabaseHas('charges', ['id' => $charge->id, 'amount_refunded' => 10_000, 'status' => 'refunded']);
    }

    public function test_rejected_operations_are_reported_without_changing_the_charge(): void
    {
        $charge = Charge::factory()->create(['amount' => 1_000, 'amount_captured' => 1_000]);

        $this->runAction('refund-charge', $charge, ['amount' => '10.01'])
            ->assertOk()
            ->assertJsonPath('danger', 'The refund exceeds the amount left to refund.');
        $this->runAction('void-charge', $charge, ['reason' => 'duplicate'])
            ->assertOk()
            ->assertJsonPath('danger', 'A captured charge cannot be voided; only authorized charges can.');

        $this->assertDatabaseHas('charges', ['id' => $charge->id, 'amount_refunded' => 0, 'status' => 'captured']);
    }

    public function test_capture_and_void_actions(): void
    {
        $toCapture = Charge::factory()->authorized()->create();
        $toVoid = Charge::factory()->authorized()->create();

        $this->runAction('capture-charge', $toCapture)->assertOk();
        $this->runAction('void-charge', $toVoid, ['reason' => 'abandoned'])->assertOk();

        $this->assertDatabaseHas('charges', ['id' => $toCapture->id, 'status' => 'captured']);
        $this->assertDatabaseHas('charges', ['id' => $toVoid->id, 'status' => 'voided', 'void_reason' => 'abandoned']);
    }

    public function test_actions_require_their_permission(): void
    {
        RoleModel::findByName(Role::Admin->value)->revokePermissionTo(Permission::RefundCharges->value);
        $this->admin = User::factory()->admin()->create();
        $charge = Charge::factory()->create();

        $this->runAction('refund-charge', $charge, ['amount' => '1.00'])->assertForbidden();

        $this->assertDatabaseHas('charges', ['id' => $charge->id, 'amount_refunded' => 0]);
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return TestResponse<Response>
     */
    private function runAction(string $action, Charge $charge, array $fields = []): TestResponse
    {
        return $this->actingAs($this->admin)->postJson(
            "/nova-api/charges/action?action={$action}",
            ['resources' => (string) $charge->id, ...$fields],
        );
    }

    /** @return array<string, mixed> */
    private function payload(Account $account): array
    {
        return [
            'account' => $account->id,
            'amount' => '10.00',
            'currency' => 'USD',
            'customer_email' => 'customer@example.com',
            'capture_immediately' => true,
        ];
    }
}
