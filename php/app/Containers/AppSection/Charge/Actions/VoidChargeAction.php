<?php

namespace App\Containers\AppSection\Charge\Actions;

use App\Containers\AppSection\Charge\Enums\VoidReason;
use App\Containers\AppSection\Charge\Models\Charge;
use App\Containers\AppSection\Charge\Tasks\LockChargeTask;
use App\Ship\Parents\Actions\Action as ParentAction;
use Illuminate\Support\Facades\DB;

final class VoidChargeAction extends ParentAction
{
    public function __construct(
        private readonly LockChargeTask $lockChargeTask,
    ) {}

    public function run(int $chargeId, VoidReason $reason): Charge
    {
        return DB::transaction(function () use ($chargeId, $reason): Charge {
            $charge = $this->lockChargeTask->run($chargeId);
            $charge->void($reason);
            $charge->save();

            return $charge;
        });
    }
}
