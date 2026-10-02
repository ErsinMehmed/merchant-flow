<?php

namespace App\Containers\AppSection\Charge\Actions;

use App\Containers\AppSection\Charge\Models\Charge;
use App\Containers\AppSection\Charge\Tasks\LockChargeTask;
use App\Ship\Parents\Actions\Action as ParentAction;
use Illuminate\Support\Facades\DB;

final class CaptureChargeAction extends ParentAction
{
    public function __construct(
        private readonly LockChargeTask $lockChargeTask,
    ) {}

    /** Serialize capture with other operations on the same charge. */
    public function run(int $chargeId): Charge
    {
        return DB::transaction(function () use ($chargeId): Charge {
            $charge = $this->lockChargeTask->run($chargeId);
            $charge->capture();
            $charge->save();

            return $charge;
        });
    }
}
