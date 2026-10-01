<?php

namespace App\Containers\AppSection\Account\Data\Factories;

use App\Containers\AppSection\Account\Enums\AccountStatus;
use App\Containers\AppSection\Account\Models\Account;
use App\Containers\AppSection\User\Models\User;
use App\Ship\Parents\Factories\Factory as ParentFactory;

/**
 * @extends ParentFactory<Account>
 */
final class AccountFactory extends ParentFactory
{
    /** @var class-string<Account> */
    protected $model = Account::class;

    public function definition(): array
    {
        $name = fake()->company();

        return [
            'business_name' => $name,
            'dba_name' => $name,
            'legal_name' => $name.' Inc.',
            'email' => fake()->unique()->safeEmail(),
            'mid' => fake()->unique()->numerify('4###########'),
            'status' => AccountStatus::Active,
            'active_yn' => true,
            'created_by' => User::factory()->admin(),
        ];
    }

    public function suspended(): self
    {
        return $this->state(['status' => AccountStatus::Suspended]);
    }

    public function disabled(): self
    {
        return $this->state(['active_yn' => false]);
    }
}
