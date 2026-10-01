<?php

namespace App\Containers\AppSection\Authentication\Tests\Functional;

use App\Containers\AppSection\Authentication\Actions\AuthenticateAdminAction;
use App\Containers\AppSection\User\Models\User;
use App\Ship\Parents\Tests\TestCase;

final class AuthenticateAdminActionTest extends TestCase
{
    public function test_only_administrators_with_the_right_password_are_authenticated(): void
    {
        $admin = User::factory()->admin()->create(['email' => 'admin@example.com', 'password' => 'Correct-Passw0rd!']);
        User::factory()->merchant()->create(['email' => 'merchant@example.com', 'password' => 'Correct-Passw0rd!']);
        User::factory()->agent()->create(['email' => 'agent@example.com', 'password' => 'Correct-Passw0rd!']);
        $action = app(AuthenticateAdminAction::class);

        $this->assertTrue($admin->is($action->run('ADMIN@example.com ', 'Correct-Passw0rd!')));
        $this->assertNull($action->run('admin@example.com', 'wrong'));
        $this->assertNull($action->run('merchant@example.com', 'Correct-Passw0rd!'));
        $this->assertNull($action->run('agent@example.com', 'Correct-Passw0rd!'));
        $this->assertNull($action->run('nobody@example.com', 'Correct-Passw0rd!'));
    }

    public function test_the_admin_flag_alone_is_not_enough_without_the_role(): void
    {
        $admin = User::factory()->admin()->create(['email' => 'admin@example.com', 'password' => 'Correct-Passw0rd!']);
        $admin->removeRole('admin');

        $this->assertNull(app(AuthenticateAdminAction::class)->run('admin@example.com', 'Correct-Passw0rd!'));
    }
}
