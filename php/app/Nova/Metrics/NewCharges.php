<?php

namespace App\Nova\Metrics;

use App\Containers\AppSection\Charge\Models\Charge;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\Value;
use Laravel\Nova\Metrics\ValueResult;

/**
 * Counts rather than sums: charges are in several currencies.
 */
final class NewCharges extends Value
{
    public function calculate(NovaRequest $request): ValueResult
    {
        return $this->count($request, Charge::class);
    }

    /** @return array<int|string, string> */
    public function ranges(): array
    {
        return [
            7 => '7 days',
            30 => '30 days',
            90 => '90 days',
        ];
    }
}
