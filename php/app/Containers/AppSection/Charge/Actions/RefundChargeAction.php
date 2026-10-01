<?php

namespace App\Containers\AppSection\Charge\Actions;

use App\Containers\AppSection\Charge\Models\Charge;
use App\Containers\AppSection\Charge\Tasks\LockChargeTask;
use App\Ship\Parents\Actions\Action as ParentAction;
use Illuminate\Support\Facades\DB;

final class RefundChargeAction extends ParentAction
{
    public function __construct(
        private readonly LockChargeTask $lockChargeTask,
    ) {}

    /**
     * @param  ?int  $amount  minor units; null refunds the remaining amount
     */
    public function run(int $chargeId, ?int $amount = null): Charge
    {
        return DB::transaction(function () use ($chargeId, $amount): Charge {
            $charge = $this->lockChargeTask->run($chargeId);
            $charge->refund($amount);
            $charge->save();

            return $charge;
        });
    }
}
