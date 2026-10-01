<?php

namespace Tests\Feature\Nova;

use App\Containers\AppSection\User\Models\User;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class AdminLoginTest extends TestCase
{
    private const PASSWORD = 'Correct-Passw0rd!';

    public function test_administrator_can_sign_in(): void
    {
        $admin = User::factory()->admin()->create(['password' => self::PASSWORD]);

        $this->post('/admin/login', ['email' => $admin->email, 'password' => self::PASSWORD])
            ->assertRedirect();

        $this->assertAuthenticatedAs($admin);
        $this->get('/admin/dashboards/main')->assertOk();
    }

    #[DataProvider('nonAdministrators')]
    public function test_other_users_are_rejected_at_the_login_form(string $type): void
    {
        $user = User::factory()->{$type}()->create(['password' => self::PASSWORD]);

        $this->post('/admin/login', ['email' => $user->email, 'password' => self::PASSWORD])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    /** @return iterable<string, array{string}> */
    public static function nonAdministrators(): iterable
    {
        yield 'merchant' => ['merchant'];
        yield 'agent' => ['agent'];
    }

    public function test_wrong_password_is_rejected(): void
    {
        $admin = User::factory()->admin()->create(['password' => self::PASSWORD]);

        $this->post('/admin/login', ['email' => $admin->email, 'password' => 'not-it'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_login_is_throttled(): void
    {
        $admin = User::factory()->admin()->create(['password' => self::PASSWORD]);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->post('/admin/login', ['email' => $admin->email, 'password' => 'not-it']);
        }

        // Even the right password is refused until the window passes.
        $this->post('/admin/login', ['email' => $admin->email, 'password' => self::PASSWORD])
            ->assertSessionHasErrors(['email' => 'Too many login attempts. Please try again in 60 seconds.']);
        $this->assertGuest();
    }

    public function test_guests_are_sent_to_the_login_page(): void
    {
        $this->get('/admin/resources/charges')->assertRedirect('/admin/login');
    }
}
