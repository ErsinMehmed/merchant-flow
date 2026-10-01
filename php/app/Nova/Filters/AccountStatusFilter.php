<?php

namespace App\Nova\Filters;

use App\Containers\AppSection\Account\Enums\AccountStatus;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;

final class AccountStatusFilter extends Filter
{
    public $name = 'Status';

    /**
     * @param  string  $value
     */
    public function apply(NovaRequest $request, Builder $query, mixed $value): Builder
    {
        $status = AccountStatus::tryFrom($value);

        return $status === null ? $query : $query->where('status', $status->value);
    }

    /** @return array<string, string> */
    public function options(NovaRequest $request): array
    {
        return array_flip(AccountStatus::options());
    }
}
