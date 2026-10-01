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
 * Fictional demo data for the local stack: 12 merchants, 5 agents in two
 * hierarchies and about 22,000 charges over the last year, enough to exercise
 * paging, sorting and filtering. Runs only on an empty database, so restarting
 * the stack never overwrites changes made in Nova. A fixed random seed makes
 * every fresh database identical.
 */
final class DemoDataSeeder_1 extends ParentSeeder
{
    private const ADMIN_EMAIL = 'admin@merchant-flow.test';

    private const SEED = 20261001;

    private const HISTORY_DAYS = 365;

    /** Card authorizations lapse after about a week, so older ones are settled. */
    private const AUTHORIZATION_DAYS = 7;

    /**
     * key => company, legal name, contact, type, split, parent, portal user.
     *
     * Harbor and Atlas are ISOs with a referral partner each. A parent agent
     * does not inherit its sub-agent's merchants.
     */
    private const AGENTS = [
        'harbor' => ['Harbor Payments', 'Harbor Payments ISO LLC', 'Harper', 'Lane', AgentType::Iso, 20, null, 'agent@merchant-flow.test'],
        'summit' => ['Summit Referral', 'Summit Referral Partners LLC', 'Riley', 'Stone', AgentType::Referral, 10, 'harbor', 'subagent@merchant-flow.test'],
        'atlas' => ['Atlas Merchant Services', 'Atlas Merchant Services Inc.', 'Quinn', 'Hale', AgentType::Iso, 25, null, 'atlas-agent@merchant-flow.test'],
        'cedar' => ['Cedar Referrals', 'Cedar Referrals LLC', 'Sage', 'Morrow', AgentType::Referral, 8, 'atlas', 'cedar-agent@merchant-flow.test'],
        'beacon' => ['Beacon Partners', 'Beacon Partners Ltd.', 'Drew', 'Ellis', AgentType::Independent, 15, null, 'beacon-agent@merchant-flow.test'],
    ];

    /**
     * key => name, MID, status, processing enabled, currencies, charges at
     * scale 1, serving agents, months of history.
     */
    private const MERCHANTS = [
        'northwind' => ['Northwind Coffee Roasters', '420000000001', AccountStatus::Active, true, ['USD'], 4000, ['harbor'], 12],
        'contoso' => ['Contoso Books', '420000000002', AccountStatus::Active, true, ['EUR'], 2600, ['harbor'], 12],
        'fabrikam' => ['Fabrikam Fitness', '420000000003', AccountStatus::Active, true, ['USD'], 2200, ['summit'], 12],
        'tailspin' => ['Tailspin Toys', '420000000004', AccountStatus::Suspended, false, ['GBP'], 900, ['atlas'], 12],
        'adventure' => ['Adventure Works Cycles', '420000000005', AccountStatus::Active, true, ['USD', 'CAD'], 3200, ['harbor'], 12],
        'wideworld' => ['Wide World Importers', '420000000006', AccountStatus::Active, true, ['USD', 'EUR', 'GBP'], 2800, ['harbor', 'beacon'], 12],
        'lucerne' => ['Lucerne Publishing', '420000000007', AccountStatus::Active, true, ['EUR'], 1400, ['summit'], 9],
        'alpine' => ['Alpine Ski House', '420000000008', AccountStatus::Active, true, ['CAD'], 1600, ['atlas'], 12],
        'coho' => ['Coho Winery', '420000000009', AccountStatus::Active, true, ['USD', 'CAD'], 1200, ['atlas'], 6],
        'margie' => ["Margie's Travel", '420000000010', AccountStatus::Pending, true, ['USD'], 0, ['atlas'], 0],
        'proseware' => ['Proseware', '420000000011', AccountStatus::Active, true, ['USD'], 1800, ['cedar', 'beacon'], 12],
        'fourthcoffee' => ['Fourth Coffee', '420000000012', AccountStatus::Closed, false, ['USD'], 700, ['beacon'], 12],
    ];

    /** email => name, merchant accounts. */
    private const MERCHANT_USERS = [
        'merchant@merchant-flow.test' => ['Morgan Merchant', ['northwind', 'fabrikam']],
        'merchant-two@merchant-flow.test' => ['Jordan Merchant', ['contoso', 'tailspin']],
        'merchant-three@merchant-flow.test' => ['Casey Merchant', ['adventure', 'wideworld', 'lucerne']],
        'merchant-four@merchant-flow.test' => ['Avery Lund', ['alpine']],
        'merchant-five@merchant-flow.test' => ['Rowan Diaz', ['coho', 'margie']],
        'merchant-six@merchant-flow.test' => ['Jamie Okafor', ['proseware', 'fourthcoffee']],
    ];

    private const CUSTOMERS = 2500;

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
            $scale = max(0.0, (float) config('appSection-demo.charge_scale'));

            $admin = $this->user(self::ADMIN_EMAIL, 'Avery Admin', isAdmin: true);

            $agents = [];
            foreach (self::AGENTS as $key => [$company, $legal, $first, $last, $type, $split, $parent, $email]) {
                $agents[$key] = Agent::query()->create([
                    'company_dba' => $company,
                    'company_legal_name' => $legal,
                    'first_name' => $first,
                    'last_name' => $last,
                    'email' => 'partners@'.strtolower(str_replace(' ', '-', $company)).'.example',
                    'type' => $type,
                    'default_split' => $split,
                    'parent_agent_id' => $parent === null ? null : $agents[$parent]->id,
                ]);
                $agents[$key]->users()->attach($this->user($email, "{$first} {$last}", isAgent: true));
            }

