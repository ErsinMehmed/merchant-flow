<?php

namespace App\Containers\AppSection\Agent\Data\Factories;

use App\Containers\AppSection\Agent\Enums\AgentType;
use App\Containers\AppSection\Agent\Models\Agent;
use App\Ship\Parents\Factories\Factory as ParentFactory;

/**
 * @extends ParentFactory<Agent>
 */
final class AgentFactory extends ParentFactory
{
    /** @var class-string<Agent> */
    protected $model = Agent::class;

    public function definition(): array
    {
        $company = fake()->company();

        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'company_legal_name' => $company.' LLC',
            'company_dba' => $company,
            'type' => fake()->randomElement(AgentType::cases()),
            'default_split' => fake()->randomFloat(2, 5, 40),
        ];
    }
}
