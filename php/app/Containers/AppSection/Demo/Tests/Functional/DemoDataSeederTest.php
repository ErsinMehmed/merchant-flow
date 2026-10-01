<?php

namespace App\Containers\AppSection\Demo\Tests\Functional;

use App\Containers\AppSection\Account\Models\Account;
use App\Containers\AppSection\Charge\Models\Charge;
use App\Containers\AppSection\Demo\Data\Seeders\DemoDataSeeder_1;
use App\Containers\AppSection\User\Models\User;
use App\Ship\Parents\Tests\TestCase;
use Illuminate\Support\Facades\DB;

final class DemoDataSeederTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['appSection-demo.enabled' => true]);
    }

    public function test_seeds_every_role_with_the_documented_scope(): void
    {
        $this->seed(DemoDataSeeder_1::class);

        $this->assertSame(['admin'], $this->user('admin@merchant-flow.test')->getRoleNames()->all());
        $this->assertSame(
            ['Fabrikam Fitness', 'Northwind Coffee Roasters'],
            $this->accountsOf('merchant@merchant-flow.test'),
        );
        $this->assertSame(
            ['Contoso Books', 'Tailspin Toys'],
            $this->accountsOf('merchant-two@merchant-flow.test'),
        );
        $this->assertSame(
            ['Contoso Books', 'Northwind Coffee Roasters'],
            $this->agentAccountsOf('agent@merchant-flow.test'),
        );
        $this->assertSame(['Fabrikam Fitness'], $this->agentAccountsOf('subagent@merchant-flow.test'));

        foreach (['authorized', 'captured', 'refunded', 'voided', 'failed'] as $status) {
            $this->assertTrue(Charge::query()->where('status', $status)->exists(), "no {$status} charges");
        }
        $this->assertTrue(Charge::query()->where('status', 'captured')->where('amount_refunded', '>', 0)->exists());
        $this->assertTrue(Charge::query()->where('disputed', true)->exists());
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
