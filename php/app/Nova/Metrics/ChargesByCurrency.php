<?php

namespace App\Nova\Metrics;

use App\Containers\AppSection\Charge\Models\Charge;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\Partition;
use Laravel\Nova\Metrics\PartitionResult;

final class ChargesByCurrency extends Partition
{
    public function calculate(NovaRequest $request): PartitionResult
    {
        return $this->count($request, Charge::class, 'currency');
    }
}
