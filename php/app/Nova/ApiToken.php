<?php

namespace App\Nova;

use App\Containers\AppSection\Authentication\Models\ApiToken as ApiTokenModel;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Panel;

/**
 * Merchant portal sessions issued by the Go API. Deleting one signs that
 * session out on its next request.
 *
 * @phpstan-extends Resource<ApiTokenModel>
 */
final class ApiToken extends Resource
{
    /** @var class-string<ApiTokenModel> */
    public static $model = ApiTokenModel::class;

    public static $title = 'id';

    public static $displayInNavigation = false;

    public static $globallySearchable = false;

    public static function label(): string
    {
        return 'Portal sessions';
    }

    public static function singularLabel(): string
    {
        return 'Portal session';
    }

    /** @return array<int, Field|Panel> */
    public function fields(NovaRequest $request): array
    {
        return [
            ID::make(),
            BelongsTo::make('User', 'user', User::class),
            DateTime::make('Signed in', 'created_at'),
            DateTime::make('Last used', 'last_used_at'),
            DateTime::make('Expires', 'expires_at'),
            Text::make('IP address', 'ip_address'),
            Text::make('User agent')->onlyOnDetail(),
        ];
    }
}
