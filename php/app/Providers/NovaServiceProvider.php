<?php

namespace App\Providers;

use App\Containers\AppSection\Authentication\Actions\AuthenticateAdminAction;
use App\Containers\AppSection\User\Models\User;
use App\Nova\Dashboards\Main;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Fortify\Features;
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
    }

    protected function fortify(): void
    {
        Nova::fortify()
            ->features([
                Features::updatePasswords(),
            ])
            // Agents and merchants are turned away at the login form, before a
            // session exists, not by a 403 after signing in.
            ->authenticateUsing(static fn (Request $request): ?User => app(AuthenticateAdminAction::class)->run(
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
        Gate::define('viewNova', static fn (?User $user): bool => $user?->isAdmin() ?? false);
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
