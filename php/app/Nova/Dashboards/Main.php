<?php

namespace App\Nova\Dashboards;

use App\Containers\AppSection\Authorization\Enums\Permission;
use App\Nova\Metrics\AgentsByType;
use App\Nova\Metrics\ApprovalRate;
use App\Nova\Metrics\CaptureRate;
use App\Nova\Metrics\ChargesByCurrency;
use App\Nova\Metrics\ChargesByStatus;
use App\Nova\Metrics\ChargesPerDay;
use App\Nova\Metrics\Disputes;
use App\Nova\Metrics\MerchantsByStatus;
use App\Nova\Metrics\NetVolumeByCurrency;
use App\Nova\Metrics\NewCharges;
use App\Nova\Metrics\TopMerchants;
use Closure;
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
        $charges = self::requires(Permission::ViewCharges);

        return [
            (new NewCharges)->canSee($charges),
            (new ChargesPerDay)->canSee($charges),
            (new ChargesByStatus)->canSee($charges),

            (new ApprovalRate)->canSee($charges),
            (new CaptureRate)->canSee($charges),
            (new Disputes)->canSee($charges),

            (new ChargesByCurrency)->canSee($charges),
            (new MerchantsByStatus)->canSee(self::requires(Permission::ManageMerchants)),
            (new AgentsByType)->canSee(self::requires(Permission::ManageAgents)),

            (new NetVolumeByCurrency)->width('1/2')->canSee($charges),
            (new TopMerchants)->width('1/2')->canSee($charges),
        ];
    }

    /** @return Closure(Request): bool */
    private static function requires(Permission $permission): Closure
    {
        return static fn (Request $request): bool => (bool) $request->user()?->hasPermission($permission);
    }
}
