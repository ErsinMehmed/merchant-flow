<?php

namespace App\Containers\AppSection\Account\Models;

use App\Containers\AppSection\Account\Enums\AccountStatus;
use App\Containers\AppSection\Agent\Models\Agent;
use App\Containers\AppSection\Charge\Models\Charge;
use App\Containers\AppSection\User\Models\User;
use App\Ship\Parents\Models\Model as ParentModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A merchant account.
 *
 * @property int $id
 * @property string $business_name
 * @property string $mid
 * @property AccountStatus $status
 * @property bool $active_yn
 * @property int $created_by
 */
final class Account extends ParentModel
{
    protected $fillable = [
        'business_name',
        'dba_name',
        'legal_name',
        'email',
        'mid',
        'status',
        'active_yn',
    ];

    protected function casts(): array
    {
        return [
            'status' => AccountStatus::class,
            'active_yn' => 'boolean',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsToMany<Agent, $this> */
    public function agents(): BelongsToMany
    {
        return $this->belongsToMany(Agent::class, 'agent_account');
    }

    /** @return BelongsToMany<User, $this> */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'account_user');
    }

    /** @return HasMany<Charge, $this> */
    public function charges(): HasMany
    {
        return $this->hasMany(Charge::class);
    }

    /**
     * New charges need an active, enabled account. Existing charges can still
     * be captured, refunded or voided so a suspended merchant can settle.
     */
    public function acceptsNewCharges(): bool
    {
        return $this->status === AccountStatus::Active && $this->active_yn;
    }
}
