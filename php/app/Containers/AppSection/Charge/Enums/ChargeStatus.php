<?php

namespace App\Containers\AppSection\Charge\Enums;

/**
 * authorized -> captured -> refunded (once captured amount is fully returned)
 * authorized -> voided
 * failed is terminal and only produced by the processor (seeded here).
 *
 * A partial refund keeps the charge "captured" with amount_refunded > 0.
 */
enum ChargeStatus: string
{
    case Authorized = 'authorized';
    case Captured = 'captured';
    case Refunded = 'refunded';
    case Voided = 'voided';
    case Failed = 'failed';
}
