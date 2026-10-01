<?php

namespace App\Ship\Parents\Models;

use Apiato\Core\Models\UserModel as AbstractUserModel;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

abstract class UserModel extends AbstractUserModel
{
    use HasRoles;
    use Notifiable;
}
