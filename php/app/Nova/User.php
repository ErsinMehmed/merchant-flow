<?php

namespace App\Nova;

use App\Containers\AppSection\User\Models\User as UserModel;
use App\Nova\Filters\UserTypeFilter;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Laravel\Nova\Fields\BelongsToMany;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\MorphToMany;
use Laravel\Nova\Fields\Password;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Panel;

final class User extends Resource
{
    public static $model = UserModel::class;

    public static $title = 'name';

    public static $search = ['id', 'name', 'email'];

    public static $group = 'Access';

    /** @var list<string> */
    public static $with = ['roles'];

    public function subtitle(): string
    {
        return $this->resource->email;
    }

    public function fields(NovaRequest $request): array
    {
        return [
            ID::make()->sortable(),

            Text::make('Name')
                ->sortable()
                ->rules('required', 'max:255'),

            Text::make('Email')
                ->sortable()
                ->rules('required', 'email:rfc', 'max:254')
                ->creationRules('unique:users,email')
                ->updateRules('unique:users,email,{{resourceId}}'),

            Password::make('Password')
                ->onlyOnForms()
                ->creationRules('required', PasswordRule::defaults())
                ->updateRules('nullable', PasswordRule::defaults()),

            new Panel('User type', [
                Boolean::make('Admin', 'is_admin')
                    // Prevents an administrator from locking themselves out.
                    ->readonly(fn (NovaRequest $request): bool => $request->user()?->is($this->resource) ?? false)
                    ->help('Admin role: Nova access and every merchant in the portal.'),
                Boolean::make('Agent', 'is_agent')
                    ->help('Agent role: charges of the merchants linked to this user\'s agents.'),
                Boolean::make('Merchant', 'is_merchant')
                    ->help('Merchant role: charges of the merchant accounts linked to this user.'),
                Text::make('Roles', fn (): string => $this->resource->roles->pluck('name')->sort()->join(', ') ?: '—')
                    ->exceptOnForms()
                    ->help('Kept in sync with the flags above. Permissions come from the roles and the direct permissions below.'),
            ]),

            BelongsToMany::make('Merchant accounts', 'accounts', Account::class),
            BelongsToMany::make('Agents', 'agents', Agent::class),
            MorphToMany::make('Direct permissions', 'permissions', Permission::class),
            HasMany::make('Portal sessions', 'apiTokens', ApiToken::class),
        ];
    }

    public function filters(NovaRequest $request): array
    {
        return [new UserTypeFilter];
    }
}
