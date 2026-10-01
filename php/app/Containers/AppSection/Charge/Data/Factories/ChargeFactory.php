<?php

namespace App\Containers\AppSection\Charge\Data\Factories;

use App\Containers\AppSection\Account\Models\Account;
use App\Containers\AppSection\Charge\Enums\ChargeStatus;
use App\Containers\AppSection\Charge\Enums\VoidReason;
use App\Containers\AppSection\Charge\Models\Charge;
use App\Containers\AppSection\User\Models\User;
use App\Ship\Parents\Factories\Factory as ParentFactory;

/**
 * States produce rows that satisfy the same invariants as the database checks.
 *
 * @extends ParentFactory<Charge>
 */
final class ChargeFactory extends ParentFactory
{
    /** @var class-string<Charge> */
    protected $model = Charge::class;

    public function definition(): array
    {
        $amount = fake()->numberBetween(500, 250_000);

        return [
            'account_id' => Account::factory(),
            'amount' => $amount,
            'amount_captured' => $amount,
            'amount_refunded' => 0,
            'amount_voided' => 0,
            'currency' => 'USD',
            'status' => ChargeStatus::Captured,
            'external_transaction_id' => Charge::newTransactionId(),
            'customer_email' => fake()->safeEmail(),
            'refunded' => false,
            'disputed' => false,
            'created_by' => User::factory()->merchant(),
        ];
    }

    public function authorized(): self
    {
        return $this->state(['status' => ChargeStatus::Authorized, 'amount_captured' => 0]);
    }

    public function partiallyRefunded(int $refunded): self
    {
        return $this->state(['amount_refunded' => $refunded]);
    }

    public function refunded(): self
    {
        return $this->state(static fn (array $attributes): array => [
            'status' => ChargeStatus::Refunded,
            'amount_refunded' => $attributes['amount'],
            'refunded' => true,
        ]);
    }

    public function voided(): self
    {
        return $this->state(static fn (array $attributes): array => [
            'status' => ChargeStatus::Voided,
            'amount_captured' => 0,
            'amount_voided' => $attributes['amount'],
            'void_reason' => VoidReason::RequestedByCustomer,
        ]);
    }

    public function failed(): self
    {
        return $this->state(['status' => ChargeStatus::Failed, 'amount_captured' => 0]);
    }

    public function disputed(): self
    {
        return $this->state(['disputed' => true]);
    }
}
