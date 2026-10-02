<?php

namespace App\Nova;

use App\Containers\AppSection\Authorization\Models\Role as RoleModel;
use Laravel\Nova\Fields\BelongsToMany;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Panel;

/**
 * Spatie roles. Attaching or detaching a permission here takes effect in the
 * Go API on the next request.
 *
 * @phpstan-extends Resource<RoleModel>
 */
final class Role extends Resource
{
    /** @var class-string<RoleModel> */
    public static $model = RoleModel::class;

    public static $title = 'name';

    /** @var list<string> */
    public static $search = ['name'];

    public static $group = 'Access';

    /** @return array<int, Field|Panel> */
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
