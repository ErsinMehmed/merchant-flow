<?php

namespace App\Containers\AppSection\Demo\Tests\Functional;

use App\Containers\AppSection\Account\Models\Account;
use App\Containers\AppSection\Charge\Models\Charge;
use App\Containers\AppSection\Demo\Data\Seeders\DemoDataSeeder_1;
use App\Containers\AppSection\User\Models\User;
use App\Containers\AppSection\User\Models\UserAvatar;
use App\Ship\Parents\Tests\TestCase;
use Illuminate\Support\Facades\DB;

final class DemoDataSeederTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // A twentieth of the demo volume keeps the test quick.
        config(['appSection-demo.enabled' => true, 'appSection-demo.charge_scale' => 0.05]);
    }

    public function test_seeds_every_role_with_the_documented_scope(): void
    {
        $this->seed(DemoDataSeeder_1::class);

        $this->assertSame(['admin'], $this->user('admin@merchant-flow.test')->getRoleNames()->all());
        $this->assertSame(12, Account::query()->count());
        $this->assertSame(
            ['Fabrikam Fitness', 'Northwind Coffee Roasters'],
            $this->accountsOf('merchant@merchant-flow.test'),
        );
        $this->assertSame(
            ['Contoso Books', 'Tailspin Toys'],
            $this->accountsOf('merchant-two@merchant-flow.test'),
        );
        $this->assertSame(
            ['Adventure Works Cycles', 'Contoso Books', 'Northwind Coffee Roasters', 'Wide World Importers'],
            $this->agentAccountsOf('agent@merchant-flow.test'),
        );
        // The sub-agent's merchants are not added to its parent's list above.
        $this->assertSame(['Fabrikam Fitness', 'Lucerne Publishing'], $this->agentAccountsOf('subagent@merchant-flow.test'));

        foreach (['authorized', 'captured', 'refunded', 'voided', 'failed'] as $status) {
            $this->assertTrue(Charge::query()->where('status', $status)->exists(), "no {$status} charges");
        }
        $this->assertTrue(Charge::query()->where('status', 'captured')->where('amount_refunded', '>', 0)->exists());
        $this->assertTrue(Charge::query()->where('disputed', true)->exists());
        $this->assertSame(['CAD', 'EUR', 'GBP', 'USD'], Charge::query()->distinct()->orderBy('currency')->pluck('currency')->all());
    }

    public function test_every_demo_user_has_a_profile_photo(): void
    {
        $this->seed(DemoDataSeeder_1::class);

        $this->assertSame(12, User::query()->count());
        $this->assertFalse(User::query()->whereNull('avatar_updated_at')->exists());
        $this->assertSame(['image/webp'], UserAvatar::query()->distinct()->pluck('content_type')->all());
    }

    /**
     * Authorizations lapse after about a week, so only recent ones stay open;
     * a pending merchant has no history yet.
     */
    public function test_history_is_plausible(): void
    {
        $this->seed(DemoDataSeeder_1::class);

        $this->assertFalse(Charge::query()->where('status', 'authorized')->where('created_at', '<', now()->subDays(8))->exists());
        $this->assertSame(0, Account::query()->where('business_name', "Margie's Travel")->firstOrFail()->charges()->count());
        $this->assertTrue(Charge::query()->where('created_at', '<', now()->subMonths(11))->exists());
    }

    public function test_second_run_changes_nothing(): void
    {
        $this->seed(DemoDataSeeder_1::class);
        $counts = $this->counts();

        $this->seed(DemoDataSeeder_1::class);

        $this->assertSame($counts, $this->counts());
    }

    public function test_disabled_outside_the_demo_stack(): void
    {
        config(['appSection-demo.enabled' => false]);

        $this->seed(DemoDataSeeder_1::class);

        $this->assertSame(0, User::query()->count());
    }

    private function user(string $email): User
    {
        return User::query()->where('email', $email)->firstOrFail();
    }

    /** @return list<string> */
    private function accountsOf(string $email): array
    {
        return $this->user($email)->accounts()->orderBy('business_name')->pluck('business_name')->all();
    }

    /** @return list<string> */
    private function agentAccountsOf(string $email): array
    {
        return Account::query()
            ->whereHas('agents.users', fn ($query) => $query->where('email', $email))
            ->orderBy('business_name')
            ->pluck('business_name')
            ->all();
    }

    /** @return array<string, int> */
    private function counts(): array
    {
        $counts = [];
        foreach (['users', 'agents', 'accounts', 'charges', 'account_user', 'agent_user', 'agent_account', 'model_has_roles'] as $table) {
            $counts[$table] = DB::table($table)->count();
        }

        return $counts;
    }
}
