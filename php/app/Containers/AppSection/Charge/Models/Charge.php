<?php

namespace App\Containers\AppSection\Charge\Models;

use App\Containers\AppSection\Account\Models\Account;
use App\Containers\AppSection\Charge\Enums\ChargeStatus;
use App\Containers\AppSection\Charge\Enums\Currency;
use App\Containers\AppSection\Charge\Enums\VoidReason;
use App\Containers\AppSection\Charge\Exceptions\ChargeOperationRejected;
use App\Containers\AppSection\User\Models\User;
use App\Ship\Parents\Models\Model as ParentModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A payment. Amounts are integer minor units; the state transitions below are
 * mirrored by the Go API (go-api/internal/charge) and both are checked against
 * contracts/charge-operations.json.
 *
 * @property int $id
 * @property int $account_id
 * @property int $amount
 * @property int $amount_captured
 * @property int $amount_refunded
 * @property int $amount_voided
 * @property string $currency
 * @property ChargeStatus $status
 * @property string $external_transaction_id
 * @property string $customer_email
 * @property bool $refunded
 * @property bool $disputed
 * @property ?VoidReason $void_reason
 * @property int $created_by
 */
final class Charge extends ParentModel
{
    /** 999,999.99 in a two-decimal currency. */
    public const MAX_AMOUNT = 99_999_999;

    /** Every attribute is set through the domain methods below. */
    protected $guarded = ['*'];

    protected $attributes = [
        'amount_captured' => 0,
        'amount_refunded' => 0,
        'amount_voided' => 0,
        'refunded' => false,
        'disputed' => false,
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'amount_captured' => 'integer',
            'amount_refunded' => 'integer',
            'amount_voided' => 'integer',
            'status' => ChargeStatus::class,
            'void_reason' => VoidReason::class,
            'refunded' => 'boolean',
            'disputed' => 'boolean',
        ];
    }

    public static function initiate(
        Account $account,
        int $amount,
        Currency $currency,
        string $customerEmail,
        bool $capture,
        User $creator,
    ): self {
        self::assertValidAmount($amount);

        if (! $account->acceptsNewCharges()) {
            throw ChargeOperationRejected::accountInactive();
        }

        $charge = new self;
        $charge->account_id = $account->id;
        $charge->amount = $amount;
        $charge->currency = $currency->value;
        $charge->customer_email = mb_strtolower(trim($customerEmail));
        $charge->status = $capture ? ChargeStatus::Captured : ChargeStatus::Authorized;
        $charge->amount_captured = $capture ? $amount : 0;
        $charge->external_transaction_id = self::newTransactionId();
        $charge->created_by = $creator->id;

        return $charge;
    }

    public static function newTransactionId(): string
    {
        return 'txn_'.bin2hex(random_bytes(12));
    }

    public function capture(): void
    {
        if ($this->status !== ChargeStatus::Authorized) {
            throw ChargeOperationRejected::notCapturable($this->status);
        }

        $this->amount_captured = $this->amount;
        $this->status = ChargeStatus::Captured;
    }

    /**
     * Refunds part or all of the captured amount. Without an amount, refunds
     * whatever is left.
     */
    public function refund(?int $amount = null): void
    {
        if ($amount !== null) {
            self::assertValidAmount($amount);
        }

        if ($this->status === ChargeStatus::Refunded) {
            throw ChargeOperationRejected::alreadyRefunded();
        }

        if ($this->status !== ChargeStatus::Captured) {
            throw ChargeOperationRejected::notRefundable($this->status);
        }

        if ($this->disputed) {
            throw ChargeOperationRejected::disputed();
        }

        $amount ??= $this->refundableAmount();

        if ($amount > $this->refundableAmount()) {
            throw ChargeOperationRejected::refundExceedsAvailable();
        }

        $this->amount_refunded += $amount;

        if ($this->amount_refunded === $this->amount_captured) {
            $this->status = ChargeStatus::Refunded;
            $this->refunded = true;
        }
    }

    public function void(VoidReason $reason): void
    {
        if ($this->status !== ChargeStatus::Authorized) {
            throw ChargeOperationRejected::notVoidable($this->status);
        }

        $this->amount_voided = $this->amount;
        $this->status = ChargeStatus::Voided;
        $this->void_reason = $reason;
    }

    public function refundableAmount(): int
    {
        return $this->status === ChargeStatus::Captured
            ? $this->amount_captured - $this->amount_refunded
            : 0;
    }

    /** @return BelongsTo<Account, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    private static function assertValidAmount(int $amount): void
    {
        if ($amount < 1 || $amount > self::MAX_AMOUNT) {
            throw ChargeOperationRejected::invalidAmount();
        }
    }
}
