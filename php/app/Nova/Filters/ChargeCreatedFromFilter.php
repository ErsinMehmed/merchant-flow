<?php

namespace App\Nova\Filters;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Laravel\Nova\Filters\DateFilter;
use Laravel\Nova\Http\Requests\NovaRequest;

final class ChargeCreatedFromFilter extends DateFilter
{
    use ParsesDay;

    public $name = 'Created from';

    /**
     * @param  string  $value  a Y-m-d date, inclusive
     */
    public function apply(NovaRequest $request, Builder $query, mixed $value): Builder
    {
        $day = $this->day($value);

        return $day === null ? $query : $query->where('created_at', '>=', $day);
    }
}
