<?php

namespace App\Ship\Providers;

use App\Containers\AppSection\Account\Models\Account;
use App\Containers\AppSection\Agent\Models\Agent;
use App\Containers\AppSection\Charge\Models\Charge;
use App\Containers\AppSection\User\Models\User;
use App\Ship\Parents\Providers\ServiceProvider as ParentServiceProvider;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Date;

final class ShipServiceProvider extends ParentServiceProvider
{
    /**
     * Stable morph aliases. Spatie stores the alias in model_has_roles and
     * model_has_permissions, and the Go API filters those rows by "user", so
     * the value must not depend on PHP namespaces.
     */
    public const MORPH_MAP = [
        'user' => User::class,
        'account' => Account::class,
        'agent' => Agent::class,
        'charge' => Charge::class,
    ];

    public function boot(): void
    {
        Date::use(CarbonImmutable::class);
        Relation::morphMap(self::MORPH_MAP);

        // Strictness catches lazy loading and typos while developing and testing.
        Model::shouldBeStrict(app()->environment('testing'));
    }
}
