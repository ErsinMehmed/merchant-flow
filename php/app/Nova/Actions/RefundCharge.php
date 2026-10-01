<?php

namespace App\Nova\Actions;

use App\Containers\AppSection\Charge\Actions\RefundChargeAction;
use App\Containers\AppSection\Charge\Exceptions\ChargeOperationRejected;
use App\Containers\AppSection\Charge\Models\Charge;
use App\Containers\AppSection\Charge\Values\MoneyAmount;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;

final class RefundCharge extends Action
{
    public $name = 'Refund';

    public $uriKey = 'refund-charge';

    public $confirmButtonText = 'Refund';

    /**
     * @param  Collection<int, Charge>  $models
     */
    public function handle(ActionFields $fields, Collection $models): ActionResponse
    {
        $charge = $models->sole();
        $input = trim((string) $fields->get('amount'));
        $amount = null;

        if ($input !== '') {
            $amount = MoneyAmount::toMinorUnits($input, $charge->currency);
            if ($amount === null || $amount < 1) {
                return ActionResponse::danger('Enter a positive amount with at most two decimals, e.g. 12.50.');
            }
        }

        try {
            $charge = app(RefundChargeAction::class)->run($charge->id, $amount);
        } catch (ChargeOperationRejected $e) {
            return ActionResponse::danger($e->getMessage());
        }

        return ActionResponse::message(sprintf(
            'Refunded. Total refunded: %s of %s.',
            MoneyAmount::format($charge->amount_refunded, $charge->currency),
            MoneyAmount::format($charge->amount_captured, $charge->currency),
        ));
    }

    public function fields(NovaRequest $request): array
    {
        return [
            Text::make('Amount')
                ->nullable()
                ->rules('nullable', 'regex:'.MoneyAmount::INPUT_PATTERN)
                ->help('In major units, e.g. 12.50. Leave empty to refund everything that is left.'),
        ];
    }
}
