<?php

namespace App\Containers\AppSection\Authorization\Enums;

/**
 * Permission names shared with the Go API (go-api/internal/access).
 */
enum Permission: string
{
    case ViewCharges = 'charges.view';
    case CreateCharges = 'charges.create';
    case CaptureCharges = 'charges.capture';
    case RefundCharges = 'charges.refund';
    case VoidCharges = 'charges.void';
    case ManageMerchants = 'merchants.manage';
    case ManageAgents = 'agents.manage';
    case ManageUsers = 'users.manage';

    public function label(): string
    {
        return match ($this) {
            self::ViewCharges => 'View charges',
            self::CreateCharges => 'Create charges',
            self::CaptureCharges => 'Capture charges',
            self::RefundCharges => 'Refund charges',
            self::VoidCharges => 'Void charges',
            self::ManageMerchants => 'Manage merchants',
            self::ManageAgents => 'Manage agents',
            self::ManageUsers => 'Manage users',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::ViewCharges => 'View charges within the user\'s scope',
            self::CreateCharges => 'Initiate a charge',
            self::CaptureCharges => 'Capture an authorized charge',
            self::RefundCharges => 'Refund a captured charge',
            self::VoidCharges => 'Void an authorized charge',
            self::ManageMerchants => 'Manage merchant accounts and their relationships',
            self::ManageAgents => 'Manage agents and their relationships',
            self::ManageUsers => 'Manage users, roles and permissions',
        };
    }
}
