<?php

namespace App\Nova;

use App\Containers\AppSection\Authorization\Models\Role as RoleModel;
use Laravel\Nova\Fields\BelongsToMany;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;

/**
 * Spatie roles. Attaching or detaching a permission here takes effect in the
 * Go API on the next request.
 */
final class Role extends Resource
{
    public static $model = RoleModel::class;

    public static $title = 'name';

    public static $search = ['name'];

    public static $group = 'Access';

    public function fields(NovaRequest $request): array
    {
        return [
            ID::make(),
            Text::make('Name')->sortable(),
            Text::make('Guard', 'guard_name')->onlyOnDetail(),
            BelongsToMany::make('Permissions', 'permissions', Permission::class),
        ];
    }
}
