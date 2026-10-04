<?php

namespace Tests\Feature\Nova;

use App\Containers\AppSection\User\Models\User;
use App\Containers\AppSection\User\Models\UserAvatar;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

final class UserAvatarTest extends TestCase
{
    /** A 1x1 transparent PNG. */
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

    public function test_administrator_uploads_a_photo_that_nova_serves(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->merchant()->create();

        $this->actingAs($admin)
            ->post("/nova-api/users/{$user->id}?_method=PUT", $this->form($user, $this->png()))
            ->assertOk();

        $avatar = UserAvatar::query()->findOrFail($user->id);
        $this->assertSame('image/png', $avatar->content_type);
        $this->assertSame(base64_decode(self::PNG), $avatar->content);

        $updatedAt = $user->fresh()?->avatar_updated_at;
        $this->assertNotNull($updatedAt);

        /** @var list<array<string, mixed>> $fields */
        $fields = $this->actingAs($admin)->getJson("/nova-api/users/{$user->id}")->json('resource.fields');
        $field = collect($fields)->firstWhere('attribute', 'avatar_updated_at');
        $this->assertIsArray($field);
        $this->assertSame("/admin/user-avatars/{$user->id}?v={$updatedAt->format('Uu')}", $field['thumbnailUrl']);

        $this->actingAs($admin)->get($field['thumbnailUrl'])
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_only_jpeg_png_and_webp_images_are_accepted(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->merchant()->create();
        $svg = UploadedFile::fake()->createWithContent('photo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');

        $this->actingAs($admin)
            ->postJson("/nova-api/users/{$user->id}?_method=PUT", $this->form($user, $svg))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('avatar_updated_at');

        $this->assertFalse(UserAvatar::query()->whereKey($user->id)->exists());
    }

    public function test_replacing_a_photo_within_one_second_changes_its_url(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->merchant()->create();
        $firstUrl = $secondUrl = null;

        try {
            $this->travelTo(CarbonImmutable::parse('2026-10-04 12:00:00.123456', 'UTC'));
            $this->actingAs($admin)
                ->post("/nova-api/users/{$user->id}?_method=PUT", $this->form($user, $this->png()))
                ->assertOk();
            $firstUrl = collect($this->getJson("/nova-api/users/{$user->id}")->json('resource.fields'))
                ->firstWhere('attribute', 'avatar_updated_at')['thumbnailUrl'];

            $this->travelTo(CarbonImmutable::parse('2026-10-04 12:00:00.654321', 'UTC'));
            $this->actingAs($admin)
                ->post("/nova-api/users/{$user->id}?_method=PUT", $this->form($user, $this->png()))
                ->assertOk();
            $secondUrl = collect($this->getJson("/nova-api/users/{$user->id}")->json('resource.fields'))
                ->firstWhere('attribute', 'avatar_updated_at')['thumbnailUrl'];
        } finally {
            $this->travelBack();
        }

        $this->assertNotNull($firstUrl);
        $this->assertNotNull($secondUrl);
        $this->assertNotSame($firstUrl, $secondUrl);
    }

    public function test_removing_the_photo_deletes_it(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->merchant()->create();
        $this->actingAs($admin)->post("/nova-api/users/{$user->id}?_method=PUT", $this->form($user, $this->png()));

        $this->actingAs($admin)
            ->deleteJson("/nova-api/users/{$user->id}/field/avatar_updated_at")
            ->assertOk();

        $this->assertFalse(UserAvatar::query()->whereKey($user->id)->exists());
        $this->assertNull($user->fresh()?->avatar_updated_at);
        $this->actingAs($admin)->get("/admin/user-avatars/{$user->id}")->assertNotFound();
    }

    public function test_photos_are_served_to_administrators_only(): void
    {
        $user = User::factory()->merchant()->create();
        UserAvatar::query()->create(['user_id' => $user->id, 'content_type' => 'image/png', 'content' => base64_decode(self::PNG)]);

        $this->get("/admin/user-avatars/{$user->id}")->assertRedirect('/admin/login');
        $this->actingAs($user)->get("/admin/user-avatars/{$user->id}")->assertForbidden();
    }

    private function png(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('photo.png', (string) base64_decode(self::PNG));
    }

    /** @return array<string, mixed> */
    private function form(User $user, UploadedFile $photo): array
    {
        return [
            'name' => $user->name,
            'email' => $user->email,
            'avatar_updated_at' => $photo,
        ];
    }
}
