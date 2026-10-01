<?php

namespace App\Nova\Filters;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;

/**
 * Users by type flag (which also decides their role).
 */
final class UserTypeFilter extends Filter
{
    public $name = 'Type';

    /**
     * @param  string  $value
     */
    public function apply(NovaRequest $request, Builder $query, mixed $value): Builder
    {
        return match ($value) {
            'admin' => $query->where('is_admin', true),
            'agent' => $query->where('is_agent', true),
            'merchant' => $query->where('is_merchant', true),
            'none' => $query->where('is_admin', false)->where('is_agent', false)->where('is_merchant', false),
            default => $query,
        };
    }

    /** @return array<string, string> */
    public function options(NovaRequest $request): array
    {
        return ['Admin' => 'admin', 'Agent' => 'agent', 'Merchant' => 'merchant', 'No type' => 'none'];
    }
}
