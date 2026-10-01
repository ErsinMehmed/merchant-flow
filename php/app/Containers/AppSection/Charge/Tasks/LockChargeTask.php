<?php

namespace App\Containers\AppSection\Charge\Tasks;

use App\Containers\AppSection\Charge\Models\Charge;
use App\Ship\Parents\Tasks\Task as ParentTask;

final class LockChargeTask extends ParentTask
{
    /**
     * Must run inside a transaction. The row lock serialises concurrent
     * operations on one charge, so each sees the amounts left by the previous.
     */
    public function run(int $chargeId): Charge
    {
        return Charge::query()->lockForUpdate()->findOrFail($chargeId);
    }
}
