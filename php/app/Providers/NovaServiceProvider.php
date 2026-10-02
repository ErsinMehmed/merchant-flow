<?php

namespace App\Providers;

use App\Containers\AppSection\Authentication\Actions\AuthenticateAdminAction;
use App\Containers\AppSection\User\Models\User as UserModel;
use App\Nova\Account;
use App\Nova\Agent;
use App\Nova\Charge;
use App\Nova\Dashboards\Main;
use App\Nova\Permission;
use App\Nova\Role;
use App\Nova\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Fortify\Features;
use Laravel\Nova\Menu\MenuItem;
use Laravel\Nova\Menu\MenuSection;
use Laravel\Nova\Nova;
use Laravel\Nova\NovaApplicationServiceProvider;

final class NovaServiceProvider extends NovaApplicationServiceProvider
{
    public function boot(): void
    {
        // Fortify throttles the Nova login form with this limiter, but only if
        // it exists when the routes are registered.
        RateLimiter::for('login', static fn (Request $request): Limit => Limit::perMinute(5)
            ->by(mb_strtolower((string) $request->input('email')).'|'.$request->ip()));

        parent::boot();

        Nova::withBreadcrumbs();
        Nova::footer(static fn (): string => 'MerchantFlow admin · demo data only');
        Nova::mainMenu(static fn (): array => self::menu());

        Nova::serving(static function (): void {
            // Replaces the browser's native logout confirm() with a Nova-styled dialog.
            Nova::script('logout-confirm', resource_path('nova/logout-confirm.js'));
            Nova::style('logout-confirm', resource_path('nova/logout-confirm.css'));
            // Styled list and calendar for every select and date field.
            Nova::script('form-controls', resource_path('nova/form-controls.js'));
            Nova::style('form-controls', resource_path('nova/form-controls.css'));
            // Always-visible filters above index tables (App\Nova\Cards\InlineFilters).
            Nova::script('inline-filters', resource_path('nova/inline-filters.js'));
            Nova::style('inline-filters', resource_path('nova/inline-filters.css'));
        });
    }

    /**
     * Resources grouped by area. Every item is hidden from users who cannot
     * view the resource.
     *
     * @return list<MenuSection>
     */
    private static function menu(): array
    {
        return [
            MenuSection::dashboard(Main::class)->icon('chart-bar'),

            MenuSection::make('Payments', [
                MenuItem::resource(Charge::class),
            ])->icon('credit-card')->collapsable(),

            MenuSection::make('Network', [
                MenuItem::resource(Account::class),
                MenuItem::resource(Agent::class),
            ])->icon('briefcase')->collapsable(),

            MenuSection::make('Access', [
                MenuItem::resource(User::class),
                MenuItem::resource(Role::class),
                MenuItem::resource(Permission::class),
            ])->icon('users')->collapsable(),
        ];
    }

    protected function fortify(): void
    {
        Nova::fortify()
            ->features([
                Features::updatePasswords(),
            ])
            // Agents and merchants are turned away at the login form, before a
            // session exists, not by a 403 after signing in.
            ->authenticateUsing(static fn (Request $request): ?UserModel => app(AuthenticateAdminAction::class)->run(
                (string) $request->input('email'),
                (string) $request->input('password'),
            ))
            ->register();
    }

    protected function routes(): void
    {
        // No mail delivery in this project, so no password reset links.
        Nova::routes()
            ->withAuthenticationRoutes()
            ->withoutPasswordResetRoutes()
            ->withoutEmailVerificationRoutes()
            ->register();
    }

    /**
     * Nova's default lets any signed-in user through when APP_ENV is "local",
     * which is what the demo stack runs as. The gate applies everywhere.
     */
    protected function authorization(): void
    {
        Nova::auth(static fn (Request $request): bool => Gate::check('viewNova', [Nova::user($request)]));
    }

    /**
     * Checked on every Nova request, so removing the admin flag (and with it
     * the role) ends access to an existing session as well.
     */
    protected function gate(): void
    {
        Gate::define('viewNova', static fn (?UserModel $user): bool => $user?->isAdmin() ?? false);
    }

    protected function dashboards(): array
    {
        return [
            new Main,
        ];
    }

    public function tools(): array
    {
        return [];
    }
}
