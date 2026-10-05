<?php

namespace App\Nova\Metrics;

use App\Containers\AppSection\Charge\Models\Charge;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\Value;
use Laravel\Nova\Metrics\ValueResult;

final class Disputes extends Value
{
    public $icon = 'shield-exclamation';

    public function calculate(NovaRequest $request): ValueResult
    {
        return $this->count($request, Charge::query()->where('disputed', true))
            ->allowZeroResult();
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
}
