<?php

namespace App\Nova\Actions;

use App\Containers\AppSection\Charge\Actions\CaptureChargeAction;
use App\Containers\AppSection\Charge\Exceptions\ChargeOperationRejected;
use App\Containers\AppSection\Charge\Models\Charge;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;

final class CaptureCharge extends Action
{
    public $name = 'Capture';

    public $uriKey = 'capture-charge';

    public $confirmText = 'Capture the full authorized amount?';

    public $confirmButtonText = 'Capture';

    /**
     * @param  Collection<int, Charge>  $models
     */
    public function handle(ActionFields $fields, Collection $models): ActionResponse
    {
        try {
            app(CaptureChargeAction::class)->run($models->sole()->id);
        } catch (ChargeOperationRejected $e) {
            return ActionResponse::danger($e->getMessage());
        }

        return ActionResponse::message('Charge captured.');
    }
}
