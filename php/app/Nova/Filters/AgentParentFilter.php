<?php

namespace App\Nova\Filters;

use App\Containers\AppSection\Agent\Models\Agent;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;

/**
 * Top-level agents, or the sub-agents of one parent.
 */
final class AgentParentFilter extends Filter
{
    private const TOP_LEVEL = 'none';

    public $name = 'Parent agent';

    /**
     * @param  int|string  $value
     */
    public function apply(NovaRequest $request, Builder $query, mixed $value): Builder
    {
        return $value === self::TOP_LEVEL
            ? $query->whereNull('parent_agent_id')
            : $query->where('parent_agent_id', (int) $value);
    }

    /** @return array<string, int|string> */
    public function options(NovaRequest $request): array
    {
        $options = ['Top-level agents' => self::TOP_LEVEL];

        foreach (Agent::query()->whereHas('childAgents')->orderBy('company_dba')->orderBy('id')->get(['id', 'company_dba']) as $agent) {
            $options["{$agent->company_dba} (#{$agent->id})"] = $agent->id;
        }

        return $options;
    }
}
