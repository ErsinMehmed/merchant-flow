<?php

namespace App\Nova;

use App\Containers\AppSection\User\Actions\RemoveUserAvatarAction;
use App\Containers\AppSection\User\Actions\UpdateUserAvatarAction;
use App\Containers\AppSection\User\Models\User as UserModel;
use App\Containers\AppSection\User\Models\UserAvatar;
use App\Nova\Filters\UserTypeFilter;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Laravel\Nova\Fields\Avatar;
use Laravel\Nova\Fields\BelongsToMany;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\MorphToMany;
use Laravel\Nova\Fields\Password;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Panel;
use Laravel\Nova\Support\Fluent;

/**
 * @phpstan-extends Resource<UserModel>
 */
final class User extends Resource
{
    /** @var class-string<UserModel> */
    public static $model = UserModel::class;

    public static $title = 'name';

    /** @var list<string> */
    public static $search = ['id', 'name', 'email'];

    public static $group = 'Access';

    /** @var list<string> */
    public static $with = ['roles'];

    public function subtitle(): string
    {
        return $this->resource->email;
    }

    /** @return array<int, Field|Panel> */
    public function fields(NovaRequest $request): array
    {
        return [
            ID::make()->sortable(),

            self::photo(),

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

    /**
     * The profile photo, stored in the database (UserAvatar) rather than on a
     * disk so the Go API can serve the same photo to the merchant portal.
     * Nova also shows it in the user menu.
     */
    private static function photo(): Avatar
    {
        $url = static fn (mixed $updatedAt, ?string $disk, UserModel $user): ?string => $updatedAt instanceof CarbonInterface
            ? route('nova.user-avatar', ['user' => $user->getKey(), 'v' => $updatedAt->getTimestamp()], absolute: false)
            : null;

        return Avatar::make('Photo', 'avatar_updated_at')
            ->acceptedTypes(implode(',', UserAvatar::CONTENT_TYPES))
            ->rules('nullable', 'image', 'mimes:jpeg,png,webp', 'max:'.UserAvatar::MAX_KILOBYTES)
            ->help('JPEG, PNG or WebP, up to 2 MB. Also shown in the merchant portal.')
            // Runs after the user is saved, so it also works on create.
            ->store(static fn (Request $request, Model|Fluent $user, string $attribute, string $requestAttribute): Closure => static function () use ($request, $user, $requestAttribute): void {
                $file = $request->file($requestAttribute);
                if ($user instanceof UserModel && $file instanceof UploadedFile) {
                    app(UpdateUserAvatarAction::class)->run($user, $file);
                }
            })
            ->thumbnail($url)
            ->preview($url)
            ->delete(static function (NovaRequest $request, UserModel $user): bool {
                app(RemoveUserAvatarAction::class)->run($user);

                return true;
            })
            ->disableDownload();
    }

    /** @return list<Filter> */
    public function filters(NovaRequest $request): array
    {
        return [new UserTypeFilter];
    }
}
