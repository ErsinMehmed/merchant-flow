<?php

namespace App\Containers\AppSection\Agent\Enums;

enum AgentType: string
{
    case Iso = 'iso';
    case Independent = 'independent';
    case Referral = 'referral';

    public function label(): string
    {
        return match ($this) {
            self::Iso => 'ISO',
            self::Independent => 'Independent agent',
            self::Referral => 'Referral partner',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $type) {
            $options[$type->value] = $type->label();
        }

        return $options;
    }
}
