<?php

namespace App\Nova;

use App\Containers\AppSection\Authorization\Models\Permission as PermissionModel;
use Laravel\Nova\Fields\BelongsToMany;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;

final class Permission extends Resource
{
    public static $model = PermissionModel::class;

    public static $title = 'name';

    public static $search = ['name'];

    public static $group = 'Access';

    public function fields(NovaRequest $request): array
    {
        return [
            ID::make(),
            Text::make('Name')->sortable(),
            Text::make('Description', fn (): ?string => $this->resource->description()),
            BelongsToMany::make('Roles', 'roles', Role::class),
        ];
    }
}
