<?php

namespace App\Containers\AppSection\User\Data\Factories;

use App\Containers\AppSection\User\Models\User;
use App\Ship\Parents\Factories\Factory as ParentFactory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends ParentFactory<User>
 */
final class UserFactory extends ParentFactory
{
    protected static ?string $password = null;

    /** @var class-string<User> */
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => self::$password ??= Hash::make('password'),
        ];
    }

    public function admin(): self
    {
        return $this->state(['is_admin' => true]);
    }

    public function merchant(): self
    {
        return $this->state(['is_merchant' => true]);
    }

    public function agent(): self
    {
        return $this->state(['is_agent' => true]);
    }
}
