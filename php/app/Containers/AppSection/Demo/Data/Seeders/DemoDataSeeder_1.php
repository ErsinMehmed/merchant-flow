<?php

namespace App\Containers\AppSection\Demo\Data\Seeders;

use App\Containers\AppSection\Account\Enums\AccountStatus;
use App\Containers\AppSection\Account\Models\Account;
use App\Containers\AppSection\Agent\Enums\AgentType;
use App\Containers\AppSection\Agent\Models\Agent;
use App\Containers\AppSection\Authorization\Data\Seeders\AuthorizationSeeder_1;
use App\Containers\AppSection\Charge\Enums\ChargeStatus;
use App\Containers\AppSection\Charge\Enums\VoidReason;
use App\Containers\AppSection\User\Models\User;
use App\Ship\Parents\Seeders\Seeder as ParentSeeder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Fictional demo data for the local stack. Runs only on an empty database,
 * so restarting the stack never overwrites changes made in Nova.
 *
 * Scope at a glance:
 *   admin        -> every account
 *   merchant     -> Northwind Coffee, Fabrikam Fitness
 *   merchant-two -> Contoso Books, Tailspin Toys (suspended)
 *   agent        -> Northwind Coffee, Contoso Books (Harbor Payments)
 *   subagent     -> Fabrikam Fitness (Summit Referral, child of Harbor; the
 *                   parent agent does not inherit its merchants)
 */
final class DemoDataSeeder_1 extends ParentSeeder
{
    private const ADMIN_EMAIL = 'admin@merchant-flow.test';

    private const SEED = 20261001;

    public function run(): void
    {
        if (! config('appSection-demo.enabled') || User::query()->where('email', self::ADMIN_EMAIL)->exists()) {
            return;
        }

        // Apiato does not guarantee the order of seeders across containers,
        // and the demo users need the roles. Re-running it is harmless.
        $this->call(AuthorizationSeeder_1::class);

        DB::transaction(function (): void {
            mt_srand(self::SEED);
            $now = CarbonImmutable::now()->startOfMinute();

            $admin = $this->user(self::ADMIN_EMAIL, 'Avery Admin', isAdmin: true);
            $agentUser = $this->user('agent@merchant-flow.test', 'Harper Agent', isAgent: true);
            $subAgentUser = $this->user('subagent@merchant-flow.test', 'Riley Referral', isAgent: true);
            $merchantUser = $this->user('merchant@merchant-flow.test', 'Morgan Merchant', isMerchant: true);
            $merchantTwoUser = $this->user('merchant-two@merchant-flow.test', 'Jordan Merchant', isMerchant: true);

            $harbor = Agent::query()->create([
                'first_name' => 'Harper',
                'last_name' => 'Lane',
                'email' => 'partners@harbor-payments.example',
                'company_legal_name' => 'Harbor Payments ISO LLC',
                'company_dba' => 'Harbor Payments',
                'type' => AgentType::Iso,
                'default_split' => 20,
            ]);
            $summit = Agent::query()->create([
                'first_name' => 'Riley',
                'last_name' => 'Stone',
                'email' => 'hello@summit-referrals.example',
                'company_legal_name' => 'Summit Referral Partners LLC',
                'company_dba' => 'Summit Referral',
                'type' => AgentType::Referral,
                'default_split' => 10,
                'parent_agent_id' => $harbor->id,
            ]);
            $harbor->users()->attach($agentUser);
            $summit->users()->attach($subAgentUser);

            $northwind = $this->account('Northwind Coffee Roasters', '420000000001', $admin);
            $contoso = $this->account('Contoso Books', '420000000002', $admin);
            $fabrikam = $this->account('Fabrikam Fitness', '420000000003', $admin);
            $tailspin = $this->account('Tailspin Toys', '420000000004', $admin, AccountStatus::Suspended);

            $northwind->users()->attach($merchantUser);
            $fabrikam->users()->attach($merchantUser);
            $contoso->users()->attach($merchantTwoUser);
            $tailspin->users()->attach($merchantTwoUser);

            $harbor->accounts()->attach([$northwind->id, $contoso->id]);
            $summit->accounts()->attach($fabrikam);

            $this->charges($northwind, 'USD', 320, $merchantUser, $now);
            $this->charges($contoso, 'EUR', 260, $merchantTwoUser, $now);
            $this->charges($fabrikam, 'USD', 240, $merchantUser, $now);
            $this->charges($tailspin, 'GBP', 120, $merchantTwoUser, $now);
        });
    }

