<?php

namespace App\Containers\AppSection\User\Actions;

use App\Containers\AppSection\User\Models\User;
use App\Containers\AppSection\User\Models\UserAvatar;
use App\Ship\Parents\Actions\Action as ParentAction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Stores a validated upload as the user's profile photo, replacing any
 * earlier one.
 */
final class UpdateUserAvatarAction extends ParentAction
{
    public function run(User $user, UploadedFile $file): void
    {
        // Read from the content, not from the client's Content-Type.
        $contentType = (string) $file->getMimeType();
        if (! in_array($contentType, UserAvatar::CONTENT_TYPES, true)) {
            throw new InvalidArgumentException("Unsupported photo type {$contentType}.");
        }

        DB::transaction(static function () use ($user, $file, $contentType): void {
            UserAvatar::query()->updateOrCreate(
                ['user_id' => $user->getKey()],
                ['content_type' => $contentType, 'content' => $file->getContent()],
            );

            // Quietly: the observer only cares about the user type flags.
            $user->forceFill(['avatar_updated_at' => now()])->saveQuietly();
        });
    }
}
