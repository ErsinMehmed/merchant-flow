<?php

namespace App\Nova;

use App\Containers\AppSection\Authorization\Models\Permission as PermissionModel;
use Laravel\Nova\Fields\BelongsToMany;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Panel;

/**
 * @phpstan-extends Resource<PermissionModel>
 */
final class Permission extends Resource
{
    /** @var class-string<PermissionModel> */
    public static $model = PermissionModel::class;

    public static $title = 'name';

    /** @var list<string> */
    public static $search = ['name'];

    public static $group = 'Access';

    /**
     * Readable label for attach dropdowns and breadcrumbs; the raw name
     * (shared with the Go API) stays visible as the key column.
     */
    public function title(): string
    {
        return $this->resource->label();
    }

    /** @return array<int, Field|Panel> */
    public function fields(NovaRequest $request): array
    {
        return [
            ID::make(),
            Text::make('Permission', fn (): string => $this->resource->label()),
            Text::make('Key', 'name')->sortable(),
            Text::make('Description', fn (): ?string => $this->resource->description()),
            BelongsToMany::make('Roles', 'roles', Role::class),
        ];
    }
}
