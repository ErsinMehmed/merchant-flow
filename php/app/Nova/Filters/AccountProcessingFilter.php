<?php

namespace App\Nova\Filters;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;

final class AccountProcessingFilter extends Filter
{
    public $name = 'Processing';

    /**
     * @param  string  $value
     */
    public function apply(NovaRequest $request, Builder $query, mixed $value): Builder
    {
        return match ($value) {
            'enabled' => $query->where('active_yn', true),
            'disabled' => $query->where('active_yn', false),
            default => $query,
        };
    }

    /** @return array<string, string> */
    public function options(NovaRequest $request): array
    {
        return ['Enabled' => 'enabled', 'Disabled' => 'disabled'];
    }
}
