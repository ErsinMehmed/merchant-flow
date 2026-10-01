<?php

namespace App\Nova\Filters;

use App\Containers\AppSection\Account\Models\Account;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;

final class ChargeMerchantFilter extends Filter
{
    public $name = 'Merchant';

    /**
     * @param  int|string  $value
     */
    public function apply(NovaRequest $request, Builder $query, mixed $value): Builder
    {
        return $query->where('account_id', (int) $value);
    }

    /** @return array<string, int> */
    public function options(NovaRequest $request): array
    {
        return Account::query()->orderBy('business_name')->pluck('id', 'business_name')->all();
    }
}
