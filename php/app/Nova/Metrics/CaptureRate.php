<?php

namespace App\Nova\Metrics;

use App\Containers\AppSection\Charge\Enums\ChargeStatus;
use App\Containers\AppSection\Charge\Models\Charge;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\Progress;
use Laravel\Nova\Metrics\ProgressResult;

/**
 * Approved authorizations that went on to be captured. A refunded charge was
 * captured first, so it counts as well.
 */
final class CaptureRate extends Progress
{
    public function name(): string
    {
        return 'Authorizations Captured';
    }

    // Nova derives the key from the name; keep the URL stable.
    public function uriKey(): string
    {
        return 'capture-rate';
    }

    public function calculate(NovaRequest $request): ProgressResult
    {
        return $this->count(
            $request,
            Charge::query()->where('status', '!=', ChargeStatus::Failed),
            static fn (Builder $query): Builder => $query->whereIn('status', [ChargeStatus::Captured, ChargeStatus::Refunded]),
        );
    }
}
