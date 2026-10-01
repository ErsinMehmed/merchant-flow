<?php

namespace App\Nova\Filters;

use App\Containers\AppSection\Agent\Models\Agent;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;

/**
 * Merchants served by an agent, or by no agent at all.
 */
final class AccountAgentFilter extends Filter
{
    private const NONE = 'none';

    public $name = 'Agent';

    /**
     * @param  int|string  $value
     */
    public function apply(NovaRequest $request, Builder $query, mixed $value): Builder
    {
        if ($value === self::NONE) {
            return $query->whereDoesntHave('agents');
        }

        return $query->whereHas('agents', static fn (Builder $agents) => $agents->whereKey((int) $value));
    }

    /** @return array<string, int|string> */
    public function options(NovaRequest $request): array
    {
        return ['No agent' => self::NONE]
            + Agent::query()->orderBy('company_dba')->pluck('id', 'company_dba')->all();
    }
}
