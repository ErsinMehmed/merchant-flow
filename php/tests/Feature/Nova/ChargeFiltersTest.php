<?php

namespace Tests\Feature\Nova;

use App\Containers\AppSection\Charge\Models\Charge;
use App\Containers\AppSection\User\Models\User;
use App\Nova\Filters\ChargeCurrencyFilter;
use App\Nova\Filters\ChargeDisputedFilter;
use App\Nova\Filters\ChargeStatusFilter;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

final class ChargeFiltersTest extends TestCase
{
    public function test_filters_narrow_the_charge_list(): void
    {
        $authorized = Charge::factory()->authorized()->create(['currency' => 'EUR']);
        $disputed = Charge::factory()->disputed()->create(['currency' => 'USD']);
        $refunded = Charge::factory()->refunded()->create(['currency' => 'GBP']);

        $this->assertSame([$authorized->id], $this->ids([ChargeStatusFilter::class => 'authorized']));
        $this->assertSame([$refunded->id], $this->ids([ChargeCurrencyFilter::class => 'GBP']));
        $this->assertSame([$disputed->id], $this->ids([ChargeDisputedFilter::class => ChargeDisputedFilter::DISPUTED]));
        $this->assertSame([], $this->ids([
            ChargeStatusFilter::class => 'authorized',
            ChargeCurrencyFilter::class => 'GBP',
        ]));
    }

    /**
     * Nova renders the menu as JSON in the page, so the labels and filter
     * links can be checked in the HTML.
     */
    public function test_menu_links_open_charges_with_a_filter_applied(): void
    {
        $response = $this->actingAs(User::factory()->admin()->create())->get('/admin/dashboards/main');

        $response->assertOk();
        foreach (['Authorized', 'Captured', 'Refunded', 'Voided', 'Disputed', 'Merchants', 'Agents'] as $label) {
            $response->assertSee($label, false);
        }
        $response->assertSee('charges_filter=', false);
    }

    public function test_menu_hides_resources_the_admin_cannot_view(): void
    {
        $admin = User::factory()->admin()->create();
        $admin->roles->first()?->revokePermissionTo('charges.view');

        $response = $this->actingAs($admin->fresh())->get('/admin/dashboards/main');

        $response->assertOk();
        $response->assertDontSee('charges_filter=', false);
        $response->assertSee('Merchants', false);
    }

    /**
     * @param  array<class-string, string>  $filters
     * @return list<int>
     */
    private function ids(array $filters): array
    {
        $encoded = base64_encode(json_encode(
            array_map(static fn (string $class, string $value): array => [$class => $value], array_keys($filters), $filters),
            JSON_THROW_ON_ERROR,
        ));

        /** @var TestResponse $response */
        $response = $this->actingAs(User::factory()->admin()->create())
            ->getJson('/nova-api/charges?filters='.urlencode($encoded))
            ->assertOk();

        return collect($response->json('resources'))->pluck('id.value')->sort()->values()->all();
    }
}
