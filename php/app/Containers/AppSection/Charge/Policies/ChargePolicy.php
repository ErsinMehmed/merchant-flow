<?php

namespace App\Containers\AppSection\Charge\Policies;

use App\Containers\AppSection\Authorization\Enums\Permission;
use App\Containers\AppSection\Charge\Models\Charge;
use App\Containers\AppSection\User\Models\User;
use App\Ship\Parents\Policies\Policy as ParentPolicy;

/**
 * Charges are a ledger: they are created, then changed only by capture, refund
 * and void (Nova actions with their own permission checks). There is no
 * free-form edit or delete.
 */
final class ChargePolicy extends ParentPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission(Permission::ViewCharges);
    }

    public function view(User $actor, Charge $charge): bool
    {
        return $actor->hasPermission(Permission::ViewCharges);
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission(Permission::CreateCharges);
    }

    public function update(): bool
    {
        return false;
    }

    public function delete(): bool
    {
        return false;
    }

    public function replicate(): bool
    {
        return false;
    }

    public function capture(User $actor, Charge $charge): bool
    {
        return $actor->hasPermission(Permission::CaptureCharges);
    }

    public function refund(User $actor, Charge $charge): bool
    {
        return $actor->hasPermission(Permission::RefundCharges);
    }

    public function void(User $actor, Charge $charge): bool
    {
        return $actor->hasPermission(Permission::VoidCharges);
    }
}
