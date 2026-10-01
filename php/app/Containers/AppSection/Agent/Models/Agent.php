<?php

namespace App\Containers\AppSection\Agent\Models;

use App\Containers\AppSection\Account\Models\Account;
use App\Containers\AppSection\Agent\Enums\AgentType;
use App\Containers\AppSection\User\Models\User;
use App\Ship\Parents\Models\Model as ParentModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $first_name
 * @property string $last_name
 * @property string $email
 * @property string $company_dba
 * @property AgentType $type
 * @property ?int $parent_agent_id
 */
final class Agent extends ParentModel
{
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'company_legal_name',
        'company_dba',
        'type',
        'default_split',
        'parent_agent_id',
    ];

    protected function casts(): array
    {
        return [
            'type' => AgentType::class,
            'default_split' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<self, $this> */
    public function parentAgent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_agent_id');
    }

    /** @return HasMany<self, $this> */
    public function childAgents(): HasMany
    {
        return $this->hasMany(self::class, 'parent_agent_id');
    }

    /** @return BelongsToMany<Account, $this> */
    public function accounts(): BelongsToMany
    {
        return $this->belongsToMany(Account::class, 'agent_account');
    }

    /** @return BelongsToMany<User, $this> */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'agent_user');
    }

    /**
     * Whether making $parentId this agent's parent would close a loop in the
     * hierarchy. Walks up from the proposed parent; hierarchies are shallow.
     */
    public function wouldCreateCycle(?int $parentId): bool
    {
        $visited = [];

        while ($parentId !== null) {
            if ($parentId === $this->id || isset($visited[$parentId])) {
                return true;
            }

            $visited[$parentId] = true;
            $next = self::query()->whereKey($parentId)->value('parent_agent_id');
            $parentId = $next === null ? null : (int) $next;
        }

        return false;
    }
}
