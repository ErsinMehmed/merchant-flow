<?php

namespace App\Nova\Metrics;

use App\Containers\AppSection\Charge\Models\Charge;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\MetricTableRow;
use Laravel\Nova\Metrics\Table;

/**
 * Captured minus refunded over the last 30 days, one row per currency: the
 * currencies are never added together.
 */
final class NetVolumeByCurrency extends Table
{
    private const DAYS = 30;

    public $emptyText = 'No captured charges in the last 30 days.';

    public function name(): string
    {
        return 'Net Volume (30 days)';
    }

    // Nova derives the key from the name; keep the URL stable.
    public function uriKey(): string
    {
        return 'net-volume-by-currency';
    }

    /** @return list<MetricTableRow> */
    public function calculate(NovaRequest $request): array
    {
        return Charge::query()
            ->where('created_at', '>=', now()->subDays(self::DAYS))
            ->where('amount_captured', '>', 0)
            ->groupBy('currency')
            ->orderBy('currency')
            ->selectRaw('currency, COUNT(*) AS charges, SUM(amount_captured - amount_refunded) AS net')
            ->toBase()
            ->get()
            ->map(static fn (object $row): MetricTableRow => MetricTableRow::make()
                ->icon('banknotes')
                ->iconClass('text-sky-500')
                ->title($row->currency.' '.number_format((int) $row->net / 100, 2))
                ->subtitle(number_format((int) $row->charges).' captured charges'))
            ->all();
    }
}
