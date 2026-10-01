<?php

namespace App\Nova\Filters;

use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;

trait ParsesDay
{
    /**
     * A Y-m-d filter value as the start of that day in UTC, or null when the
     * value is not a valid date (it comes from the URL).
     */
    private function day(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        try {
            $day = CarbonImmutable::createFromFormat('!Y-m-d', $value, 'UTC');
        } catch (InvalidFormatException) {
            return null;
        }

        return $day !== null && $day->format('Y-m-d') === $value ? $day : null;
    }
}
