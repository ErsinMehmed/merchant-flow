<?php

namespace App\Containers\AppSection\Charge\Enums;

enum VoidReason: string
{
    case RequestedByCustomer = 'requested_by_customer';
    case Duplicate = 'duplicate';
    case Fraudulent = 'fraudulent';
    case Abandoned = 'abandoned';

    /** @return array<string, string> */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $reason) {
            $options[$reason->value] = ucfirst(str_replace('_', ' ', $reason->value));
        }

        return $options;
    }
}
