<?php

namespace Tests\Feature\Nova;

use App\Containers\AppSection\Account\Models\Account;
use App\Containers\AppSection\Authorization\Enums\Permission;
use App\Containers\AppSection\Authorization\Enums\Role;
use App\Containers\AppSection\Authorization\Models\Role as RoleModel;
use App\Containers\AppSection\Charge\Models\Charge;
use App\Containers\AppSection\User\Models\User;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class DashboardMetricsTest extends TestCase
{
    #[DataProvider('metrics')]
    public function test_every_dashboard_metric_resolves(string $uriKey): void
    {
        Charge::factory()->count(2)->create();
        Charge::factory()->failed()->disputed()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->getJson("/nova-api/dashboards/cards/main/metrics/{$uriKey}?range=30")
            ->assertOk();
    }

    /** @return iterable<string, array{string}> */
    public static function metrics(): iterable
    {
        foreach ([
            'new-charges', 'charges-per-day', 'charges-by-status',
            'approval-rate', 'capture-rate', 'disputes',
            'charges-by-currency', 'merchants-by-status', 'agents-by-type',
            'net-volume-by-currency', 'top-merchants',
        ] as $uriKey) {
            yield $uriKey => [$uriKey];
        }
    }

    public function test_approval_rate_ignores_charges_outside_the_range(): void
    {
        Charge::factory()->count(3)->create();
        Charge::factory()->failed()->create();
        Charge::factory()->failed()->create(['created_at' => now()->subDays(45)]);

        $this->actingAs(User::factory()->admin()->create())
            ->getJson('/nova-api/dashboards/cards/main/metrics/approval-rate?range=30')
            ->assertOk()
            ->assertJsonPath('value.value', 75)
            ->assertJsonPath('value.previous', 0);
    }

    public function test_capture_rate_leaves_out_declined_charges(): void
    {
        Charge::factory()->count(2)->create();
        Charge::factory()->refunded()->create();
        Charge::factory()->authorized()->create();
        Charge::factory()->failed()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->getJson('/nova-api/dashboards/cards/main/metrics/capture-rate')
            ->assertOk()
            ->assertJsonPath('value.value', 3)
            ->assertJsonPath('value.target', 4);
    }

    public function test_net_volume_keeps_currencies_apart(): void
    {
        Charge::factory()->create(['amount' => 10_000, 'amount_captured' => 10_000]);
        Charge::factory()->partiallyRefunded(2_500)->create(['amount' => 5_000, 'amount_captured' => 5_000]);
        Charge::factory()->create(['amount' => 1_999, 'amount_captured' => 1_999, 'currency' => 'EUR']);
        Charge::factory()->create(['amount' => 9_000, 'amount_captured' => 9_000, 'created_at' => now()->subDays(45)]);

        $rows = $this->actingAs(User::factory()->admin()->create())
            ->getJson('/nova-api/dashboards/cards/main/metrics/net-volume-by-currency')
            ->assertOk()
            ->json('value');

        $this->assertSame(['EUR 19.99', 'USD 125.00'], array_column($rows, 'title'));
        $this->assertSame(['1 captured charges', '2 captured charges'], array_column($rows, 'subtitle'));
    }

    public function test_top_merchants_rank_by_recent_charge_count(): void
    {
        $busy = Account::factory()->create(['business_name' => 'Busy Bakery']);
        $quiet = Account::factory()->create(['business_name' => 'Quiet Cafe']);
        Charge::factory()->count(3)->for($busy)->create();
        Charge::factory()->for($quiet)->create();
        Charge::factory()->count(5)->for($quiet)->create(['created_at' => now()->subDays(45)]);

        $rows = $this->actingAs(User::factory()->admin()->create())
            ->getJson('/nova-api/dashboards/cards/main/metrics/top-merchants')
            ->assertOk()
            ->json('value');

        $this->assertSame(['Busy Bakery', 'Quiet Cafe'], array_column($rows, 'title'));
    }

    public function test_cards_follow_the_role_permissions(): void
    {
        RoleModel::findByName(Role::Admin->value)->revokePermissionTo(Permission::ManageAgents->value);

        $cards = $this->actingAs(User::factory()->admin()->create())
            ->getJson('/nova-api/dashboards/cards/main')
            ->assertOk()
            ->json();

        $uriKeys = array_column($cards, 'uriKey');
        $this->assertContains('merchants-by-status', $uriKeys);
        $this->assertNotContains('agents-by-type', $uriKeys);
    }
}
