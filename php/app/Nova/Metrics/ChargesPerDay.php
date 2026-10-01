<?php

namespace App\Nova\Metrics;

use App\Containers\AppSection\Charge\Models\Charge;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\Trend;
use Laravel\Nova\Metrics\TrendResult;

final class ChargesPerDay extends Trend
{
    public function calculate(NovaRequest $request): TrendResult
    {
        return $this->countByDays($request, Charge::class);
    }

    /** @return array<int|string, string> */
    public function ranges(): array
    {
        return [
            30 => '30 days',
            90 => '90 days',
        ];
    }
}
