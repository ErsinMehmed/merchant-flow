<?php

namespace App\Nova\Http;

use App\Containers\AppSection\User\Models\UserAvatar;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves profile photos to Nova (the user menu and the Users resource). The
 * route sits behind Nova's authentication and gate.
 */
final class UserAvatarController
{
    public function __invoke(int $user): Response
    {
        $avatar = UserAvatar::query()->findOrFail($user);

        return response($avatar->content, 200, [
            'Content-Type' => $avatar->content_type,
            'Content-Disposition' => 'inline',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'",
            // The URL carries a version, so a new photo gets a new URL.
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }
}
