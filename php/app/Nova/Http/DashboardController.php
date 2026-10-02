<?php

namespace App\Nova\Http;

use Laravel\Nova\Http\Controllers\Pages\DashboardController as NovaDashboardController;
use Laravel\Nova\Http\Requests\DashboardRequest;
use Laravel\Nova\Menu\Breadcrumb;
use Laravel\Nova\Menu\Breadcrumbs;
use Laravel\Nova\Nova;

/**
 * Nova's dashboard page with a single breadcrumb. The admin has one dashboard,
 * so the default "Dashboards > Dashboard" trail only repeats itself.
 *
 * Bound over Nova's controller in App\Providers\NovaServiceProvider::register().
 */
final class DashboardController extends NovaDashboardController
{
    protected function breadcrumbs(DashboardRequest $request, string $name): Breadcrumbs
    {
        return Breadcrumbs::make([
            Breadcrumb::make(Nova::dashboardForKey($name, $request)->label()),
        ]);
    }
}
