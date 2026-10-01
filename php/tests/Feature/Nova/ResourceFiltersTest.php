<?php

namespace Tests\Feature\Nova;

use App\Containers\AppSection\Account\Models\Account;
use App\Containers\AppSection\Agent\Enums\AgentType;
use App\Containers\AppSection\Agent\Models\Agent;
use App\Containers\AppSection\User\Models\User;
use App\Nova\Filters\AccountAgentFilter;
use App\Nova\Filters\AccountProcessingFilter;
use App\Nova\Filters\AccountStatusFilter;
use App\Nova\Filters\AgentParentFilter;
use App\Nova\Filters\AgentTypeFilter;
use App\Nova\Filters\UserTypeFilter;
use Tests\TestCase;

final class ResourceFiltersTest extends TestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    public function test_merchant_filters(): void
    {
        $active = Account::factory()->create();
        $suspended = Account::factory()->suspended()->disabled()->create();
        $agent = Agent::factory()->create();
        $agent->accounts()->attach($active);

        $this->assertSame([$suspended->id], $this->ids('merchants', [AccountStatusFilter::class => 'suspended']));
        $this->assertSame([$suspended->id], $this->ids('merchants', [AccountProcessingFilter::class => 'disabled']));
        $this->assertSame([$active->id], $this->ids('merchants', [AccountAgentFilter::class => (string) $agent->id]));
        $this->assertSame([$suspended->id], $this->ids('merchants', [AccountAgentFilter::class => 'none']));
    }

    public function test_agent_filters(): void
    {
        $parent = Agent::factory()->create(['type' => AgentType::Iso]);
        $child = Agent::factory()->create(['type' => AgentType::Referral, 'parent_agent_id' => $parent->id]);

        $this->assertSame([$child->id], $this->ids('agents', [AgentTypeFilter::class => 'referral']));
        $this->assertSame([$child->id], $this->ids('agents', [AgentParentFilter::class => (string) $parent->id]));
        $this->assertSame([$parent->id], $this->ids('agents', [AgentParentFilter::class => 'none']));
    }

    public function test_user_type_filter(): void
    {
        $merchant = User::factory()->merchant()->create();
        $none = User::factory()->create();

        $this->assertSame([$merchant->id], $this->ids('users', [UserTypeFilter::class => 'merchant']));
        $this->assertSame([$this->admin->id], $this->ids('users', [UserTypeFilter::class => 'admin']));
        $this->assertSame([$none->id], $this->ids('users', [UserTypeFilter::class => 'none']));
    }

    /**
     * Every resource gets the inline card; one without filters still gets the
     * page size control.
     */
    public function test_every_index_has_the_inline_card(): void
    {
        foreach (['charges' => 6, 'merchants' => 3, 'agents' => 2, 'users' => 1, 'roles' => 0, 'permissions' => 0] as $resource => $filters) {
            $cards = $this->actingAs($this->admin)->getJson("/nova-api/{$resource}/cards")->assertOk()->json();

            $this->assertSame('inline-filters', $cards[0]['component'], $resource);
            $this->assertCount($filters, $cards[0]['filters'], $resource);
            $this->assertSame([25, 50, 100], $cards[0]['perPageOptions'], $resource);
        }
    }

    /**
     * @param  array<class-string, string>  $filters
     * @return list<int>
     */
    private function ids(string $resource, array $filters): array
    {
        $encoded = base64_encode(json_encode(
            array_map(static fn (string $class, string $value): array => [$class => $value], array_keys($filters), $filters),
            JSON_THROW_ON_ERROR,
        ));

        $response = $this->actingAs($this->admin)
            ->getJson("/nova-api/{$resource}?filters=".urlencode($encoded))
            ->assertOk();

        return collect($response->json('resources'))->pluck('id.value')->sort()->values()->all();
    }
}
