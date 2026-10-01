<?php

namespace App\Nova;

use App\Containers\AppSection\Account\Enums\AccountStatus;
use App\Containers\AppSection\Account\Models\Account as AccountModel;
use App\Nova\Filters\AccountAgentFilter;
use App\Nova\Filters\AccountProcessingFilter;
use App\Nova\Filters\AccountStatusFilter;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Laravel\Nova\Fields\Badge;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\BelongsToMany;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;

/**
 * Merchant accounts.
 */
final class Account extends Resource
{
    public static $model = AccountModel::class;

    public static $title = 'business_name';

    public static $search = ['id', 'business_name', 'dba_name', 'mid', 'email'];

    public static $group = 'Network';

    public static function label(): string
    {
        return 'Merchants';
    }

    public static function singularLabel(): string
    {
        return 'Merchant';
    }

    public static function uriKey(): string
    {
        return 'merchants';
    }

    public function subtitle(): string
    {
        return 'MID '.$this->resource->mid;
    }

    public function fields(NovaRequest $request): array
    {
        return [
            ID::make()->sortable(),
            Text::make('Business Name')->sortable()->rules('required', 'max:255'),
            Text::make('DBA Name', 'dba_name')->hideFromIndex()->rules('required', 'max:255'),
            Text::make('Legal Name')->hideFromIndex()->rules('required', 'max:255'),
            Text::make('Email')->hideFromIndex()->rules('required', 'email:rfc', 'max:254'),
            Text::make('MID', 'mid')
                ->sortable()
                ->rules('required', 'regex:/^[0-9A-Za-z-]{6,32}$/')
                ->creationRules('unique:accounts,mid')
                ->updateRules('unique:accounts,mid,{{resourceId}}')
                ->help('Merchant ID assigned by the processor.'),
            Select::make('Status')
                ->options(AccountStatus::options())
                ->default(AccountStatus::Active->value)
                ->rules('required', Rule::enum(AccountStatus::class))
                ->onlyOnForms(),
            Badge::make('Status', fn (): string => $this->resource->status->value)
                ->map([
                    'pending' => 'info',
                    'active' => 'success',
                    'suspended' => 'warning',
                    'closed' => 'danger',
                ]),
            Boolean::make('Processing enabled', 'active_yn')
                ->default(true)
                ->help('New charges need an active status and processing enabled.'),
            BelongsTo::make('Created by', 'creator', User::class)->exceptOnForms(),

            BelongsToMany::make('Agents', 'agents', Agent::class),
            BelongsToMany::make('Users', 'users', User::class),
            HasMany::make('Charges', 'charges', Charge::class),
        ];
    }

    public static function fill(NovaRequest $request, $model): array
    {
        if (! $model->exists) {
            $model->created_by = $request->user()->getKey();
        }

        return parent::fill($request, $model);
    }

    /**
     * Only merchant users can be linked to a merchant account.
     */
    public static function relatableUsers(NovaRequest $request, Builder $query): Builder
    {
        return $query->where('is_merchant', true);
    }

    public function filters(NovaRequest $request): array
    {
        return [new AccountStatusFilter, new AccountProcessingFilter, new AccountAgentFilter];
    }
}
