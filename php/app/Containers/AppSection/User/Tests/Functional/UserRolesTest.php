<?php

namespace App\Containers\AppSection\User\Tests\Functional;

use App\Containers\AppSection\Authorization\Enums\Permission;
use App\Containers\AppSection\User\Models\User;
use App\Ship\Parents\Tests\TestCase;
use Illuminate\Support\Facades\DB;

final class UserRolesTest extends TestCase
{
    public function test_roles_follow_the_user_type_flags(): void
    {
        $user = User::factory()->merchant()->create();
        $this->assertSame(['merchant'], $user->getRoleNames()->all());

        $user->update(['is_merchant' => false, 'is_agent' => true]);
        $this->assertSame(['agent'], $user->refresh()->getRoleNames()->all());

        $user->update(['is_agent' => false]);
        $this->assertSame([], $user->refresh()->getRoleNames()->all());
    }

    public function test_unrelated_changes_do_not_touch_roles(): void
    {
        $user = User::factory()->agent()->create();
        $user->givePermissionTo(Permission::RefundCharges->value);

        $user->update(['name' => 'Renamed']);

        $user->refresh();
        $this->assertSame(['agent'], $user->getRoleNames()->all());
        $this->assertTrue($user->hasPermission(Permission::RefundCharges));
    }

    /**
     * The Go API looks up roles and permissions by these exact values.
     */
    public function test_spatie_rows_use_the_shared_morph_alias_and_guard(): void
    {
        $user = User::factory()->admin()->create();
        $user->givePermissionTo(Permission::ViewCharges->value);

        $this->assertSame('user', $user->getMorphClass());
        $this->assertDatabaseHas('model_has_roles', ['model_type' => 'user', 'model_id' => $user->id]);
        $this->assertDatabaseHas('model_has_permissions', ['model_type' => 'user', 'model_id' => $user->id]);
    }

    /**
     * Go verifies portal passwords against the stored hash, so it has to be
     * bcrypt.
     */
    public function test_passwords_are_stored_as_bcrypt(): void
    {
        $user = User::factory()->create(['password' => 'Secret-Passw0rd!']);

        $hash = (string) DB::table('users')->where('id', $user->id)->value('password');
        $this->assertStringStartsWith('$2y$', $hash);
        $this->assertTrue(password_verify('Secret-Passw0rd!', $hash));
    }

    public function test_email_is_stored_normalised(): void
    {
        $user = User::factory()->create(['email' => '  Mixed.Case@Example.COM ']);

        $this->assertSame('mixed.case@example.com', $user->fresh()?->email);
    }
}
