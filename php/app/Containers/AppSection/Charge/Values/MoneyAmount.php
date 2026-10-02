<?php

namespace App\Containers\AppSection\Charge\Values;

use Brick\Math\Exception\MathException;
use Brick\Money\Exception\MoneyException;
use Brick\Money\Money;

/**
 * Converts between what an administrator types ("12.50") and minor units
 * (1250) without floating point.
 */
final class MoneyAmount
{
    /** Up to 999,999.99 with at most two decimals and no sign or separators. */
    public const INPUT_PATTERN = '/^\d{1,6}(\.\d{1,2})?$/';

    /** Return null for malformed amounts or unsupported currencies. */
    public static function toMinorUnits(string $input, string $currency): ?int
    {
        $input = trim($input);
        if (preg_match(self::INPUT_PATTERN, $input) !== 1) {
            return null;
        }

        try {
            return Money::of($input, $currency)->getMinorAmount()->toInt();
        } catch (MathException|MoneyException) {
            return null;
        }
    }

    public static function format(int $minorUnits, string $currency): string
    {
        try {
            return Money::ofMinor($minorUnits, $currency)->formatTo('en_US');
        } catch (MathException|MoneyException) {
            return number_format($minorUnits / 100, 2).' '.$currency;
        }
    }
}
