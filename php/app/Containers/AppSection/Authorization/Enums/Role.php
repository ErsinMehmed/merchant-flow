<?php

namespace App\Containers\AppSection\Authorization\Enums;

/**
 * Role names shared with the Go API. "admin" also widens the charge scope to
 * every account, so the name itself is part of the contract.
 */
enum Role: string
{
    case Admin = 'admin';
    case Merchant = 'merchant';
    case Agent = 'agent';

    /**
     * Permissions a role receives when it is first created. Later edits made in
     * Nova are kept; seeding never resets them.
     *
     * @return list<Permission>
     */
    public function defaultPermissions(): array
    {
        return match ($this) {
            self::Admin => Permission::cases(),
            self::Merchant => [
                Permission::ViewCharges,
                Permission::CreateCharges,
                Permission::CaptureCharges,
                Permission::RefundCharges,
                Permission::VoidCharges,
            ],
            self::Agent => [Permission::ViewCharges],
        };
    }
}