    private function user(string $email, string $name, bool $isAdmin = false, bool $isAgent = false, bool $isMerchant = false): User
    {
        // Created through the model so the observer assigns the Spatie roles.
        return User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => config('appSection-demo.password'),
            'is_admin' => $isAdmin,
            'is_agent' => $isAgent,
            'is_merchant' => $isMerchant,
        ]);
    }

    private function account(string $name, string $mid, User $creator, AccountStatus $status = AccountStatus::Active): Account
    {
        $slug = strtolower(str_replace(' ', '-', $name));

        $account = new Account([
            'business_name' => $name,
            'dba_name' => $name,
            'legal_name' => $name.' Ltd.',
            'email' => "billing@{$slug}.example",
            'mid' => $mid,
            'status' => $status,
            'active_yn' => $status === AccountStatus::Active,
        ]);
        $account->created_by = $creator->id;
        $account->save();

        return $account;
    }

    /**
     * Bulk insert with a fixed random sequence; every row satisfies the
     * database checks on charges.
     */
    private function charges(Account $account, string $currency, int $count, User $creator, CarbonImmutable $now): void
    {
        $rows = [];

        for ($i = 0; $i < $count; $i++) {
            $amount = $this->amount();
            $createdAt = $now->subMinutes(mt_rand(5, 180 * 24 * 60));
            $row = [
                'account_id' => $account->id,
                'amount' => $amount,
                'amount_captured' => $amount,
                'amount_refunded' => 0,
                'amount_voided' => 0,
                'currency' => $currency,
                'status' => ChargeStatus::Captured->value,
                'external_transaction_id' => sprintf('txn_demo%02d%04d%08x', $account->id, $i, mt_rand()),
                'customer_email' => sprintf('customer%03d@example.com', mt_rand(1, 400)),
                'refunded' => false,
                'disputed' => false,
                'void_reason' => null,
                'created_by' => $creator->id,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ];

            $roll = mt_rand(1, 100);
            $row = match (true) {
                $roll <= 8 => [...$row, 'status' => ChargeStatus::Authorized->value, 'amount_captured' => 0],
                $roll <= 15 => [
                    ...$row,
                    'status' => ChargeStatus::Voided->value,
                    'amount_captured' => 0,
                    'amount_voided' => $amount,
                    'void_reason' => VoidReason::cases()[mt_rand(0, 3)]->value,
                    'updated_at' => $createdAt->addHours(mt_rand(1, 24)),
                ],
                $roll <= 21 => [...$row, 'status' => ChargeStatus::Failed->value, 'amount_captured' => 0],
                $roll <= 30 => [
                    ...$row,
                    'status' => ChargeStatus::Refunded->value,
                    'amount_refunded' => $amount,
                    'refunded' => true,
                    'updated_at' => $createdAt->addDays(mt_rand(1, 10)),
                ],
                $roll <= 36 => [
                    ...$row,
                    'amount_refunded' => intdiv($amount * mt_rand(10, 90), 100),
                    'updated_at' => $createdAt->addDays(mt_rand(1, 10)),
                ],
                $roll <= 38 => [...$row, 'disputed' => true],
                default => $row,
            };

            $rows[] = $row;
        }

        foreach (array_chunk($rows, 250) as $chunk) {
            DB::table('charges')->insert($chunk);
        }
    }

    /**
     * Mostly small tickets with an occasional large one, in minor units.
     */
    private function amount(): int
    {
        return match (true) {
            mt_rand(1, 100) <= 70 => mt_rand(300, 9_999),
            mt_rand(1, 100) <= 85 => mt_rand(10_000, 49_999),
            default => mt_rand(50_000, 250_000),
        };
    }
}
