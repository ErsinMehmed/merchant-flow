<?php

namespace Tests\Feature\Nova;

use App\Containers\AppSection\Charge\Models\Charge;
use App\Containers\AppSection\User\Models\User;
use App\Nova\Filters\ChargeCreatedFromFilter;
use App\Nova\Filters\ChargeCreatedToFilter;
use App\Nova\Filters\ChargeCurrencyFilter;
use App\Nova\Filters\ChargeDisputedFilter;
use App\Nova\Filters\ChargeMerchantFilter;
use App\Nova\Filters\ChargeStatusFilter;
use Carbon\CarbonImmutable;
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
        $this->assertSame([$disputed->id], $this->ids([ChargeMerchantFilter::class => (string) $disputed->account_id]));
        $this->assertSame([], $this->ids([
            ChargeStatusFilter::class => 'authorized',
            ChargeCurrencyFilter::class => 'GBP',
        ]));
    }

    public function test_date_filters_include_both_days(): void
    {
        $before = $this->chargeOn('2026-09-09 23:59:59');
        $first = $this->chargeOn('2026-09-10 00:00:00');
        $last = $this->chargeOn('2026-09-12 23:59:59');
        $after = $this->chargeOn('2026-09-13 00:00:00');

        $this->assertSame([$first, $last], $this->ids([
            ChargeCreatedFromFilter::class => '2026-09-10',
            ChargeCreatedToFilter::class => '2026-09-12',
        ]));
        $this->assertSame([$before, $first, $last, $after], $this->ids([ChargeCreatedFromFilter::class => 'not-a-date']));
    }

    /**
     * The inline filter row gets every filter with its key, so it writes the
     * same URL parameter as Nova's dropdown.
     */
    public function test_charges_index_has_the_inline_filter_card(): void
    {
        $charge = Charge::factory()->create();

        $cards = $this->actingAs(User::factory()->admin()->create())
            ->getJson('/nova-api/charges/cards')
            ->assertOk()
            ->json();

        $this->assertCount(1, $cards);
        $this->assertSame('inline-filters', $cards[0]['component']);
        $this->assertSame('charges', $cards[0]['resource']);
        $this->assertSame([
            ChargeStatusFilter::class,
            ChargeMerchantFilter::class,
            ChargeCurrencyFilter::class,
            ChargeDisputedFilter::class,
            ChargeCreatedFromFilter::class,
            ChargeCreatedToFilter::class,
        ], array_column($cards[0]['filters'], 'key'));
        $this->assertContains(['label' => 'Authorized', 'value' => 'authorized'], $cards[0]['filters'][0]['options']);
        $this->assertContains([
            'label' => "{$charge->account->business_name} (MID {$charge->account->mid})",
            'value' => (string) $charge->account_id,
        ], $cards[0]['filters'][1]['options']);
        $this->assertSame('date', $cards[0]['filters'][4]['type']);
    }

    private function chargeOn(string $createdAt): int
    {
        return Charge::factory()->create(['created_at' => CarbonImmutable::parse($createdAt, 'UTC')])->id;
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

        $response = $this->actingAs(User::factory()->admin()->create())
            ->getJson('/nova-api/charges?filters='.urlencode($encoded))
            ->assertOk();

        return $response->collect('resources')->pluck('id.value')->sort()->values()->all();
    }
}
