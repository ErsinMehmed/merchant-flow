<?php

namespace App\Nova\Metrics;

use App\Containers\AppSection\Charge\Enums\ChargeStatus;
use App\Containers\AppSection\Charge\Models\Charge;
use Carbon\CarbonInterface;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\Value;
use Laravel\Nova\Metrics\ValueResult;
use Laravel\Nova\Nova;

/**
 * Share of charges in the range that the processor did not decline.
 */
final class ApprovalRate extends Value
{
    public $icon = 'check-circle';

    public function calculate(NovaRequest $request): ValueResult
    {
        $timezone = Nova::resolveUserTimezone($request) ?? $request->timezone ?? config('app.timezone');
        $range = $request->range ?? array_key_first($this->ranges());

        return $this->result($this->rateBetween($this->currentRange($range, $timezone)))
            ->previous($this->rateBetween($this->previousRange($range, $timezone)))
            ->suffix('%')
            ->withoutSuffixInflection()
            ->format('0.0');
    }

    /** @return array<int|string, string> */
    public function ranges(): array
    {
        return [
            30 => '30 days',
            90 => '90 days',
            365 => '365 days',
        ];
    }

    /** @param  array<int, CarbonInterface>  $range */
    private function rateBetween(array $range): ?float
    {
        $row = Charge::query()
            ->whereBetween('created_at', $this->formatQueryDateBetween($range))
            ->selectRaw('COUNT(*) AS total, SUM(CASE WHEN status = ? THEN 0 ELSE 1 END) AS approved', [ChargeStatus::Failed->value])
            ->toBase()
            ->first();

        $total = (int) $row->total;

        return $total === 0 ? null : round((int) $row->approved * 100 / $total, 1);
    }
}
