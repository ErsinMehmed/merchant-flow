<?php

namespace App\Nova\Filters;

use App\Containers\AppSection\Charge\Enums\ChargeStatus;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;

final class ChargeStatusFilter extends Filter
{
    public $name = 'Status';

    /**
     * @param  string  $value
     */
    public function apply(NovaRequest $request, Builder $query, mixed $value): Builder
    {
        $status = ChargeStatus::tryFrom($value);

        return $status === null ? $query : $query->where('status', $status->value);
    }

    /** @return array<string, string> */
    public function options(NovaRequest $request): array
    {
        $options = [];
        foreach (ChargeStatus::cases() as $status) {
            $options[ucfirst($status->value)] = $status->value;
        }

        return $options;
    }
}
