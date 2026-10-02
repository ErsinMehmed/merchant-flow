<?php

namespace App\Nova\Dashboards;

use App\Containers\AppSection\Authorization\Enums\Permission;
use App\Nova\Metrics\ChargesByStatus;
use App\Nova\Metrics\ChargesPerDay;
use App\Nova\Metrics\NewCharges;
use Illuminate\Http\Request;
use Laravel\Nova\Card;
use Laravel\Nova\Dashboards\Main as Dashboard;

final class Main extends Dashboard
{
    /**
     * Shown in the menu and breadcrumbs; Nova's default is the class name.
     * The URL stays /dashboards/main.
     */
    public function name(): string
    {
        return 'Dashboard';
    }

    /** @return list<Card> */
    public function cards(): array
    {
        $canViewCharges = static fn (Request $request): bool => (bool) $request->user()?->hasPermission(Permission::ViewCharges);

        return [
            (new NewCharges)->canSee($canViewCharges),
            (new ChargesPerDay)->canSee($canViewCharges),
            (new ChargesByStatus)->canSee($canViewCharges),
        ];
    }
}
