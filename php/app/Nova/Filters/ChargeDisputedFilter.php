<?php

namespace App\Nova\Filters;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;

final class ChargeDisputedFilter extends Filter
{
    public const DISPUTED = 'disputed';

    public const NOT_DISPUTED = 'not-disputed';

    public $name = 'Disputes';

    /**
     * @param  string  $value
     */
    public function apply(NovaRequest $request, Builder $query, mixed $value): Builder
    {
        return match ($value) {
            self::DISPUTED => $query->where('disputed', true),
            self::NOT_DISPUTED => $query->where('disputed', false),
            default => $query,
        };
    }

    /** @return array<string, string> */
    public function options(NovaRequest $request): array
    {
        return [
            'Disputed' => self::DISPUTED,
            'Not disputed' => self::NOT_DISPUTED,
        ];
    }
}
