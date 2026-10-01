<?php

namespace App\Nova\Filters;

use App\Containers\AppSection\Charge\Enums\Currency;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;

final class ChargeCurrencyFilter extends Filter
{
    public $name = 'Currency';

    /**
     * @param  string  $value
     */
    public function apply(NovaRequest $request, Builder $query, mixed $value): Builder
    {
        $currency = Currency::tryFrom($value);

        return $currency === null ? $query : $query->where('currency', $currency->value);
    }

    /** @return array<string, string> */
    public function options(NovaRequest $request): array
    {
        return Currency::options();
    }
}
