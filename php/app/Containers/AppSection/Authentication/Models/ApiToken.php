<?php

namespace App\Containers\AppSection\Authentication\Models;

use App\Containers\AppSection\User\Models\User;
use App\Ship\Parents\Models\Model as ParentModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Read-only from PHP apart from deletion, which revokes the session.
 *
 * @property int $id
 * @property int $user_id
 */
final class ApiToken extends ParentModel
{
    public const UPDATED_AT = null;

    protected $guarded = ['*'];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return [
            'last_used_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
