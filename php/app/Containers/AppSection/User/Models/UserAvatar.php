<?php

namespace App\Containers\AppSection\User\Models;

use App\Ship\Parents\Models\Model as ParentModel;

/**
 * A user's profile photo. See the create_user_avatars_table migration.
 *
 * @property int $user_id
 * @property string $content_type
 * @property string $content
 */
final class UserAvatar extends ParentModel
{
    /** JPEG, PNG and WebP only: SVG can carry scripts. */
    public const CONTENT_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    /** Kept in step with maxAvatarBytes in the Go API. */
    public const MAX_KILOBYTES = 2048;

    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = 'user_id';

    protected $fillable = ['user_id', 'content_type', 'content'];
}
