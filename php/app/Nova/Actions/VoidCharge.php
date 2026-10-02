<?php

namespace App\Nova\Actions;

use App\Containers\AppSection\Charge\Actions\VoidChargeAction;
use App\Containers\AppSection\Charge\Enums\VoidReason;
use App\Containers\AppSection\Charge\Exceptions\ChargeOperationRejected;
use App\Containers\AppSection\Charge\Models\Charge;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Http\Requests\NovaRequest;

final class VoidCharge extends Action
{
    public $name = 'Void';

    public $uriKey = 'void-charge';

    public $confirmButtonText = 'Void';

    /**
     * @param  Collection<int, Charge>  $models
     */
    public function handle(ActionFields $fields, Collection $models): ActionResponse
    {
        try {
            app(VoidChargeAction::class)->run(
                $models->sole()->id,
                VoidReason::from((string) $fields->get('reason')),
            );
        } catch (ChargeOperationRejected $e) {
            return ActionResponse::danger($e->getMessage());
        }

        return ActionResponse::message('Charge voided.');
    }

    /** @return array<int, Field> */
    public function fields(NovaRequest $request): array
    {
        return [
            Select::make('Reason')
                ->options(VoidReason::options())
                ->default(VoidReason::RequestedByCustomer->value)
                ->rules('required', Rule::enum(VoidReason::class)),
        ];
    }
}
