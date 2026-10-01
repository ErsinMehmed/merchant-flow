<?php

namespace App\Containers\AppSection\Authentication\Actions;

use App\Containers\AppSection\User\Models\User;
use App\Ship\Parents\Actions\Action as ParentAction;
use Illuminate\Support\Facades\Hash;

/**
 * Credential check for the Nova login form. Agents and merchants get the same
 * "credentials do not match" answer as a wrong password and no session is
 * started, so the admin portal does not reveal which accounts exist.
 */
final class AuthenticateAdminAction extends ParentAction
{
    /**
     * A bcrypt hash of a throwaway value. Checking against it when the email is
     * unknown keeps the response time close to that of a real account.
     */
    private const TIMING_HASH = '$2y$12$sCXdpMx.PIl/SpRO.oe2oeOFflEwlHsXtgnUuiAmyepcdk5A0CFwW';

    public function run(string $email, string $password): ?User
    {
        $user = User::query()->where('email', mb_strtolower(trim($email)))->first();

        $passwordMatches = Hash::check($password, $user === null ? self::TIMING_HASH : $user->getAuthPassword());

        if ($user === null || ! $passwordMatches || ! $user->isAdmin()) {
            return null;
        }

        return $user;
    }
}
