<?php

namespace App\Nova\Metrics;

use App\Containers\AppSection\Account\Models\Account;
use App\Nova\Account as AccountResource;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Menu\MenuItem;
use Laravel\Nova\Metrics\MetricTableRow;
use Laravel\Nova\Metrics\Table;

/**
 * Ranked by charge count, not volume, for the same reason as NewCharges.
 */
final class TopMerchants extends Table
{
    private const DAYS = 30;

    private const LIMIT = 5;

    public $emptyText = 'No charges in the last 30 days.';

    public function name(): string
    {
        return 'Top Merchants (30 days)';
    }

    // Nova derives the key from the name; keep the URL stable.
    public function uriKey(): string
    {
        return 'top-merchants';
    }

    /** @return list<MetricTableRow> */
    public function calculate(NovaRequest $request): array
    {
        return Account::query()
            ->withCount(['charges' => static fn (Builder $query): Builder => $query->where('created_at', '>=', now()->subDays(self::DAYS))])
            ->having('charges_count', '>', 0)
            ->orderByDesc('charges_count')
            ->orderBy('id')
            ->limit(self::LIMIT)
            ->get()
            ->map(static fn (Account $account): MetricTableRow => MetricTableRow::make()
                ->icon('building-storefront')
                ->iconClass('text-sky-500')
                ->title($account->business_name)
                ->subtitle(number_format($account->charges_count).' charges · MID '.$account->mid)
                ->actions(static fn (): array => [
                    MenuItem::link('View merchant', '/resources/'.AccountResource::uriKey().'/'.$account->getKey()),
                ]))
            ->all();
    }
}
