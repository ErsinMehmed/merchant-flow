<?php

namespace App\Containers\AppSection\User\Actions;

use App\Containers\AppSection\User\Models\User;
use App\Containers\AppSection\User\Models\UserAvatar;
use App\Ship\Parents\Actions\Action as ParentAction;
use Illuminate\Support\Facades\DB;

final class RemoveUserAvatarAction extends ParentAction
{
    public function run(User $user): void
    {
        DB::transaction(static function () use ($user): void {
            UserAvatar::query()->whereKey($user->getKey())->delete();
            $user->forceFill(['avatar_updated_at' => null])->saveQuietly();
        });
    }
}
