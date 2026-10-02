<?php

namespace App\Containers\AppSection\Authorization\Models;

use App\Containers\AppSection\Authorization\Enums\Permission as PermissionName;
use Spatie\Permission\Models\Permission as SpatiePermission;

final class Permission extends SpatiePermission
{
    public function label(): string
    {
        return PermissionName::tryFrom($this->name)?->label() ?? $this->name;
    }

    public function description(): ?string
    {
        return PermissionName::tryFrom($this->name)?->description();
    }
}
