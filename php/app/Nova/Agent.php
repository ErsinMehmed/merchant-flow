<?php

namespace App\Nova;

use App\Containers\AppSection\Agent\Enums\AgentType;
use App\Containers\AppSection\Agent\Models\Agent as AgentModel;
use App\Containers\AppSection\Agent\Rules\ValidParentAgent;
use App\Nova\Filters\AgentParentFilter;
use App\Nova\Filters\AgentTypeFilter;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\BelongsToMany;
use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;

final class Agent extends Resource
{
    public static $model = AgentModel::class;

    public static $title = 'company_dba';

    public static $search = ['id', 'email', 'first_name', 'last_name', 'company_dba', 'company_legal_name'];

    public static $group = 'Network';

    public function subtitle(): string
    {
        return $this->resource->first_name.' '.$this->resource->last_name;
    }

    public function fields(NovaRequest $request): array
    {
        return [
            ID::make()->sortable(),
            Text::make('Company DBA')->sortable()->rules('required', 'max:255'),
            Text::make('Company Legal Name')->hideFromIndex()->rules('required', 'max:255'),
            Text::make('First Name')->hideFromIndex()->rules('required', 'max:255'),
            Text::make('Last Name')->hideFromIndex()->rules('required', 'max:255'),
            Text::make('Email')
                ->rules('required', 'email:rfc', 'max:254')
                ->creationRules('unique:agents,email')
                ->updateRules('unique:agents,email,{{resourceId}}'),
            Select::make('Type')
                ->options(AgentType::options())
                ->displayUsingLabels()
                ->rules('required', Rule::enum(AgentType::class)),
            Number::make('Default Split')
                ->step(0.01)
                ->min(0)
                ->max(100)
                ->rules('required', 'numeric', 'between:0,100', 'decimal:0,2')
                ->displayUsing(static fn (mixed $value): string => $value.'%')
                ->help('Residual share in percent.'),
            BelongsTo::make('Parent Agent', 'parentAgent', self::class)
                ->nullable()
                ->rules(new ValidParentAgent($this->resource)),

            HasMany::make('Sub-agents', 'childAgents', self::class),
            BelongsToMany::make('Merchants', 'accounts', Account::class),
            BelongsToMany::make('Users', 'users', User::class),
        ];
    }

    /**
     * Only agent users can be linked to an agent.
     */
    public static function relatableUsers(NovaRequest $request, Builder $query): Builder
    {
        return $query->where('is_agent', true);
    }

    public function filters(NovaRequest $request): array
    {
        return [new AgentTypeFilter, new AgentParentFilter];
    }
}
