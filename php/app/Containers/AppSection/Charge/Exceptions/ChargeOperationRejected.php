<?php

namespace App\Containers\AppSection\Charge\Exceptions;

use App\Containers\AppSection\Charge\Enums\ChargeStatus;
use App\Ship\Parents\Exceptions\Exception as ParentException;

/**
 * A charge operation that breaks a business rule. The codes match the Go API
 * error codes and the shared cases in contracts/charge-operations.json.
 */
final class ChargeOperationRejected extends ParentException
{
    private function __construct(public readonly string $reason, string $message)
    {
        parent::__construct($message);
    }

    public static function invalidAmount(): self
    {
        return new self('invalid_amount', 'The amount must be a positive number of minor units.');
    }

    public static function accountInactive(): self
    {
        return new self('account_inactive', 'The merchant account cannot accept new charges.');
    }

    public static function notCapturable(ChargeStatus $status): self
    {
        return new self('charge_not_capturable', "A {$status->value} charge cannot be captured.");
    }

    public static function notVoidable(ChargeStatus $status): self
    {
        return new self('charge_not_voidable', "A {$status->value} charge cannot be voided; only authorized charges can.");
    }

    public static function notRefundable(ChargeStatus $status): self
    {
        return new self('charge_not_refundable', "A {$status->value} charge cannot be refunded; only captured charges can.");
    }

    public static function alreadyRefunded(): self
    {
        return new self('charge_already_refunded', 'The charge has already been fully refunded.');
    }

    public static function disputed(): self
    {
        return new self('charge_disputed', 'A disputed charge cannot be refunded.');
    }

    public static function refundExceedsAvailable(): self
    {
        return new self('refund_exceeds_available', 'The refund exceeds the amount left to refund.');
    }
}