            $accounts = [];
            foreach (self::MERCHANTS as $key => [$name, $mid, $status, $active, , , $servedBy]) {
                $accounts[$key] = $this->account($name, $mid, $status, $active, $admin);
                foreach ($servedBy as $agentKey) {
                    $agents[$agentKey]->accounts()->attach($accounts[$key]);
                }
            }

            $owners = [];
            foreach (self::MERCHANT_USERS as $email => [$name, $accountKeys]) {
                $user = $this->user($email, $name, isMerchant: true);
                foreach ($accountKeys as $key) {
                    $user->accounts()->attach($accounts[$key]);
                    $owners[$key] = $user;
                }
            }

            foreach (self::MERCHANTS as $key => [, , , , $currencies, $count, , $months]) {
                $this->charges($accounts[$key], $owners[$key], $currencies, (int) round($count * $scale), $months, $now);
            }
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

    private function account(string $name, string $mid, AccountStatus $status, bool $active, User $creator): Account
    {
        $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name) ?? $mid);

        $account = new Account([
            'business_name' => $name,
            'dba_name' => $name,
            'legal_name' => $name.' Ltd.',
            'email' => "billing@{$slug}.example",
            'mid' => $mid,
            'status' => $status,
            'active_yn' => $active,
        ]);
        $account->created_by = $creator->id;
        $account->save();

        return $account;
    }

    /**
     * Bulk insert with a fixed random sequence; every row satisfies the
     * database checks on charges. A closed account only has older history.
     *
     * @param  list<string>  $currencies
     */
    private function charges(Account $account, User $creator, array $currencies, int $count, int $months, CarbonImmutable $now): void
    {
        $historyMinutes = min(self::HISTORY_DAYS, $months * 31) * 24 * 60;
        $closedOffset = $account->status === AccountStatus::Closed ? 60 * 24 * 60 : 0;
        $rows = [];

        for ($i = 0; $i < $count; $i++) {
            // A sixth of the charges fall in the last two weeks, so recent
            // pages are busy and there are live authorizations to work with.
            $ageMinutes = mt_rand(1, 6) === 1 && $closedOffset === 0
                ? mt_rand(5, 14 * 24 * 60)
                : mt_rand($closedOffset + 5, max($closedOffset + 5, $historyMinutes));
            $createdAt = $now->subMinutes($ageMinutes);
            $rows[] = $this->charge($account, $creator, $currencies, $i, $createdAt, $ageMinutes <= self::AUTHORIZATION_DAYS * 24 * 60);

            if (count($rows) === 1000) {
                DB::table('charges')->insert($rows);
                $rows = [];
            }
        }

        if ($rows !== []) {
            DB::table('charges')->insert($rows);
        }
    }

    /**
     * @param  list<string>  $currencies
     * @return array<string, mixed>
     */
    private function charge(Account $account, User $creator, array $currencies, int $index, CarbonImmutable $createdAt, bool $recent): array
    {
        $amount = $this->amount();
        $row = [
            'account_id' => $account->id,
            'amount' => $amount,
            'amount_captured' => $amount,
            'amount_refunded' => 0,
            'amount_voided' => 0,
            'currency' => $currencies[mt_rand(0, count($currencies) - 1)],
            'status' => ChargeStatus::Captured->value,
            'external_transaction_id' => sprintf('txn_demo%02d%05d%08x', $account->id, $index, mt_rand()),
            'customer_email' => sprintf('customer%04d@example.com', mt_rand(1, self::CUSTOMERS)),
            'refunded' => false,
            'disputed' => false,
            'void_reason' => null,
            'created_by' => $creator->id,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ];

        $roll = mt_rand(1, 100);

        return match (true) {
            $recent && $roll <= 25 => [...$row, 'status' => ChargeStatus::Authorized->value, 'amount_captured' => 0],
            $roll <= 7 => [
                ...$row,
                'status' => ChargeStatus::Voided->value,
                'amount_captured' => 0,
                'amount_voided' => $amount,
                'void_reason' => VoidReason::cases()[mt_rand(0, 3)]->value,
                'updated_at' => $createdAt->addHours(mt_rand(1, 48)),
            ],
            $roll <= 12 => [...$row, 'status' => ChargeStatus::Failed->value, 'amount_captured' => 0],
            $roll <= 20 => [
                ...$row,
                'status' => ChargeStatus::Refunded->value,
                'amount_refunded' => $amount,
                'refunded' => true,
                'updated_at' => $createdAt->addDays(mt_rand(1, 20)),
            ],
            $roll <= 26 => [
                ...$row,
                'amount_refunded' => intdiv($amount * mt_rand(10, 90), 100),
                'updated_at' => $createdAt->addDays(mt_rand(1, 20)),
            ],
            $roll <= 28 => [...$row, 'disputed' => true],
            default => $row,
        };
    }

    /**
     * Mostly small tickets with an occasional large one, in minor units.
     */
    private function amount(): int
    {
        $bucket = mt_rand(1, 100);

        return match (true) {
            $bucket <= 60 => mt_rand(300, 9_999),
            $bucket <= 85 => mt_rand(10_000, 49_999),
            $bucket <= 97 => mt_rand(50_000, 250_000),
            default => mt_rand(250_001, 1_500_000),
        };
    }
}
