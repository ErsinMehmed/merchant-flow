<?php

namespace App\Containers\AppSection\User\Models;

use App\Containers\AppSection\Account\Models\Account;
use App\Containers\AppSection\Agent\Models\Agent;
use App\Containers\AppSection\Authentication\Models\ApiToken;
use App\Containers\AppSection\Authorization\Enums\Permission;
use App\Containers\AppSection\Authorization\Enums\Role;
use App\Containers\AppSection\Charge\Models\Charge;
use App\Containers\AppSection\User\Observers\UserObserver;
use App\Ship\Parents\Models\UserModel as ParentUserModel;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property bool $is_admin
 * @property bool $is_agent
 * @property bool $is_merchant
 */
#[ObservedBy(UserObserver::class)]
final class User extends ParentUserModel
{
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_admin',
        'is_agent',
        'is_merchant',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $attributes = [
        'is_admin' => false,
        'is_agent' => false,
        'is_merchant' => false,
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'is_agent' => 'boolean',
            'is_merchant' => 'boolean',
        ];
    }

    /** @return BelongsToMany<Account, $this> */
    public function accounts(): BelongsToMany
    {
        return $this->belongsToMany(Account::class, 'account_user');
    }

    /** @return BelongsToMany<Agent, $this> */
    public function agents(): BelongsToMany
    {
        return $this->belongsToMany(Agent::class, 'agent_user');
    }

    /** @return HasMany<Account, $this> */
    public function createdAccounts(): HasMany
    {
        return $this->hasMany(Account::class, 'created_by');
    }

    /** @return HasMany<Charge, $this> */
    public function createdCharges(): HasMany
    {
        return $this->hasMany(Charge::class, 'created_by');
    }

    /** @return HasMany<ApiToken, $this> */
    public function apiTokens(): HasMany
    {
        return $this->hasMany(ApiToken::class);
    }

    public function isAdmin(): bool
    {
        return $this->hasRole(Role::Admin->value);
    }

    /**
     * Direct or role-granted permission. Unlike hasPermissionTo() this does not
     * throw when the permission row is missing.
     */
    public function hasPermission(Permission $permission): bool
    {
        return $this->checkPermissionTo($permission->value);
    }

    /**
     * The flags are how an administrator chooses a user's type; the Spatie
     * roles derived from them are what PHP and Go actually authorize against.
     *
     * @return list<string>
     */
    public function rolesFromFlags(): array
    {
        return array_keys(array_filter([
            Role::Admin->value => $this->is_admin,
            Role::Agent->value => $this->is_agent,
            Role::Merchant->value => $this->is_merchant,
        ]));
    }

    /**
     * Rows that reference the user through restricting foreign keys.
     */
    public function hasCreatedRecords(): bool
    {
        return $this->createdAccounts()->exists() || $this->createdCharges()->exists();
    }

    /** @return Attribute<string, string> */
    protected function email(): Attribute
    {
        return Attribute::make(
            set: static fn (string $value): string => mb_strtolower(trim($value)),
        );
    }
}
