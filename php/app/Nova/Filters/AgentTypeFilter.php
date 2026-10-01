<?php

namespace App\Nova\Filters;

use App\Containers\AppSection\Agent\Enums\AgentType;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;

final class AgentTypeFilter extends Filter
{
    public $name = 'Type';

    /**
     * @param  string  $value
     */
    public function apply(NovaRequest $request, Builder $query, mixed $value): Builder
    {
        $type = AgentType::tryFrom($value);

        return $type === null ? $query : $query->where('type', $type->value);
    }

    /** @return array<string, string> */
    public function options(NovaRequest $request): array
    {
        return array_flip(AgentType::options());
    }
}
