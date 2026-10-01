<?php

namespace App\Containers\AppSection\Charge\Enums;

/**
 * Supported settlement currencies. All of them use two decimal places, so one
 * minor unit is always one hundredth.
 */
enum Currency: string
{
    case USD = 'USD';
    case EUR = 'EUR';
    case GBP = 'GBP';
    case CAD = 'CAD';

    /** @return array<string, string> */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $currency) {
            $options[$currency->value] = $currency->value;
        }

        return $options;
    }
}
