<?php

namespace App\Nova;

use App\Containers\AppSection\Account\Models\Account as AccountModel;
use App\Containers\AppSection\Authorization\Enums\Permission;
use App\Containers\AppSection\Charge\Enums\Currency;
use App\Containers\AppSection\Charge\Exceptions\ChargeOperationRejected;
use App\Containers\AppSection\Charge\Models\Charge as ChargeModel;
use App\Containers\AppSection\Charge\Values\MoneyAmount;
use App\Nova\Actions\CaptureCharge;
use App\Nova\Actions\RefundCharge;
use App\Nova\Actions\VoidCharge;
use App\Nova\Filters\ChargeStatusFilter;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Nova\Fields\Badge;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Panel;

/**
 * Charges are created here or through the Go API and afterwards changed only
 * by the capture, refund and void actions, which apply the same rules as Go.
 */
final class Charge extends Resource
{
    public static $model = ChargeModel::class;

    public static $title = 'external_transaction_id';

    public static $search = ['id', 'external_transaction_id', 'customer_email'];

    public static $group = 'Payments';

    /** @var list<string> */
    public static $with = ['account'];

    public static $perPageOptions = [25, 50, 100];

    public function fields(NovaRequest $request): array
    {
        return [
            ID::make()->sortable(),
            BelongsTo::make('Merchant', 'account', Account::class)
                ->sortable()
                ->rules('required'),
            Text::make('Transaction ID', 'external_transaction_id')->exceptOnForms()->copyable(),
            $this->money('Amount', 'amount')->sortable(),
            $this->money('Refunded', 'amount_refunded')->sortable(),
            Badge::make('Status', fn (): string => $this->resource->status->value)
                ->map([
                    'authorized' => 'info',
                    'captured' => 'success',
                    'refunded' => 'warning',
                    'voided' => 'danger',
                    'failed' => 'danger',
                ]),
            Text::make('Customer Email')
                ->sortable()
                ->rules('required', 'email:rfc', 'max:254'),
            DateTime::make('Created At')->sortable()->exceptOnForms(),

            new Panel('Amounts', [
                $this->money('Captured', 'amount_captured')->onlyOnDetail(),
                $this->money('Voided', 'amount_voided')->onlyOnDetail(),
                Text::make('Currency')->onlyOnDetail(),
                Boolean::make('Fully refunded', 'refunded')->onlyOnDetail(),
                Boolean::make('Disputed')->onlyOnDetail(),
                Text::make('Void Reason', fn (): ?string => $this->resource->void_reason?->value)->onlyOnDetail(),
                BelongsTo::make('Created by', 'creator', User::class)->onlyOnDetail(),
                DateTime::make('Updated At')->onlyOnDetail(),
            ]),

            // Creation form. Amounts are typed in major units and converted
            // without floating point in fill().
            Text::make('Amount', 'amount')
                ->onlyOnForms()
                ->rules('required', 'regex:'.MoneyAmount::INPUT_PATTERN)
                ->help('In major units, e.g. 12.50.'),
            Select::make('Currency')
                ->onlyOnForms()
                ->options(Currency::options())
                ->default(Currency::USD->value)
                ->rules('required', Rule::enum(Currency::class)),
            // Not "capture": Eloquent would resolve that attribute by calling
            // Charge::capture() as if it were a relationship.
            Boolean::make('Capture immediately', 'capture_immediately')
                ->onlyOnForms()
                ->default(true)
                ->fillUsing(static fn (): null => null)
                ->help('Off: the charge is only authorized and can be captured or voided later.'),
        ];
    }

    /**
     * Builds the charge through the domain constructor instead of letting
     * Nova assign attributes, so status and amounts follow the charge rules.
     */
    public static function fill(NovaRequest $request, $model): array
    {
        $currency = Currency::from($request->string('currency')->value());
        $amount = MoneyAmount::toMinorUnits($request->string('amount')->value(), $currency->value);
        $account = AccountModel::query()->findOrFail($request->integer('account'));

        try {
            $charge = ChargeModel::initiate(
                account: $account,
                amount: $amount ?? 0,
                currency: $currency,
                customerEmail: $request->string('customer_email')->value(),
                capture: $request->boolean('capture_immediately'),
                creator: $request->user(),
            );
        } catch (ChargeOperationRejected $e) {
            $field = $e->reason === 'account_inactive' ? 'account' : 'amount';
            throw ValidationException::withMessages([$field => $e->getMessage()]);
        }

        return [$charge, []];
    }

    /**
     * New charges can only be booked against accounts that accept them.
     */
    public static function relatableAccounts(NovaRequest $request, Builder $query): Builder
    {
        if (! $request->isCreateOrAttachRequest()) {
            return $query;
        }

        return $query->where('status', 'active')->where('active_yn', true);
    }

    public function filters(NovaRequest $request): array
    {
        return [new ChargeStatusFilter];
    }

    public function actions(NovaRequest $request): array
    {
        return [
            (new CaptureCharge)
                ->sole()
                ->canSee(static fn (NovaRequest $request): bool => (bool) $request->user()?->hasPermission(Permission::CaptureCharges))
                ->canRun(static fn (NovaRequest $request, ChargeModel $charge): bool => $request->user()->can('capture', $charge)),
            (new RefundCharge)
                ->sole()
                ->canSee(static fn (NovaRequest $request): bool => (bool) $request->user()?->hasPermission(Permission::RefundCharges))
                ->canRun(static fn (NovaRequest $request, ChargeModel $charge): bool => $request->user()->can('refund', $charge)),
            (new VoidCharge)
                ->sole()
                ->canSee(static fn (NovaRequest $request): bool => (bool) $request->user()?->hasPermission(Permission::VoidCharges))
                ->canRun(static fn (NovaRequest $request, ChargeModel $charge): bool => $request->user()->can('void', $charge)),
        ];
    }

    private function money(string $label, string $attribute): Number
    {
        return Number::make($label, $attribute)
            ->exceptOnForms()
            ->displayUsing(fn (?int $value): string => MoneyAmount::format($value ?? 0, $this->resource->currency));
    }
}
